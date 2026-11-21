<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PirateAttackFuelReceipt, PirateCampaignFuel};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PirateAttackFuelReceiptTest extends TestCase
{
    public function testSerializesNativeBudgetAndFreezeGeometrySeparatelyFromTheBattle(): void
    {
        $receipt = $this->fixture();
        $json = json_decode(json_encode($receipt->checkpoint(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $read = PirateAttackFuelReceipt::read($json);
        self::assertEquals($receipt, $read);
        self::assertSame(2, $read->attempt);
        self::assertSame(9.5, $read->fromPlanet);
        self::assertSame(1.5, $read->fromHold);
        self::assertSame(2.0, $read->budget->orbitHydrogen);
        self::assertSame([8, 2, -1, 0, 4], $read->origin);
        self::assertSame([9, 2, 1, 0], $read->site);
        self::assertArrayNotHasKey('arrived_at', $json);
        self::assertArrayNotHasKey('survivors', $json);
    }

    #[DataProvider('invalidChanges')]
    public function testMalformedPrivateReceiptCannotBeUsed(array $changes): void
    {
        $this->expectException(\DomainException::class);
        PirateAttackFuelReceipt::read($changes + $this->fixture()->checkpoint());
    }

    public static function invalidChanges(): iterable
    {
        yield 'legacy version' => [['version' => 0]];
        yield 'attempt absent' => [['attempt' => null]];
        yield 'planet unavailable' => [['planet_id' => 0]];
        yield 'position outside slots' => [['origin' => [8, 2, -1, 0, 10]]];
        yield 'site cannot occupy slot' => [['site' => [9, 2, 1, 0, 9]]];
        yield 'other galaxy' => [['site' => [9, 3, 1, 0]]];
        yield 'false total' => [['total' => 12.0, 'planet' => 10.5]];
        yield 'unmatched payment' => [['planet' => 8.5]];
        yield 'invalid carry capacity' => [['capacity' => 10]];
        yield 'missing porters' => [['porters' => []]];
        yield 'unchecked capacity' => [['capacity' => 42.0]];
    }

    private function fixture(): PirateAttackFuelReceipt
    {
        $budget = PirateCampaignFuel::expedition([
            'leg_seconds' => 93, 'one_way_hydrogen' => 4.5, 'round_trip_hydrogen' => 9,
            'orbit_hydrogen' => 2, 'round_trip_with_orbit_hydrogen' => 11, 'total_capacity' => 16,
        ]);
        $profiles = [
            ['slug' => 'fighter', 'tier' => 'basic', 'quantity' => 2, 'cargo_capacity' => 3.0, 'fuel_capacity' => 5.0],
        ];

        return PirateAttackFuelReceipt::forDeparture(2, 52, [8, 2, -1, 0, 4], [9, 2, 1, 0], $profiles, $budget, 1.5, 9.5);
    }
}
