<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Market\MarketPricing;
use PHPUnit\Framework\TestCase;

final class MarketPricingTest extends TestCase
{
    public function testMovingCurveAndFeesMakeRoundTripCostMore(): void
    {
        $targets = ['metal' => 50_000, 'nano' => 50_000];
        $forward = MarketPricing::exchange('metal', 'nano', 2000, [], $targets, 10_000);
        self::assertLessThan($forward['before_fee'], $forward['received']);
        self::assertLessThan(1000, $forward['received']);
        $pressure = ['metal' => -2000, 'nano' => $forward['received']];
        self::assertLessThan(MarketPricing::index('metal', 0, 50_000), MarketPricing::index('metal', -2000, 50_000));
        self::assertGreaterThan(MarketPricing::index('nano', 0, 50_000), MarketPricing::index('nano', $forward['received'], 50_000));
        $return = MarketPricing::exchange('nano', 'metal', $forward['received'], $pressure, $targets, 10_000);
        self::assertLessThan(2000, $return['received']);
    }

    public function testFullLotMustBeAvailable(): void
    {
        $this->expectExceptionMessage('Le stock disponible');
        MarketPricing::exchange('metal', 'nano', 100, [], [], 1);
    }

    public function testCatalogueResourcesAndPriceCaps(): void
    {
        self::assertSame(80_000, MarketPricing::index('antimatter', 0, 1_000_000));
        self::assertSame(120_000, MarketPricing::index('antimatter', 2_000_000, 1_000_000));
        self::assertSame(40_000, MarketPricing::index('antimatter', -2_000_000, 1_000_000));
        self::assertSame(['metal', 'nano', 'hydrogen', 'antimatter'], array_keys(MarketPricing::WEIGHTS));
    }
}
