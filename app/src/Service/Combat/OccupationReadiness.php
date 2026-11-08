<?php
namespace App\Service\Combat;

use App\Entity\{CombatEvent,FleetShip,PlanetOccupation};
use App\Repository\GameRepository;
use App\Service\Player\RelationResolver;

/** Lecture de l'état courant ; le transfert doit la refaire sous verrou, après traitement des files échues. */
final class OccupationReadiness
{
    public function __construct(private GameRepository $repo,private OccupationPolicy $policy,private OccupationService $occupations,private RelationResolver $relations) {}

    public function inspect(PlanetOccupation $occupation): array
    {
        $planet=$occupation->planet;$member=$occupation->occupant;
        $combatOpen=$this->repo->one(CombatEvent::class,['planet'=>$planet,'status'=>'pending'])!==null;
        $present=[];$colonyShipIds=[];$hostile=$this->repo->hasPlanetHostileUnits($planet);
        foreach($this->repo->fleetsPresentForOccupation($planet) as $fleet) {
            if($fleet->playerUniverse->id===$member->id && $fleet->status==='occupying') {
                $present[]=$fleet->id;
                foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet,'slug'=>'colony_ship'],['id'=>'ASC']) as $ship) {
                    if($ship->quantity>0) $colonyShipIds[]=$ship->id;
                }
            } elseif($fleet->playerUniverse->id!==$member->id && !$this->relations->allied($member,$fleet->playerUniverse)) $hostile=true;
        }
        $pendingReturns=[];
        foreach($this->repo->fleetsAwayFromOccupation($planet) as $fleet) {
            if($fleet->playerUniverse->id!==$member->id && !$this->relations->allied($member,$fleet->playerUniverse)) $pendingReturns[]=$fleet->id;
        }
        $hasCapacity=count($this->repo->planets($member->universe,$member))+$this->occupations->reservedSlots($member)<=$this->occupations->colonyLimit($member);
        $decision=$this->policy->decision($occupation,$combatOpen,$present!==[],$colonyShipIds!==[],$hasCapacity,$hostile,$pendingReturns!==[]);
        if($occupation->reservesSlot() && !$this->occupations->canOccupy($member)) $decision='occupant_unavailable';
        sort($colonyShipIds,SORT_NUMERIC);
        return ['decision'=>$decision,'combat_open'=>$combatOpen,'fleet_ids'=>$present,'colony_ship_ids'=>$colonyShipIds,
            'hostile_forces'=>$hostile,'pending_return_ids'=>$pendingReturns,'has_capacity'=>$hasCapacity];
    }
}
