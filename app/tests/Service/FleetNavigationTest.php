<?php
namespace App\Tests\Service;

use App\Entity\Fleet;
use App\Service\Fleet\{FleetNavigation,MovementCalculator};
use PHPUnit\Framework\TestCase;

final class FleetNavigationTest extends TestCase
{
    private function ships(): array { return [['slug'=>'transport','quantity'=>2,'speed_system'=>1000,'speed_galaxy'=>1000,'fuel_travel_system'=>.5,'fuel_travel_galaxy'=>.25,'fuel_orbit'=>0,'fuel_capacity'=>10000,'cargo_capacity'=>10000]]; }
    public function testSuccessiveBoostsNeverRewriteDistanceAlreadyTraveled(): void
    {
        $nav=new FleetNavigation();$calculator=new MovementCalculator();$at=new \DateTimeImmutable('2026-11-12T12:00:00Z');
        $origin=[1,1,0,0,2];$target=[1,1,0,0,8];$q=$calculator->quote($origin,$target,$this->ships(),1,1,'proportional');
        self::assertSame(216,$q['seconds']);
        $f=new Fleet();$f->status='traveling';$f->departureAt=$at;$f->arrivalAt=$at->modify('+216 seconds');
        $f->navigation=$nav->freeze($origin,$target,$this->ships(),1,'proportional',$q,$at);
        $now=$at->modify('+108 seconds');$nav->reschedule($f,$now);$f->arrivalAt=$at->modify('+189 seconds');
        self::assertEqualsWithDelta(.5,$nav->progress($f,$now),.000001);
        $now=$at->modify('+135 seconds');$p=$nav->progress($f,$now);self::assertEqualsWithDelta(2/3,$p,.000001);
        $traveled=$nav->traveled($f->navigation,$p);self::assertEqualsWithDelta(40,$traveled['fuel'],.000001);
        self::assertEqualsWithDelta(6,$traveled['point']['position'],.000001);self::assertSame(144,$traveled['seconds']);
        $nav->reschedule($f,$now);$f->arrivalAt=$at->modify('+176 seconds');
        self::assertEqualsWithDelta($p,$nav->progress($f,$now),.000001);
        self::assertEqualsWithDelta(1,$nav->progress($f,$f->arrivalAt),.000001);
    }
    public function testHyperlanePointAndFuelUseTheFrozenRoute(): void
    {
        $nav=new FleetNavigation();$c=new MovementCalculator();$at=new \DateTimeImmutable();
        $origin=[1,1,0,0,2];$target=[2,1,1,0,8];$q=$c->quote($origin,$target,$this->ships(),1,1,'proportional');
        self::assertSame(720,$q['seconds']);self::assertEqualsWithDelta(150,$q['one_way_fuel'],.000001);
        $state=$nav->freeze($origin,$target,$this->ships(),1,'proportional',$q,$at);$result=$nav->traveled($state,.5);
        self::assertNull($result['point']['system_id']);self::assertEqualsWithDelta(.2,$result['point']['x'],.000001);
        self::assertEqualsWithDelta(90,$result['fuel'],.000001);
        $d=$nav->distances($result['point'],[2,1,1,0,5]);
        $return=$c->quoteDistances($d['normal'],$d['hyper'],$d['same'],$this->ships(),1,1,'proportional');
        self::assertEqualsWithDelta(90,$return['one_way_fuel'],.000001);self::assertSame(468,$return['seconds']);
    }
    public function testFallbackCannotInventIntergalacticTravel(): void
    {
        $this->expectException(\DomainException::class);
        (new FleetNavigation())->distances(['system_id'=>null,'galaxy_id'=>1,'x'=>0,'y'=>0,'position'=>null],[1,2,0,0,2]);
    }
}
