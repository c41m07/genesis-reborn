<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildAtelierManufacturingOrder, GuildHall, OutboxMessage, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Message\GuildAtelierManufacturingDue;
use App\Repository\GameRepository;
use App\Service\Colony\ResourceService;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Démarrer un seul lot et consommer les deux paiements dans la même transaction. */
final readonly class GuildAtelierManufacturing
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildHallAccess $access,
        private GuildAtelierDonations $atelier,
        private GuildAtelierManufacturingQuote $quotes,
        private GuildHallStockStore $materials,
        private GuildAtelierStockStore $ships,
        private ResourceService $resources,
        private ClockInterface $clock,
    ) {
    }

    public function start(
        PlayerUniverse $sponsor,
        int $guildId,
        int $hallId,
        int $colonyId,
        string $slug,
        string $tier,
        int $quantity,
        string $key,
        string $confirmation,
    ): GuildAtelierManufacturingOrder {
        if (!preg_match('/^[a-f0-9]{32}$/D', $key) || !preg_match('/^[a-f0-9]{64}$/D', $confirmation)) {
            throw new \DomainException('Confirmez le montant débité par votre colonie.');
        }

        return $this->manager->transaction(function () use ($sponsor, $guildId, $hallId, $colonyId, $slug, $tier, $quantity, $key, $confirmation): GuildAtelierManufacturingOrder {
            // Le droit de débiter le Hall ne donne jamais le droit de débiter une colonie tierce.
            $hall = $this->access->lockAtelierDelivery($sponsor, $guildId, $hallId);
            $existing = $this->repository->one(GuildAtelierManufacturingOrder::class, ['sponsorId' => $sponsor->id, 'requestKey' => $key]);
            if ($existing instanceof GuildAtelierManufacturingOrder) {
                if ($existing->hall->id !== $hallId || $existing->colonyId !== $colonyId || $existing->shipSlug !== $slug
                    || $existing->tier !== $tier || $existing->quantity !== $quantity || !hash_equals($existing->confirmation, $confirmation)) {
                    throw new \DomainException('Ce reçu correspond à un autre lot.');
                }

                return $existing;
            }
            if (!$this->atelier->isBuiltLocked($hall)) {
                throw new \DomainException('Construisez l’Atelier avant de lancer un lot.');
            }
            if ($this->repository->one(GuildAtelierManufacturingOrder::class, ['hall' => $hall, 'completedAt' => null]) !== null) {
                throw new \DomainException('Un lot est déjà en fabrication dans cet Atelier.');
            }
            $colony = $this->repository->find(Planet::class, $colonyId);
            if (!$colony instanceof Planet || $colony->owner?->id !== $sponsor->id
                || $colony->solarSystem->galaxy->universe->id !== $sponsor->universe->id) {
                throw new \DomainException('Sélectionnez une colonie de votre empire.');
            }
            $this->manager->lockCurrent($colony);
            $quote = $this->quotes->forColony($sponsor, $colony, $hallId, $slug, $tier, $quantity);
            if (!hash_equals($quote['confirmation'], $confirmation)) {
                throw new \DomainException('Le devis a changé. Vérifiez les nouveaux montants avant de confirmer.');
            }

            $order = new GuildAtelierManufacturingOrder();
            $order->hall = $hall;
            $order->sponsorId = $sponsor->id;
            $order->colonyId = $colonyId;
            $order->requestKey = $key;
            $order->shipSlug = $slug;
            $order->tier = $tier;
            $order->quantity = $quantity;
            $order->paidMaterials = $quote['materials'];
            $order->paidElectricity = $quote['electricity'];
            $order->confirmation = $confirmation;
            $order->dispatchToken = Uuid::v4()->toRfc4122();
            $order->startedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $order->dueAt = $order->startedAt->modify('+'.$quote['duration'].' seconds');

            $this->materials->consumeLocked($hall, $order->paidMaterials);
            $this->ships->reserveProductionCapacityLocked($hall, $order->composition());
            $this->resources->consume($colony, ['electricity' => $order->paidElectricity], lockStocks: true);
            $this->manager->save($order);
            $this->manager->flush();

            $message = new OutboxMessage();
            $message->kind = GuildAtelierManufacturingDue::KIND;
            $message->targetId = $order->id;
            $message->token = $order->dispatchToken;
            $message->dueAt = $order->dueAt;
            $this->manager->save($message);

            return $order;
        });
    }

    public function complete(int $orderId, string $token): bool
    {
        return $this->manager->transaction(function () use ($orderId, $token): bool {
            $order = $this->repository->find(GuildAtelierManufacturingOrder::class, $orderId);
            if (!$order instanceof GuildAtelierManufacturingOrder) {
                return false;
            }
            $guild = $order->hall->guild;
            $this->manager->lockCurrent($guild->universe);
            $this->manager->lockCurrent($guild);
            $hall = $this->repository->one(GuildHall::class, ['guild' => $guild]);
            if ($guild->archivedAt !== null || !$hall instanceof GuildHall || $hall->id !== $order->hall->id) {
                return false;
            }
            $this->manager->lockCurrent($hall);
            $this->manager->lockCurrent($order);
            $now = $this->clock->now();
            if ($hall->placedAt === null || $hall->solarSystem === null || !$guild->universe->isAccessible($now) || !$order->isDue($token, $now)) {
                return false;
            }
            $this->ships->settleReservationLocked($hall, $order->composition(), true);
            $order->completedAt = $now;

            return true;
        });
    }
}
