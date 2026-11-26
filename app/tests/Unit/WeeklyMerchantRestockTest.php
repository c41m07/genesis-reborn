<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Market\WeeklyMerchantRestock;
use PHPUnit\Framework\TestCase;
use DateTimeImmutable;
use DomainException;


final class WeeklyMerchantRestockTest extends TestCase
{
    public function testFridayBeforeAndAtOpening(): void
    {
        $planner = new WeeklyMerchantRestock();
        self::assertSame('2026-11-20', $planner->window(new DateTimeImmutable('2026-11-27T07:59:59Z'))['occurrence']);
        $window = $planner->window(new DateTimeImmutable('2026-11-27T08:00:00Z'));
        self::assertSame('2026-11-27', $window['occurrence']);
        self::assertSame('2026-11-20T08:00:00+00:00', $window['from']->format(DATE_ATOM));
    }

    public function testWeeksThatIncludeTheClockChangesKeepTheParisHour(): void
    {
        $planner = new WeeklyMerchantRestock();
        $spring = $planner->window(new DateTimeImmutable('2026-04-03T07:00:00Z'));
        self::assertSame(167 * 3600, $spring['until']->getTimestamp() - $spring['from']->getTimestamp());
        $autumn = $planner->window(new DateTimeImmutable('2026-10-30T08:00:00Z'));
        self::assertSame(169 * 3600, $autumn['until']->getTimestamp() - $autumn['from']->getTimestamp());
    }

    public function testHalfOfCompletedSalesCappedByPhysicalStockWithoutSurplusRemoval(): void
    {
        $planner = new WeeklyMerchantRestock();
        $stock = ['metal' => 51_000, 'nano' => 5_000, 'hydrogen' => 47_000, 'antimatter' => 1];
        $targets = array_fill_keys(array_keys($stock), 50_000);
        self::assertSame(['nano' => 10_000, 'hydrogen' => 3_000, 'antimatter' => 2], $planner->quantities(
            $stock, $targets, ['metal' => 2_000, 'nano' => 20_000, 'hydrogen' => 20_000, 'antimatter' => 5, 'electricity' => 10_000],
        ));
        self::assertSame([], $planner->quantities([], $targets, []));
    }

    public function testRejectsInvalidStock(): void
    {
        $this->expectException(DomainException::class);
        (new WeeklyMerchantRestock())->quantities(['metal' => -1], [], []);
    }
}
