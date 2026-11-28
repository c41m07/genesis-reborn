<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PirateCampaignFuel, PirateScoutFuelReceipt};
use PHPUnit\Framework\TestCase;

final class PirateScoutFuelReceiptTest extends TestCase
{
    public function testExactNativeSplitSurvivesJsonWithoutRecalculatingTheQuote(): void
    {
        $native = PirateCampaignFuel::scout([
            'leg_seconds' => 45, 'one_way_hydrogen' => 0.6, 'orbit_hydrogen' => 0.0,
            'round_trip_hydrogen' => 1.2, 'round_trip_with_orbit_hydrogen' => 1.2, 'total_capacity' => 16000,
        ]);
        $receipt = PirateScoutFuelReceipt::forDeparture(17, $native, 0.4, 1.2 - 0.4);
        $decoded = json_decode(json_encode($receipt->checkpoint(), JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $historical = $decoded;
        unset($historical['tier']);
        self::assertSame('basic', PirateScoutFuelReceipt::read($historical)->tier);
        self::assertSame(17, $receipt->originId);
        self::assertSame(45, $receipt->seconds);
        self::assertSame(1.2, $receipt->reserved);
        self::assertSame(0.4, $receipt->fromHold);
        self::assertSame(1.2 - 0.4, $receipt->fromPlanet);
        self::assertSame($receipt->checkpoint(), PirateScoutFuelReceipt::read($decoded)->checkpoint());
    }

    public function testMissingOrWrongNativeSplitDoesNotReconstituteALegacyReceipt(): void
    {
        $this->expectException(\DomainException::class);
        PirateScoutFuelReceipt::read(['version' => 1, 'origin' => 17, 'seconds' => 45,
            'reserved' => 1.2, 'hold' => 0.4, 'planet' => 0.4]);
    }
}
