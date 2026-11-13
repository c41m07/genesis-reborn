<?php
namespace App\Tests\Unit;

use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use App\Service\Queue\QueueTiming;
use App\Entity\Universe;
use PHPUnit\Framework\TestCase;

final class OperationElectricityTest extends TestCase
{
    public function testEveryTierGroupAndUniversePaysForTheEffectiveBatchDuration():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        foreach(['omega-1','turbo-1'] as $slug) foreach(GameCatalog::TIERS as $tier) {
            $universe=new Universe();$universe->slug=$slug;$universe->name='Test';$config=$catalog->forUniverse($universe);
            foreach(['technologies'=>'engineering_foundations','ships'=>'probe','defenses'=>'ground_turret_anti_light'] as $group=>$item) foreach([1,10,100] as $quantity) {
                $definition=$catalog->getTierForUniverse($universe,$group,$item,$tier);
                $plan=$calculator->calculate($definition,$group,1,$quantity,['lab'=>['basic'=>80],'shipyard'=>['basic'=>80]],$config);
                self::assertSame((int)ceil($plan['duration']/60),$plan['costs']['electricity']);
                $boosted=QueueTiming::boostedDuration($plan['duration'],0,2);
                self::assertLessThanOrEqual($plan['duration'],$boosted);
            }
        }
        $definition=$catalog->getTierForUniverse($universe,'buildings','mine_metal','basic');
        self::assertArrayNotHasKey('electricity',$calculator->calculate($definition,'buildings',1,1,[],$config)['costs']);
    }

    public function testBatchRoundsOnceAndElectricityLimitsTheMaximum():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='omega-1';$universe->name='Test';$config=$catalog->forUniverse($universe);
        $definition=['slug'=>'test','_tier'=>'basic','build_time_base'=>1,'costs_base'=>['metal'=>1]];
        self::assertSame(2,$calculator->calculate($definition,'defenses',1,61,[],$config)['costs']['electricity']);
        self::assertSame(60,$calculator->maximum($definition,'defenses',['metal'=>['stock'=>1000],'electricity'=>['stock'=>1]],[],$config));
        self::assertSame(0,$calculator->maximum($definition,'defenses',['metal'=>['stock'=>1000],'electricity'=>['stock'=>0]],[],$config));
        $normal=$calculator->calculate($catalog->getTierForUniverse($universe,'ships','colony_ship','basic'),'ships',1,1,[],$config);
        $reduced=$calculator->calculate($catalog->getTierForUniverse($universe,'ships','colony_ship','basic'),'ships',1,1,['shipyard'=>['basic'=>80,'intermediate'=>80,'superior'=>80]],$config);
        self::assertLessThan($normal['costs']['electricity'],$reduced['costs']['electricity']);
    }
}
