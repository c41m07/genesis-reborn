<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, GuildHallConvoy, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Fleet\{FleetDispatch, FleetNavigation};
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;

/** La récupération des biens personnels ne dépend pas de l'existence de la guilde. */
final readonly class GuildCommandReturn
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private ActionGuard $guard,
        private VacationCalendar $vacations,
        private FleetNavigation $navigation,
        private FleetDispatch $dispatch,
        private ClockInterface $clock,
    ) {
    }

    public function depart(PlayerUniverse $owner, int $id, string $key): bool
    {
        return $this->manager->transaction(function () use ($owner, $id, $key): bool {
            $this->guard->lockMembership($owner, currentReads: true);
            $this->vacations->assertCanSendFleet($owner);
            $fleet = $this->repository->find(Fleet::class, $id);
            if (!$fleet instanceof Fleet || $fleet->playerUniverse->id !== $owner->id || $fleet->mission !== GuildHallConvoy::MISSION) {
                throw new \DomainException('Flotte du Centre inaccessible.');
            }
            $this->manager->lockCurrent($fleet);
            if (!$this->guard->receipt($owner, $key)) {
                return false;
            }
            $receipt = $this->repository->one(GuildHallConvoy::class, ['fleet' => $fleet]);
            if (!$receipt instanceof GuildHallConvoy || !$receipt->commandStation || $receipt->shipDonation
                || $receipt->settledAt === null || $fleet->status !== GuildCommandBerth::DOCKED) {
                throw new \DomainException('La flotte n’est plus stationnée au Centre.');
            }
            $this->startLocked($fleet, $this->clock->now());

            return true;
        });
    }

    /** L'appelant possède la transaction et a attesté la flotte stationnée. */
    private function startLocked(Fleet $fleet, \DateTimeImmutable $clock): void
    {
        $origin = $fleet->navigation['origin'] ?? [];
        $target = $fleet->navigation['target'] ?? [];
        $home = $fleet->homePlanet;
        $fuel = $fleet->reservedFuel;
        $hydrogen = (float) ($fleet->cargo['hydrogen'] ?? 0);
        $oneWay = (float) ($fleet->navigation['one_way_fuel'] ?? NAN);
        if (($origin[0] ?? null) !== $home->solarSystem->id || ($origin[4] ?? null) !== $home->position
            || ($target[0] ?? null) !== $fleet->destinationSystem?->id || ($target[4] ?? null) !== GuildHallFlight::DOCK_POSITION
            || $fleet->returnPlanet !== null || $fleet->travelSeconds < 1 || empty($fleet->navigation['legs'])
            || !is_finite($fuel) || !is_finite($hydrogen) || !is_finite($oneWay)
            || $fuel < 0 || $hydrogen < $fuel - 1.0e-8 || abs($fuel - $oneWay) > max(1.0e-8, $oneWay * PHP_FLOAT_EPSILON * 64)) {
            throw new \DomainException('La réserve ou la route d’origine est incohérente. La flotte reste à quai.');
        }
        // Ne pas réutiliser l’ETA aller : le joueur a pu rester à quai longtemps.
        // Si la colonie change de propriétaire, le worker garde les vaisseaux à la
        // position exacte, sans les transférer au nouveau propriétaire.
        $now = $clock->setTimezone(new \DateTimeZone('UTC'));
        $fleet->status = 'returning';
        $fleet->departureAt = $now;
        $fleet->arrivalAt = $now->modify('+'.$fleet->travelSeconds.' seconds');
        $this->navigation->beginLeg($fleet, $now);
        $this->dispatch->schedule($fleet);
    }
}
