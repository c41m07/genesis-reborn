<?php
namespace App\Tests\Service;

use App\Service\Catalog\GameCatalog;
use App\Entity\Universe;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Fleet\MovementCalculator;
use PHPUnit\Framework\TestCase;

final class MovementMapCoverageTest extends TestCase
{
    /** Chaque paire de planètes est visitée ; seuls les calculs de distance identiques sont regroupés. */
    public static function distanceCases():array
    {
        $generated=[];
        for($i=0;$i<100;++$i)$generated[]=['coord_x'=>($i%10)*3,'coord_y'=>intdiv($i,10)*3,'planets'=>array_map(static fn(int $p):array=>['position'=>$p],range(1,9))];
        $maps=['generated-100'=>$generated];
        foreach(glob(dirname(__DIR__,2).'/config/universes/*.json') as $file){
            $data=json_decode(file_get_contents($file),true,512,JSON_THROW_ON_ERROR);
            foreach($data['galaxies'] as $index=>$galaxy)$maps[$data['slug'].'/galaxy-'.$index]=$galaxy['solar_systems'];
        }
        $cases=[];$counts=[];
        foreach($maps as $name=>$systems){
            $planets=[];
            foreach($systems as $i=>$system)foreach($system['planets'] as $planet)$planets[]=[$i+1,1,$system['coord_x'],$system['coord_y'],$planet['position']];
            $counts[$name]=0;$count=count($planets);
            for($i=0;$i<$count;++$i)for($j=$i+1;$j<$count;++$j){
                $origin=$planets[$i];$target=$planets[$j];++$counts[$name];
                if($origin[0]===$target[0]){
                    $key='local:'.abs($origin[4]-$target[4]);
                }else{
                    $squaredDistance=($origin[2]-$target[2])**2+($origin[3]-$target[3])**2;
                    $key='space:'.($origin[4]+$target[4]).':'.$squaredDistance;
                }
                $cases[$key]??=['map'=>$name,'origin'=>$origin,'target'=>$target];
            }
        }
        return ['planet_pairs_by_map'=>$counts,'distinct_distances'=>$cases];
    }

    public function testEveryPlanetPairRespectsTheReferenceMaximumsForAllShipClasses():void
    {
        $coverage=self::distanceCases();
        self::assertSame(404550,$coverage['planet_pairs_by_map']['generated-100']);
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new MovementCalculator();$maximums=[];
        foreach($catalog->all('ships') as $slug=>$definition){
            $ship=$catalog->withTier($definition,'basic')+['quantity'=>1];
            foreach($coverage['distinct_distances'] as $case){
                $origin=$case['origin'];$target=$case['target'];$local=$origin[0]===$target[0];$probe=$slug==='probe';
                $limit=$probe?($local?60:180):($local?3600:43200);
                $quote=$calculator->quote($origin,$target,[$ship],1,1,'proportional');
                $return=$calculator->quote($target,$origin,[$ship],1,1,'proportional');
                $message=$slug.' : '.json_encode($case);
                self::assertLessThanOrEqual($limit,$quote['seconds'],$message);
                self::assertGreaterThanOrEqual($probe&&$local?30:60,$quote['seconds'],$message);
                self::assertSame($quote['seconds'],$return['seconds'],$message);
                $type=$local?'local':'space';$maximums[$slug][$type]=max($maximums[$slug][$type]??0,$quote['seconds']);
            }
        }
        self::assertSame(['local'=>3600,'space'=>43167],$maximums['capital']);
        self::assertSame(['local'=>60,'space'=>180],$maximums['probe']);
    }

    public function testReferenceMaximumIsNotAnArtificialCapOnVoluntarilySlowerTravel():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');
        $ship=$catalog->withTier($catalog->get('ships','capital'),'basic')+['quantity'=>1];
        $quote=(new MovementCalculator())->quote([1,1,0,0,1],[100,1,27,27,1],[$ship],.1,1,'proportional');
        self::assertSame(431668,$quote['seconds']);
        self::assertGreaterThan(43200,$quote['seconds']);
    }

    public function testEveryShipCanFundEveryReturnTripWithItsDedicatedTankAtEveryRank():void
    {
        $coverage=self::distanceCases();$catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new MovementCalculator();
        $policy=$catalog->rules()['fuel_ratio_policy'];
        self::assertSame('proportional',$policy,'Avec cette règle, un départ à 100 % est le plus coûteux.');
        $combatHours=$catalog->rules()['combat_window_seconds']/3600;$maximumFuel=0.0;
        foreach(GameCatalog::TIERS as $tier)foreach($catalog->all('ships') as $slug=>$definition){
            $ship=$catalog->withTier($definition,$tier)+['quantity'=>1];
            foreach($coverage['distinct_distances'] as $case){
                $quote=$calculator->quote($case['origin'],$case['target'],[$ship],1,1,$policy,$combatHours);
                $message="$slug/$tier : ".json_encode($case);
                self::assertLessThanOrEqual($quote['fuel_capacity'],$quote['total_fuel'],$message);
                self::assertEqualsWithDelta($ship['cargo_capacity'],$quote['freight_capacity'],1e-8,$message.' : aucune soute empruntée pour le carburant');
                $maximumFuel=max($maximumFuel,$quote['total_fuel']);
            }
            foreach([.1,.5,1.0] as $ratio){
                $quote=$calculator->quote([1,1,0,0,1],[100,1,27,27,1],[$ship],$ratio,1,$policy,$combatHours);
                self::assertLessThanOrEqual($ship['fuel_capacity'],$quote['total_fuel'],"$slug/$tier/$ratio");
            }
        }
        self::assertEqualsWithDelta(15453.556473629428,$maximumFuel,1e-8);
    }

    public function testTankResearchIncreasesAutonomyAndAMixedFleetKeepsItsCargoFree():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new MovementCalculator();
        $universe=new Universe();$universe->slug='simulation';$universe->name='Simulation';
        $definition=$catalog->withTier($catalog->get('ships','fighter'),'basic')+['quantity'=>1];
        $boosted=(new TechnologyEffectCalculator($catalog))->applyToShips([$definition],['cryogenic_reserves'=>['basic'=>25,'intermediate'=>25,'superior'=>25]],$universe)[0];
        self::assertSame(16000,$definition['fuel_capacity']);
        self::assertSame(20000.0,$boosted['fuel_capacity']);
        $fleet=[];$expectedCargo=0;
        foreach($catalog->all('ships') as $ship){
            $ship=$catalog->withTier($ship,'basic')+['quantity'=>3];$fleet[]=$ship;$expectedCargo+=$ship['cargo_capacity']*3;
        }
        $quote=$calculator->quote([1,1,0,0,1],[100,1,27,27,1],$fleet,1,1,$catalog->rules()['fuel_ratio_policy'],1/6);
        self::assertLessThanOrEqual($quote['fuel_capacity'],$quote['total_fuel']);
        self::assertEqualsWithDelta($expectedCargo,$quote['freight_capacity'],1e-8);
    }
}
