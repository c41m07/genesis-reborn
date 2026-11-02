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
                $resource=$d['production_resource'];
                return [['label'=>'Production de '.mb_strtolower($this->catalog->rules()['resources'][$resource]),'before'=>$this->resources->rate($planet,$resource,0,$levels)*3600,'after'=>$this->resources->rate($planet,$resource,0,$next)*3600,'unit'=>'/ h']];
            }
            if(isset($d['storage_resource'])) {
                $resource=$d['storage_resource'];
                return [['label'=>'Stockage de '.mb_strtolower($this->catalog->rules()['resources'][$resource]),'before'=>$this->resources->capacity($planet,$resource,$levels),'after'=>$this->resources->capacity($planet,$resource,$next),'unit'=>'']];
            }
            if($d['slug']==='shipyard') {
                $per=(float)($d['build_time_reduction_percent_per_level']??5);
                $cap=(float)($d['build_time_reduction_max_percent']??50);
                return [['label'=>'Réduction du temps de construction des vaisseaux','before'=>min($cap,($levels['shipyard']??0)*$per),'after'=>min($cap,$target*$per),'unit'=>'%']];
            }
            if($d['slug']==='lab') {
                $per=(float)($d['research_time_reduction_percent_per_level']??10);
                $cap=(float)($d['research_time_reduction_max_percent']??50);
                return [['label'=>'Réduction du temps de recherche','before'=>min($cap,($levels['lab']??0)*$per),'after'=>min($cap,$target*$per),'unit'=>'%']];
            }
            if($d['slug']==='fortress') {
                $effect=$d['effect']??[];
                $per=(float)($effect['value_per_level']??2.5);$cap=(float)($effect['max_bonus']??25);
                return [['label'=>'Structure supplémentaire des défenses','before'=>min($cap,($levels['fortress']??0)*$per),'after'=>min($cap,$target*$per),'unit'=>'%']];
            }
        }
        if($group==='technologies') return $this->technologyBenefits($d,$target,$tech);
        if($group==='ships') return [
            ['label'=>'Attaque','value'=>$d['base_attack'],'unit'=>'points'],
            ['label'=>'Structure','value'=>$d['hp'],'unit'=>'PV'],
            ['label'=>'Bouclier maximal','value'=>$d['shield']??0,'unit'=>'PV'],
            ['label'=>'Soute','value'=>$d['cargo_capacity'],'unit'=>''],
            ['label'=>'Réservoir à carburant','value'=>$d['fuel_capacity'],'unit'=>'H'],
        ];
        if($group==='defenses') return [
            ['label'=>'Attaque','value'=>$d['base_attack']??0,'unit'=>'points'],
            ['label'=>'Structure','value'=>$d['hp'],'unit'=>'PV'],
            ['label'=>'Bouclier maximal','value'=>$d['shield']??0,'unit'=>'PV'],
        ];
        return [];
    }

    private function technologyBenefits(array $definition,int $target,array $levels):array
    {
        $effect=$definition['effect']??[];$slug=$definition['slug'];$current=(int)($levels[$slug]??0);
        $maxLevel=(int)($definition['max_level']??$target);$target=min($target,$maxLevel);
        $type=$effect['type']??null;
        if($type==='stat_bonus') {
            $stats=$effect['stats']??[$effect['stat']??null];$result=[];
            foreach(array_filter($stats,'is_string') as $stat) {
                $label=match($stat){'precision'=>'Précision','tracking'=>'Suivi','shield'=>'Bouclier maximal','base_attack'=>'Attaque','speed_system'=>'Vitesse dans un système','speed_galaxy'=>'Vitesse entre systèmes',default=>$stat};
                $per=(float)($effect['value_per_level']??0);$cap=(float)($effect['max_bonus']??PHP_FLOAT_MAX);
                $before=$per*$current;$after=$per*$target;
                if(($effect['formula']??'flat')==='percent') {$before=min($cap,$before)*100;$after=min($cap,$after)*100;$unit='%';}
                else {$before=min($cap,$before);$after=min($cap,$after);$unit='points';}
                $result[]=['label'=>$label,'before'=>$before,'after'=>$after,'unit'=>$unit];
            }
            return $result;
        }
        if(in_array($type,['time_discount','cost_discount'],true)) {
            $cap=(float)($effect['max_bonus']??PHP_FLOAT_MAX);$per=(float)($effect['value_per_level']??0);
            $before=min($cap,$per*$current);$after=min($cap,$per*$target);
            $targetLabel=match($effect['targets']??''){ 'buildings'=>'des bâtiments','ships'=>'des vaisseaux','default'=>'des opérations concernées' };
            $label=$type==='time_discount'?'Réduction du temps '.$targetLabel:'Réduction du coût '.$targetLabel;
            return [['label'=>$label,'before'=>$before,'after'=>$after,'unit'=>'%']];
        }
        if($type==='defense_rebuild') {
            $cap=(float)($effect['max_bonus']??50);$per=(float)($effect['value_per_level']??10);
            return [['label'=>'Défenses reconstruites après combat','before'=>min($cap,$per*$current),'after'=>min($cap,$per*$target),'unit'=>'%']];
        }
        if($type==='planet_limit') {
            $base=(int)($effect['base_planets']??1);$per=(int)($effect['value_per_level']??1);$cap=(int)($effect['max_planets']??5);
            return [['label'=>'Nombre maximal de planètes','before'=>min($cap,$base+$per*$current),'after'=>min($cap,$base+$per*$target),'unit'=>'']];
        }
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
