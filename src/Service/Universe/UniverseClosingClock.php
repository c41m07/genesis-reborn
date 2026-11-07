<?php
namespace App\Service\Universe;

use App\Entity\Universe;
use Psr\Clock\ClockInterface;

final class UniverseClosingClock
{
    public function __construct(private ClockInterface $clock) {}

    public function snapshot(Universe $universe): array
    {
        $now = $this->clock->now();
        $remaining = $universe->endsAt ? (float) $universe->endsAt->format('U.u') - (float) $now->format('U.u') : null;

        return [
            'serverNow' => $now->format('Y-m-d\\TH:i:s.vP'),
            'endsAt' => $universe->endsAt?->format('Y-m-d\\TH:i:s.vP'),
            'status' => $universe->status,
            'visible' => $universe->status === 'active' && $remaining !== null && $remaining <= 86400,
            'remaining' => $remaining === null ? null : max(0, (int) ceil($remaining)),
        ];
    }
}
