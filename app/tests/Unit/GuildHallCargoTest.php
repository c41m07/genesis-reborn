<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Guild\GuildHallCargo;
use PHPUnit\Framework\TestCase;

final class GuildHallCargoTest extends TestCase
{
    public function testOnlyWholeMaterialsAreCreditedAndTheReserveStaysInTheFleet(): void
    {
        $original = ['metal' => 22.25, 'nano' => 2, 'hydrogen' => 17.75, 'energy' => 4];

        self::assertSame(['metal' => 22, 'nano' => 2, 'antimatter' => 0, 'hydrogen' => 10], GuildHallCargo::deliverable($original, 7.25));
        self::assertSame(
            ['metal' => 2.25, 'hydrogen' => 7.75, 'energy' => 4],
            GuildHallCargo::retain($original, 7.25, ['metal' => 20, 'nano' => 2, 'hydrogen' => 10]),
        );
    }

    public function testRemainingFuelCannotBeCreditedAsFreight(): void
    {
        $this->expectException(\DomainException::class);
        GuildHallCargo::retain(['hydrogen' => 7.25], 7.25, ['hydrogen' => 1]);
    }

    public function testAmountBeyondStockIntegerRangeStaysInCargo(): void
    {
        self::assertSame(2147483647, GuildHallCargo::deliverable(['metal' => 5000000000], 0.0)['metal']);
        self::assertSame(['metal' => 2852516353], GuildHallCargo::retain(['metal' => 5000000000], 0.0, ['metal' => 2147483647]));
    }
}
