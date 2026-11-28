<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Guild\GuildAtelierInventory;
use PHPUnit\Framework\TestCase;

final class GuildAtelierInventoryTest extends TestCase
{
    public function testUnitsStaySeparatedByRankAndAnEmptyRowDisappears(): void
    {
        $stock = GuildAtelierInventory::receive(['transport' => ['basic' => 2]], [
            'transport' => ['basic' => 3, 'intermediate' => 0], 'fighter' => ['basic' => 4],
        ]);
        self::assertSame(['transport' => ['basic' => 5], 'fighter' => ['basic' => 4]], $stock);
        self::assertSame(['fighter' => ['basic' => 3]], GuildAtelierInventory::reserve($stock, [
            'transport' => ['basic' => 5], 'fighter' => ['basic' => 1],
        ]));
    }

    public function testCannotReserveAnotherRankOrRunAStockNegative(): void
    {
        $this->expectException(\DomainException::class);
        GuildAtelierInventory::reserve(['fighter' => ['basic' => 20]], ['fighter' => ['intermediate' => 1]]);
    }

    public function testQuantitiesMustNotBeParsedFromFloats(): void
    {
        $this->expectException(\DomainException::class);
        GuildAtelierInventory::receive([], ['fighter' => ['basic' => 1.2]]);
    }

    public function testReceivingCannotOverflowTheStorageBoundary(): void
    {
        $this->expectException(\DomainException::class);
        GuildAtelierInventory::receive(['fighter' => ['basic' => 2147483647]], ['fighter' => ['basic' => 1]]);
    }
}
