<?php
namespace App\Tests\Service;

use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;
use PHPUnit\Framework\TestCase;

final class InitialUnlockRequirementsTest extends TestCase
{
    public function testAllRanksHaveFixedReachableRequirementsAndNoMilestones():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$checker=new RequirementChecker($catalog);
        $pending=[];$buildings=[];$tech=[];
        foreach(['buildings','technologies','ships','defenses'] as $group)foreach($catalog->all($group) as $slug=>$base){
            self::assertArrayNotHasKey('levels',$base);
            foreach(GameCatalog::TIERS as $tier){
                $requirements=$checker->forTier($base,$tier);
                foreach([1,2,3,6,10,25,80,120,200] as $level)self::assertSame($requirements,$checker->forTier($base,$tier,$level));
                foreach(['buildings','technologies'] as $kind)foreach($requirements[$kind]??[] as $r){
                    $parent=$catalog->get($kind,$r['slug']);$rank=$r['tier']??'basic';
                    self::assertLessThanOrEqual($parent['effect']['tier_max_levels'][$rank]??$parent['max_level']??PHP_INT_MAX,$r['level'],"$group/$slug/$tier");
                }
                $pending[$group.':'.$slug.':'.$tier]=[$group,$slug,$tier,$base,$requirements];
            }
        }
        self::assertCount(150,$pending);
        do{
            $previous=count($pending);
            foreach($pending as $key=>[$group,$slug,$tier,$base,$requirements]){
                if($checker->check($requirements,$buildings,$tech)!==[])continue;
                if($group==='buildings')$buildings[$slug][$tier]=$base['max_level']??200;
                if($group==='technologies')$tech[$slug][$tier]=$base['effect']['tier_max_levels'][$tier]??$base['max_level']??25;
                unset($pending[$key]);
            }
        }while($pending&&count($pending)<$previous);
        self::assertSame([],array_keys($pending),'Chaque rang doit être atteignable depuis les éléments de départ, sans dépendance circulaire.');
    }

    public function testHistoricalNumericLevelsNeverSatisfyAnAdvancedRank():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$checker=new RequirementChecker($catalog);
        $requirements=['technologies'=>[['slug'=>'naval_engineering','tier'=>'intermediate','level'=>1]]];
        self::assertNotEmpty($checker->check($requirements,[],['naval_engineering'=>25]));
        self::assertSame([],$checker->check($requirements,[],['naval_engineering'=>['intermediate'=>1]]));
    }

    public function testLegacyMilestonesAreIgnoredEvenInUniverseOverrides():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$checker=new RequirementChecker($catalog);
        $base=['unlock_requirements'=>[],'levels'=>[2=>['unlock_requirements'=>['technologies'=>[['slug'=>'naval_engineering','level'=>99]]]]]];
        self::assertSame([],$checker->forTier($base,'basic',100));
    }
}
