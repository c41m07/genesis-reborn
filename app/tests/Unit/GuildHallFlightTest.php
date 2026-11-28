<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Guild, GuildHall, Planet, SolarSystem, Universe};
use App\Service\Fleet\{FleetNavigation, MovementCalculator};
use App\Service\Guild\GuildHallFlight;
use PHPUnit\Framework\TestCase;

final class GuildHallFlightTest extends TestCase
{
    public function testRouteKeepsPositionZeroAndTracksOutboundFuelForRecall(): void
    {
        $universe = new Universe();
        $universe->id = 1;
        $galaxy = new Galaxy();
        $galaxy->id = 1;
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->id = 5;
        $system->galaxy = $galaxy;
        $system->coordX = 3;
        $system->coordY = 3;
        $origin = new Planet();
        $origin->solarSystem = $system;
        $origin->position = 3;
        $guild = new Guild();
        $guild->universe = $universe;
        $hall = new GuildHall();
        $hall->id = 19;
        $hall->guild = $guild;
        $hall->solarSystem = $system;
        $hall->placedAt = new \DateTimeImmutable('2026-11-27T11:10:00Z');

        $navigation = new FleetNavigation();
        $flight = new GuildHallFlight(new MovementCalculator(), $navigation);
        $ships = [[
            'slug' => 'transport',
            'quantity' => 1,
            'speed_system' => 360,
            'speed_galaxy' => 360,
            'fuel_travel_system' => 1.0,
            'fuel_travel_galaxy' => 2.0,
            'fuel_orbit' => 0.0,
            'cargo_capacity' => 200,
        ]];
        $result = $flight->quote($origin, $hall, $ships, 1.0, 1.0, 'proportional', $hall->placedAt);

        self::assertSame(0, $result['navigation']['target'][4]);
        self::assertEquals(30.0, $result['movement']['one_way_fuel']);
        self::assertEquals(1.5, $navigation->traveled($result['navigation'], .5)['point']['position']);
        self::assertEquals(15.0, $navigation->traveled($result['navigation'], .5)['fuel']);
        self::assertEquals(0.0, $navigation->traveled($result['navigation'], 1.0)['point']['position']);

        $delivery = $flight->quoteDelivery($hall, $origin, $ships, 1.0, 1.0, 'proportional', $hall->placedAt);
        self::assertSame($result['navigation']['target'], $delivery['navigation']['origin']);
        self::assertSame($result['navigation']['origin'], $delivery['navigation']['target']);
        self::assertSame($result['movement']['seconds'], $delivery['movement']['seconds']);
        self::assertEquals(30.0, $delivery['movement']['one_way_fuel']);
        self::assertEquals(60.0, $delivery['movement']['total_fuel']);
        self::assertEquals(3.0, $navigation->traveled($delivery['navigation'], 1.0)['point']['position']);

        // Le même hall sert à un aller depuis 0, mais la colonie privée
        // n'est ni ce point 0 ni le système cible du retour standard.
        $origin->id = 11;
        $otherSystem = clone $system;
        $otherSystem->id = 6;
        $otherSystem->coordX = 6;
        $target = new Planet();
        $target->id = 12;
        $target->solarSystem = $otherSystem;
        $target->position = 6;
        $routes = $flight->quoteCommandSortie($hall, $origin, $target, $ships, 1.0, 1.0, 'proportional', $hall->placedAt);
        self::assertSame([5, 1, 3, 3, 0], $routes['outbound']['navigation']['origin']);
        self::assertSame([6, 1, 6, 3, 6], $routes['outbound']['navigation']['target']);
        self::assertSame($routes['outbound']['navigation']['target'], $routes['hall_return']['navigation']['origin']);
        self::assertSame($routes['outbound']['navigation']['target'], $routes['colony_return']['navigation']['origin']);
        self::assertSame([5, 1, 3, 3, 0], $routes['hall_return']['navigation']['target']);
        self::assertSame([5, 1, 3, 3, 3], $routes['colony_return']['navigation']['target']);
        self::assertEqualsWithDelta(
            $routes['hall_return']['movement']['one_way_fuel'] - 30.0,
            $routes['colony_return']['movement']['one_way_fuel'],
            1.0e-8,
        );
        self::assertEquals(3.0, $navigation->traveled($routes['colony_return']['navigation'], 1.0)['point']['position']);

        self::assertEqualsWithDelta(
            $routes['outbound']['navigation']['one_way_fuel'] + $routes['hall_return']['navigation']['one_way_fuel'],
            $routes['reserved_hall_fuel'],
            1.0e-8,
        );

        // Une origine privée dans un autre système peut produire un repli
        // plus cher que le retour au Hall : le devis doit le couvrir aussi.
        $origin->solarSystem = clone $system;
        $origin->solarSystem->id = 7;
        $origin->solarSystem->coordX = 20;
        $routes = $flight->quoteCommandSortie($hall, $origin, $target, $ships, 1.0, 1.0, 'proportional', $hall->placedAt);
        self::assertGreaterThan(
            $routes['hall_return']['navigation']['one_way_fuel'],
            $routes['colony_return']['navigation']['one_way_fuel'],
        );
        self::assertEqualsWithDelta(
            $routes['outbound']['navigation']['one_way_fuel'] + $routes['colony_return']['navigation']['one_way_fuel'],
            $routes['reserved_hall_fuel'],
            1.0e-8,
        );

        $this->expectException(\DomainException::class);
        $flight->quoteCommandSortie($hall, $origin, $origin, $ships, 1.0, 1.0, 'proportional', $hall->placedAt);
    }
}
