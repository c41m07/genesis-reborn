<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Fleet\{FleetNavigation, MovementCalculator};
use PHPUnit\Framework\TestCase;

final class GuildJumpMovementTest extends TestCase
{
    public function testJumpDoublesTravelFuelWithoutDoublingOrbitOrFallbackProfile(): void
    {
        $origin = [1, 1, 0, 0, 1];
        $target = [1, 1, 0, 0, 9];
        $ships = [[
            'slug' => 'transport', 'quantity' => 1, 'speed_system' => 80, 'speed_galaxy' => 500,
            'fuel_travel_system' => 2.0, 'fuel_travel_galaxy' => 3.0, 'fuel_orbit' => 5.0,
            'cargo_capacity' => 800, 'fuel_capacity' => 100,
        ]];

        $calculator = new MovementCalculator();
        $ordinary = $calculator->quote($origin, $target, $ships, 1.0, 1.0, 'proportional', 2.0);
        $jump = $calculator->quote($origin, $target, $ships, 1.0, 0.5, 'proportional', 2.0, 2.0);
        self::assertSame(3600, $ordinary['seconds']);
        self::assertSame(1800, $jump['seconds']);
        self::assertSame(320.0, $jump['one_way_fuel']);
        self::assertSame($ordinary['orbit_fuel'], $jump['orbit_fuel']);
        self::assertSame(650.0, $jump['total_fuel']);
        self::assertSame(250.0, $jump['freight_capacity']);
        self::assertArrayNotHasKey('travel_fuel_multiplier', $ordinary);

        $navigation = new FleetNavigation();
        $frozen = $navigation->freeze($origin, $target, $ships, 1.0, 'proportional', $jump, new \DateTimeImmutable());
        self::assertSame(320.0, array_sum(array_column($frozen['legs'], 'fuel')));
        self::assertSame(2.0, $frozen['fuel_profiles']['transport:basic']['fuel_travel_system']);
        self::assertSame(160.0, $navigation->traveled($frozen, 0.5)['fuel']);
        self::assertSame(60, $calculator->quote($origin, $target, $ships, 1.0, 0.001, 'proportional', 0, 2.0)['seconds']);
    }
}
