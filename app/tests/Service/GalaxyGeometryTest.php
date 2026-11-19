<?php

namespace App\Tests\Service;

use App\Service\Fleet\{FleetNavigation, GalaxyGeometry, MovementCalculator};
use PHPUnit\Framework\TestCase;

final class GalaxyGeometryTest extends TestCase
{
    public function testApprovedSpiralExamplesMatchThePhysicalDistances(): void
    {
        $geometry = new GalaxyGeometry();
        self::assertEqualsWithDelta(151.7180575864618, $geometry->distance(6, 12, 9, 12), 1e-8);
        self::assertEqualsWithDelta(522.3883974071722, $geometry->distance(6, 12, 6, 15), 1e-8);
        $calculator = new MovementCalculator($geometry);
        $adjacent = $calculator->distances([43, 1, 6, 12, 3], [44, 1, 9, 12, 3]);
        self::assertEqualsWithDelta(291.7180575864618, $adjacent['normal'] + $adjacent['hyper'], 1e-8);
        self::assertSame(70, $calculator->distances([43, 1, 6, 12, 3], [43, 1, 6, 12, null])['normal']);
    }

    public function testEverySystemInTheHundredSystemReferenceRemainsDistinctAndSymmetric(): void
    {
        $geometry = new GalaxyGeometry();
        for ($first = 0; $first < 100; $first++) {
            for ($second = $first + 1; $second < 100; $second++) {
                $distance = $geometry->distance(($first % 10) * 3, intdiv($first, 10) * 3, ($second % 10) * 3, intdiv($second, 10) * 3);
                self::assertGreaterThan(0, $distance);
                self::assertLessThanOrEqual(2850.000001, $distance);
                self::assertSame($distance, $geometry->distance(($second % 10) * 3, intdiv($second, 10) * 3, ($first % 10) * 3, intdiv($first, 10) * 3));
            }
        }
    }

    public function testNewRouteKeepsGridIdentityAndUsesPhysicalCheckpointsWithoutRepricing(): void
    {
        $calculator = new MovementCalculator();
        $navigation = new FleetNavigation();
        $ships = [['slug'=>'transport','quantity'=>1,'speed_system'=>1000,'speed_galaxy'=>1000,'fuel_travel_system'=>1,'fuel_travel_galaxy'=>.5,'fuel_orbit'=>0,'cargo_capacity'=>10000]];
        $origin = [43, 1, 6, 12, 3];
        $target = [44, 1, 9, 12, 3];
        $quote = $calculator->quote($origin, $target, $ships, 1, 1, 'proportional');
        $state = $navigation->freeze($origin, $target, $ships, 1, 'proportional', $quote, new \DateTimeImmutable());
        self::assertSame($origin, $state['origin']);
        self::assertSame($target, $state['target']);
        self::assertSame(GalaxyGeometry::SPIRAL, $state['distance_geometry']);
        $checkpoint = $navigation->traveled($state, .5);
        self::assertSame(GalaxyGeometry::SPIRAL, $checkpoint['point']['geometry']);
        self::assertEqualsWithDelta($quote['one_way_fuel'] / 2, $checkpoint['fuel'], 1e-8);
        $remaining = $navigation->distances($checkpoint['point'], $target);
        self::assertEqualsWithDelta($quote['hyper_distance'] / 2, $remaining['hyper'], 1e-8);
        self::assertSame(70, $remaining['normal']);
    }

    public function testAnUnidentifiedLegacyCheckpointIsRefused(): void
    {
        $this->expectException(\DomainException::class);
        (new FleetNavigation())->distances(['system_id'=>null,'galaxy_id'=>1,'x'=>.5,'y'=>.5,'position'=>null], [1,1,0,0,3]);
    }

    public function testNonFiniteCoordinatesAreRefused(): void
    {
        $this->expectException(\DomainException::class);
        (new GalaxyGeometry())->position(INF, 0);
    }
}
