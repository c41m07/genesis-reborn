<?php
namespace App\Tests\Service;
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

}
