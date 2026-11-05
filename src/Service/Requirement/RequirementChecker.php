<?php
namespace App\Service\Requirement;
use App\Service\Catalog\GameCatalog;
final class RequirementChecker
{
    public function __construct(private GameCatalog $catalog) {}
    public function check(array $requirements,array $buildings,array $technologies,array $visited=[]): array
    {
        $failures=[];
        foreach (['buildings'=>$buildings,'technologies'=>$technologies] as $group=>$levels) {
            foreach ($requirements[$group]??[] as $r) {
                $requiredTier=(string)($r['tier']??'basic');
                if (($this->level($levels,$r['slug'],$requiredTier))<(int)$r['level']) {
                    $label=$this->catalog->withTier($this->catalog->get($group,$r['slug']),$requiredTier)['label'];
                    $failures[]=$label.' · niveau '.$r['level'];
                }
            }
        }
        foreach ($requirements['ships']??[] as $entry) {
            $slug=is_array($entry)?$entry['slug']:$entry;
            $tier=is_array($entry)?($entry['tier']??'basic'):'basic';
            if (in_array($slug,$visited,true)) throw new \LogicException('Cycle de prérequis : '.implode(' → ',[...$visited,$slug]));
            $d=$this->catalog->get('ships',$slug);
            $nested=$d['unlock_requirements']??[];
            if($tier!=='basic')$nested=$this->merge($nested,$d['tier_unlock_requirements'][$tier]??[]);
            $failures=[...$failures,...$this->check($nested,$buildings,$technologies,[...$visited,$slug])];
        }
        return array_values(array_unique($failures));
    }
    public function forLevel(array $definition,int $level): array
    {
        $requirements=$definition['unlock_requirements']??[];$best=0;
        foreach ($definition['levels']??[] as $tier=>$data) if ((int)$tier<=$level && (int)$tier>$best) { $milestone=$data['unlock_requirements']??[];$best=(int)$tier; }
        if($best>0)$requirements=$this->merge($requirements,$milestone);
        return $requirements;
    }
    public function forTier(array $definition,string $tier,int $level=1):array
    {
        return $this->merge($this->forLevel($definition,$level),$definition['tier_unlock_requirements'][$tier]??[]);
    }
    private function level(array $levels,string $slug,string $tier):int
    {
        $value=$levels[$slug]??0;
        if(is_numeric($value))return (int)$value;
        return (int)($value[$tier]??0);
    }
    private function merge(array $left,array $right):array
    {
        foreach($right as $group=>$items)$left[$group]=[...($left[$group]??[]),...$items];
        return $left;
    }
}
