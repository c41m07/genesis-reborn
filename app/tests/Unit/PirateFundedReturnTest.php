<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PirateAttackFuelReceipt, PirateCampaignFuel, PirateFundedReturn};
use PHPUnit\Framework\TestCase;

final class PirateFundedReturnTest extends TestCase
{
    public function testLostOriginKeepsOnlyRealCarriedHydrogenWithoutDeparture(): void
    {
        $receipt = $this->receipt();
        $at = new \DateTimeImmutable('2026-11-21T20:06:34Z');
        $result = PirateFundedReturn::atResolution($receipt, ['escort' => ['basic' => 1]], $at, false);

        self::assertSame('origin_unavailable', $result->decision);
        self::assertSame(5.0, $result->hydrogen);
        self::assertNull($result->dueAt);
        self::assertSame('2026-11-21T20:06:34+00:00', $result->checkpoint(1, $at)['observed_at']);
    }

    public function testLostPortersCannotCreateFuelNorStartReturnWithoutIt(): void
    {
        $receipt = $this->receipt();
        $at = new \DateTimeImmutable('2026-11-21T20:06:34Z');
        $result = PirateFundedReturn::atResolution($receipt, ['fighter' => ['basic' => 1]], $at, true);

        self::assertSame('insufficient_hydrogen', $result->decision);
        self::assertSame(2.0, $result->hydrogen);
        self::assertNull($result->dueAt);
    }

    public function testExactReserveStartsAtActualResolutionWithFullFrozenLeg(): void
    {
        $receipt = $this->receipt();
        $at = new \DateTimeImmutable('2026-11-21T20:06:34Z');
        $result = PirateFundedReturn::atResolution($receipt, ['escort' => ['basic' => 1]], $at, true);

        self::assertSame('traveling', $result->decision);
        self::assertSame(5.0, $result->hydrogen);
        self::assertSame($at->getTimestamp() + 95, $result->dueAt->getTimestamp());
        self::assertSame(0.0, $receipt->budget->arrivalHydrogen($result->hydrogen));
    }

    public function testUnrelatedSurvivorsCannotUseAnotherAttemptsFuel(): void
    {
        $this->expectException(\LogicException::class);
        PirateFundedReturn::atResolution($this->receipt(), ['unrelated' => ['basic' => 1]], new \DateTimeImmutable(), true);
    }

    private function receipt(): PirateAttackFuelReceipt
    {
        $budget = PirateCampaignFuel::expedition([
            'leg_seconds' => 95, 'one_way_hydrogen' => 5, 'round_trip_hydrogen' => 10,
            'orbit_hydrogen' => 1, 'round_trip_with_orbit_hydrogen' => 11, 'total_capacity' => 22,
        ]);
        $profiles = [
            ['slug' => 'escort', 'tier' => 'basic', 'quantity' => 1, 'cargo_capacity' => 0, 'fuel_capacity' => 20],
            ['slug' => 'fighter', 'tier' => 'basic', 'quantity' => 1, 'cargo_capacity' => 1, 'fuel_capacity' => 1],
        ];

        $nativeMotion = ['speed_system' => 100, 'speed_galaxy' => 200, 'fuel_travel_system' => 0.1,
            'fuel_travel_galaxy' => 0.2, 'fuel_orbit' => 0.4];
        $profiles = array_map(static fn (array $ship): array => $ship + $nativeMotion, $profiles);

        return PirateAttackFuelReceipt::forDeparture(1, 400, [2, 7, 8, 9, 4], [2, 7, 8, 9], $profiles, $budget, 3.0, 8.0);
    }
}
