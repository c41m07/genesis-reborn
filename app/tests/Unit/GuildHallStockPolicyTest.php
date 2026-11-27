<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Guild\GuildHallStockPolicy;
use PHPUnit\Framework\TestCase;

final class GuildHallStockPolicyTest extends TestCase
{
    public function testAnEmptyPlacedHallCanReceiveEverythingNeededForItsFirstWarehouse(): void
    {
        // Barème de fixture, pas un prix de jeu.
        $firstWarehouse = ['metal' => 20, 'nano' => 8];
        $fullWarehouse = ['metal' => 100, 'nano' => 40, 'antimatter' => 20, 'hydrogen' => 80];
        $capacity = GuildHallStockPolicy::capacity(0, $firstWarehouse, $fullWarehouse);
        $firstCargo = GuildHallStockPolicy::receive([], $capacity, ['metal' => 25, 'nano' => 3, 'antimatter' => 2]);
        self::assertSame(20, $firstCargo['delivered']['metal']);
        self::assertSame(5, $firstCargo['overflow']['metal']);
        self::assertSame(2, $firstCargo['overflow']['antimatter']);
        $secondCargo = GuildHallStockPolicy::receive($firstCargo['stock'], $capacity, ['nano' => 5]);
        $emptyStock = GuildHallStockPolicy::consume($secondCargo['stock'], $firstWarehouse);
        self::assertSame(0, array_sum($emptyStock));

        $capacity = GuildHallStockPolicy::capacity(1, $firstWarehouse, $fullWarehouse);
        $thirdCargo = GuildHallStockPolicy::receive($emptyStock, $capacity, ['metal' => 50, 'hydrogen' => 10]);
        $afterDock = GuildHallStockPolicy::consume($thirdCargo['stock'], ['metal' => 45, 'hydrogen' => 8]);
        self::assertSame(5, $afterDock['metal']);
        self::assertSame(2, $afterDock['hydrogen']);
    }

    public function testElectricityNeverEntersSharedStock(): void
    {
        $this->expectException(\DomainException::class);
        GuildHallStockPolicy::receive([], ['metal' => 10], ['electricity' => 1]);
    }
}
