<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\QueueItem;
use App\Service\Queue\LiveQueueTiming;
use PHPUnit\Framework\TestCase;

final class TemporaryQueueThroughputTest extends TestCase
{
    public function testLongLegacyOrderOnlyReceivesFiveHoursOfExtraWork(): void
    {
        $order = new QueueItem();
        $order->startedAt = new \DateTimeImmutable('2026-11-26T12:00:00Z');
        $order->durationSeconds = 36000;
        $activatedAt = $order->startedAt->getTimestamp() + 1200;
        $endsAt = $activatedAt + 18000;
        LiveQueueTiming::setThroughput($order, $activatedAt, $endsAt, 1.25);
        self::assertSame(31500, $order->durationSeconds);
        self::assertEqualsWithDelta((36000 - 1200 - 22500) / 36000, LiveQueueTiming::remainingWork($order->liveTiming, $endsAt), 1e-8);
        self::assertEqualsWithDelta(1 / 36000, LiveQueueTiming::remainingWork($order->liveTiming, $order->finishAt()->getTimestamp() - 1), 1e-8);
        self::assertSame(0.0, LiveQueueTiming::remainingWork($order->liveTiming, $order->finishAt()->getTimestamp()));
    }

    public function testPausingKeepsOnlyExtraWorkAlreadyDone(): void
    {
        $order = new QueueItem();
        $order->startedAt = new \DateTimeImmutable('2026-11-26T12:00:00Z');
        $order->durationSeconds = 36000;
        $startedAt = $order->startedAt->getTimestamp();
        LiveQueueTiming::setThroughput($order, $startedAt, $startedAt + 18000, 1.25);
        LiveQueueTiming::setThroughput($order, $startedAt + 3600, null);
        self::assertSame(35100, $order->durationSeconds);
        self::assertArrayNotHasKey('throughput_until', $order->liveTiming);
    }
}
