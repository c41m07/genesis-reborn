<?php
namespace App\Tests\Service;

use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatSimulator;
use PHPUnit\Framework\TestCase;

final class CombatCountersTest extends TestCase
{
    public function testCounterMultipliesBoundedChanceRatherThanAddingFlatPoints():void
    {
        $simulator=new CombatSimulator();$attacker=['precision'=>70,'tracking'=>55];$target=['evasion'=>60];
        self::assertSame(50.0,$simulator->hitChance($attacker,$target));
        self::assertSame(65.0,$simulator->hitChance($attacker,$target,0,1.3));
        self::assertSame(35.0,$simulator->hitChance($attacker,$target,0,.7));
        self::assertSame(95.0,$simulator->hitChance($attacker,$target,30,1.3));
        self::assertEqualsWithDelta(19.5,$simulator->hitChance(['precision'=>0,'tracking'=>0],$target,0,1.3),1e-8);
        self::assertEqualsWithDelta(66.5,$simulator->hitChance(['precision'=>200,'tracking'=>200],$target,0,.7),1e-8);
        self::assertSame(15.0,$simulator->hitChance(['precision'=>0,'tracking'=>0],$target,0,.7));
        self::assertEqualsWithDelta(91,$simulator->hitChance($attacker+['tier'=>'superior'],$target+['tier'=>'basic'],0,1.3),1e-8);
    }

    public function testPrimaryExtraAndSecondaryShotsUseTheSameCounterOnceForChanceAndDamage():void
    {
        $attacker=$this->unit('attacker',10);$attacker['shots_min']=100;$attacker['shots_max']=100;$attacker['extra_shot_chances']=[1.0];
        $attacker+=['secondary_attack'=>20,'secondary_shots_min'=>2,'secondary_shots_max'=>2,'secondary_precision_modifier'=>30,'secondary_target_category'=>'light'];
        $target=$this->unit('target',0);$target['category']='light';
        $participants=$this->participants($attacker,$target);
        foreach([1.3,.7] as $factor){
            $result=(new CombatSimulator())->simulate($participants,[],['attacker'=>['target'=>$factor]],1,12345);
            $primary=array_values(array_filter($result['rounds'][0]['shots'],static fn(array $s):bool=>$s['weapon']==='primary'));
            $secondary=array_values(array_filter($result['rounds'][0]['shots'],static fn(array $s):bool=>$s['weapon']==='anti_light'));
            self::assertCount(200,$primary);self::assertCount(2,$secondary);
            self::assertCount(100,array_filter($primary,static fn(array $s):bool=>$s['extra_shot']));
            $hits=0;
            foreach([...$primary,...$secondary] as $shot){
                $isSecondary=$shot['weapon']==='anti_light';
                self::assertEqualsWithDelta(min(95,($isSecondary?80:50)*$factor),$shot['chance'],1e-8);
                if($shot['hit']){++$hits;self::assertEqualsWithDelta(($isSecondary?20:10)*$factor,$shot['damage'],1e-8);}
            }
            self::assertGreaterThan(0,$hits);
        }
    }

