<?php

namespace App\Tests\Unit;

use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class RankEconomyTest extends TestCase
{
    public function testEconomicPriceAtEveryBracketBoundary(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $mine = $catalog->get('buildings', 'mine_metal');
        foreach ([
            1=>[384,208], 10=>[906,491], 11=>[987,535], 20=>[2144,1162], 21=>[2316,1254],
            30=>[4628,2507], 31=>[4952,2683], 40=>[9104,4932], 41=>[9650,5227],
            50=>[16303,8831], 51=>[17119,9273], 60=>[26556,14385], 61=>[27618,14960],
            70=>[39309,21293], 71=>[40489,21932], 80=>[52828,28616], 81=>[53885,29188],
            90=>[64397,34882], 91=>[65041,35231],
        ] as $level => [$metal, $nano]) {
            self::assertSame(['metal'=>$metal, 'nano'=>$nano, 'antimatter'=>0, 'hydrogen'=>0], $catalog->costs($mine, $level, 1, 'buildings'));
        }
    }

    public function testResearchEntryUsesOnlyMostExpensiveRequiredStepInItsOwnFamily(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $research = $catalog->get('technologies', 'vector_propulsion');
        $tier = $catalog->withTier($research, 'superior');
        // Le T1 personnalisé coûte 100 métal. Son passage à 10 coûte 1490 métal.
        // Même famille en T2 niveau 1 : 110 % du T1 niveau 5, donc moins cher.
        $research['cost_profiles']['basic'] = ['metal'=>100];
        $tier['cost_profiles']['superior'] = ['nano'=>1];
        $tier['tier_unlock_requirements']['superior']['technologies'] = [
            ['slug'=>'vector_propulsion', 'tier'=>'basic', 'level'=>10],
            ['slug'=>'vector_propulsion', 'tier'=>'intermediate', 'level'=>1],
            ['slug'=>'naval_engineering', 'tier'=>'superior', 'level'=>25],
        ];
        $definitions = ['technologies'=>['vector_propulsion'=>$research]];
        $operations = new OperationCalculator($catalog);
        $quote = $operations->calculate($tier, 'technologies', 1, 1, [], ['catalog'=>$definitions]);
        self::assertSame(4219, $quote['duration']);
        self::assertSame(['metal'=>0, 'nano'=>820, 'antimatter'=>0, 'hydrogen'=>0, 'electricity'=>7100], $quote['costs']);
        // 819,5 × 1,35², sans réutiliser les 820 unités du premier devis.
        self::assertSame(1494, $catalog->costs($tier, 3, 1, 'technologies', $definitions)['nano']);
    }

    public function testAdvancedBuildingUsesRepricedBuildingOrUnrelatedTechnology(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        foreach ([
            ['mine_metal','intermediate',[13621,3072,1736,0]],
            ['storage_metal','superior',[310458,38808,0,0]],
        ] as [$slug, $tier, $expected]) {
            $building = $catalog->withTier($catalog->get('buildings', $slug), $tier);
            self::assertSame($expected, array_values($catalog->costs($building, 1, 1, 'buildings')));
        }
    }

    public function testStorageDurationLimitIsExplicitBeforeQueuePayment(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $storage = $catalog->withTier($catalog->get('buildings', 'storage_metal'), 'superior');
        $calculator = new OperationCalculator($catalog);
        self::assertLessThanOrEqual(2147483647, $calculator->calculateQueuePlan($storage, 'buildings', 323, 1, [], [], [])['duration']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('dépasse la capacité de la file');
        $calculator->calculateQueuePlan($storage, 'buildings', 324, 1, [], [], []);
    }

    public function testInfrastructureReferenceLevelsAndCaps(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $growth = $catalog->rules()['building_level_growth'];
        foreach ([[10,25,70,23.475817], [20,25,70,40.763469], [8,10,25,15.672654]] as [$level,$max,$cap,$expected]) {
            $levels = ['basic'=>$level, 'intermediate'=>$level, 'superior'=>1];
            self::assertEqualsWithDelta($expected, GameCatalog::infrastructureBonus($levels, $cap, $max, $growth), 1e-6);
            self::assertEquals($cap, GameCatalog::infrastructureBonus(array_fill_keys(GameCatalog::TIERS, $max), $cap, $max, $growth));
        }
    }
}
