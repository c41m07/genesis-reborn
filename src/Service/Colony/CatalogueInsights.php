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
            if(isset($d['production_resource'])){$r=$d['production_resource'];$result=[['label'=>'Production de '.mb_strtolower($this->catalog->rules()['resources'][$r]),'before'=>$this->resources->rate($planet,$r,0,$levels)*3600,'after'=>$this->resources->rate($planet,$r,0,$next)*3600,'unit'=>'/ h']];$beforeUse=$this->resources->factoryConsumption($planet,$slug,$levels);$afterUse=$this->resources->factoryConsumption($planet,$slug,$next);foreach(['electricity'=>'Consommation électrique','hydrogen'=>'Consommation d’hydrogène'] as $resource=>$label)if(max($beforeUse[$resource],$afterUse[$resource])>0)$result[]=['label'=>$label,'before'=>$beforeUse[$resource]*3600,'after'=>$afterUse[$resource]*3600,'unit'=>'/ h','consumption'=>true];return $result;}
            if(isset($d['storage_resource'])){$r=$d['storage_resource'];return [['label'=>'Stockage de '.mb_strtolower($this->catalog->rules()['resources'][$r]),'before'=>$this->resources->capacity($planet,$r,$levels),'after'=>$this->resources->capacity($planet,$r,$next),'unit'=>'']];}
            if(in_array($slug,['lab','shipyard'],true)){$maxLevel=max(1,(int)($d['max_level']??25));$afterRanks=$levels[$slug]??[];$afterRanks[$tier]=$target;$cap=(float)($this->catalog->rules()['time_reduction_max_percent']??70);$before=GameCatalog::diminishingBonus($levels[$slug]??[],$cap,$maxLevel);$after=GameCatalog::diminishingBonus($afterRanks,$cap,$maxLevel);return [['label'=>$slug==='lab'?'Réduction du temps de recherche':'Réduction du temps de construction des vaisseaux','before'=>$before,'after'=>$after,'unit'=>'%']];}
            if($slug==='fortress'){$effect=$d['effect']??[];$maxLevel=max(1,(int)($d['max_level']??25));$afterRanks=$levels[$slug]??[];$afterRanks[$tier]=$target;$cap=(float)($effect['max_bonus']??25);return [['label'=>'Structure supplémentaire des défenses','before'=>GameCatalog::diminishingBonus($levels[$slug]??[],$cap,$maxLevel),'after'=>GameCatalog::diminishingBonus($afterRanks,$cap,$maxLevel),'unit'=>'%']];}
        }
        if($group==='technologies')return $this->technologyBenefits($d,$target,$tech,$planet);
        if($group==='ships'){$shipBenefits=[['label'=>'Vitesse dans le système','value'=>$d['speed_system']??0,'unit'=>''],['label'=>'Vitesse entre systèmes','value'=>$d['speed_galaxy']??0,'unit'=>''],['label'=>'Tirs principaux par round','text'=>(float)($d['base_attack']??0)>0?(($d['shots_min']??0).'–'.($d['shots_max']??0)):'Aucun'],['label'=>'Attaque','value'=>$d['base_attack']??0,'unit'=>''],['label'=>'Structure','value'=>$d['hp'],'unit'=>''],['label'=>'Bouclier maximal','value'=>$d['shield']??0,'unit'=>''],['label'=>'Précision de tir','value'=>($d['precision']??0),'unit'=>'%'],['label'=>'Suivi de cible','value'=>($d['tracking']??0),'unit'=>'%'],['label'=>'Capacité d’esquive','value'=>($d['evasion']??0),'unit'=>'%'],['label'=>'Signature · faible = moins ciblé','value'=>($d['signature']??0),'unit'=>''],['label'=>'Capacité de soute','value'=>($d['cargo_capacity']??0),'unit'=>'unités'],['label'=>'Réservoir d’hydrogène','value'=>($d['fuel_capacity']??0),'unit'=>'unités']];if(isset($d['secondary_attack']))$shipBenefits[]=['label'=>'Tir secondaire anti-léger','value'=>$d['secondary_attack'],'unit'=>''];return $shipBenefits;}
        if($group==='defenses')return [...(isset($d['countered_class_label'])?[['text'=>'Bonus contre : '.$d['countered_class_label'].' ('.implode(', ',$d['countered_ship_labels']??[]).') · dégâts et chance de toucher ×1,30']]:[]),['label'=>'Attaque','value'=>$d['base_attack']??0,'unit'=>''],['label'=>'Structure','value'=>$d['hp'],'unit'=>''],['label'=>'Bouclier maximal','value'=>$d['shield']??0,'unit'=>'']];
        return [];
    }
    private function technologyBenefits(array $d,int $target,array $levels,Planet $planet):array
    {
        $effect=$d['effect']??[];$slug=$d['slug'];$tier=(string)($d['_tier']??'basic');$ranks=$levels[$slug]??[];if(is_numeric($ranks))$ranks=['basic'=>(int)$ranks];$target=min($target,(int)($effect['tier_max_levels'][$tier]??$d['max_level']??$target));$afterRanks=$ranks;$afterRanks[$tier]=$target;$maxLevel=max(1,(int)($d['max_level']??25));$type=$effect['type']??null;
        if(in_array($slug,['signature_masking','intelligence_countermeasures','tactical_intelligence'],true)){
            // L’espionnage arrondit la somme des niveaux pondérés des trois rangs.
            $effectiveLevel=static function(array $ranks):int {
                $value=0.0;
                foreach(GameCatalog::TIERS as $rank)$value+=(int)($ranks[$rank]??0)*GameCatalog::tierFactor($rank);
                return max(0,(int)round($value));
            };
            $before=$effectiveLevel($ranks);$after=$effectiveLevel($afterRanks);
            $settings=$this->catalog->all('espionage');
            if($slug==='tactical_intelligence')return [
                ['label'=>'Score de renseignement de vos sondes','before'=>$before,'after'=>$after,'unit'=>'points'],
                ['label'=>'Score de renseignement des sondes adverses','before'=>-$before,'after'=>-$after,'unit'=>'points']
            ];
            $weight=(int)($settings[$slug==='signature_masking'?'stealth_weight':'intelligence_countermeasures_weight']??10);
            $sign=$slug==='signature_masking'?-1:1;
            return [
                ['label'=>$slug==='signature_masking'?'Risque de détection de vos sondes':'Détection des sondes adverses','before'=>$sign*$weight*$before,'after'=>$sign*$weight*$after,'unit'=>'points de %']
            ];
        }
        if($type==='stat_bonus'){$cap=(float)($effect['max_bonus']??0);$before=GameCatalog::diminishingBonus($ranks,$cap,$maxLevel);$after=GameCatalog::diminishingBonus($afterRanks,$cap,$maxLevel);$formula=$effect['formula']??'flat';$result=[];foreach($effect['stats']??[$effect['stat']??null] as $stat){if(!is_string($stat))continue;$label=match($stat){'hp'=>'Structure','shield'=>'Bouclier','base_attack'=>'Attaque','precision'=>'Précision','tracking'=>'Suivi','evasion'=>'Esquive','signature'=>'Signature des vaisseaux et défenses','shots_min','shots_max'=>'Nombre de tirs','speed_system'=>'Vitesse dans le système','speed_galaxy'=>'Vitesse entre systèmes','fuel_capacity'=>'Réservoir','cargo_capacity'=>'Soute',default=>$stat};$factor=($formula==='percent'?100:1)*(($effect['direction']??'increase')==='decrease'?-1:1);$scope=match($effect['targets']??''){'all_combat_units'=>'des vaisseaux et défenses','all_ships','combat_ships'=>'des vaisseaux',default=>''};$result[]=['label'=>$stat==='signature'?$label:trim($label.' '.$scope),'before'=>$before*$factor,'after'=>$after*$factor,'unit'=>$formula==='percent'?'%':''];}return $result;}
        if(in_array($type,['time_discount','cost_discount'],true)){$cap=$type==='time_discount'?(float)($this->catalog->rules()['time_reduction_max_percent']??70):(float)($this->catalog->rules()['cost_reduction_max_percent']??40);$before=GameCatalog::diminishingBonus($ranks,$cap,$maxLevel);$after=GameCatalog::diminishingBonus($afterRanks,$cap,$maxLevel);$label=match([$type,$effect['targets']??'']){['time_discount','buildings']=>'Réduction du temps de construction des bâtiments',['cost_discount','buildings']=>'Réduction du coût de construction des bâtiments',['time_discount','ships']=>'Réduction du temps de construction des vaisseaux',['cost_discount','ships']=>'Réduction du coût de construction des vaisseaux',default=>$type==='time_discount'?'Réduction du temps de construction':'Réduction du coût de construction'};return [['label'=>$label,'before'=>$before,'after'=>$after,'unit'=>'%']];}
        if($type==='extra_shot_chance'){$cap=(float)($effect['max_bonus']??0.5);return [['label'=>'Chance de tir principal supplémentaire','before'=>GameCatalog::diminishingBonus($ranks,$cap,$maxLevel)*100,'after'=>GameCatalog::diminishingBonus($afterRanks,$cap,$maxLevel)*100,'unit'=>'%']];}
        if($type==='defense_rebuild'){$cap=(float)($this->catalog->rules()['defense_rebuild_max_percent']??40);return [['label'=>'Reconstruction des défenses détruites','before'=>GameCatalog::diminishingBonus($ranks,$cap,$maxLevel),'after'=>GameCatalog::diminishingBonus($afterRanks,$cap,$maxLevel),'unit'=>'%']];}
        if($type==='planet_limit'){$limit=(int)$this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['limits']['max_planets'];$before=GameCatalog::colonyLimit($ranks,$effect,$limit);$after=GameCatalog::colonyLimit($afterRanks,$effect,$limit);return [['label'=>'Limite totale de planètes','before'=>$before,'after'=>$after,'unit'=>'planètes']];}
        return [];
    }
    public function unlocks(string $group,string $slug,int $target):array
    {
        if(!in_array($group,['buildings','technologies'],true))return [];$result=[];
        foreach(['buildings','technologies','ships','defenses'] as $candidateGroup)foreach($this->catalog->all($candidateGroup) as $candidate){$requirements=$candidate['unlock_requirements']??[];foreach($candidate['levels']??[] as $n=>$level)$requirements=$this->merge($requirements,$level['unlock_requirements']??[]);foreach($candidate['tier_unlock_requirements']??[] as $rank=>$rankRequirements)$requirements=$this->merge($requirements,$rankRequirements);foreach($requirements[$group]??[] as $r)if(($r['slug']??'')===$slug&&(int)($r['level']??1)<=$target){$tier=$r['tier']??'basic';$key=$candidateGroup.':'.$candidate['slug'].':'.$tier;$tierDefinition=$this->catalog->withTier($candidate,$tier);$result[$key]=['group'=>$candidateGroup,'slug'=>$candidate['slug'],'tier'=>$tier,'label'=>$tierDefinition['label'],'requiredLevel'=>$r['level']??1];}}
        return array_values($result);
    }
    public function tree(array $levels,array $tech):array
    {
        $definitions=$this->catalog->all('technologies');$nodes=[];$depths=[];$visiting=[];
        foreach(GameCatalog::TIERS as $tier)foreach($definitions as $slug=>$base){
            $key=$slug.'-'.$tier;$definition=$this->catalog->withTier($base,$tier);$requirements=$this->requirements->forTier($definition,$tier,1);
            if(!array_filter($requirements['buildings']??[],static fn($r)=>$r['slug']==='lab'))$requirements['buildings'][]=['slug'=>'lab','level'=>1,'tier'=>'basic'];
            $prerequisites=[];$parents=[];
            foreach(['buildings'=>$levels,'technologies'=>$tech] as $group=>$owned)foreach($requirements[$group]??[] as $requirement){
                $requiredTier=$requirement['tier']??'basic';$have=(int)($owned[$requirement['slug']][$requiredTier]??(is_numeric($owned[$requirement['slug']]??null)?$owned[$requirement['slug']]:0));
                $requiredDefinition=$this->catalog->withTier($this->catalog->get($group,$requirement['slug']),$requiredTier);
                $prerequisites[]=['group'=>$group,'slug'=>$requirement['slug'],'tier'=>$requiredTier,'label'=>$requiredDefinition['label'],'level'=>$requirement['level'],'current'=>$have,'met'=>$have>=(int)$requirement['level']];
                if($group==='technologies')$parents[]=$requirement['slug'].'-'.$requiredTier;
            }
            $nodes[$key]=['key'=>$key,'definition'=>$definition,'tier'=>$tier,'slug'=>$slug,'level'=>(int)($tech[$slug][$tier]??0),'prerequisites'=>$prerequisites,'parents'=>array_values(array_unique($parents)),'available'=>!$this->requirements->check($requirements,$levels,$tech)];
        }
        $depth=function(string $key)use(&$depth,&$depths,&$visiting,$nodes):int{
            if(isset($depths[$key]))return $depths[$key];
            if(isset($visiting[$key]))throw new \LogicException('Cycle dans l’arbre technologique : '.$key);
            $visiting[$key]=true;$parentDepths=[];
            foreach($nodes[$key]['parents'] as $parent)if(isset($nodes[$parent]))$parentDepths[]=$depth($parent);
            unset($visiting[$key]);return $depths[$key]=$parentDepths?max($parentDepths)+1:0;
        };
        $columns=[];foreach($nodes as $key=>$node){$node['depth']=$depth($key);$columns[$node['depth']][]=$node;}
        ksort($columns);return $columns;
    }
    private function merge(array $left,array $right):array{foreach($right as $group=>$items)$left[$group]=[...($left[$group]??[]),...$items];return $left;}
}

