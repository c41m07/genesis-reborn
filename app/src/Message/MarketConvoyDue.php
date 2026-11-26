<?php

declare(strict_types=1);

namespace App\Message;

final readonly class MarketConvoyDue
{
    public const KIND = 'market_convoy';

    public function __construct(public int $tradeId, public string $token)
    {
    }
}
