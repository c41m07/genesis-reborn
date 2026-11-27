<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GuildHallConstructionDue
{
    public const KIND = 'guild_hall_construction';

    public function __construct(public int $buildingId, public string $dispatchToken)
    {
    }
}
