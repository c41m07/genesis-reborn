<?php
namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\{LiveQueueTiming, OperationCalculator};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class OperationDurationTest extends TestCase
{
    /** Références du prompt : niveau 1 ou une unité, avant réduction, univers ×1. */
    public static function profiles(): iterable
    {
        $groups = [
            'buildings' => ['mine_metal'=>60,'mine_nano'=>60,'mine_hydrogen'=>60,'power_plant'=>120,
                'mine_antimatter'=>120,'storage_metal'=>180,'storage_nano'=>180,'storage_hydrogen'=>180,
                'storage_electricity'=>180,'storage_antimatter'=>180,'lab'=>240,'fortress'=>360,'shipyard'=>1800],
            'technologies' => ['engineering_foundations'=>600,'naval_engineering'=>900,'colonial_expansion'=>28800,
                'nanomaterial_synthesis'=>1200,'weapons_engineering'=>1800,'tactical_intelligence'=>1200,
                'modular_cargo_bays'=>1800,'vector_propulsion'=>2700,'weapon_calibration'=>2700,
                'structural_armor'=>3600,'target_acquisition'=>3600,'cryogenic_reserves'=>2700,
                'automated_defense_repair'=>2700,'intelligence_countermeasures'=>2700,'signature_masking'=>3600,
                'signature_reduction'=>5400,'shield_harmonics'=>7200,'evasive_navigation'=>7200,'rapid_fire_cycle'=>14400],
            'ships' => ['probe'=>120,'fighter'=>180,'interceptor'=>240,'heavy_fighter'=>600,'stealth_fighter'=>900,
                'hydrogen_collector'=>180,'solar_satellite'=>180,'bomber'=>1200,'corvette'=>1800,'transport'=>1800,'colony_ship'=>7200,'frigate'=>5400,
                'destroyer'=>10800,'cruiser'=>21600,'battleship'=>43200,'capital'=>86400],
            'defenses' => ['ground_turret_anti_light'=>300,'ground_turret_anti_escort'=>900,
                'ground_turret_anti_heavy'=>1800,'ground_turret_anti_capital'=>3600],
        ];
        foreach ($groups as $group=>$profiles) {
            foreach ($profiles as $slug=>$seconds) {
                yield $group.'/'.$slug => [$group,$slug,$seconds];
            }
        }
    }

    #[DataProvider('profiles')]
    public function testStartingDurationRanksUniverseAndBatchElectricity(string $group,string $slug,int $base): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='duration-x1';$universe->name='Durées';
        $config=$catalog->forUniverse($universe);
        $multiplierKey=match($group){'buildings'=>'building_time','technologies'=>'research_time',default=>'ship_build_time'};
        foreach (['basic'=>1,'intermediate'=>1.25,'superior'=>1.5625] as $tier=>$factor) {
            $definition=$catalog->getTierForUniverse($universe,$group,$slug,$tier);
            foreach ([1,0.01,2] as $multiplier) {
                $config['multipliers'][$multiplierKey]=$multiplier;
                foreach (in_array($group,['ships','defenses'],true)?[1,7]:[1] as $quantity) {
                    $plan=$calculator->calculate($definition,$group,1,$quantity,[],$config);
                    $expected=max(1,(int)ceil($base*$factor*$multiplier*$quantity));
                    self::assertSame($expected,$plan['duration'],$slug.'/'.$tier);
                    if ($group==='buildings') {
                        self::assertArrayNotHasKey('electricity',$plan['costs']);
                    } else {
                        self::assertSame(100*(int)ceil($expected/60),$plan['costs']['electricity']);
                    }
                }
            }
        }
    }

    public function testMaximumInfrastructureReductionDoesNotCreateAnExtraSecondOrElectricMinute(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='duration-x1';$universe->name='Durées';$config=$catalog->forUniverse($universe);
        $maximum=['basic'=>25,'intermediate'=>25,'superior'=>25];
        foreach (['basic'=>9072,'intermediate'=>11340,'superior'=>14175] as $tier=>$expected) {
            $definition=$catalog->getTierForUniverse($universe,'ships','capital',$tier);
            $plan=$calculator->calculate($definition,'ships',1,1,['shipyard'=>$maximum],$config);
            self::assertSame($expected,$plan['duration']);
            self::assertSame(100*(int)ceil($expected/60),$plan['costs']['electricity']);
            $overflow=$calculator->calculate($definition,'ships',1,1,['shipyard'=>array_fill_keys(GameCatalog::TIERS,200)],$config);
            self::assertSame($plan,$overflow,'Les niveaux au-delà du plafond n’ajoutent pas de réduction.');
        }
        $colony=$calculator->calculate($catalog->getTierForUniverse($universe,'technologies','colonial_expansion','basic'),
            'technologies',1,1,['lab'=>$maximum],$config);
        self::assertSame(3024,$colony['duration']);self::assertSame(5100,$colony['costs']['electricity']);
    }

    public function testBothInfrastructuresUseTheSameCurveAndPreservePrecisionInQuotesAndQueueSnapshots(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $calculator = new OperationCalculator($catalog);
        $universe = new Universe();
        $universe->slug = 'duration-x1';
        $universe->name = 'Durées';
        $config = $catalog->forUniverse($universe);

        // Valeurs de référence calculées sur 10 h sans arrondir le pourcentage en amont.
        $cases = [
            [[20, 20, 20], 76.20, 8568],
            [[21, 20, 20], 76.83, 8342],
            [[20, 21, 20], 77.07, 8256],
            [[20, 20, 21], 77.51, 8098],
            [[25, 25, 24], 88.29, 4216],
            [[25, 25, 25], 89.50, 3780],
        ];
        foreach (['technologies' => ['engineering_foundations', 'research_time_base', 'lab'],
            'ships' => ['fighter', 'build_time_base', 'shipyard']] as $group => [$slug, $baseField, $infrastructure]) {
            $definition = $catalog->getTierForUniverse($universe, $group, $slug, 'basic');
            $definition[$baseField] = 36000;
            $quoteWithoutBonus = $calculator->calculate($definition, $group, 1, 1, [], $config);
            foreach ($cases as [$rankLevels, $expectedPercent, $expectedSeconds]) {
                $levels = array_combine(GameCatalog::TIERS, $rankLevels);
                $buildings = [$infrastructure => $levels];
                $quote = $calculator->calculate($definition, $group, 1, 1, $buildings, $config);
                $plan = $calculator->calculateQueuePlan($definition, $group, 1, 1, $buildings, $config, []);
                self::assertSame(89.5, $plan['live_timing']['cap']);
                $effectivePercent = 100 * (1 - $plan['raw_duration'] / 36000);
                self::assertSame($expectedPercent, round($effectivePercent, 2));
                self::assertSame($expectedSeconds, $quote['duration']);
                self::assertSame($quote['duration'], $plan['duration']);
                self::assertSame($quote['duration'], LiveQueueTiming::fullDuration($plan['live_timing'], $levels));
                self::assertSame($quote['costs'], $plan['costs']);
                $materials = $quote['costs'];
                unset($materials['electricity']);
                self::assertSame(array_diff_key($quoteWithoutBonus['costs'], ['electricity' => true]), $materials);
                self::assertSame(100 * (int) ceil($expectedSeconds / 60), $quote['costs']['electricity']);
            }
        }

        // La technologie qui réduit le temps de construction des bâtiments garde son plafond propre.
        $definition = $catalog->getTierForUniverse($universe, 'buildings', 'mine_metal', 'basic');
        $plan = $calculator->calculateQueuePlan($definition, 'buildings', 1, 1, [], $config, [
            'engineering_foundations' => array_fill_keys(GameCatalog::TIERS, 25),
        ]);
        self::assertSame(70.0, $plan['live_timing']['cap']);
        self::assertSame(18, $plan['duration']);
    }

    public function testDefensesKeepTheirDurationDespiteAllBuildingAndResearchBonuses(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='duration-x1';$universe->name='Durées';$config=$catalog->forUniverse($universe);
        $maximum=array_fill_keys(GameCatalog::TIERS,25);
        foreach ($catalog->all('defenses') as $slug=>$base) {
            foreach (GameCatalog::TIERS as $tier) {
                $definition=$catalog->withTier($base,$tier);
                self::assertSame($calculator->calculate($definition,'defenses',1,13,[],$config),
                    $calculator->calculate($definition,'defenses',1,13,['shipyard'=>$maximum,'fortress'=>$maximum,'lab'=>$maximum],
                        $config,['engineering_foundations'=>$maximum,'naval_engineering'=>$maximum]));
            }
        }
    }

    public function testGrowthBoundariesUseTheRequestedLevelAndKeepTheFullCurve(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='duration-x1';$universe->name='Durées';$config=$catalog->forUniverse($universe);
        // Références calculées en décimal à partir des paliers du prompt, puis arrondies une fois.
        foreach ([
            'buildings'=>['mine_metal',[1=>60,2=>68,20=>540,21=>605,40=>5390,41=>5737,
                80=>65180,81=>67481,100=>130448,101=>135053,120=>261072,121=>270810]],
            'technologies'=>['engineering_foundations',[1=>600,2=>810,5=>1993,6=>2492,10=>6082,
                11=>7177,15=>13914,16=>15584,25=>43215]],
        ] as $group=>[$slug,$levels]) {
            foreach ($levels as $level=>$expected) {
                self::assertSame($expected,$calculator->calculate($catalog->getTierForUniverse($universe,$group,$slug,'basic'),
                    $group,$level,1,[],$config)['duration'],$group.'/'.$level);
            }
        }
    }

    public function testBatchCeilingMaximumAndOneSecondMinimumStayConsistent(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='duration-x1';$universe->name='Durées';$config=$catalog->forUniverse($universe);
        $definition=$catalog->getTierForUniverse($universe,'ships','probe','superior');
        $one=$calculator->calculate($definition,'ships',1,1,[],$config);
        $two=$calculator->calculate($definition,'ships',1,2,[],$config);
        self::assertSame(188,$one['duration']);self::assertSame(375,$two['duration']);
        self::assertSame(400,$one['costs']['electricity']);self::assertSame(700,$two['costs']['electricity']);
        $stocks=array_map(static fn(int $amount):array=>['stock'=>$amount],$two['costs']);
        self::assertSame(2,$calculator->maximum($definition,'ships',$stocks,[],$config));
        --$stocks['electricity']['stock'];
        self::assertSame(1,$calculator->maximum($definition,'ships',$stocks,[],$config));
        $config['multipliers']['ship_build_time']=0.00001;
        $minimum=$calculator->calculate($definition,'ships',1,1,[],$config);
        self::assertSame(1,$minimum['duration']);self::assertSame(100,$minimum['costs']['electricity']);
    }
}
