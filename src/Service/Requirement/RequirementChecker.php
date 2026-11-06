<?php
namespace App\Service\Requirement;
use App\Service\Catalog\GameCatalog;
final class RequirementChecker
{
    public function __construct(private GameCatalog $catalog) {}
    public function check(array $requirements,array $buildings,array $technologies,array $visited=[],?array $definitions=null): array
    {
        $failures=[];
        foreach (['buildings'=>$buildings,'technologies'=>$technologies] as $group=>$levels) {
            foreach ($requirements[$group]??[] as $r) {
                $requiredTier=(string)($r['tier']??'basic');
                if (($this->level($levels,$r['slug'],$requiredTier))<(int)$r['level']) {
                    $label=$this->catalog->withTier($definitions[$group][$r['slug']]??$this->catalog->get($group,$r['slug']),$requiredTier)['label'];
                    $failures[]=$label.' · niveau '.$r['level'];
                }
            }
        }
        foreach ($requirements['ships']??[] as $entry) {
            $slug=is_array($entry)?$entry['slug']:$entry;
            $tier=is_array($entry)?($entry['tier']??'basic'):'basic';
            $key=$slug.':'.$tier;
            if (in_array($key,$visited,true)) throw new \LogicException('Cycle de prérequis : '.implode(' → ',[...$visited,$key]));
            $d=$definitions['ships'][$slug]??$this->catalog->get('ships',$slug);
            $nested=$d['unlock_requirements']??[];
            if($tier!=='basic')$nested=$this->merge($nested,$d['tier_unlock_requirements'][$tier]??[]);
            $failures=[...$failures,...$this->check($nested,$buildings,$technologies,[...$visited,$key],$definitions)];
        }
        return array_values(array_unique($failures));
    }
    public function forLevel(array $definition,int $level): array
    {
        return $definition['unlock_requirements']??[];
    }
    public function forTier(array $definition,string $tier,int $level=1):array
    {
        return $this->merge($this->forLevel($definition,$level),$definition['tier_unlock_requirements'][$tier]??[]);
    }
    private function level(array $levels,string $slug,string $tier):int
    {
        $value=$levels[$slug]??0;
        if(is_numeric($value))return $tier==='basic'?(int)$value:0;
        return (int)($value[$tier]??0);
    }
    private function merge(array $left,array $right):array
    {
        $result=[];
        foreach(array_unique([...array_keys($left),...array_keys($right)]) as $group){
            $entries=[];
            foreach([...($left[$group]??[]),...($right[$group]??[])] as $item){
                $key=is_array($item)?$item['slug'].':'.($item['tier']??'basic'):$item.':basic';
                if(!isset($entries[$key])||(int)($item['level']??0)>(int)($entries[$key]['level']??0))$entries[$key]=$item;
            }
            $result[$group]=array_values($entries);
        }
        return $result;
    }
}
