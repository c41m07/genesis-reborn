<?php

declare(strict_types=1);

namespace App\Message;

final readonly class GuildDockOrderDue
{
    public const KIND = 'guild_dock_order';

    public function __construct(public int $orderId, public string $dispatchToken)
    {
    }
}
