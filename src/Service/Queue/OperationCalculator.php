<?php
namespace App\Service\Queue;
use App\Service\Catalog\GameCatalog;
final class OperationCalculator
{
    public function __construct(private GameCatalog $catalog) {}
    public function calculate(array $d,string $group,int $level,int $quantity,array $buildings,array $universe,array $technologies=[]):array
    {
        if($level<1||$quantity<1)throw new \InvalidArgumentException('Niveau ou quantité invalide.');
        $tier=(string)($d['_tier']??'basic');$tierFactor=GameCatalog::tierFactor($tier);
        $base=(float)($d['base_build_time']??$d['research_time_base']??$d['build_time_base']??1);
        $curveGroup=match($group){'buildings'=>'building_duration','technologies'=>'research_duration',default=>null};
        $segments=$curveGroup?($this->catalog->rules()['progression_curves'][$curveGroup]??[]):[];
        $levelFactor=$segments!==[]?GameCatalog::growthMultiplier($level,$segments):((float)($d['build_time_scaling']??$d['research_time_scaling']??1)**($level-1));
        $timeCap=(float)($this->catalog->rules()['time_reduction_max_percent']??70);
        $timeReduction=match($group){
            'buildings'=>$this->discountProduct($universe,'engineering_foundations',$technologies['engineering_foundations']??[],'time_discount',$timeCap),
            'technologies'=>$this->rankedBuildingBonus($universe['catalog']['buildings']['lab']??$this->catalog->get('buildings','lab'),$buildings['lab']??[],'research_time_reduction_percent_per_level',$timeCap),
            'ships'=>$this->rankedBuildingBonus($universe['catalog']['buildings']['shipyard']??$this->catalog->get('buildings','shipyard'),$buildings['shipyard']??[],'build_time_reduction_percent_per_level',$timeCap),
            default=>0.0,
        };
        $mult=(float)($universe['multipliers'][match($group){'buildings'=>'building_time','technologies'=>'research_time',default=>'ship_build_time'}]??1);
        $duration=max(1,(int)ceil($base*$levelFactor*$quantity*$mult*$tierFactor*(1-$timeReduction/100)));
        $costs=$this->catalog->costs($d,$level,$quantity,$group);
        $discountSlug=match($group){'buildings'=>'nanomaterial_synthesis','ships'=>'naval_engineering',default=>null};
        if($discountSlug!==null){$costCap=(float)($this->catalog->rules()['cost_reduction_max_percent']??40);$discount=$this->discountProduct($universe,$discountSlug,$technologies[$discountSlug]??[],'cost_discount',$costCap);foreach($costs as $resource=>$amount)$costs[$resource]=$amount>0?max(1,(int)ceil($amount*(1-$discount/100))):0;}
        return ['duration'=>$duration,'costs'=>$costs];
    }
    private function rankedBuildingBonus(array $definition,array|int $levels,string $perField,float $cap):float
    {
        if(is_int($levels)||is_numeric($levels))$levels=['basic'=>(int)$levels];
        $maxLevel=max(1,(int)($definition['max_level']??25));
        return GameCatalog::diminishingBonus($levels,$cap,$maxLevel);
    }
    private function discountProduct(array $universe,string $slug,array|int $levels,string $type,float $globalCap):float
    {
        $definition=$universe['catalog']['technologies'][$slug]??null;$effect=$definition['effect']??null;
        if(!is_array($effect)||($effect['type']??null)!==$type)return 0.0;
        if(is_int($levels)||is_numeric($levels))$levels=['basic'=>(int)$levels];
        $maxLevel=max(1,(int)($definition['max_level']??25));
        return GameCatalog::diminishingBonus($levels,$globalCap,$maxLevel);
    }
}
