<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, FleetShip, GuildDockOrder, GuildHall, OutboxMessage, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Message\GuildDockOrderDue;
use App\Repository\GameRepository;
use App\Service\Colony\ResourceService;
use App\Service\Combat\LocalGarrison;
use App\Service\Fleet\FleetHold;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

/** Réservation interne au parcours de modernisation, jamais appelée directement par le contrôleur. */
final readonly class GuildDockReservation
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildDockQuote $quotes,
        private FleetHold $hold,
        private ResourceService $resources,
        private LocalGarrison $garrison,
        private ClockInterface $clock,
    ) {
    }

    /**
     * L'appelant tient la transaction et les verrous participation, univers, guilde, Hall puis colonie.
     * Il vérifie l’adhésion actuelle, la participation active et le bâtiment Dock avant un nouvel ordre.
     * @param array{duration:int,costs:array<string,int>} $accepted Devis accepté, sans trajet ni attente.
     */
    public function reserveLocked(
        PlayerUniverse $membership,
        GuildHall $hall,
        Planet $planet,
        string $requestKey,
        string $ship,
        string $sourceTier,
        int $quantity,
        array $accepted,
    ): GuildDockOrder {
        if ($membership->id === null || $hall->id === null || $planet->id === null
            || !preg_match('/^[a-f0-9]{32}$/D', $requestKey)) {
            throw new \DomainException('Requête de modernisation invalide.');
        }

        $previous = $this->repository->one(GuildDockOrder::class, [
            'membershipId' => $membership->id,
            'requestKey' => $requestKey,
        ]);
        if ($previous instanceof GuildDockOrder) {
            if (!$previous->matchesRequest($hall->id, $planet->id, $ship, $sourceTier, $quantity)) {
                throw new \DomainException('Cette demande est déjà utilisée pour un autre lot.');
            }

            return $previous;
        }

        if ($hall->placedAt === null || $hall->solarSystem === null || $hall->guild->archivedAt !== null
            || $hall->guild->universe->id !== $membership->universe->id
            || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            throw new \DomainException('Le Hall de modernisation est indisponible.');
        }

        // Le devis affiché n’autorise pas un nouveau prix : revérifier valeur et durée sous les verrous du départ.
        $quote = $this->quotes->forColony($membership, $planet, $ship, $sourceTier, $quantity);
        $costs = $accepted['costs'];
        $newCosts = $quote['costs'];
        ksort($costs);
        ksort($newCosts);
        if ($accepted['duration'] !== $quote['duration'] || $costs !== $newCosts) {
            throw new \DomainException('Le devis a changé. Vérifiez les nouveaux montants avant de confirmer.');
        }

        $idle = $this->repository->one(Fleet::class, ['playerUniverse' => $membership, 'homePlanet' => $planet, 'status' => 'idle']);
        if (!$idle instanceof Fleet) {
            throw new \DomainException('Vaisseaux indisponibles dans cette colonie.');
        }
        $this->manager->lockCurrent($idle);
        $stock = $this->repository->one(FleetShip::class, ['fleet' => $idle, 'slug' => $ship, 'tier' => $sourceTier]);
        if (!$stock instanceof FleetShip) {
            throw new \DomainException('Vaisseaux indisponibles dans cette colonie.');
        }
        $this->manager->lockCurrent($stock);
        if ($stock->quantity < $quantity) {
            throw new \DomainException('Quantité de vaisseaux insuffisante.');
        }
        // Une immobilisation ne consomme aucun carburant embarqué et ne doit jamais faire déborder la soute.
        $this->hold->departure($idle, [$ship => [$sourceTier => $quantity]], 0.0);
        $this->resources->consume($planet, $quote['costs'], lockStocks: true);
        $stock->quantity -= $quantity;

        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $order = new GuildDockOrder();
        $order->universe = $membership->universe;
        $order->hallId = $hall->id;
        $order->membershipId = $membership->id;
        $order->originPlanetId = $planet->id;
        $order->requestKey = $requestKey;
        $order->shipSlug = $ship;
        $order->sourceTier = $sourceTier;
        $order->targetTier = $quote['target_tier'];
        $order->quantity = $quantity;
        $order->paidCosts = $quote['costs'];
        $order->dispatchToken = Uuid::v4()->toRfc4122();
        $order->startedAt = $now;
        $order->dueAt = $now->modify('+'.$quote['duration'].' seconds');
        $this->manager->save($order);
        $this->manager->flush();
        $this->garrison->enlist($planet, $now);

        // Un rollback annule ensemble le débit, les coques, le reçu et l’événement différé.
        $message = new OutboxMessage();
        $message->kind = GuildDockOrderDue::KIND;
        $message->targetId = $order->id;
        $message->token = $order->dispatchToken;
        $message->dueAt = $order->dueAt;
        $this->manager->save($message);

        return $order;
    }
}
