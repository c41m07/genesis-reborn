<?php
namespace App\Service\Queue;
use App\Service\Catalog\GameCatalog;
final class OperationCalculator
{
    public function __construct(private GameCatalog $catalog) {}
    public function calculate(array $d,string $group,int $level,int $quantity,array $buildings,array $universe,array $technologies=[]):array
    {
        $plan = $this->plan($d, $group, $level, $quantity, $buildings, $universe, $technologies);
        return ['duration' => $plan['duration'], 'costs' => $plan['costs']];
    }
    private function plan(array $d,string $group,int $level,int $quantity,array $buildings,array $universe,array $technologies=[]):array
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
        $rawDuration=$base*$levelFactor*$quantity*$mult*$tierFactor*(1-$timeReduction/100);
        $duration=max(1,(int)ceil($rawDuration));
        $costs=$this->catalog->costs($d,$level,$quantity,$group);
        $discountSlug=match($group){'buildings'=>'nanomaterial_synthesis','ships'=>'naval_engineering',default=>null};
        if($discountSlug!==null){$costCap=(float)($this->catalog->rules()['cost_reduction_max_percent']??40);$discount=$this->discountProduct($universe,$discountSlug,$technologies[$discountSlug]??[],'cost_discount',$costCap);foreach($costs as $resource=>$amount)$costs[$resource]=$amount>0?max(1,(int)ceil($amount*(1-$discount/100))):0;}
        return ['duration'=>$duration,'costs'=>$costs,'raw_duration'=>$rawDuration];
    }
    public function maximum(array $definition, string $group, array $stocks, array $buildings, array $universe, array $technologies = [], int $limit = 2147483647): int
    {
        if (!in_array($group, ['ships', 'defenses'], true)) throw new \InvalidArgumentException('Seules les unités se construisent par lot.');
        $unit = $this->plan($definition, $group, 1, 1, $buildings, $universe, $technologies);
        // Respecte la capacité des entiers enregistrés, sans modifier les coûts ni les durées.
        $high = (int) min(max(0, $limit), 2147483647, 2147483647 / max(1e-9, $unit['raw_duration']), PHP_INT_MAX / max(1, max($unit['costs']) * 2));
        $low = 0;
        while ($low < $high) {
            $quantity = $low + intdiv($high - $low + 1, 2);
            $plan = $this->plan($definition, $group, 1, $quantity, $buildings, $universe, $technologies);
            $affordable = is_finite($plan['raw_duration']) && ceil($plan['raw_duration']) <= 2147483647;
            foreach ($plan['costs'] as $slug => $cost) {
                if ($cost < 0 || $cost > ($stocks[$slug]['stock'] ?? 0) + 1e-6) $affordable = false;
            }
            if ($affordable) $low = $quantity;
            else $high = $quantity - 1;
        }
        return $low;
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
