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
        $tier=(string)($d['_tier']??'basic');$factor=GameCatalog::tierFactor($tier);$slug=$d['slug'];
        if($group==='buildings'){
            $next=$levels;$next[$slug][$tier]=$target;
            if(isset($d['production_resource'])){$r=$d['production_resource'];return [['label'=>'Production de '.mb_strtolower($this->catalog->rules()['resources'][$r]),'before'=>$this->resources->rate($planet,$r,0,$levels)*3600,'after'=>$this->resources->rate($planet,$r,0,$next)*3600,'unit'=>'/ h']];}
            if(isset($d['storage_resource'])){$r=$d['storage_resource'];return [['label'=>'Stockage de '.mb_strtolower($this->catalog->rules()['resources'][$r]),'before'=>$this->resources->capacity($planet,$r,$levels),'after'=>$this->resources->capacity($planet,$r,$next),'unit'=>'']];}
            if(in_array($slug,['lab','shipyard'],true)){$per=(float)($d[$slug==='lab'?'research_time_reduction_percent_per_level':'build_time_reduction_percent_per_level']??2);$cap=(float)($d[$slug==='lab'?'research_time_reduction_max_percent':'build_time_reduction_max_percent']??50);$ranks=$levels[$slug]??[];$before=0.0;$after=0.0;foreach(GameCatalog::TIERS as $rank){$f=GameCatalog::tierFactor($rank);$b=min($cap*$f,$per*$f*(int)($ranks[$rank]??0));$a=min($cap*$f,$per*$f*(int)(($rank===$tier)?$target:($ranks[$rank]??0)));$before=1-(1-$before/100)*(1-$b/100);$after=1-(1-$after/100)*(1-$a/100);}return [['label'=>$slug==='lab'?'Réduction du temps de recherche':'Réduction du temps de construction des vaisseaux','before'=>$before*100,'after'=>$after*100,'unit'=>'%']];}
            if($slug==='fortress'){$effect=$d['effect']??[];$per=(float)($effect['value_per_level']??2.5);$cap=(float)($effect['max_bonus']??25);$ranks=$levels[$slug]??[];$before=0.;$after=0.;foreach(GameCatalog::TIERS as $rank){$f=GameCatalog::tierFactor($rank);$b=min($cap*$f,$per*$f*(int)($ranks[$rank]??0));$a=min($cap*$f,$per*$f*(int)(($rank===$tier)?$target:($ranks[$rank]??0)));$before=(1+$before/100)*(1+$b/100)-1;$after=(1+$after/100)*(1+$a/100)-1;}return [['label'=>'Structure supplémentaire des défenses','before'=>$before*100,'after'=>$after*100,'unit'=>'%']];}
        }
        if($group==='technologies')return $this->technologyBenefits($d,$target,$tech);
        if($group==='ships')return [['label'=>'Attaque','value'=>$d['base_attack']??0,'unit'=>'points'],['label'=>'Structure','value'=>$d['hp'],'unit'=>'PV'],['label'=>'Bouclier maximal','value'=>$d['shield']??0,'unit'=>'PV'],['label'=>'Précision / suivi / esquive','value'=>($d['precision']??0).' / '.($d['tracking']??0).' / '.($d['evasion']??0),'unit'=>''],['label'=>'Soute / réservoir','value'=>($d['cargo_capacity']??0).' / '.($d['fuel_capacity']??0),'unit'=>'']];
        if($group==='defenses')return [['label'=>'Attaque','value'=>$d['base_attack']??0,'unit'=>'points'],['label'=>'Structure','value'=>$d['hp'],'unit'=>'PV'],['label'=>'Bouclier maximal','value'=>$d['shield']??0,'unit'=>'PV']];
        return [];
    }
    private function technologyBenefits(array $d,int $target,array $levels):array
    {
        $effect=$d['effect']??[];$slug=$d['slug'];$tier=(string)($d['_tier']??'basic');$ranks=$levels[$slug]??[];if(is_numeric($ranks))$ranks=['basic'=>(int)$ranks];$target=min($target,(int)($d['max_level']??$target));$type=$effect['type']??null;
        if($type==='stat_bonus'){$result=[];$per=(float)($effect['value_per_level']??0);$cap=(float)($effect['max_bonus']??PHP_FLOAT_MAX);$formula=$effect['formula']??'flat';$direction=($effect['direction']??'increase')==='decrease'?-1:1;foreach($effect['stats']??[$effect['stat']??null] as $stat){if(!is_string($stat))continue;$label=match($stat){'hp'=>'Structure','shield'=>'Bouclier','base_attack'=>'Attaque','precision'=>'Précision','tracking'=>'Suivi','evasion'=>'Esquive','signature'=>'Signature','shots_min','shots_max'=>'Nombre de tirs','speed_system','speed_galaxy'=>'Vitesse','fuel_capacity'=>'Réservoir','cargo_capacity'=>'Soute',default=>$stat};$before=$formula==='percent'?1.0:0.0;$after=$before;foreach(GameCatalog::TIERS as $rank){$f=GameCatalog::tierFactor($rank);$lv=(int)($ranks[$rank]??0);$a=$rank===$tier?$target:$lv;$v=min($cap*$f,$per*$lv*$f);$n=min($cap*$f,$per*$a*$f);if($formula==='percent'){$before*=1+$direction*$v;$after*=1+$direction*$n;}else{$before+=$v;$after+=$n;}}$result[]=['label'=>$label,'before'=>$formula==='percent'?($before-1)*100:$before,'after'=>$formula==='percent'?($after-1)*100:$after,'unit'=>$formula==='percent'?'%':'points'];}return $result;}
        if(in_array($type,['time_discount','cost_discount'],true)){$cap=(float)($effect['max_bonus']??100);$per=(float)($effect['value_per_level']??0);$before=0.;$after=0.;foreach(GameCatalog::TIERS as $rank){$f=GameCatalog::tierFactor($rank);$lv=(int)($ranks[$rank]??0);$a=$rank===$tier?$target:$lv;$before=1-(1-$before/100)*(1-min($cap*$f,$per*$lv*$f)/100);$after=1-(1-$after/100)*(1-min($cap*$f,$per*$a*$f)/100);}return [['label'=>$type==='time_discount'?'Réduction du temps des opérations':'Réduction du coût des opérations','before'=>$before*100,'after'=>$after*100,'unit'=>'%']];}
        if($type==='extra_shot_chance'){$f=GameCatalog::tierFactor($tier);$chance=min((float)($effect['max_bonus']??1)*$f,(float)($effect['value_per_level']??0)*(int)($ranks[$tier]??0)*$f);$next=min((float)($effect['max_bonus']??1)*$f,(float)($effect['value_per_level']??0)*$target*$f);return [['label'=>'Chance de tir supplémentaire par tir de base','before'=>$chance*100,'after'=>$next*100,'unit'=>'%']];}
        if($type==='defense_rebuild'){$per=(float)($effect['value_per_level']??0);$cap=(float)($effect['max_bonus']??100);$before=0.;$after=0.;foreach(GameCatalog::TIERS as $rank){$f=GameCatalog::tierFactor($rank);$lv=(int)($ranks[$rank]??0);$a=$rank===$tier?$target:$lv;$before=1-(1-$before/100)*(1-min($cap*$f,$lv*$per*$f)/100);$after=1-(1-$after/100)*(1-min($cap*$f,$a*$per*$f)/100);}return [['label'=>'Reconstruction des défenses','before'=>$before*100,'after'=>$after*100,'unit'=>'%']];}
        if($type==='planet_limit'){$base=(int)($effect['base_planets']??1);$per=(float)($effect['value_per_level']??1);$cap=max(0,(int)($effect['max_planets']??5)-$base);$before=0.;$after=0.;foreach(GameCatalog::TIERS as $rank){$f=GameCatalog::tierFactor($rank);$lv=(int)($ranks[$rank]??0);$n=$rank===$tier?$target:$lv;$before+=min($cap*$f,$per*$lv*$f);$after+=min($cap*$f,$per*$n*$f);}return [['label'=>'Limite technique de colonies','before'=>$base+(int)floor($before),'after'=>$base+(int)floor($after),'unit'=>'planètes']];}
        return [];
    }
    public function unlocks(string $group,string $slug,int $target):array
    {
        if(!in_array($group,['buildings','technologies'],true))return [];$result=[];
        foreach(['buildings','technologies','ships','defenses'] as $candidateGroup)foreach($this->catalog->all($candidateGroup) as $candidate){$requirements=$candidate['unlock_requirements']??[];foreach($candidate['levels']??[] as $n=>$level)$requirements=$this->merge($requirements,$level['unlock_requirements']??[]);foreach($candidate['tier_unlock_requirements']??[] as $rank=>$rankRequirements)$requirements=$this->merge($requirements,$rankRequirements);foreach($requirements[$group]??[] as $r)if(($r['slug']??'')===$slug&&(int)($r['level']??1)<=$target){$tier=$r['tier']??'basic';$key=$candidateGroup.':'.$candidate['slug'].':'.$tier;$result[$key]=['group'=>$candidateGroup,'slug'=>$candidate['slug'],'tier'=>$tier,'label'=>$candidate['label'].' · '.$this->catalog->tiers()[$tier]['label'],'requiredLevel'=>$r['level']??1];}}
        return array_values($result);
    }
    public function tree(array $levels,array $tech):array
    {
        $all=$this->catalog->all('technologies');$result=[];
        foreach(GameCatalog::TIERS as $tier)foreach($all as $slug=>$base){$d=$this->catalog->withTier($base,$tier);$requirements=$this->requirements->forTier($d,$tier,1);if(!array_filter($requirements['buildings']??[],static fn($r)=>$r['slug']==='lab'))$requirements['buildings'][]=['slug'=>'lab','level'=>1,'tier'=>'basic'];$prerequisites=[];foreach(['buildings'=>$levels,'technologies'=>$tech] as $group=>$owned)foreach($requirements[$group]??[] as $r){$requiredTier=$r['tier']??'basic';$have=(int)($owned[$r['slug']][$requiredTier]??(is_numeric($owned[$r['slug']]??null)?$owned[$r['slug']]:0));$prerequisites[]=['group'=>$group,'slug'=>$r['slug'],'tier'=>$requiredTier,'label'=>$this->catalog->get($group,$r['slug'])['label'].' · '.$this->catalog->tiers()[$requiredTier]['label'],'level'=>$r['level'],'met'=>$have>=(int)$r['level']];}$current=(int)($tech[$slug][$tier]??0);$result[$tier][]=['definition'=>$d,'tier'=>$tier,'level'=>$current,'prerequisites'=>$prerequisites,'available'=>!$this->requirements->check($requirements,$levels,$tech)];}
        return $result;
    }
    private function merge(array $left,array $right):array{foreach($right as $group=>$items)$left[$group]=[...($left[$group]??[]),...$items];return $left;}
}
