<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\GuildDockOrder;
use PHPUnit\Framework\TestCase;

final class GuildDockOrderTest extends TestCase
{
    public function testARequestCannotBeReusedForADifferentOrder(): void
    {
        $order = new GuildDockOrder();
        $order->hallId = 17;
        $order->originPlanetId = 24;
        $order->shipSlug = 'fighter';
        $order->sourceTier = 'basic';
        $order->quantity = 10;

        self::assertTrue($order->matchesRequest(17, 24, 'fighter', 'basic', 10));
        self::assertFalse($order->matchesRequest(23, 24, 'fighter', 'basic', 10));
        self::assertFalse($order->matchesRequest(17, 33, 'fighter', 'basic', 10));
        self::assertFalse($order->matchesRequest(17, 24, 'transport', 'basic', 10));
        self::assertFalse($order->matchesRequest(17, 24, 'fighter', 'intermediate', 10));
        self::assertFalse($order->matchesRequest(17, 24, 'fighter', 'basic', 11));
    }

    public function testOnlyTheCurrentDueDispatchCanCompleteTheOrder(): void
    {
        $order = new GuildDockOrder();
        $order->dueAt = new \DateTimeImmutable('2026-11-27T10:00:00+00:00');
        $order->dispatchToken = 'current';

        self::assertFalse($order->isDue('current', $order->dueAt->modify('-1 second')));
        self::assertFalse($order->isDue('obsolete', $order->dueAt));
        self::assertTrue($order->isDue('current', $order->dueAt));

        $order->completedAt = $order->dueAt;
        self::assertFalse($order->isDue('current', $order->dueAt->modify('+1 second')));
    }
}
