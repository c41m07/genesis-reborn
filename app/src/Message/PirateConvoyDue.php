<?php

declare(strict_types=1);

namespace App\Message;

final readonly class PirateConvoyDue
{
    public const KIND = 'pirate_convoy';

    public function __construct(public int $deliveryId, public string $dispatchToken)
    {
    }
}
