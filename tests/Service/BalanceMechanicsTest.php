<?php
namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class BalanceMechanicsTest extends TestCase
{
    private function catalog(): GameCatalog
    {
        return new GameCatalog(dirname(__DIR__, 2) . '/config/game');
    }

    private function universe(): Universe
    {
        $universe = new Universe();
        $universe->slug = 'omega-1';
        $universe->name = 'Omega';

        return $universe;
    }

    public function testMineOutputScalesAfterStoredEnergyIsExhausted(): void
    {
        $window = \App\Service\Colony\ResourceService::miningWindow(0.15, 0.30, 3, 30);

        self::assertSame(20.0, $window['full_mining_seconds']);
        self::assertSame(10.0, $window['throttled_mining_seconds']);
        self::assertEqualsWithDelta(0.5, $window['throttle'], 0.000001);
        self::assertEquals(0.0, $window['energy_stock']);
    }

    public function testSelectedCombatResearchChangesShipStatistics(): void
    {
        $catalog = $this->catalog();
        $universe = $this->universe();
        $fighter = $catalog->getForUniverse($universe, 'ships', 'fighter');
        $result = (new TechnologyEffectCalculator($catalog))->applyToShips(
            [$fighter],
            ['advanced_targeting' => 5, 'precision_fire' => 3, 'shield_technology' => 5, 'weapon_technology' => 5, 'advanced_propulsion' => 5],
            $universe,
        )[0];

        self::assertSame(79.8, $result['tracking']);
        self::assertEqualsWithDelta(72.1, $result['precision'], 0.000001);
        self::assertSame(52.5, $result['shield']);
        self::assertSame(42.0, $result['base_attack']);
        self::assertSame(52.5, $result['speed_system']);
    }

    public function testSelectedShipyardAndFabricationResearchAffectQueuePlan(): void
    {
        $catalog = $this->catalog();
        $universe = $catalog->forUniverse($this->universe());
        $fighter = $universe['catalog']['ships']['fighter'];
        $plan = (new OperationCalculator($catalog))->calculate(
            $fighter,
            'ships',
            1,
            1,
            ['shipyard' => 10],
            $universe,
            ['advanced_engineering' => 5],
        );

        self::assertSame(48, $plan['duration']);
        self::assertSame(['metal' => 2850, 'energy' => 950, 'antimatter' => 0, 'hydrogen' => 0, 'carbon' => 0], $plan['costs']);
    }

    public function testAllBuildingTechnologyAndShipCostsScaleEachResourceAcrossAllRanks(): void
    {
        $catalog = $this->catalog();
        $factors = ['basic' => 1.0, 'intermediate' => 1.25, 'superior' => 1.5625];

        foreach (['buildings', 'technologies', 'ships'] as $group) {
            foreach ($catalog->all($group) as $slug => $definition) {
                $basicCosts = $catalog->costs($catalog->withTier($definition, 'basic'));
                foreach ($factors as $tier => $factor) {
                    $rankedCosts = $catalog->costs($catalog->withTier($definition, $tier));
                    foreach ($basicCosts as $resource => $basicCost) {
                        $expected = $basicCost > 0 ? (int) ceil($basicCost * $factor) : 0;
                        self::assertSame($expected, $rankedCosts[$resource] ?? 0, $group.'/'.$slug.' '.$tier.' '.$resource);
                        if ($basicCost > 0) {
                            self::assertGreaterThanOrEqual(1, $rankedCosts[$resource], $group.'/'.$slug.' '.$tier.' '.$resource.' ne doit pas tomber à zéro.');
                        }
                    }
                }
            }
        }
    }

    public function testHigherShipRanksImproveBaseStatsAndIntegerShotCountsRemainProbabilistic(): void
    {
        $catalog=$this->catalog();$universe=$this->universe();$intermediate=$catalog->getTierForUniverse($universe,'ships','fighter','intermediate');$superior=$catalog->getTierForUniverse($universe,'ships','fighter','superior');
        self::assertSame(188,$intermediate['hp']);self::assertSame(63,$intermediate['shield']);self::assertSame(16,$intermediate['signature']);self::assertSame([0.25],$intermediate['extra_shot_chances']);
        self::assertSame(235,$superior['hp']);self::assertSame(79,$superior['shield']);self::assertSame(13,$superior['signature']);self::assertSame([0.5625],$superior['extra_shot_chances']);
    }

    public function testTierCostsRoundEachResourceUpWithoutCreatingZeroCosts(): void
    {
        $catalog=$this->catalog();$fighter=$catalog->get('ships','fighter');
        self::assertSame(['metal'=>3750,'energy'=>1250,'antimatter'=>0,'hydrogen'=>0,'carbon'=>0],$catalog->costs($catalog->withTier($fighter,'intermediate')));
        self::assertSame(['metal'=>4688,'energy'=>1563,'antimatter'=>0,'hydrogen'=>0,'carbon'=>0],$catalog->costs($catalog->withTier($fighter,'superior')));
        self::assertSame(['metal'=>1,'energy'=>0],$catalog->costs(['_tier'=>'intermediate','costs'=>['metal'=>0.1,'energy'=>0]]));
    }

    public function testRanksCompoundAndKeepExtraShotChancePerRank(): void
    {
        $catalog=$this->catalog();$universe=$this->universe();$fighter=$catalog->getForUniverse($universe,'ships','fighter');
        $result=(new TechnologyEffectCalculator($catalog))->applyToShips([$fighter],['precision_fire'=>['basic'=>10,'intermediate'=>5,'superior'=>5],'ballistic_overdrive'=>['basic'=>25,'intermediate'=>25,'superior'=>25]],$universe)[0];
        self::assertEqualsWithDelta(70*1.10*1.0625*1.078125,$result['precision'],0.000001);
        self::assertSame([0.25,0.3125,0.390625],$result['extra_shot_chances']);
    }

    public function testTierDurationsScaleWithoutChangingResearchLevelGrowth(): void
    {
        $catalog = $this->catalog();
        $universe = $catalog->forUniverse($this->universe());
        $calculator = new OperationCalculator($catalog);
        $operations = [
            ['buildings', 'mine_metal'],
            ['technologies', 'advanced_propulsion'],
            ['ships', 'fighter'],
        ];
        $factors = ['intermediate' => 1.25, 'superior' => 1.5625];

        foreach ($operations as [$group, $slug]) {
            $definition = $universe['catalog'][$group][$slug];
            $basic = $calculator->calculate(
                $catalog->withTier($definition, 'basic'),
                $group,
                1,
                1,
                [],
                $universe,
            )['duration'];

            foreach ($factors as $tier => $factor) {
                $duration = $calculator->calculate(
                    $catalog->withTier($definition, $tier),
                    $group,
                    1,
                    1,
                    [],
                    $universe,
                )['duration'];
                self::assertSame((int) ceil($basic * $factor), $duration, $group.'/'.$slug.' '.$tier);
            }
        }

        $research = $universe['catalog']['technologies']['advanced_propulsion'];
        self::assertSame(3600, $calculator->calculate($catalog->withTier($research, 'basic'), 'technologies', 1, 1, [], $universe)['duration']);
        self::assertSame(4500, $calculator->calculate($catalog->withTier($research, 'intermediate'), 'technologies', 1, 1, [], $universe)['duration']);
        self::assertSame(5625, $calculator->calculate($catalog->withTier($research, 'superior'), 'technologies', 1, 1, [], $universe)['duration']);
    }

    public function testTechnologyRankUnlocksRequireThePreviousRankThreshold(): void
    {
        $catalog=$this->catalog();$checker=new \App\Service\Requirement\RequirementChecker($catalog);$definition=$catalog->get('technologies','advanced_propulsion');$rankedTech=['basic_engineering'=>['basic'=>4,'intermediate'=>10]];$rankedBuildings=['lab'=>['basic'=>4,'intermediate'=>10]];
        self::assertNotEmpty($checker->check($checker->forTier($definition,'intermediate'),$rankedBuildings,$rankedTech));
        $rankedTech['basic_engineering']['basic']=5;$rankedBuildings['lab']['basic']=5;self::assertSame([],$checker->check($checker->forTier($definition,'intermediate'),$rankedBuildings,$rankedTech));
        $rankedTech['basic_engineering']['intermediate']=10;$rankedBuildings['lab']['intermediate']=10;self::assertSame([],$checker->check($checker->forTier($definition,'superior'),$rankedBuildings,$rankedTech));
    }

    public function testResourceAndStorageBuildingsHaveNoConfiguredLevelCap(): void
    {
        $catalog=$this->catalog();
        foreach($catalog->all('buildings') as $building)if(isset($building['production_resource'])||isset($building['storage_resource']))self::assertArrayNotHasKey('max_level',$building,$building['slug']);
    }

    public function testLaboratoryLevelOneAppliesTwoPercentResearchTimeReduction(): void
    {
        $catalog = $this->catalog();
        $universe = $catalog->forUniverse($this->universe());
        $research = $universe['catalog']['technologies']['colonization'];
        $plan = (new OperationCalculator($catalog))->calculate($research, 'technologies', 1, 1, ['lab' => 1], $universe);

        self::assertSame(282240, $plan['duration']);
    }
}
