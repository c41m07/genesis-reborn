<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GuildAtelierManufacturingDue
{
    public const KIND = 'guild_atelier_manufacturing';

    public function __construct(public int $orderId, public string $dispatchToken)
    {
    }
}