    public function testAllSecondaryWeaponsOnlyFireAtEligibleShipCategoriesAndUseConfiguredChances():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$modifiers=$catalog->all('combat_modifiers')['modifiers'];
        $expected=['destroyer'=>['fighter'=>81,'interceptor'=>66,'stealth_fighter'=>51],
            'cruiser'=>['fighter'=>75,'interceptor'=>60,'stealth_fighter'=>45],
            'battleship'=>['fighter'=>68,'interceptor'=>53,'stealth_fighter'=>38],
            'capital'=>['fighter'=>70.15,'interceptor'=>52.9,'stealth_fighter'=>35.65]];
        foreach($expected as $slug=>$targets){
            $attacker=$catalog->withTier($catalog->get('ships',$slug),'basic');$attacker['quantity']=1;
            self::assertSame(30,$attacker['secondary_precision_modifier']);
            foreach($catalog->all('ships') as $targetSlug=>$base){
                $target=$catalog->withTier($base,'basic');$target['quantity']=1;$target['hp']=1000000;$target['shield']=0;$target['base_attack']=0;
                $result=(new CombatSimulator())->simulate($this->participants($attacker,$target),[],$modifiers,1,12345);
                $shots=array_values(array_filter($result['rounds'][0]['shots'],static fn(array $s):bool=>$s['weapon']==='anti_light'));
                if(!isset($targets[$targetSlug])){self::assertSame([],$shots,$slug.' contre '.$targetSlug);continue;}
                self::assertCount(1,$shots,$slug.' contre '.$targetSlug);
                self::assertEqualsWithDelta($targets[$targetSlug],$shots[0]['chance'],1e-8,$slug.' contre '.$targetSlug);
            }
        }
    }

    public function testPlanetaryDefenseUsesCounterForItsHitChanceAndDamage():void
    {
        $target=$this->unit('target',0);$target['category']='light';
        $defense=$this->unit('turret',10);$defense['id']='turret';$defense['camp']='defenders';
        $participants=[['id'=>'a','role'=>'attacker','camp'=>'attackers','arrival_round'=>1,'units'=>[$target]]];
        $result=(new CombatSimulator())->simulate($participants,[$defense],['turret'=>['target'=>1.3]],1,12345);
        self::assertNotEmpty($result['rounds'][0]['shots']);
        foreach($result['rounds'][0]['shots'] as $shot){self::assertSame(65.0,$shot['chance']);if($shot['hit'])self::assertSame(13.0,$shot['damage']);}
    }

    public function testGenericDefenseCounterAppliesToAllTurretsUnlessAnExplicitRelationOverridesIt():void
    {
        $attacker=$this->unit('bomber',10);$attacker['shots_min']=100;$attacker['shots_max']=100;
        foreach(['ground_turret_anti_light','ground_turret_anti_heavy'] as $slug){
            $defense=$this->unit($slug,0);$defense['id']='defense';$defense['camp']='defenders';
            foreach([['ground_defense'=>1.3],['ground_defense'=>1.3,$slug=>.85]] as $relations){
                $factor=$relations[$slug]??$relations['ground_defense'];
                $participants=[['id'=>'a','role'=>'attacker','camp'=>'attackers','arrival_round'=>1,'units'=>[$attacker]]];
                $result=(new CombatSimulator())->simulate($participants,[$defense],['bomber'=>$relations],1,12345);
                self::assertCount(100,$result['rounds'][0]['shots']);$hits=0;
                foreach($result['rounds'][0]['shots'] as $shot){
                    self::assertEqualsWithDelta(50*$factor,$shot['chance'],1e-8);
                    if($shot['hit']){++$hits;self::assertEqualsWithDelta(10*$factor,$shot['damage'],1e-8);}
                }
                self::assertGreaterThan(0,$hits);
            }
        }
    }

    public function testChasseurCounterTargetsAllThreeMembersAcrossEveryRankButExcludesHeavyFighters():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$counters=$catalog->all('combat_modifiers');
        self::assertSame(['fighter','stealth_fighter','interceptor'],$counters['target_groups']['fighters']['members']);
        self::assertSame(1.3,$counters['relations']['corvette']['fighters']);
        self::assertArrayNotHasKey('fighters',$counters['modifiers']['corvette']);
        foreach(['fighter','stealth_fighter','interceptor'] as $slug){
            self::assertSame(1.3,$counters['modifiers']['corvette'][$slug]);
            self::assertSame(1.3,$counters['modifiers']['heavy_fighter'][$slug]);
            foreach(GameCatalog::TIERS as $attackerTier)foreach(GameCatalog::TIERS as $targetTier){
                $attacker=$catalog->withTier($catalog->get('ships','corvette'),$attackerTier);$attacker['quantity']=1;
                $target=$catalog->withTier($catalog->get('ships',$slug),$targetTier);$target['quantity']=1;$target['hp']=1000000;$target['shield']=0;$target['base_attack']=0;
                $simulator=new CombatSimulator();$result=$simulator->simulate($this->participants($attacker,$target),[],$counters['modifiers'],1,12345);
                self::assertNotEmpty($result['rounds'][0]['shots']);
                foreach($result['rounds'][0]['shots'] as $shot){
                    self::assertEqualsWithDelta($simulator->hitChance($attacker,$target,0,1.3),$shot['chance'],1e-8);
                    if($shot['hit'])self::assertEqualsWithDelta($attacker['base_attack']*1.3,$shot['damage'],1e-8);
                }
            }
        }
        self::assertEquals(1,$counters['modifiers']['corvette']['heavy_fighter']);
        self::assertSame(1.3,$counters['modifiers']['capital']['heavy_fighter']);
    }

    private function unit(string $slug,int $attack):array
    {
        return ['slug'=>$slug,'quantity'=>1,'hp'=>1000000,'shield'=>0,'precision'=>70,'tracking'=>55,'evasion'=>60,'signature'=>50,'base_attack'=>$attack,'shots_min'=>1,'shots_max'=>1];
    }

    private function participants(array $attacker,array $target):array
    {
        return [['id'=>'a','role'=>'attacker','camp'=>'attackers','arrival_round'=>1,'units'=>[$attacker]],
            ['id'=>'b','role'=>'defender','camp'=>'defenders','arrival_round'=>1,'units'=>[$target]]];
    }
}
