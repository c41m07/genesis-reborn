<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GuildAtelierShipmentDue
{
    public const KIND = 'guild_atelier_shipment';

    public function __construct(public int $shipmentId, public string $token)
    {
    }
}
