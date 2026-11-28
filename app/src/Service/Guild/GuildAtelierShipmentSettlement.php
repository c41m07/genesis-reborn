<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Guild, GuildAtelierShipment, GuildHall, Planet, PlayerUniverse, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Combat\LocalGarrison;
use App\Service\PirateCampaign\PirateRecoveryHangar;
use App\Service\Player\VacationCalendar;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final readonly class GuildAtelierShipmentSettlement
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private Connection $connection,
        private PirateRecoveryHangar $hangar,
        private LocalGarrison $garrison,
        private VacationCalendar $vacations,
        private GuildAtelierStockStore $ships,
        private GuildAtelierShipmentDispatch $dispatch,
        private ClockInterface $clock,
    ) {
    }

    public function finish(int $id, string $token): void
    {
        $this->manager->transaction(function () use ($id, $token): void {
            $shipment = $this->repository->find(GuildAtelierShipment::class, $id);
            if (!$shipment instanceof GuildAtelierShipment || !$shipment->isDue($token, $this->clock->now())) {
                return;
            }
            $recipient = $this->repository->find(PlayerUniverse::class, $shipment->recipientId);
            if ($recipient instanceof PlayerUniverse) {
                $this->manager->lockCurrent($recipient);
            }
            $universe = $this->repository->find(Universe::class, $shipment->universeId);
            if ($universe instanceof Universe) {
                $this->manager->lockCurrent($universe);
            }
            // Relire l’adhésion avant de verrouiller la guilde, comme au départ.
            $memberGuild = $recipient instanceof PlayerUniverse ? $this->connection->fetchOne(
                'SELECT guild_id FROM guild_member WHERE player_universe_id = ? FOR UPDATE',
                [$recipient->id],
            ) : false;
            $guild = $this->repository->find(Guild::class, $shipment->guildId);
            if ($guild instanceof Guild) {
                $this->manager->lockCurrent($guild);
            }
            $hall = $this->repository->find(GuildHall::class, $shipment->hallId);
            if ($hall instanceof GuildHall) {
                $this->manager->lockCurrent($hall);
            }
            $destination = $shipment->status === 'outbound' ? $this->repository->find(Planet::class, $shipment->destinationId) : null;
            if ($destination instanceof Planet) {
                $this->manager->lockCurrent($destination);
            }
            $this->manager->lockCurrent($shipment);
            $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            if (!$shipment->isDue($token, $now)) {
                return;
            }

            $hallPoint = $shipment->navigation[$shipment->status === 'returning' ? 'target' : 'origin'];
            if (!$guild instanceof Guild || $guild->archivedAt !== null || !$hall instanceof GuildHall
                || $hall->guild->id !== $guild->id || $hall->placedAt === null
                || $hall->solarSystem?->id !== $hallPoint[0] || $guild->universe->id !== $shipment->universeId) {
                $this->close($shipment, 'lost');

                return;
            }

            if ($shipment->status === 'returning') {
                $this->ships->settleReservationLocked($hall, $shipment->ships, returned: true);
                $this->close($shipment, 'returned');

                return;
            }

            $target = $shipment->navigation['target'];
            if ($destination instanceof Planet && $recipient instanceof PlayerUniverse && !$recipient->isForgotten()
                && $recipient->universe->id === $shipment->universeId && (int) $memberGuild === $guild->id
                && !$this->vacations->isActive($recipient, $now)
                && $destination->owner?->id === $recipient->id && $destination->solarSystem->id === $target[0]
                && $destination->position === $target[4]
                && $this->hangar->install($recipient, $destination, $shipment->ships, 0.0)) {
                $this->ships->settleReservationLocked($hall, $shipment->ships, returned: false);
                $this->close($shipment, 'delivered');
                $this->manager->flush();
                $this->garrison->enlist($destination, $now);

                return;
            }

            $this->startReturn($shipment, $now);
        });
    }

    private function startReturn(GuildAtelierShipment $shipment, \DateTimeImmutable $now): void
    {
        $route = $shipment->navigation;
        $legs = array_reverse($route['legs']);
        foreach ($legs as &$leg) {
            [$leg['from'], $leg['to']] = [$leg['to'], $leg['from']];
        }
        unset($leg);
        $shipment->navigation = array_replace($route, [
            'origin' => $route['target'],
            'target' => $route['origin'],
            'legs' => $legs,
            'active' => ['status' => 'returning', 'progress' => 0.0, 'at' => $now->getTimestamp()],
        ]);
        $shipment->status = 'returning';
        $shipment->departureAt = $now;
        $shipment->arrivalAt = $now->modify('+'.(int) $route['seconds'].' seconds');
        $shipment->reservedFuel = max(0.0, $shipment->reservedFuel - (float) $route['one_way_fuel']);
        $shipment->arrivalToken = Uuid::v4()->toRfc4122();
        $this->dispatch->schedule($shipment);
    }

    private function close(GuildAtelierShipment $shipment, string $status): void
    {
        $shipment->status = $status;
        $shipment->reservedFuel = 0.0;
    }
}
