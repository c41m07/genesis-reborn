<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Advent\{AdventAllowance, AdventSeasonPlan};
use App\Service\Catalog\GameCatalog;
use PHPUnit\Framework\TestCase;

final class AdventSeasonPlanTest extends TestCase
{
    public function testConfiguredResourceMultipliersAndSpecialBudgets(): void
    {
        $doors = (new AdventSeasonPlan(new GameCatalog(dirname(__DIR__, 2).'/config/game')))->doors();
        self::assertCount(24, $doors);
        foreach ([1 => 600, 5 => 600, 7 => 750, 11 => 750, 13 => 1520, 17 => 1520, 19 => 1600, 23 => 1600] as $day => $metal) {
            foreach (AdventSeasonPlan::MULTIPLIERS as $name => $factor) {
                self::assertSame(['metal' => $metal * $factor, 'nano' => intdiv($metal, 2) * $factor], $doors[$day]['options'][$name][0]['resources']);
            }
        }
        foreach ([6 => 6000, 12 => 7500, 18 => 15200, 24 => 16000] as $day => $budget) {
            foreach (AdventSeasonPlan::MULTIPLIERS as $name => $factor) {
                foreach ($doors[$day]['options'][$name] as $choice) {
                    self::assertSame($factor * $budget, $choice['budget_metal']);
                }
            }
        }
        self::assertSame(['guardian_of_stars', 'polar_night'], $doors[24]['cosmetics']);
        self::assertSame(['cruiser', 'battleship', 'capital'], array_column($doors[24]['options']['beginner'], 'slug'));
        self::assertSame(['basic', 'basic', 'basic'], array_column($doors[24]['options']['beginner'], 'tier'));
    }

    public function testCatalogueRevealsOnlyTheRequestedTransportConflict(): void
    {
        $doors = (new AdventSeasonPlan(new GameCatalog(dirname(__DIR__, 2).'/config/game')))->doors();
        $over = [];
        foreach ($doors as $day => $door) {
            foreach ($door['options'] as $bracket => $choices) {
                foreach ($choices as $index => $option) {
                    if ($option['kind'] === 'ship_pack' && $option['value_twice_metal'] > $option['budget_metal'] * 2) {
                        $over[] = [$day, $bracket, $index, $option['value_twice_metal']];
                    }
                }
            }
        }
        self::assertSame([[6, 'beginner', 0, 24000]], $over);
    }

    public function testResearchDiscountUsesWholeMaterialUnitsAndDoesNotChangeEnergy(): void
    {
        $allowance = new AdventAllowance();
        $costs = ['metal' => 12000, 'nano' => 3151, 'antimatter' => 701, 'hydrogen' => 3101, 'electricity' => 307];
        $small = $allowance->payment($costs, 7500, research: true);
        self::assertSame(307, $small['paid']['electricity']);
        self::assertArrayNotHasKey('electricity', $small['discount']);
        self::assertLessThanOrEqual(15000, AdventSeasonPlan::twiceMetal($small['discount']));
        foreach ($small['discount'] as $resource => $discount) {
            self::assertLessThanOrEqual(intdiv($costs[$resource], 2), $discount);
            self::assertSame($costs[$resource] - $discount, $small['paid'][$resource]);
        }
        $full = $allowance->payment($costs, 7500, research: false);
        self::assertLessThanOrEqual(15000, AdventSeasonPlan::twiceMetal($full['discount']));
        self::assertSame(307, $full['paid']['electricity']);
    }

    public function testInitialPortfolioLivesAcrossGiftsAndEndsAtExactAgeOf48Hours(): void
    {
        $allowance = new AdventAllowance();
        $joined = new \DateTimeImmutable('2026-12-01T05:00:00Z');
        $remaining = ['metal' => 5000, 'nano' => 2500];
        $used = ['metal' => 1500, 'nano' => 900];
        self::assertSame(['metal' => 500, 'nano' => 100], $allowance->resourcePortion($remaining, $used, $joined, $joined->modify('+47 hours +59 minutes +59 seconds')));
        self::assertSame($remaining, $allowance->resourcePortion($remaining, $used, $joined, $joined->modify('+48 hours')));
    }
}
