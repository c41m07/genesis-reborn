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
            foreach ($requirements[$group]??[] as $r) if (($levels[$r['slug']]??0)<$r['level']) $failures[]=$this->catalog->get($group,$r['slug'])['label'].' niveau '.$r['level'];
        }
        foreach ($requirements['ships']??[] as $slug) {
            if (in_array($slug,$visited,true)) throw new \LogicException('Cycle de prérequis : '.implode(' → ',[...$visited,$slug]));
            $d=$this->catalog->get('ships',$slug);
            $failures=[...$failures,...$this->check($d['unlock_requirements']??[],$buildings,$technologies,[...$visited,$slug])];
        }
        return array_values(array_unique($failures));
    }
    public function forLevel(array $definition,int $level): array
    {
        $requirements=$definition['unlock_requirements']??[];$best=0;
        foreach ($definition['levels']??[] as $tier=>$data) if ((int)$tier<=$level && (int)$tier>$best) { $requirements=$data['unlock_requirements']??[];$best=(int)$tier; }
        return $requirements;
    }
}
