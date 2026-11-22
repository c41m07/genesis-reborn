<?php

declare(strict_types=1);

namespace App\Message;

final readonly class PirateSurvivorsDue
{
    public const KIND = 'pirate_survivors';

    public function __construct(public int $campaignId, public string $dispatchToken)
    {
    }
}
