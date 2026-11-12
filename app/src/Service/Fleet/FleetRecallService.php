<?php
namespace App\Service\Fleet;

use App\Entity\{AdminAuditLog,CombatParticipant,Fleet,FleetShip,Planet,PlayerBoost,PlayerTechnology,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use App\Service\Combat\TechnologyEffectCalculator;
use Psr\Clock\ClockInterface;

final class FleetRecallService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ActionGuard $guard,
        private FleetService $fleets,private FleetNavigation $navigation,private MovementCalculator $calculator,
        private GameCatalog $catalog,private TechnologyEffectCalculator $technologyEffects,private ClockInterface $clock) {}

    public function preview(PlayerUniverse $member,Fleet $fleet,?int $returnPlanetId=null): array
    {
        $this->authorizeFleet($member,$fleet);
        return $this->plan($member,$fleet,$returnPlanetId,$this->clock->now());
    }

    public function recall(PlayerUniverse $member,int $fleetId,?int $returnPlanetId,string $version,string $requestKey): bool
    {
        return $this->manager->transaction(function()use($member,$fleetId,$returnPlanetId,$version,$requestKey): bool {
            $this->guard->lockMembership($member);
            $fleet=$this->repo->find(Fleet::class,$fleetId);
            if(!$fleet) throw new \DomainException('Flotte inaccessible.');
            $this->authorizeFleet($member,$fleet);
            $this->manager->lock($fleet->homePlanet);$this->manager->lock($fleet);
            $this->authorizeFleet($member,$fleet);
            if(!$this->guard->receipt($member,$requestKey)) return false;
            if(!hash_equals(hash('sha256',$fleet->dispatchToken),$version)) throw new \DomainException('La mission a changé. Rouvrez le formulaire de rappel.');
            $returnPlanet=$this->destination($member,$fleet,$returnPlanetId);
            $this->manager->lock($returnPlanet);
            if($returnPlanet->owner?->id!==$member->id || $returnPlanet->solarSystem->galaxy->universe->id!==$member->universe->id) throw new \DomainException('La colonie de retour ne vous appartient plus.');
            $quote=$this->plan($member,$fleet,$returnPlanet->id,$this->clock->now());
            $this->fleets->startPlayerRecallLocked($fleet,$returnPlanet,$quote);
            return true;
        });
    }

    private function authorizeFleet(PlayerUniverse $member,Fleet $fleet): void
    {
        if(!$member->isPlayerControlled() || $member->player->lockedAt!==null || $fleet->playerUniverse->id!==$member->id
            || $fleet->homePlanet->solarSystem->galaxy->universe->id!==$member->universe->id) throw new \DomainException('Flotte inaccessible dans cet univers.');
    }

    private function destination(PlayerUniverse $member,Fleet $fleet,?int $id): Planet
    {
        if($fleet->homePlanet->owner?->id===$member->id) {
            if($id!==null && $id!==0 && $id!==$fleet->homePlanet->id) throw new \DomainException('La flotte doit revenir à sa colonie d’origine tant qu’elle vous appartient.');
            return $fleet->homePlanet;
        }
        $planet=$id?$this->repo->find(Planet::class,$id):null;
        if(!$planet instanceof Planet || $planet->owner?->id!==$member->id
            || $planet->solarSystem->galaxy->universe->id!==$member->universe->id) throw new \DomainException('L’origine est perdue. Choisissez une colonie de repli de cet univers.');
        return $planet;
    }

    private function plan(PlayerUniverse $member,Fleet $fleet,?int $returnPlanetId,\DateTimeImmutable $now): array
    {
        if(!in_array($fleet->status,['traveling','orbiting','orbit_pending'],true)) throw new \DomainException('Rappel indisponible pour un retour, un combat ou une occupation.');
        if(!in_array($fleet->mission,DestinationCatalogue::MISSIONS,true)) throw new \DomainException('Mission non rappelable.');
        if($fleet->arrivalAt && $fleet->arrivalAt<=$now) throw new \DomainException('L’échéance est atteinte : l’arrivée doit être résolue avant toute autre action.');
        if($fleet->status!=='orbit_pending' && (!$fleet->arrivalAt || !$fleet->departureAt)) throw new \DomainException('Chronologie insuffisante pour un rappel fiable.');
        foreach($this->repo->all(CombatParticipant::class,['fleet'=>$fleet]) as $participant)
            if($participant->combatEvent->status==='pending') throw new \DomainException('Cette flotte participe à un combat en cours.');
        if(array_sum(array_map(static fn(FleetShip $ship)=>$ship->quantity,$this->repo->all(FleetShip::class,['fleet'=>$fleet])))<1) throw new \DomainException('Cette flotte ne possède plus de vaisseau.');
        $returnPlanet=$this->destination($member,$fleet,$returnPlanetId);
        $state=$fleet->navigation??$this->legacyNavigation($fleet);
        if(($state['version']??null)!==1 || empty($state['legs'])) throw new \DomainException('Les données de cette ancienne flotte ne permettent pas un rappel fiable. La mission continue normalement.');
        $copy=clone $fleet;$copy->navigation=$state;
        if($fleet->status==='traveling') {
            $progress=$this->navigation->progress($copy,$now);$traveled=$this->navigation->traveled($state,$progress);
            $spent=$traveled['fuel'];$fuel=$spent;$seconds=$traveled['seconds'];$position=$traveled['point'];
        } else {
            $progress=1.0;$position=$this->navigation->point($state['target']);
            $spent=$fleet->status==='orbiting'?$fleet->orbitFuel*$this->navigation->progress($copy,$now):0.0;
            $fuel=max(0.0,$fleet->reservedFuel-$fleet->orbitFuel);$seconds=max(1,$fleet->travelSeconds);
        }
        $originLost=$fleet->homePlanet->owner?->id!==$member->id;
        if($originLost) {
            $target=$this->coords($returnPlanet);$distances=$this->navigation->distances($position,$target);
            $config=$this->catalog->forUniverse($member->universe);
            $quote=$this->calculator->quoteDistances($distances['normal'],$distances['hyper'],$distances['same'],
                $this->ships($fleet),$fleet->speedRatio,$config['multipliers']['travel_time'],$this->catalog->rules()['fuel_ratio_policy']);
            $fuel=$quote['one_way_fuel'];$seconds=$quote['seconds'];
        }
        $hydrogen=(float)($fleet->cargo['hydrogen']??0);
        $available=$fleet->reservedFuel-$spent;
        // JSON et DOUBLE peuvent différer à la relecture ; tolérer seulement l’arrondi, puis plafonner au stock réel.
        $tolerance=max(1e-9,abs($fleet->reservedFuel)*PHP_FLOAT_EPSILON*64);
        if(!is_finite($hydrogen) || !is_finite($available) || !is_finite($fuel) || !is_finite($spent)
            || $available<-$tolerance || $fuel<0 || $fuel>$available+$tolerance
            || $hydrogen+$tolerance<$fleet->reservedFuel) throw new \DomainException('Carburant réservé insuffisant pour ce retour. Le fret et les stocks restent inchangés.');
        $spent=min($spent,$hydrogen,$fleet->reservedFuel);
        $available=max(0.0,min($hydrogen,$fleet->reservedFuel)-$spent);
        $fuel=min($fuel,$available);
        $freight=$fleet->cargo;$freight['hydrogen']=max(0.0,($freight['hydrogen']??0)-$fleet->reservedFuel);
        return ['at'=>$now,'navigation'=>$state,'progress'=>$progress,'position'=>$position,'spent_now'=>$spent,
            'return_fuel'=>$fuel,'unused_fuel'=>$available-$fuel,'seconds'=>$seconds,'arrival_at'=>$now->modify('+'.$seconds.' seconds'),
            'return_planet'=>$returnPlanet,'origin_lost'=>$originLost,'freight'=>$freight,
            'version'=>hash('sha256',$fleet->dispatchToken)];
    }

    private function ships(Fleet $fleet): array
    {
        $ships=[];
        foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet]) as $ship) if($ship->quantity>0) {
            $definition=$this->catalog->getTierForUniverse($fleet->playerUniverse->universe,'ships',$ship->slug,$ship->tier);
            $definition['quantity']=$ship->quantity;$ships[]=$definition;
        }
        if(!$ships) throw new \DomainException('Cette flotte ne possède plus de vaisseau.');
        $levels=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$fleet->playerUniverse]);
        return $this->technologyEffects->applyToShips($ships,$levels,$fleet->playerUniverse->universe);
    }

    private function legacyNavigation(Fleet $fleet): array
    {
        $target=$fleet->destination?->solarSystem??$fleet->destinationSystem;
        if(!$target || !$fleet->departureAt || $fleet->travelSeconds<1) throw new \DomainException('Les données de cette ancienne flotte ne permettent pas un rappel fiable. La mission continue normalement.');
        foreach($this->repo->all(PlayerBoost::class,['operationType'=>'fleet','operationId'=>$fleet->id,'status'=>'used']) as $boost)
            if($fleet->status!=='orbit_pending' && (!$boost->usedAt || $boost->usedAt>=$fleet->departureAt)) throw new \DomainException('L’avancement de cette ancienne flotte boostée ne peut pas être reconstitué avec certitude. La mission continue normalement.');
        foreach($this->repo->all(AdminAuditLog::class,['targetType'=>'fleet','targetId'=>$fleet->id,'action'=>'admin_fleet_reschedule']) as $audit)
            if($fleet->status!=='orbit_pending' && $audit->createdAt>=$fleet->departureAt) throw new \DomainException('L’échéance de cette ancienne flotte a été modifiée sans suivi de progression. La mission continue normalement.');
        $origin=$this->coords($fleet->homePlanet);
        $end=[$target->id,$target->galaxy->id,$target->coordX,$target->coordY,$fleet->destination?->position];
        $traveling=$fleet->status==='traveling';
        if($traveling && ($origin[0]!==$end[0] || !$fleet->arrivalAt
            || $fleet->arrivalAt->getTimestamp()!==$fleet->departureAt->getTimestamp()+$fleet->travelSeconds))
            throw new \DomainException('Le trajet de cette ancienne flotte ne permet pas un rappel fiable. La mission continue normalement.');
        $fuel=$traveling?max(0.0,($fleet->reservedFuel-$fleet->orbitFuel)/2):max(0.0,$fleet->reservedFuel-$fleet->orbitFuel);
        return ['version'=>1,'origin'=>$origin,'target'=>$end,'seconds'=>$fleet->travelSeconds,'one_way_fuel'=>$fuel,
            'legs'=>[['from'=>$origin,'to'=>$end,'seconds'=>$fleet->travelSeconds,'fuel'=>$fuel,'hyper'=>false]],
            'active'=>['status'=>$fleet->status,'progress'=>0.0,'at'=>$fleet->departureAt->getTimestamp()]];
    }

    private function coords(Planet $planet): array
    {
        $system=$planet->solarSystem;
        return [$system->id,$system->galaxy->id,$system->coordX,$system->coordY,$planet->position];
    }
}
