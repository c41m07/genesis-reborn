<?php
namespace App\Service\Fleet;
final class MovementCalculator
{
    /** Coordinates use [system-id, galaxy-id, x, y, planet-position]; null position is an exploration at the system border. */
    public function quote(array $origin,array $target,array $ships,float $ratio,float $timeMultiplier,string $fuelPolicy,float $orbitHours=0): array
    {
        if(!is_finite($ratio) || $ratio<.1 || $ratio>1 || !is_finite($timeMultiplier) || $timeMultiplier<=0 || !is_finite($orbitHours) || $orbitHours<0) throw new \DomainException('Vitesse ou durée invalide.');
        if(!$ships) throw new \DomainException('Sélectionnez au moins un vaisseau.');
        if($origin[1]!==$target[1]) throw new \DomainException('Les voyages inter-galaxies ne sont pas encore définis.');
        if(!in_array($fuelPolicy,['proportional','inverse'],true)) throw new \DomainException('Départs en attente du choix de la règle de carburant.');
        foreach([$origin[4],$target[4]] as $pos) if($pos!==null && ($pos<1 || $pos>9)) throw new \DomainException('Position invalide.');
        $same=$origin[0]===$target[0];
        $normal=$same ? abs(($target[4]??10)-$origin[4])*10 : (100-$origin[4]*10)+($target[4]===null?0:100-$target[4]*10);
        $hyper=$same ? 0 : hypot($target[2]-$origin[2],$target[3]-$origin[3])*100;
        return $this->quoteDistances($normal,$hyper,$same,$ships,$ratio,$timeMultiplier,$fuelPolicy,$orbitHours);
    }

    public function quoteDistances(int|float $normal,int|float $hyper,bool $same,array $ships,float $ratio,float $timeMultiplier,string $fuelPolicy,float $orbitHours=0): array
    {
        if(!is_finite($normal) || !is_finite($hyper) || $normal<0 || $hyper<0) throw new \DomainException('Distance invalide.');
        if(!is_finite($ratio) || $ratio<.1 || $ratio>1 || !is_finite($timeMultiplier) || $timeMultiplier<=0 || !is_finite($orbitHours) || $orbitHours<0) throw new \DomainException('Vitesse ou durée invalide.');
        if(!$ships) throw new \DomainException('Sélectionnez au moins un vaisseau.');
        if(!in_array($fuelPolicy,['proportional','inverse'],true)) throw new \DomainException('Départs en attente du choix de la règle de carburant.');
        $systemSpeed=INF;$galaxySpeed=INF;$fuelNormal=0;$fuelHyper=0;$orbit=0;$cargoCapacity=0;$fuelCapacity=0;$probeOnly=true;
        foreach($ships as $ship) {
            $n=$ship['quantity'];if(!is_int($n)||$n<1||$ship['speed_system']<=0||$ship['speed_galaxy']<=0) throw new \DomainException('Composition de flotte invalide.');
            $systemSpeed=min($systemSpeed,$ship['speed_system']);$galaxySpeed=min($galaxySpeed,$ship['speed_galaxy']);
            $probeOnly=$probeOnly&&($ship['slug']??null)==='probe';
            $fuelNormal+=$ship['fuel_travel_system']*$n;$fuelHyper+=$ship['fuel_travel_galaxy']*$n;$orbit+=$ship['fuel_orbit']*$n*$orbitHours;
            $cargoCapacity+=$ship['cargo_capacity']*$n;
            $fuelCapacity+=($ship['fuel_capacity']??0)*$n;
        }
        $factor=$fuelPolicy==='proportional'?$ratio:1/$ratio;
        $oneWay=($normal*$fuelNormal+$hyper*$fuelHyper)*$factor;
        $fuel=$oneWay*2+$orbit;
        // Les minimums restent valables après les recherches, le réglage de départ et la vitesse de l’univers.
        $minimumSeconds=$same&&$probeOnly?30:60;
        $seconds=max($minimumSeconds,(int)ceil(($normal/($systemSpeed*$ratio)+$hyper/($galaxySpeed*$ratio))*3600*$timeMultiplier));
        // Dedicated tanks carry fuel only. Cargo bays can carry additional fuel or freight.
        return ['normal_distance'=>$normal,'hyper_distance'=>$hyper,'distance'=>$normal+$hyper,'seconds'=>$seconds,'one_way_fuel'=>$oneWay,'orbit_fuel'=>$orbit,'total_fuel'=>$fuel,'fuel_capacity'=>$fuelCapacity,'cargo_capacity'=>$cargoCapacity,'capacity'=>$fuelCapacity+$cargoCapacity,'freight_capacity'=>max(0,$cargoCapacity-max(0,$fuel-$fuelCapacity))];
    }
}
