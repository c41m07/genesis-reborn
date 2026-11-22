<?php

declare(strict_types=1);

namespace App\Message;

final readonly class PirateReconnaissanceDue
{
    public const KIND = 'pirate_reconnaissance';

    public function __construct(public int $missionId, public string $dispatchToken)
    {
    }
}
