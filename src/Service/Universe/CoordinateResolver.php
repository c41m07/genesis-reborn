<?php
namespace App\Service\Universe;

use App\Entity\{Planet,SolarSystem,Universe};
use App\Repository\GameRepository;

final class CoordinateResolver
{
    public function __construct(private GameRepository $repo) {}

    public function system(Universe $universe,string $coordinates): SolarSystem
    {
        [$galaxyName,$systemNumber]=$this->parts($coordinates,false);
        foreach($this->repo->systems($universe) as $system) {
            if((strcasecmp($system->galaxy->coordinateCode(),$galaxyName)===0 || strcasecmp(trim($system->galaxy->name),$galaxyName)===0) && $system->number()===$systemNumber) return $system;
        }
        throw new \DomainException('Système introuvable dans cet univers. Vérifiez les coordonnées galaxie:système.');
    }

    public function planet(Universe $universe,string $coordinates): Planet
    {
        [$galaxyName,$systemNumber,$position]=$this->parts($coordinates,true);
        $system=null;
        foreach($this->repo->systems($universe) as $candidate) {
            if((strcasecmp($candidate->galaxy->coordinateCode(),$galaxyName)===0 || strcasecmp(trim($candidate->galaxy->name),$galaxyName)===0) && $candidate->number()===$systemNumber) { $system=$candidate;break; }
        }
        if(!$system) throw new \DomainException('Système introuvable dans cet univers.');
        $planet=$this->repo->one(Planet::class,['solarSystem'=>$system,'position'=>$position]);
        if(!$planet instanceof Planet) throw new \DomainException('Aucune planète n’existe à cette position.');
        return $planet;
    }

    /** @return array{string,int,?int} */
    private function parts(string $coordinates,bool $requirePlanet): array
    {
        $parts=array_map('trim',explode(':',trim($coordinates)));
        if(count($parts)!==($requirePlanet?3:2) || $parts[0]==='' || !ctype_digit($parts[1]) || ($requirePlanet && !ctype_digit($parts[2]))) {
            throw new \DomainException($requirePlanet?'Saisissez les coordonnées au format GALAXIE:SYSTÈME:PLANÈTE, par exemple A:12:3.':'Saisissez un système au format GALAXIE:SYSTÈME, par exemple A:12.');
        }
        $systemNumber=(int)$parts[1];$position=$requirePlanet?(int)$parts[2]:null;
        if($systemNumber<1 || ($position!==null && ($position<1 || $position>9))) throw new \DomainException('Coordonnées hors limites. Les positions planétaires vont de 1 à 9.');
        return [$parts[0],$systemNumber,$position];
    }
}
