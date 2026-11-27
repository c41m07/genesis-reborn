<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Guild\GuildHallFuelBalance;
use PHPUnit\Framework\TestCase;

final class GuildHallFuelBalanceTest extends TestCase
{
    public function testSuccessiveFlightsPreserveUnspentFractions(): void
    {
        $first = GuildHallFuelBalance::debit(14, 0.0, 6.25);
        self::assertSame(['whole' => 7, 'fraction' => 0.75], $first);
        self::assertSame(['whole' => 4, 'fraction' => 0.5], GuildHallFuelBalance::debit($first['whole'], $first['fraction'], 3.25));
    }

    public function testFractionCanPayAFlightWithoutWholeHydrogen(): void
    {
        self::assertSame(['whole' => 0, 'fraction' => 0.25], GuildHallFuelBalance::debit(0, 0.75, 0.5));
    }

    public function testLargeWholeStockDoesNotSwallowSmallConsumption(): void
    {
        $after = GuildHallFuelBalance::debit(2_000_000_000, 0.25, 0.0000001);
        self::assertSame(2_000_000_000, $after['whole']);
        self::assertEqualsWithDelta(0.2499999, $after['fraction'], 1e-12);
    }

    public function testSubtractingMoreThanTheActualStockFails(): void
    {
        $this->expectException(\DomainException::class);
        GuildHallFuelBalance::debit(2, 0.125, 2.25);
    }
}
