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

    public function testUnusedReserveReturnsWithoutRounding(): void
    {
        $reserved = GuildHallFuelBalance::debit(12, 0.5, 7.25);
        self::assertSame(['whole' => 5, 'fraction' => 0.25], $reserved);

        $settled = GuildHallFuelBalance::credit($reserved['whole'], $reserved['fraction'], 2.875);
        self::assertSame(['whole' => 8, 'fraction' => 0.125], $settled);
        self::assertSame(GuildHallFuelBalance::debit(12, 0.5, 4.375), $settled);
    }

    public function testRestoringASmallFractionDoesNotRoundALargeStock(): void
    {
        $settled = GuildHallFuelBalance::credit(2_000_000_000, 0.125, 0.0000001);
        self::assertSame(2_000_000_000, $settled['whole']);
        self::assertEqualsWithDelta(0.1250001, $settled['fraction'], 1e-12);
    }

    public function testCreditRejectsOverflowAtTheIntegerBoundary(): void
    {
        $this->expectException(\DomainException::class);
        GuildHallFuelBalance::credit(PHP_INT_MAX, 0.75, 0.5);
    }
}
