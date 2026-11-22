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

    public function testVersionedMovementUsesOnlyFrozenNativeFieldsAndSurvivingQuantities(): void
    {
        $receipt = $this->fixture();
        $checkpoint = $receipt->checkpoint();
        self::assertSame(2, $checkpoint['version']);
        $move = $receipt->survivorMovement(['fighter' => ['basic' => 1]]);
        self::assertSame(1, $move[0]['quantity']);
        self::assertSame(114, $move[0]['speed_system']);
        self::assertSame(802, $move[0]['speed_galaxy']);
        self::assertSame(0.07, $move[0]['fuel_travel_system']);
        self::assertSame(0.24, $move[0]['fuel_travel_galaxy']);
        self::assertSame(0.025, $move[0]['fuel_orbit']);
        self::assertArrayNotHasKey('hp', $move[0]);
        self::assertArrayNotHasKey('catalogue', $move[0]);

        // Les données mouvement tentées sur une ancienne preuve ne constituent jamais une preuve rétroactive.
        $legacy = PirateAttackFuelReceipt::read(array_replace($checkpoint, ['version' => 1]));
        self::assertSame(114, $receipt->survivorMovement(['fighter' => ['basic' => 1]])[0]['speed_system']);
        self::assertNull($legacy->survivorMovement(['fighter' => ['basic' => 1]]));
        self::assertSame(3.0, $legacy->survivorPorters(['fighter' => ['basic' => 1]])[0]['cargo_capacity']);
        self::assertArrayNotHasKey('speed_system', $legacy->checkpoint()['porters'][0]);
    }

    public function testMissingOrMalformedNativeMotionCannotBecomeAnEligibleReceipt(): void
    {
        $receipt = $this->fixture()->checkpoint();
        $bad = $receipt;
        unset($bad['porters'][0]['speed_system']);
        $other = $receipt;
        $other['porters'][0]['fuel_travel_galaxy'] = '0.24';
        foreach ([$bad, $other] as $record) {
            try {
                PirateAttackFuelReceipt::read($record);
                self::fail('Les valeurs mouvement de départ sont strictes.');
            } catch (\DomainException $error) {
                self::assertStringContainsString('mouvement natifs', $error->getMessage());
            }
        }
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
            ['slug' => 'fighter', 'tier' => 'basic', 'quantity' => 2, 'cargo_capacity' => 3.0, 'fuel_capacity' => 5.0,
                'speed_system' => 114, 'speed_galaxy' => 802, 'fuel_travel_system' => 0.07,
                'fuel_travel_galaxy' => 0.24, 'fuel_orbit' => 0.025, 'hp' => 58, 'catalogue' => 'fixture'],
        ];

        return PirateAttackFuelReceipt::forDeparture(2, 52, [8, 2, -1, 0, 4], [9, 2, 1, 0], $profiles, $budget, 1.5, 9.5);
    }
}
