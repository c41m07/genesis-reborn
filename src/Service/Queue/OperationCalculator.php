<?php
namespace App\Service\Queue;
use App\Service\Catalog\GameCatalog;
final class OperationCalculator
{
    public function __construct(private GameCatalog $catalog) {}
    public function calculate(array $d,string $group,int $level,int $quantity,array $buildings,array $universe,array $technologies=[]):array
    {
        $base=$d['base_build_time']??$d['research_time_base']??$d['build_time_base'];
        $scaling=$d['build_time_scaling']??$d['research_time_scaling']??1;
        $lab=$universe['catalog']['buildings']['lab']??$this->catalog->get('buildings','lab');
        $shipyard=$universe['catalog']['buildings']['shipyard']??$this->catalog->get('buildings','shipyard');
        $labReduction=min((float)($lab['research_time_reduction_max_percent']??50),(float)($lab['research_time_reduction_percent_per_level']??10)*($buildings['lab']??0))/100;
        $shipyardReduction=min((float)($shipyard['build_time_reduction_max_percent']??50),(float)($shipyard['build_time_reduction_percent_per_level']??5)*($buildings['shipyard']??0))/100;
        $engineering=$this->discount($universe,'basic_engineering',$technologies,'time_discount');
        $durationFactor=match($group){'buildings'=>max(.01,1-$engineering),'technologies'=>max(.01,1-$labReduction),'ships'=>max(.01,1-$shipyardReduction),default=>1};
        $mult=$universe['multipliers'][match($group){'buildings'=>'building_time','technologies'=>'research_time',default=>'ship_build_time'}];
        $duration=max(1,(int)ceil($base*$scaling**($level-1)*$quantity*$mult*$durationFactor));
        $costs=$this->catalog->costs($d,$level,$quantity);
        $discountSlug=match($group){'buildings'=>'nano_construction','ships'=>'advanced_engineering',default=>null};
        if($discountSlug!==null){$discount=$this->discount($universe,$discountSlug,$technologies,'cost_discount');foreach($costs as $resource=>$amount)$costs[$resource]=(int)ceil($amount*(1-$discount));}
        return ['duration'=>$duration,'costs'=>$costs];
    }

    private function discount(array $universe,string $slug,array $levels,string $type):float
    {
        $level=max(0,(int)($levels[$slug]??0));$definition=$universe['catalog']['technologies'][$slug]??null;$effect=$definition['effect']??null;
        if($level===0||!is_array($effect)||($effect['type']??null)!==$type)return 0.0;
        $perLevel=(float)($effect['value_per_level']??0);$cap=(float)($effect['max_bonus']??($perLevel*(int)($definition['max_level']??$level)));
        return min($cap,$perLevel*min($level,(int)($definition['max_level']??$level)))/100;
    }
}
