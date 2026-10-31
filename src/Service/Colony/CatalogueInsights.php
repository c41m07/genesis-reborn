<?php
namespace App\Service\Colony;
use App\Entity\Planet;
use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;

final class CatalogueInsights
{
    public function __construct(private GameCatalog $catalog,private ResourceService $resources,private RequirementChecker $requirements) {}
    public function benefits(array $d,string $group,int $target,Planet $planet,array $levels,array $tech):array
    {
        if($group==='buildings') {
            $next=$levels;$next[$d['slug']]=$target;
            if(isset($d['production_resource'])) {
                $r=$d['production_resource'];return [['label'=>'Production de '.mb_strtolower($this->catalog->rules()['resources'][$r]),'before'=>$this->resources->rate($planet,$r,0,$levels)*3600,'after'=>$this->resources->rate($planet,$r,0,$next)*3600,'unit'=>'/ h']];
            }
            if(isset($d['storage_resource'])) {
                $r=$d['storage_resource'];return [['label'=>'Stockage de '.mb_strtolower($this->catalog->rules()['resources'][$r]),'before'=>$this->resources->capacity($planet,$r,$levels),'after'=>$this->resources->capacity($planet,$r,$next),'unit'=>'']];
            }
            if($d['slug']==='shipyard') return [['label'=>'Réduction du temps des vaisseaux','before'=>min(80,($levels['shipyard']??0)*$d['build_time_reduction_percent_per_level']),'after'=>min(80,$target*$d['build_time_reduction_percent_per_level']),'unit'=>'%']];
            if($d['slug']==='lab') return [['label'=>'Vitesse des recherches','before'=>$levels['lab']??0,'after'=>$target,'unit'=>'×']];
            if($d['slug']==='fortress') return [['text'=>'Permet de fabriquer les défenses selon leurs prérequis.']];
        }
        if($group==='technologies'&&($d['effect']['type']??null)==='stat_bonus') {
            $per=$d['effect']['value_per_level'];return [['label'=>'Précision prévue pour les combats','before'=>($tech[$d['slug']]??0)*$per,'after'=>$target*$per,'unit'=>'points']];
        }
        if($group==='ships') return [['label'=>'Réservoir de carburant','value'=>$d['fuel_capacity'],'unit'=>'H'],['label'=>'Soute de fret','value'=>$d['cargo_capacity'],'unit'=>'']];
        if($group==='defenses') return [['label'=>'Structure par unité','value'=>$d['hp'],'unit'=>'PV']];
        return [];
    }
    public function unlocks(string $group,string $slug,int $target):array
    {
        if(!in_array($group,['buildings','technologies'],true)) return [];
        $result=[];
        foreach(['buildings','technologies','ships','defenses'] as $candidateGroup) foreach($this->catalog->all($candidateGroup) as $candidate) {
            $tiers=[1=>$candidate['unlock_requirements']??[]];foreach($candidate['levels']??[] as $n=>$tier) $tiers[(int)$n]=$tier['unlock_requirements']??[];
            if($candidateGroup==='technologies'&&!array_filter($tiers[1]['buildings']??[],fn($r)=>$r['slug']==='lab')) $tiers[1]['buildings'][]=['slug'=>'lab','level'=>1];
            foreach($tiers as $tier=>$requirements) foreach($requirements[$group]??[] as $r) if($r['slug']===$slug&&$r['level']<=$target) {
                $key=$candidateGroup.':'.$candidate['slug'].':'.$tier;
                $result[$key]=['group'=>$candidateGroup,'slug'=>$candidate['slug'],'label'=>$candidate['label'].($tier>1?' · niveau '.$tier:''),'requiredLevel'=>$r['level']];
            }
        }
        return array_values($result);
    }
    public function tree(array $levels,array $tech):array
    {
        $all=$this->catalog->all('technologies');$depth=[];
        $visit=function(string $slug,array $path=[])use(&$visit,&$depth,$all):int {
            if(isset($depth[$slug])) return $depth[$slug];
            if(in_array($slug,$path,true)) throw new \LogicException('Cycle de recherches.');
            $n=0;foreach($all[$slug]['unlock_requirements']['technologies']??[] as $r) $n=max($n,1+$visit($r['slug'],[...$path,$slug]));
            return $depth[$slug]=$n;
        };
        $tiers=[];
        foreach($all as $slug=>$d) {
            $requirements=$d['unlock_requirements']??[];
            if(!array_filter($requirements['buildings']??[],fn($r)=>$r['slug']==='lab')) $requirements['buildings'][]=['slug'=>'lab','level'=>1];
            $prerequisites=[];
            foreach(['buildings'=>$levels,'technologies'=>$tech] as $group=>$owned) foreach($requirements[$group]??[] as $r) $prerequisites[]=['group'=>$group,'slug'=>$r['slug'],'label'=>$this->catalog->get($group,$r['slug'])['label'],'level'=>$r['level'],'met'=>($owned[$r['slug']]??0)>=$r['level']];
            $tiers[$visit($slug)][]=['definition'=>$d,'level'=>$tech[$slug]??0,'prerequisites'=>$prerequisites,'available'=>!$this->requirements->check($requirements,$levels,$tech)];
        }
        ksort($tiers);return $tiers;
    }
}
