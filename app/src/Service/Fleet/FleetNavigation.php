<?php
namespace App\Service\Fleet;

use App\Entity\Fleet;

final class FleetNavigation
{
    public function freeze(array $origin,array $target,array $ships,float $ratio,string $fuelPolicy,array $quote,\DateTimeImmutable $now): array
    {
        $systemSpeed=min(array_column($ships,'speed_system'));$galaxySpeed=min(array_column($ships,'speed_galaxy'));
        $fuelNormal=$fuelHyper=0.0;
        foreach($ships as $ship) { $fuelNormal+=$ship['fuel_travel_system']*$ship['quantity'];$fuelHyper+=$ship['fuel_travel_galaxy']*$ship['quantity']; }
        $factor=$fuelPolicy==='proportional'?$ratio:1/$ratio;
        $legs=[];$same=$origin[0]===$target[0];
        if($same) $legs[]=$this->leg($origin,$target,(float)$quote['normal_distance'],$systemSpeed,$fuelNormal*$factor);
        else {
            $exit=$origin;$exit[4]=10;$entry=$target;$entry[4]=10;
            $legs[]=$this->leg($origin,$exit,(10-$origin[4])*10,$systemSpeed,$fuelNormal*$factor);
            $legs[]=$this->leg($exit,$entry,(float)$quote['hyper_distance'],$galaxySpeed,$fuelHyper*$factor,true);
            if($target[4]!==null) $legs[]=$this->leg($entry,$target,(10-$target[4])*10,$systemSpeed,$fuelNormal*$factor);
        }
        $legs=array_values(array_filter($legs,static fn($leg)=>$leg['distance']>0));
        $raw=array_sum(array_column($legs,'weight'));
        if($raw<=0) throw new \DomainException('Trajet nul ou incohérent.');
        foreach($legs as &$leg) { $leg['seconds']=$quote['seconds']*$leg['weight']/$raw;unset($leg['weight']); }unset($leg);
        return ['version'=>1,'origin'=>$origin,'target'=>$target,'seconds'=>(int)$quote['seconds'],'one_way_fuel'=>(float)$quote['one_way_fuel'],
            'legs'=>$legs,'active'=>['status'=>'traveling','progress'=>0.0,'at'=>$now->getTimestamp()]];
    }

    private function leg(array $from,array $to,float $distance,float $speed,float $fuel,bool $hyper=false): array
    {
        return ['from'=>$from,'to'=>$to,'distance'=>$distance,'weight'=>$distance/$speed,'fuel'=>$distance*$fuel,'hyper'=>$hyper];
    }

    public function progress(Fleet $fleet,\DateTimeImmutable $now): float
    {
        $active=$fleet->navigation['active']??null;
        if(!$active || ($active['status']??null)!==$fleet->status || !$fleet->arrivalAt) throw new \DomainException('Progression de cette ancienne mission insuffisamment documentée pour un rappel fiable.');
        $base=(float)$active['progress'];$start=(int)$active['at'];$end=$fleet->arrivalAt->getTimestamp();
        if($end<=$start) return 1.0;
        return min(1.0,max($base,$base+(1-$base)*max(0,$now->getTimestamp()-$start)/($end-$start)));
    }

    public function reschedule(Fleet $fleet,\DateTimeImmutable $now): void
    {
        if(!$fleet->navigation) return;
        try { $progress=$this->progress($fleet,$now); }
        catch(\DomainException) { return; }
        $fleet->navigation['active']=['status'=>$fleet->status,'progress'=>$progress,'at'=>$now->getTimestamp()];
    }

    public function beginLeg(Fleet $fleet,\DateTimeImmutable $at): void
    {
        if($fleet->navigation) $fleet->navigation['active']=['status'=>$fleet->status,'progress'=>0.0,'at'=>$at->getTimestamp()];
    }

    public function traveled(array $navigation,float $progress): array
    {
        if(!is_finite($progress) || $progress<0 || $progress>1 || empty($navigation['legs'])) throw new \DomainException('Trajet de cette mission insuffisamment documenté.');
        $remaining=$progress*$navigation['seconds'];$fuel=0.0;$point=$this->point($navigation['origin']);
        foreach($navigation['legs'] as $leg) {
            $part=min(1.0,max(0.0,$remaining/max(0.000001,$leg['seconds'])));
            $fuel+=$leg['fuel']*$part;
            if($leg['hyper'] && $part>0 && $part<1) $point=['system_id'=>null,'galaxy_id'=>$leg['from'][1],
                'x'=>$leg['from'][2]+($leg['to'][2]-$leg['from'][2])*$part,'y'=>$leg['from'][3]+($leg['to'][3]-$leg['from'][3])*$part,'position'=>null];
            else {
                $coords=$part>=1?$leg['to']:$leg['from'];
                if(!$leg['hyper']) $coords[4]=($leg['from'][4]??10)+(($leg['to'][4]??10)-($leg['from'][4]??10))*$part;
                $point=$this->point($coords);
            }
            $remaining-=$leg['seconds'];
            if($remaining<=0) break;
        }
        return ['point'=>$point,'fuel'=>min((float)$navigation['one_way_fuel'],max(0.0,$fuel)),
            'seconds'=>max(1,(int)ceil($progress*$navigation['seconds']))];
    }

    public function point(array $coordinates): array
    {
        return ['system_id'=>$coordinates[0],'galaxy_id'=>$coordinates[1],'x'=>$coordinates[2],'y'=>$coordinates[3],'position'=>$coordinates[4]??10];
    }

    public function distances(array $point,array $target): array
    {
        if($point['galaxy_id']!==$target[1]) throw new \DomainException('Le repli inter-galaxies n’est pas défini.');
        $same=$point['system_id']===$target[0];
        $normal=$same?abs($point['position']-$target[4])*10:
            ($point['system_id']===null?0:(10-$point['position'])*10)+(10-$target[4])*10;
        $hyper=$same?0:hypot($target[2]-$point['x'],$target[3]-$point['y'])*100;
        return ['normal'=>$normal,'hyper'=>$hyper,'same'=>$same];
    }
}
