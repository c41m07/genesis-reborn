<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildAtelierShipment, OutboxMessage};
use App\Manager\GameManager;
use App\Message\GuildAtelierShipmentDue;

final readonly class GuildAtelierShipmentDispatch
{
    public function __construct(private GameManager $manager)
    {
    }

    /** Le voyage et la publication différée partagent la transaction de l’appelant. */
    public function schedule(GuildAtelierShipment $shipment): void
    {
        if ($shipment->id === null) {
            $this->manager->flush();
        }
        $message = new OutboxMessage();
        $message->kind = GuildAtelierShipmentDue::KIND;
        $message->targetId = $shipment->id;
        $message->token = $shipment->arrivalToken;
        $message->dueAt = $shipment->arrivalAt;
        $this->manager->save($message);
    }
}
