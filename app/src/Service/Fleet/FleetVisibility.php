<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,FleetShip,Galaxy,Planet,PlayerDiscovery,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{LeaderboardService,RelationResolver};
use Psr\Clock\ClockInterface;

final class FleetVisibility
{
    private const MISSIONS=['explore'=>'Exploration','spy'=>'Espionnage','colonize'=>'Colonisation','transport'=>'Transport','attack'=>'Attaque','conquer'=>'Conquête JcE','defend'=>'Défense','orbit'=>'Orbite'];
    private const STATUSES=['traveling'=>'En vol','returning'=>'Retour','attacking'=>'Combat','defending'=>'Défense','orbiting'=>'En orbite','occupying'=>'Occupation JcE','orbit_pending'=>'Demande d’orbite'];

    public function __construct(private GameRepository $repo,private RelationResolver $relations,private AttackDetection $detection,
        private GameCatalog $catalog,private LeaderboardService $leaderboard,private ClockInterface $clock) {}

    public function forMap(PlayerUniverse $viewer,?Galaxy $galaxy): array
    {
        if(!$galaxy || !$this->canRead($viewer) || $galaxy->universe->id!==$viewer->universe->id) return [];
        $known=$this->knownSystems($viewer);$level=$this->detection->level($viewer);$now=$this->clock->now();$rows=[];$allies=[];
        foreach($this->repo->activeFleetsInUniverse($viewer->universe) as $fleet) {
            $destination=$fleet->destination?->solarSystem??$fleet->destinationSystem;
            if(!$destination || ($fleet->homePlanet->solarSystem->galaxy->id!==$galaxy->id && $destination->galaxy->id!==$galaxy->id)) continue;
            $owner=$fleet->playerUniverse->id;
            $allies[$owner]??=$this->relations->allied($viewer,$fleet->playerUniverse);
            if(!$this->visible($viewer,$fleet,$known,$level,$now,$allies[$owner])) continue;
            $rows[]=$this->row($viewer,$fleet,$known);
        }
        return $rows;
    }

    public function incoming(PlayerUniverse $viewer): array
    {
        if(!$this->canRead($viewer)) return [];
        $known=$this->knownSystems($viewer);$level=$this->detection->level($viewer);$now=$this->clock->now();$rows=[];
        foreach($this->repo->incomingAttackFleets($viewer) as $fleet) {
            if($this->visible($viewer,$fleet,$known,$level,$now,false)) $rows[]=$this->row($viewer,$fleet,$known);
        }
        return $rows;
    }

    private function canRead(PlayerUniverse $viewer): bool
    {
        return $viewer->isPlayerControlled() && $viewer->player->lockedAt===null && $viewer->universe->isAccessible($this->clock->now());
    }

    private function knownSystems(PlayerUniverse $viewer): array
    {
        $known=[];
        foreach($this->repo->all(PlayerDiscovery::class,['playerUniverse'=>$viewer]) as $discovery)
            if($discovery->solarSystem->galaxy->universe->id===$viewer->universe->id) $known[$discovery->solarSystem->id]=true;
        foreach($this->repo->planets($viewer->universe,$viewer) as $planet) $known[$planet->solarSystem->id]=true;
        return $known;
    }

    private function visible(PlayerUniverse $viewer,Fleet $fleet,array $known,int $level,\DateTimeImmutable $now,bool $allied): bool
    {
        $origin=$fleet->homePlanet->solarSystem;$target=$fleet->destination?->solarSystem??$fleet->destinationSystem;
        if(!isset(self::STATUSES[$fleet->status]) || !$target || $fleet->playerUniverse->universe->id!==$viewer->universe->id
            || $origin->galaxy->universe->id!==$viewer->universe->id || $target->galaxy->universe->id!==$viewer->universe->id) return false;
        if($fleet->playerUniverse->id===$viewer->id) return true;
        if($fleet->mission==='spy') return false;
        $recipient=$fleet->destination?->owner?->id===$viewer->id;
        if($recipient && $fleet->status==='traveling' && in_array($fleet->mission,['attack','conquer'],true)) return $this->detection->detected($fleet,$now,$level);
        return $recipient || ($allied && isset($known[$origin->id],$known[$target->id]));
    }

