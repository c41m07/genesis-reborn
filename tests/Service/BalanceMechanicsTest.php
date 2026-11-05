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

    public function testSelectedCombatResearchChangesShipStatistics(): void
    {
        $catalog = $this->catalog();
        $universe = $this->universe();
        $fighter = $catalog->getForUniverse($universe, 'ships', 'fighter');
        $result = (new TechnologyEffectCalculator($catalog))->applyToShips(
            [$fighter],
            ['target_acquisition' => 5, 'weapon_calibration' => 3, 'shield_harmonics' => 5, 'weapons_engineering' => 5, 'vector_propulsion' => 5],
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
            ['naval_engineering' => 5],
        );

        self::assertSame(48, $plan['duration']);
        self::assertSame(['metal'=>2565,'nano'=>143,'antimatter'=>0,'hydrogen'=>0],$plan['costs']);
    }

    public function testTierCostProfilesIncreaseRareValueShareWithoutPricingElectricity(): void
    {
        $catalog=$this->catalog();$factor=['basic'=>1.0,'intermediate'=>1.25,'superior'=>1.5625];
        foreach(['buildings','technologies','ships','defenses'] as $group)foreach($catalog->all($group) as $slug=>$definition){
            foreach($factor as $tier=>$multiplier){$costs=$catalog->costs($catalog->withTier($definition,$tier));self::assertArrayNotHasKey('electricity',$costs);self::assertArrayNotHasKey('energy',$costs);foreach($costs as $amount)self::assertGreaterThanOrEqual(0,$amount);}
            if(in_array($slug,['mine_metal','mine_nano','mine_antimatter','mine_hydrogen','power_plant'],true))continue;
            $shares=[];foreach(GameCatalog::TIERS as $tier){$costs=$catalog->costs($catalog->withTier($definition,$tier));$total=2*$costs['metal']+4*$costs['nano']+8*$costs['antimatter']+$costs['hydrogen'];$shares[]=$total>0?(4*$costs['nano']+8*$costs['antimatter'])/$total:0;}
            self::assertGreaterThanOrEqual($shares[0],$shares[1],$group.'/'.$slug.' rang intermédiaire');self::assertGreaterThanOrEqual($shares[1],$shares[2],$group.'/'.$slug.' rang supérieur');
        }
    }

    public function testTierCostProfilesScaleTheReferenceBudget(): void
    {
        $catalog=$this->catalog();$fighter=$catalog->get('ships','fighter');$expected=[
            'basic'=>['metal'=>1800,'nano'=>600,'antimatter'=>0,'hydrogen'=>0],
            'intermediate'=>['metal'=>1688,'nano'=>844,'antimatter'=>94,'hydrogen'=>0],
            'superior'=>['metal'=>1406,'nano'=>1055,'antimatter'=>235,'hydrogen'=>468],
        ];
        foreach($expected as $tier=>$costs)self::assertSame($costs,$catalog->costs($catalog->withTier($fighter,$tier)));
    }

    public function testTierCostsRoundEachResourceUpWithoutCreatingZeroCosts(): void
    {
        $catalog=$this->catalog();self::assertSame(['metal'=>1,'nano'=>0,'antimatter'=>0,'hydrogen'=>0],$catalog->costs(['_tier'=>'intermediate','costs'=>['metal'=>0.1,'energy'=>0]]));
    }

    public function testHigherShipRanksImproveCombatStatsAndPreserveTheBaseShotRange(): void
    {
        $catalog=$this->catalog();$universe=$this->universe();$intermediate=$catalog->getTierForUniverse($universe,'ships','fighter','intermediate');$superior=$catalog->getTierForUniverse($universe,'ships','fighter','superior');
        self::assertSame(225,$intermediate['hp']);self::assertSame(75,$intermediate['shield']);self::assertSame(36,$intermediate['base_attack']);self::assertSame(20,$intermediate['signature']);self::assertSame(55,$intermediate['precision']);self::assertSame(75,$intermediate['tracking']);self::assertSame(60,$intermediate['evasion']);
        self::assertSame(338,$superior['hp']);self::assertSame(113,$superior['shield']);self::assertSame(54,$superior['base_attack']);self::assertSame(20,$superior['signature']);self::assertSame(55,$superior['precision']);self::assertSame(75,$superior['tracking']);self::assertSame(60,$superior['evasion']);
        foreach([$intermediate,$superior] as $unit){self::assertSame(2,$unit['shots_min']);self::assertSame(3,$unit['shots_max']);self::assertArrayNotHasKey('extra_shot_chances',$unit);}
    }

    public function testRanksCompoundAndKeepExtraShotChancePerRank(): void
    {
        $catalog=$this->catalog();$universe=$this->universe();$fighter=$catalog->getForUniverse($universe,'ships','fighter');
        $result=(new TechnologyEffectCalculator($catalog))->applyToShips([$fighter],['weapon_calibration'=>['basic'=>10,'intermediate'=>5,'superior'=>5],'rapid_fire_cycle'=>['basic'=>25,'intermediate'=>25,'superior'=>25]],$universe)[0];
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
            ['technologies', 'vector_propulsion'],
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

        $research = $universe['catalog']['technologies']['vector_propulsion'];
        self::assertSame(3600, $calculator->calculate($catalog->withTier($research, 'basic'), 'technologies', 1, 1, [], $universe)['duration']);
        self::assertSame(4500, $calculator->calculate($catalog->withTier($research, 'intermediate'), 'technologies', 1, 1, [], $universe)['duration']);
        self::assertSame(5625, $calculator->calculate($catalog->withTier($research, 'superior'), 'technologies', 1, 1, [], $universe)['duration']);
    }

    public function testTechnologyRankUnlocksRequireThePreviousRankThreshold(): void
    {
        $catalog=$this->catalog();$checker=new \App\Service\Requirement\RequirementChecker($catalog);$definition=$catalog->get('technologies','vector_propulsion');$rankedTech=['engineering_foundations'=>['basic'=>4,'intermediate'=>10]];$rankedBuildings=['lab'=>['basic'=>4,'intermediate'=>10]];
        self::assertNotEmpty($checker->check($checker->forTier($definition,'intermediate'),$rankedBuildings,$rankedTech));
        $rankedTech['engineering_foundations']['basic']=5;$rankedBuildings['lab']['basic']=5;self::assertSame([],$checker->check($checker->forTier($definition,'intermediate'),$rankedBuildings,$rankedTech));
        $rankedTech['engineering_foundations']['intermediate']=10;$rankedBuildings['lab']['intermediate']=10;self::assertSame([],$checker->check($checker->forTier($definition,'superior'),$rankedBuildings,$rankedTech));
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
        $research = $universe['catalog']['technologies']['colonial_expansion'];
        $plan = (new OperationCalculator($catalog))->calculate($research, 'technologies', 1, 1, ['lab' => 1], $universe);

        self::assertSame(282240, $plan['duration']);
    }

    public function testResearchNamesAreDistinctAndEveryCombatShipHasOneSpecialistTurret(): void
    {
        $catalog = $this->catalog();
        $researchLabels = array_column($catalog->all('technologies'), 'label');
        self::assertCount(count(array_unique($researchLabels)), $researchLabels, 'Les libellés de recherche doivent être distincts.');

        $expected = [
            'ground_turret_anti_light' => ['interceptor','fighter','heavy_fighter','stealth_fighter'],
            'ground_turret_anti_escort' => ['corvette','frigate','destroyer'],
            'ground_turret_anti_heavy' => ['cruiser','battleship'],
            'ground_turret_anti_capital' => ['capital'],
        ];
        $defenses = $catalog->all('defenses');
        $combat = $catalog->get('combat_modifiers', 'modifiers');
        self::assertCount(4, $defenses);
        self::assertSame([], array_diff(array_keys($expected), array_keys($defenses)));
        foreach ($expected as $slug => $ships) {
            self::assertSame($ships, $defenses[$slug]['countered_ships']);
            self::assertContains($defenses[$slug]['weapon_type'], ['laser','plasma']);
            foreach ($ships as $ship) self::assertSame(1.3, $combat[$slug][$ship]);
        }
    }


    public function testFirstResearchLevelChangesOperationDurationCostsAndShipStats(): void
    {
        $catalog = $this->catalog();
        $universe = $catalog->forUniverse($this->universe());
        $calculator = new OperationCalculator($catalog);

        $building = $universe['catalog']['buildings']['mine_metal'];
        $baseBuild = $calculator->calculate($building, 'buildings', 1, 1, [], $universe);
        $fasterBuild = $calculator->calculate($building, 'buildings', 1, 1, [], $universe, ['engineering_foundations' => ['basic' => 1]]);
        self::assertSame((int) ceil($baseBuild['duration'] * .99), $fasterBuild['duration']);

        $baseBuildingCosts = $baseBuild['costs'];
        $discountedBuildingCosts = $calculator->calculate($building, 'buildings', 1, 1, [], $universe, ['nanomaterial_synthesis' => ['basic' => 1]])['costs'];
        self::assertLessThan(array_sum($baseBuildingCosts), array_sum($discountedBuildingCosts));

        $ship = $universe['catalog']['ships']['fighter'];
        $baseShipCosts = $calculator->calculate($ship, 'ships', 1, 1, [], $universe)['costs'];
        $discountedShipCosts = $calculator->calculate($ship, 'ships', 1, 1, [], $universe, ['naval_engineering' => ['basic' => 1]])['costs'];
        self::assertLessThan(array_sum($baseShipCosts), array_sum($discountedShipCosts));

        $fighter = $catalog->getForUniverse($this->universe(), 'ships', 'fighter');
        $improved = (new TechnologyEffectCalculator($catalog))->applyToShips([$fighter], ['weapon_calibration' => ['basic' => 1]], $this->universe())[0];
        self::assertEqualsWithDelta(70.7, $improved['precision'], .000001);
    }

}
