<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\GuildHallConvoy;
use PHPUnit\Framework\TestCase;

final class GuildHallConvoyTest extends TestCase
{
    public function testOneOutboundTokenCanOnlySettleOnce(): void
    {
        $receipt = new GuildHallConvoy();
        $receipt->outboundToken = 'expected-outbound-token';
        $now = new \DateTimeImmutable('2026-11-27T11:40:00+01:00');

        self::assertFalse($receipt->settle('other-token', $now, ['metal' => 99]));
        self::assertNull($receipt->settledAt);
        self::assertTrue($receipt->settle($receipt->outboundToken, $now, ['metal' => 15]));
        self::assertFalse($receipt->settle($receipt->outboundToken, $now->modify('+2 seconds'), ['metal' => 25]));
        self::assertSame(['metal' => 15], $receipt->delivered);
        self::assertSame('2026-11-27T10:40:00+00:00', $receipt->settledAt?->format(DATE_ATOM));
    }

    public function testRescheduleInvalidatesOriginalTokenWithoutRearmingCompletedReceipt(): void
    {
        $receipt = new GuildHallConvoy();
        $now = new \DateTimeImmutable('2026-11-27T12:10:00Z');
        $receipt->scheduleOutbound('', 'first');
        $receipt->scheduleOutbound('first', 'second');

        self::assertFalse($receipt->settle('first', $now, ['metal' => 99]));
        self::assertTrue($receipt->settle('second', $now, ['metal' => 15]));
        $this->expectException(\LogicException::class);
        $receipt->scheduleOutbound('second', 'third');
    }
}