    private function row(PlayerUniverse $m,Fleet $fleet,array $known): array
    {
        $originSystem=$fleet->homePlanet->solarSystem;$destinationSystem=$fleet->destination?->solarSystem??$fleet->destinationSystem;
        $isOwnFleet=$fleet->playerUniverse->id===$m->id;$targetsOwnColony=$fleet->destination?->owner?->id===$m->id;
        $ships = array_values(array_filter($this->repo->all(FleetShip::class, ['fleet' => $fleet]), static fn (FleetShip $ship): bool => $ship->quantity > 0));
        $shipsSummary = $isOwnFleet ? implode(' · ', array_map(fn (FleetShip $ship): string => $ship->quantity.' × '.str_replace('_', ' ', $ship->slug).' ('.$this->catalog->tiers()[$ship->tier]['label'].')', $ships)) : null;
        $relationLabel = $isOwnFleet ? 'Votre empire' : ($targetsOwnColony ? 'Flotte entrante' : 'Allié');
        $recallRoute=null;
        if(isset($fleet->navigation['recall']) && $fleet->status==='returning') {
            $returnPlanet=$fleet->returnPlanet??$fleet->homePlanet;
            $canSeeRoute=$isOwnFleet || (isset($known[$originSystem->id],$known[$destinationSystem->id],$known[$returnPlanet->solarSystem->id]));
            $recallRoute=$canSeeRoute?['startProgress'=>(float)$fleet->navigation['recall']['progress'],
                'returnSystemId'=>$returnPlanet->solarSystem->id,'returnName'=>$returnPlanet->name,
                'returnPosition'=>$returnPlanet->position]:['hidden'=>true];
        }
        $progressState=($fleet->navigation['active']['status']??null)===$fleet->status?$fleet->navigation['active']:null;

        $seeOrigin=$isOwnFleet || isset($known[$originSystem->id]);
        $incomingAttack=!$isOwnFleet && $targetsOwnColony && in_array($fleet->mission,['attack','conquer'],true) && $fleet->status==='traveling';
        return [
            'id' => $fleet->id,
            'mission' => $fleet->mission,
            'missionLabel' => self::MISSIONS[$fleet->mission] ?? 'Mission',
            'status' => $fleet->status,
            'statusLabel' => self::STATUSES[$fleet->status],
            'relationLabel' => $relationLabel,
            'ownerName' => $fleet->playerUniverse->getDisplayName(),
            'originSystemId' => $seeOrigin?$originSystem->id:null,
            'originGalaxy' => $seeOrigin?$originSystem->galaxy->coordinateCode():null,
            'originX' => $seeOrigin?$originSystem->coordX:null,
            'originY' => $seeOrigin?$originSystem->coordY:null,
            'originName' => $seeOrigin?$fleet->homePlanet->name:'Origine non découverte',
            'originCoordinates' => $seeOrigin?$fleet->homePlanet->coordinates():null,
            'destinationPlanetId' => $fleet->destination?->id,
            'destinationGalaxyId' => $destinationSystem->galaxy->id,
            'destinationSystemId' => $destinationSystem->id,
            'destinationGalaxy' => $destinationSystem->galaxy->coordinateCode(),
            'destinationX' => $destinationSystem->coordX,
            'destinationY' => $destinationSystem->coordY,
            'destinationName' => $fleet->destination?->name ?? $destinationSystem->name,
            'destinationCoordinates' => $fleet->destination?->coordinates() ?? ($destinationSystem->galaxy->coordinateCode().':'.$destinationSystem->number()),
            'recallRoute'=>$recallRoute,
            'progressBase'=>$incomingAttack?null:($progressState['progress']??null),
            'progressAt'=>$incomingAttack?null:($progressState['at']??null),
            'departureAt' => $incomingAttack?null:$fleet->departureAt?->format(\DateTimeInterface::ATOM),
            'arrivalAt' => $fleet->arrivalAt?->format(\DateTimeInterface::ATOM),
            'ownedByCurrentPlayer' => $isOwnFleet,
            'shipCount' => array_sum(array_map(static fn (FleetShip $ship): int => $ship->quantity, $ships)),
            'fleetPoints' => $this->leaderboard->fleetPoints($fleet, $ships),
            'ships' => $shipsSummary,
        ];
    }
}
