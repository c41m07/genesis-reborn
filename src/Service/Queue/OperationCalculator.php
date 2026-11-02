<?php
namespace App\Service\Queue;
use App\Service\Catalog\GameCatalog;
final class OperationCalculator
{
    public function __construct(private GameCatalog $catalog) {}
    public function calculate(array $d,string $group,int $level,int $quantity,array $buildings,array $universe,array $technologies=[]):array
    {
        $tier=(string)($d['_tier']??'basic');$tierFactor=GameCatalog::tierFactor($tier);
        $base=$d['base_build_time']??$d['research_time_base']??$d['build_time_base'];
        $scaling=$d['build_time_scaling']??$d['research_time_scaling']??1;
        $lab=$universe['catalog']['buildings']['lab']??$this->catalog->get('buildings','lab');
        $shipyard=$universe['catalog']['buildings']['shipyard']??$this->catalog->get('buildings','shipyard');
        $labRemaining=$this->remainingFactor($lab,'research_time_reduction_percent_per_level','research_time_reduction_max_percent',$buildings['lab']??[]);
        $yardRemaining=$this->remainingFactor($shipyard,'build_time_reduction_percent_per_level','build_time_reduction_max_percent',$buildings['shipyard']??[]);
        $basicEngineering=$this->discountProduct($universe,'basic_engineering',$technologies['basic_engineering']??[],'time_discount');
        $durationFactor=match($group){'buildings'=>max(.01,1-$basicEngineering),'technologies'=>$labRemaining,'ships'=>$yardRemaining,default=>1};
        $mult=$universe['multipliers'][match($group){'buildings'=>'building_time','technologies'=>'research_time',default=>'ship_build_time'}];
        $duration=max(1,(int)ceil($base*$scaling**($level-1)*$quantity*$mult*$tierFactor*$durationFactor));
        $costs=$this->catalog->costs($d,$level,$quantity);
        $discountSlug=match($group){'buildings'=>'nano_construction','ships'=>'advanced_engineering',default=>null};
        if($discountSlug!==null){$remaining=1-$this->discountProduct($universe,$discountSlug,$technologies[$discountSlug]??[],'cost_discount');foreach($costs as $resource=>$amount)$costs[$resource]=$amount>0?max(1,(int)ceil($amount*$remaining)):0;}
        return ['duration'=>$duration,'costs'=>$costs];
    }
    private function remainingFactor(array $definition,string $perField,string $capField,array|int $levels):float
    {
        $per=(float)($definition[$perField]??0);$baseCap=(float)($definition[$capField]??100);$max=(int)($definition['max_level']??25);$remaining=1.0;if(is_int($levels))$levels=['basic'=>$levels];if(is_numeric($levels))$levels=['basic'=>(int)$levels];
        foreach(GameCatalog::TIERS as $tier){$level=max(0,(int)($levels[$tier]??0));if($level===0)continue;$factor=GameCatalog::tierFactor($tier);$bonus=min($baseCap*$factor,$per*$factor*min($level,$max))/100;$remaining*=max(0,1-$bonus);}
        return $remaining;
    }
    private function discountProduct(array $universe,string $slug,array|int $levels,string $type):float
    {
        $definition=$universe['catalog']['technologies'][$slug]??null;$effect=$definition['effect']??null;
        if(!is_array($effect)||($effect['type']??null)!==$type)return 0.0;if(is_int($levels))$levels=['basic'=>$levels];
        if(is_numeric($levels))$levels=['basic'=>(int)$levels];
        $per=(float)($effect['value_per_level']??0);$cap=(float)($effect['max_bonus']??0);$max=(int)($definition['max_level']??25);$remaining=1.0;
        foreach(GameCatalog::TIERS as $tier){$level=max(0,(int)($levels[$tier]??0));if($level===0)continue;$factor=GameCatalog::tierFactor($tier);$bonus=min($cap*$factor,$per*$factor*min($level,$max))/100;$remaining*=max(0,1-$bonus);}
        return 1-$remaining;
    }
}
