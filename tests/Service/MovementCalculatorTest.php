<?php
namespace App\Tests\Service;
use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Fleet\MovementCalculator;
use PHPUnit\Framework\TestCase;
final class MovementCalculatorTest extends TestCase
{
    private function ships(): array { return [['quantity'=>7,'speed_system'=>50,'speed_galaxy'=>500,'fuel_travel_system'=>.5,'fuel_travel_galaxy'=>2,'fuel_orbit'=>.3,'cargo_capacity'=>50000]]; }
    public function testDocument3ExampleInverseAndSymmetry(): void
    {
        $c=new MovementCalculator();$a=[1,1,3,5,3];$b=[2,1,7,9,7];$q=$c->quote($a,$b,$this->ships(),.8,1,'inverse',1/6);$r=$c->quote($b,$a,$this->ships(),.8,1,'inverse',1/6);
        self::assertEqualsWithDelta(665.7,$q['distance'],.02);self::assertEqualsWithDelta(10337.25,$q['one_way_fuel'],.3);self::assertEqualsWithDelta(20674.85,$q['total_fuel'],.6);
        self::assertSame($q['distance'],$r['distance']);self::assertSame($q['seconds'],$r['seconds']);self::assertSame($q['total_fuel'],$r['total_fuel']);
    }
    public function testBothExplicitFuelPoliciesAndLowerSpeed(): void
    {
        $c=new MovementCalculator();$a=[1,1,0,0,1];$b=[1,1,0,0,9];
        $fast=$c->quote($a,$b,$this->ships(),1,1,'proportional');$slow=$c->quote($a,$b,$this->ships(),.5,1,'proportional');$inverse=$c->quote($a,$b,$this->ships(),.5,1,'inverse');
        self::assertSame(80,$fast['normal_distance']);self::assertSame(0,$fast['hyper_distance']);self::assertSame($fast['seconds']*2,$slow['seconds']);self::assertSame($fast['one_way_fuel']/2,$slow['one_way_fuel']);self::assertSame($fast['one_way_fuel']*2,$inverse['one_way_fuel']);
    }
    public function testPendingPolicyCannotLaunch(): void
    {
        $this->expectException(\DomainException::class);(new MovementCalculator())->quote([1,1,0,0,1],[2,1,1,1,2],$this->ships(),1,1,'pending');
    }
    public function testCrossGalaxyRejected(): void
    {
        $this->expectException(\DomainException::class);(new MovementCalculator())->quote([1,1,0,0,1],[2,2,1,1,2],$this->ships(),1,1,'proportional');
    }
    public function testDedicatedFuelTanksAllowSoloShipButCannotCarryFreight(): void
    {
        $ships=$this->ships();$ships[0]['quantity']=1;$ships[0]['cargo_capacity']=0;$ships[0]['fuel_capacity']=1000;
        $q=(new MovementCalculator())->quote([1,1,0,0,1],[1,1,0,0,9],$ships,.5,1,'proportional',2);
        self::assertSame(1000,$q['fuel_capacity']);self::assertSame(0,$q['cargo_capacity']);self::assertSame(0,$q['freight_capacity']);
        self::assertEqualsWithDelta(40.6,$q['total_fuel'],.000001);
        self::assertEqualsWithDelta(.6,$q['orbit_fuel'],.000001);
    }
    public function testExtraFuelUsesCargoButDedicatedTankNeverAddsFreightSpace(): void
    {
        $ships=$this->ships();$ships[0]['quantity']=1;$ships[0]['cargo_capacity']=500;$ships[0]['fuel_capacity']=10;
        $q=(new MovementCalculator())->quote([1,1,0,0,1],[1,1,0,0,9],$ships,1,1,'proportional');
        self::assertEqualsWithDelta(80,$q['total_fuel'],.000001);self::assertEqualsWithDelta(430,$q['freight_capacity'],.000001);
    }

    private function catalog():GameCatalog
    {
        return new GameCatalog(dirname(__DIR__,2).'/config/game');
    }

    private function ship(string $slug,string $tier='basic'):array
    {
        $catalog=$this->catalog();
        return $catalog->withTier($catalog->get('ships',$slug),$tier)+['quantity'=>1];
    }

    public function testLocalTravelMatchesTheReferenceTimesAndIsSymmetric():void
    {
        $calculator=new MovementCalculator();$origin=[40,1,27,9,4];
        foreach(['capital'=>[9=>2250,1=>1350,3=>450],'probe'=>[9=>38,1=>30,3=>30]] as $slug=>$destinations){
            foreach($destinations as $position=>$expected){
                $target=[40,1,27,9,$position];$ships=[$this->ship($slug)];
                $outbound=$calculator->quote($origin,$target,$ships,1,1,'proportional');
                $return=$calculator->quote($target,$origin,$ships,1,1,'proportional');
                self::assertSame($expected,$outbound['seconds'],"$slug : 4 → $position");
                self::assertSame($expected,$return['seconds']);
            }
        }
    }

