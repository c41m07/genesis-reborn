<?php

declare(strict_types=1);

namespace App\Message;

final readonly class PirateAttackDue
{
    public const KIND = 'pirate_attack';

    public function __construct(public int $campaignId, public string $dispatchToken)
    {
    }
}
