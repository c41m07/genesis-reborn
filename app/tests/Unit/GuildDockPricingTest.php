<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Guild\GuildDockPricing;
use PHPUnit\Framework\TestCase;

final class GuildDockPricingTest extends TestCase
{
    public function testPriceAndDurationFollowTheTargetWithoutChargingElectricity(): void
    {
        $quote = GuildDockPricing::fromConstructionQuotes(
            ['duration' => 28800, 'costs' => ['metal' => 3000, 'electricity' => 999]],
            ['duration' => 36000, 'costs' => ['metal' => 2750, 'nano' => 500, 'hydrogen' => 4, 'electricity' => 998]],
        );

        self::assertSame(10800, $quote['duration']);
        self::assertSame(28800, $quote['source_duration']);
        self::assertSame(36000, $quote['target_duration']);
        self::assertSame(0, $quote['costs']['electricity']);
        self::assertSame(0, $quote['costs']['antimatter']);
        self::assertGreaterThan(0, $quote['costs']['nano']);
        $combined = 6000 + $quote['costs']['metal'] * 2 + $quote['costs']['nano'] * 4 + $quote['costs']['hydrogen'];
        self::assertGreaterThanOrEqual(8255, $combined);
        self::assertLessThan(8262, $combined);
    }

    public function testUnsupportedNegativeDurationNeverBecomesAnInstantUpgrade(): void
    {
        $this->expectException(\DomainException::class);
        GuildDockPricing::fromConstructionQuotes(
            ['duration' => 12, 'costs' => ['metal' => 10]],
            ['duration' => 10, 'costs' => ['metal' => 15]],
        );
    }

    public function testTheDifferenceCannotExceedTheNormalCommandDurationLimit(): void
    {
        $this->expectException(\DomainException::class);
        GuildDockPricing::fromConstructionQuotes(
            ['duration' => 1, 'costs' => ['metal' => 1]],
            ['duration' => 2147483647, 'costs' => ['metal' => 200]],
        );
    }

    public function testOverflowFailsBeforeReturningAnInvalidAmount(): void
    {
        $this->expectException(\DomainException::class);
        GuildDockPricing::fromConstructionQuotes(
            ['duration' => 60, 'costs' => ['metal' => 1]],
            ['duration' => 120, 'costs' => ['antimatter' => PHP_INT_MAX]],
        );
    }
}
