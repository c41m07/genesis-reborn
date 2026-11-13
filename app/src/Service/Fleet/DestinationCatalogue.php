<?php
namespace App\Service\Fleet;

use App\Entity\{Planet,PlayerDiscovery,PlayerUniverse,SolarSystem};
use App\Repository\GameRepository;
use App\Service\Combat\CombatEventService;
use App\Service\Player\{RelationResolver,VacationCalendar};
use App\Service\Universe\CoordinateResolver;

final class DestinationCatalogue
{
    public const MISSIONS=['explore','colonize','transport','attack','conquer','defend','orbit','spy'];

    public function __construct(private GameRepository $repo,private CombatEventService $combats,
        private TransportService $transports,private RelationResolver $relations,
        private VacationCalendar $vacations,private CoordinateResolver $coordinates,private OrbitPolicy $orbitPolicy) {}

    public function choices(PlayerUniverse $viewer,string $mission,?string $query=null): array
    {
        $this->assertMission($mission);
        $result=['groups'=>['friendly'=>[],'enemies'=>[]],'suggestions'=>[]];
        if(!$viewer->isPlayerControlled() || $this->vacations->isActive($viewer)) return $result;
        $query=$query===null?null:mb_substr(trim($query),0,40);
        if($query!==null && mb_strlen($query)<2) return $result;
        $known=[];
        foreach($this->repo->all(PlayerDiscovery::class,['playerUniverse'=>$viewer]) as $discovery) $known[$discovery->solarSystem->id]=true;
        $owned=$this->repo->planets($viewer->universe,$viewer);
        $others=array_filter($this->repo->planets($viewer->universe),static fn(Planet $p):bool=>$p->owner?->id!==$viewer->id);
        foreach([...$owned,...array_values($others)] as $planet) {
            if(!isset($known[$planet->solarSystem->id])) continue;
            if($query!==null && ($planet->owner?->player===null || mb_stripos($planet->owner->player->username,$query)===false)) continue;
            if(!$this->allows($viewer,$planet,$mission,false)) continue;
            $category=$this->category($viewer,$planet);
            $owner=$planet->owner?->getDisplayName()??($planet->npcPlanet?'PNJ':'Inhabitée');
            $row=['id'=>$planet->id,'name'=>$planet->name,'owner'=>$owner,'coordinates'=>$planet->coordinates(),
                'systemCoordinates'=>$planet->solarSystem->galaxy->coordinateCode().':'.$planet->solarSystem->number(),
                'label'=>$planet->name.' · '.$planet->coordinates().' · '.$owner];
            if($query!==null) {
                $result['suggestions'][]=$row;
                if(count($result['suggestions'])===20) break;
            } elseif(isset($result['groups'][$category])) {
                $result['groups'][$category][]=$row;
            }
        }
        return $result;
    }

    public function category(PlayerUniverse $viewer,Planet $planet): string
    {
        $owner=$planet->owner;
        if($owner?->id===$viewer->id || ($owner && $this->relations->allied($viewer,$owner))) return 'friendly';
        if(($owner===null && $planet->npcPlanet) || $owner?->isForgotten() || ($owner && $this->relations->enemy($viewer,$owner))) return 'enemies';
        return 'custom';
    }

    public function canPrefill(PlayerUniverse $viewer,Planet $planet,string $mission,bool $checkDiscovery=true): bool
    {
        $this->assertMission($mission);
        if(!$viewer->isPlayerControlled() || $planet->solarSystem->galaxy->universe->id!==$viewer->universe->id) return false;
        if($planet->owner!==null && $planet->owner->universe->id!==$viewer->universe->id) return false;
        if($checkDiscovery && !$this->repo->one(PlayerDiscovery::class,['playerUniverse'=>$viewer,'solarSystem'=>$planet->solarSystem])) return false;
        return match($mission) {
            'explore','transport'=>true,
            'colonize'=>$planet->owner===null && !$planet->npcPlanet,
            'attack'=>$this->combats->canAttack($viewer,$planet),
            'conquer'=>$this->combats->canConquer($viewer,$planet),
            'defend'=>$this->combats->canDefend($viewer,$planet),
            'orbit'=>$this->orbitPolicy->canRequest($viewer,$planet),
            'spy'=>$planet->owner?->id!==$viewer->id,
        };
    }

    public function allows(PlayerUniverse $viewer,Planet $planet,string $mission,bool $checkDiscovery=true): bool
    {
        if(!$this->canPrefill($viewer,$planet,$mission,$checkDiscovery)) return false;
        if($this->vacations->isActive($viewer) || ($planet->owner && $this->vacations->isActive($planet->owner))) return false;
        return $mission!=='transport' || $this->transports->canReceive($viewer,$planet);
    }

    public function resolve(PlayerUniverse $viewer,string $mission,string $mode,string $coordinates,mixed $selection=null): Planet|SolarSystem
    {
        $this->assertMission($mission);
        if(!$viewer->isPlayerControlled()) throw new \DomainException('Destination inaccessible.');
        if($mode==='custom') return $mission==='explore'
            ?$this->coordinates->system($viewer->universe,$coordinates):$this->coordinates->planet($viewer->universe,$coordinates);
        if(!in_array($mode,['friendly','enemies'],true)) throw new \DomainException('Mode de destination invalide.');
        $id=filter_var($selection,FILTER_VALIDATE_INT);
        $planet=$id!==false && $id>0?$this->repo->find(Planet::class,$id):null;
        if(!$planet instanceof Planet || !$this->allows($viewer,$planet,$mission) || $this->category($viewer,$planet)!==$mode) {
            throw new \DomainException('Destination indisponible pour ce mode ou cette mission.');
        }
        return $mission==='explore'?$planet->solarSystem:$planet;
    }

    private function assertMission(string $mission): void
    {
        if(!in_array($mission,self::MISSIONS,true)) throw new \DomainException('Mission indisponible.');
    }
}
