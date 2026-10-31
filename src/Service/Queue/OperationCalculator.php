<?php
namespace App\Service\Queue;
use App\Service\Catalog\GameCatalog;
final class OperationCalculator
{
    public function __construct(private GameCatalog $catalog) {}
    public function calculate(array $d,string $group,int $level,int $quantity,array $buildings,array $universe):array
    {
        $base=$d['base_build_time']??$d['research_time_base']??$d['build_time_base'];
        $scaling=$d['build_time_scaling']??$d['research_time_scaling']??1;
        $reduction=$this->catalog->get('buildings','shipyard')['build_time_reduction_percent_per_level']/100;
        $speed=match($group){'technologies'=>max(1,$buildings['lab']??0),'ships'=>1/max(.2,1-$reduction*($buildings['shipyard']??0)),default=>1};
        $mult=$universe['multipliers'][match($group){'buildings'=>'building_time','technologies'=>'research_time',default=>'ship_build_time'}];
        return ['duration'=>max(1,(int)ceil($base*$scaling**($level-1)*$quantity*$mult/$speed)),'costs'=>$this->catalog->costs($d,$level,$quantity)];
    }
}