    public function testInterSystemTimesUseTheMapGeometryIncludingSystemEntryAndExit():void
    {
        $calculator=new MovementCalculator();$origin=[40,1,27,9,4];
        foreach([[1,1,0,0,4],[80,1,27,21,4]] as $index=>$target){
            foreach(['capital'=>[31538,16421],'probe'=>[124,105]] as $slug=>$expected){
                $ships=[$this->ship($slug)];
                $outbound=$calculator->quote($origin,$target,$ships,1,1,'proportional');
                $return=$calculator->quote($target,$origin,$ships,1,1,'proportional');
                self::assertSame($expected[$index],$outbound['seconds'],$slug);
                self::assertSame($outbound['seconds'],$return['seconds']);
                self::assertSame(120,$outbound['normal_distance']);
            }
        }
    }

    public function testMinimumsApplyAfterUniverseAccelerationAndRequireOnlyProbesLocally():void
    {
        $calculator=new MovementCalculator();$origin=[40,1,27,9,4];$local=[40,1,27,9,9];$distant=[1,1,0,0,4];
        $probe=$this->ship('probe');$probe['quantity']=3;$capital=$this->ship('capital');
        foreach([1/40,1/100,0.0001] as $multiplier){
            self::assertSame(30,$calculator->quote($origin,$local,[$probe],1,$multiplier,'proportional')['seconds']);
            $capitalSeconds=max(60,(int)ceil(2250*$multiplier));
            self::assertSame($capitalSeconds,$calculator->quote($origin,$local,[$capital],1,$multiplier,'proportional')['seconds']);
            self::assertSame($capitalSeconds,$calculator->quote($origin,$local,[$probe,$capital],1,$multiplier,'proportional')['seconds']);
            self::assertSame(60,$calculator->quote($origin,$distant,[$probe],1,$multiplier,'proportional')['seconds']);
        }
    }

    public function testRanksAndResearchShortenTravelWhileTheSlowestShipDeterminesTheFleetSpeed():void
    {
        $catalog=$this->catalog();$calculator=new MovementCalculator();$origin=[40,1,27,9,4];$target=[40,1,27,9,9];
        $universe=new Universe();$universe->slug='simulation';$universe->name='Simulation';
        $max=['basic'=>25,'intermediate'=>25,'superior'=>25];
        $basic=$this->ship('capital');$superior=$this->ship('capital','superior');
        $boosted=(new TechnologyEffectCalculator($catalog))->applyToShips([$superior],['vector_propulsion'=>$max],$universe)[0];
        $base=$calculator->quote($origin,$target,[$basic],1,1,'proportional');
        $ranked=$calculator->quote($origin,$target,[$superior],1,1,'proportional');
        $researched=$calculator->quote($origin,$target,[$boosted],1,1,'proportional');
        self::assertLessThan($base['seconds'],$ranked['seconds']);
        self::assertLessThan($ranked['seconds'],$researched['seconds']);
        self::assertSame(4500,$calculator->quote($origin,$target,[$basic],.5,1,'proportional')['seconds']);
        self::assertSame($base['seconds'],$calculator->quote($origin,$target,[$basic,$this->ship('probe')],1,1,'proportional')['seconds']);
        $probe=(new TechnologyEffectCalculator($catalog))->applyToShips([$this->ship('probe','superior')],['vector_propulsion'=>$max],$universe)[0];
        $probeOrigin=$origin;$probeOrigin[4]=1;
        self::assertSame(31,$calculator->quote($probeOrigin,$target,[$probe],1,1,'proportional')['seconds']);
        self::assertSame(30,$calculator->quote($origin,$target,[$probe],1,.5,'proportional')['seconds']);
    }

    public function testClassesFollowTheChosenSpeedOrderAtEveryComparableRank():void
    {
        $order=['capital','battleship','colony_ship','transport','bomber','destroyer','cruiser','frigate','heavy_fighter','fighter','corvette','stealth_fighter','interceptor','probe'];
        foreach(GameCatalog::TIERS as $tier){
            $previous=null;
            foreach($order as $slug){
                $unit=$this->ship($slug,$tier);
                if($previous!==null){
                    self::assertGreaterThan($previous['speed_system'],$unit['speed_system'],"$slug/$tier");
                    self::assertGreaterThan($previous['speed_galaxy'],$unit['speed_galaxy'],"$slug/$tier");
                }
                $previous=$unit;
            }
        }
    }

}
