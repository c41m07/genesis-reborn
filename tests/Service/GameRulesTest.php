<?php
namespace App\Tests\Service;
use App\Service\Queue\QueueTiming;
use App\Service\Colony\ResourceService;
use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;
use App\Entity\Universe;
use PHPUnit\Framework\TestCase;
final class GameRulesTest extends TestCase
{
    public function testQueueFinishTimesFromPdf7Page2(): void
    {
        $t=new \DateTimeImmutable('2026-01-01T00:00:00Z');$ends=QueueTiming::finishTimes($t,[60,90,120]);
        self::assertSame([60,150,270],array_map(fn($e)=>$e->getTimestamp()-$t->getTimestamp(),$ends));
    }
    public function testBoostOnlyRemainingAndCumulativePdf7Page5(): void
    {
        self::assertSame(2400,QueueTiming::boostedDuration(3600,1200,2));
        self::assertSame(1800,QueueTiming::boostedDuration(2400,1200,2));
        self::assertSame(900,QueueTiming::boostedDuration(QueueTiming::boostedDuration(3600,0,2),0,2));
    }
    public function testLazyResourcesAndBackwardClock(): void
    {
        $last=new \DateTimeImmutable('2026-01-01T12:00:00Z');
        self::assertSame(130.0,ResourceService::stock(100,.5,$last,$last->modify('+60 seconds'),1000));
        self::assertSame(120.0,ResourceService::stock(100,.5,$last,$last->modify('+60 seconds'),120));
        self::assertSame(100.0,ResourceService::stock(100,.5,$last,$last->modify('-60 seconds'),1000));
    }
    public function testTierRequirementsAndRecursiveShipUnlock(): void
    {
        $c=new GameCatalog(dirname(__DIR__,2).'/config/game');$checker=new RequirementChecker($c);
        $d=$c->get('buildings','shipyard');
        self::assertNotEmpty($checker->check($checker->forLevel($d,1),[],[]));
        self::assertNotEmpty($checker->check($checker->forLevel($d,4),[],[]));
        self::assertSame([], $checker->check($checker->forLevel($d,4),['mine_metal'=>2],['naval_engineering'=>1]));
        self::assertSame([], $checker->check(['ships'=>['fighter']],[],[]));
        self::assertSame(['metal'=>5400,'nano'=>1800,'antimatter'=>0,'hydrogen'=>0],$c->costs($c->get('ships','fighter'),1,3));
    }
    public function testCatalogueUnlockRequirementsForBuildingsAndTechnologies(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$checker=new RequirementChecker($catalog);
        $plain=['buildings'=>[],'technologies'=>[]];
        self::assertSame([],$checker->check($plain,[],[]));

        $buildingGate=['buildings'=>[['slug'=>'mine_metal','tier'=>'basic','level'=>2]]];
        self::assertNotEmpty($checker->check($buildingGate,[],[]));
        self::assertSame([],$checker->check($buildingGate,['mine_metal'=>['basic'=>2]],[]));

        $technologyGate=['technologies'=>[['slug'=>'engineering_foundations','tier'=>'basic','level'=>1]]];
        self::assertNotEmpty($checker->check($technologyGate,[],[]));
        self::assertSame([],$checker->check($technologyGate,[],['engineering_foundations'=>['basic'=>1]]));
    }
    public function testBuildingAccessIsFixedAndDefenseRanksAreInitiallyGated(): void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$checker=new RequirementChecker($catalog);
        $lab=$catalog->get('buildings','lab');$labLevelTwo=$checker->forTier($lab,'basic',2);
        self::assertSame([],$checker->check($labLevelTwo,[],[]));
        self::assertSame([],$checker->check($labLevelTwo,[
            'power_plant'=>['basic'=>3],'mine_nano'=>['basic'=>2],
        ],['engineering_foundations'=>['basic'=>2]]));

        $defense=$catalog->get('defenses','ground_turret_anti_capital');
        self::assertNotEmpty($checker->check($checker->forTier($defense,'basic'),[],[]));
        self::assertSame([],$checker->check($checker->forTier($defense,'basic'),['fortress'=>['basic'=>5]],[
            'rapid_fire_cycle'=>['basic'=>5],'weapons_engineering'=>['basic'=>8],
        ]));
        $advanced=$checker->forTier($defense,'intermediate');
        self::assertNotEmpty($checker->check($advanced,['fortress'=>['basic'=>10]],[]));
    }
    public function testClosedAndFutureUniverses(): void
    {
        $u=new Universe();$t=new \DateTimeImmutable('2026-01-01');self::assertTrue($u->isAccessible($t));
        $u->endsAt=$t;self::assertFalse($u->isAccessible($t));$u->endsAt=null;$u->startsAt=$t->modify('+1 second');self::assertFalse($u->isAccessible($t));
    }
}
