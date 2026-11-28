<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildAtelierShipment, PlayerUniverse};
use App\Manager\GameManager;
use App\Service\Colony\ActionGuard;
use Symfony\Component\Uid\Uuid;

/** Réservation interne ; ne pas exposer le départ avant le raccordement du traitement d’arrivée. */
final readonly class GuildAtelierDeliveryDeparture
{
    public function __construct(
        private GameManager $manager,
        private GuildHallAccess $access,
        private ActionGuard $guard,
        private GuildAtelierDeliveryPlanning $planning,
        private GuildAtelierStockStore $ships,
        private GuildHallStockStore $materials,
        private GuildAtelierShipmentDispatch $dispatch,
    ) {
    }

    /** @param array<string,array<string,int>> $composition */
    public function start(
        PlayerUniverse $actor,
        int $guildId,
        int $hallId,
        int $destinationId,
        array $composition,
        float $ratio,
        string $requestKey,
        string $reviewedQuote,
    ): ?GuildAtelierShipment {
        return $this->manager->transaction(function () use ($actor, $guildId, $hallId, $destinationId, $composition, $ratio, $requestKey, $reviewedQuote): ?GuildAtelierShipment {
            $hall = $this->access->lockAtelierDelivery($actor, $guildId, $hallId);
            if (!$this->guard->receipt($actor, $requestKey)) {
                return null;
            }

            $plan = $this->planning->prepareLocked($actor, $hall, $destinationId, $composition, $ratio);
            if (!hash_equals($plan->fingerprint(), $reviewedQuote)) {
                throw new \DomainException('Le destinataire, le trajet ou le carburant a changé. Demandez un nouveau devis.');
            }
            $recipientId = $plan->destination->owner?->id;
            if ($recipientId === null) {
                throw new \DomainException('Le destinataire est introuvable.');
            }
            $this->ships->reserveLocked($hall, $plan->composition);
            $this->materials->consumeFuelLocked($hall, $plan->fuel);

            $shipment = new GuildAtelierShipment();
            $shipment->hallId = $hallId;
            $shipment->guildId = $guildId;
            $shipment->universeId = $actor->universe->id;
            $shipment->dispatchedById = $actor->id;
            $shipment->recipientId = $recipientId;
            $shipment->destinationId = $destinationId;
            $shipment->ships = $plan->composition;
            $shipment->navigation = $plan->navigation;
            $shipment->reservedFuel = $plan->fuel;
            $shipment->departureAt = $plan->preparedAt;
            $shipment->arrivalAt = $plan->preparedAt->modify('+'.$plan->seconds.' seconds');
            $shipment->arrivalToken = Uuid::v4()->toRfc4122();
            $this->manager->save($shipment);
            $this->dispatch->schedule($shipment);

            return $shipment;
        });
    }
}
