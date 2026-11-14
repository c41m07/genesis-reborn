<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,Planet,PlayerDiscovery,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use App\Service\Notification\NotificationService;
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class FleetStationing
{
    public function __construct(private GameRepository $repo,private GameManager $manager,
        private ActionGuard $guard,private FleetHold $hold,private MovementCalculator $calculator,
        private FleetNavigation $navigation,private FleetDispatch $dispatch,private GameCatalog $catalog,
        private NotificationService $notifications,private VacationCalendar $vacations,private ClockInterface $clock) {}

    /** Le contrôle du jeton, de l'échéance et les verrous de participation et flotte précèdent cet appel. */
    public function arriveLocked(Fleet $fleet): void
    {
        $legFuel=(float)($fleet->navigation['station']['leg_fuel']??NAN);$hydrogen=(float)($fleet->cargo['hydrogen']??0);
        $tolerance=max(1e-9,abs($fleet->reservedFuel)*PHP_FLOAT_EPSILON*64);
        if(!is_finite($legFuel) || $legFuel<0 || !is_finite($fleet->reservedFuel) || $fleet->reservedFuel<0 || !is_finite($hydrogen) || $hydrogen<0 || $fleet->reservedFuel+$tolerance<$legFuel
            || ($fleet->cargo['hydrogen']??0)+$tolerance<$fleet->reservedFuel) {
            throw new \DomainException('Réserve de stationnement incohérente. Aucun transfert effectué.');
        }
        $fleet->reservedFuel=min($fleet->reservedFuel,$hydrogen);
        $legFuel=min($legFuel,$fleet->reservedFuel);
        $fleet->reservedFuel-=$legFuel;$fleet->cargo['hydrogen']-=$legFuel;
        $target=$fleet->status==='returning'?($fleet->returnPlanet??$fleet->homePlanet):$fleet->destination;
        if(!$target || $target->solarSystem->galaxy->universe->id!==$fleet->playerUniverse->universe->id) {
            throw new \DomainException('Destination de stationnement incohérente.');
        }
        $this->manager->lock($target);
        $fleet->navigation['station']['position_planet_id']=$target->id;
        $cancel=$fleet->status==='traveling' && ($fleet->playerUniverse->isForgotten() || $this->vacations->isActive($fleet->playerUniverse));
        if($target->owner?->id===$fleet->playerUniverse->id && !$cancel) {
            $this->install($fleet,$target);return;
        }
        $choices=[];
        foreach($this->repo->planets($fleet->playerUniverse->universe,$fleet->playerUniverse) as $planet) {
            try { $quote=$this->route($fleet,$target,$planet); }
            catch(\DomainException) {continue;}
            $choices[]=$quote;
        }
        usort($choices,static fn($a,$b)=>[$a['target']->id===$fleet->homePlanet->id?0:1,$a['seconds'],$a['target']->id]
            <=>[$b['target']->id===$fleet->homePlanet->id?0:1,$b['seconds'],$b['target']->id]);
        if($choices) {$this->start($fleet,$target,$choices[0]);return;}
        $fleet->status='station_waiting';$fleet->arrivalAt=null;$fleet->returnPlanet=null;
        $fleet->destination=$target;$fleet->destinationSystem=$target->solarSystem;
        $fleet->dispatchToken=Uuid::v4()->toRfc4122();
        $fleet->navigation['station']['leg_fuel']=0.0;
        if(empty($fleet->navigation['station']['waiting_notified'])) {
            $fleet->navigation['station']['waiting_notified']=true;
            $this->notifications->enqueue($fleet->playerUniverse,'Stationnement en attente : aucune colonie de repli atteignable avec la réserve. Vos vaisseaux et leur cargaison sont conservés. Reprise possible depuis Flottes.',journalKind:'arrival');
        }
    }

    public function owned(PlayerUniverse $member,int $id): Fleet
    {
        $fleet=$this->repo->find(Fleet::class,$id);
        if(!$member->isPlayerControlled() || $member->player->lockedAt!==null || !$fleet || $fleet->playerUniverse->id!==$member->id || $fleet->playerUniverse->universe->id!==$member->universe->id
            || $fleet->homePlanet->solarSystem->galaxy->universe->id!==$member->universe->id
            || $fleet->mission!=='station' || $fleet->status!=='station_waiting') throw new \DomainException('Stationnement en attente inaccessible.');
        return $fleet;
    }

    public function preview(PlayerUniverse $member,int $id,int $targetId): array
    {
        return $this->manager->transaction(function()use($member,$id,$targetId){
            $this->guard->lockMembership($member);$this->vacations->assertCanSendFleet($member);
            $fleet=$this->owned($member,$id);$this->manager->lock($fleet);
            return $this->resumeQuote($fleet,$targetId);
        });
    }

    public function resume(PlayerUniverse $member,int $id,int $targetId,string $version,string $key): void
    {
        $this->manager->transaction(function()use($member,$id,$targetId,$version,$key){
            $this->guard->lockMembership($member);$this->vacations->assertCanSendFleet($member);
            if(!$this->guard->receipt($member,$key)) return;
            $fleet=$this->owned($member,$id);$this->manager->lock($fleet);
            $quote=$this->resumeQuote($fleet,$targetId);
            if(!hash_equals($quote['version'],$version)) throw new \DomainException('Le devis a changé. Recalculez le trajet.');
            $source=$this->position($fleet);
            if($source->id===$quote['target']->id) $this->install($fleet,$source);
            else $this->start($fleet,$source,$quote);
        });
    }

    private function resumeQuote(Fleet $fleet,int $id): array
    {
        $planet=$this->repo->find(Planet::class,$id);
        if(!$planet || $planet->owner?->id!==$fleet->playerUniverse->id
            || $planet->solarSystem->galaxy->universe->id!==$fleet->playerUniverse->universe->id
            || !$this->repo->one(PlayerDiscovery::class,['playerUniverse'=>$fleet->playerUniverse,'solarSystem'=>$planet->solarSystem])) {
            throw new \DomainException('Choisissez une colonie possédée et découverte dans cet univers.');
        }
        $this->manager->lock($planet);
        return $this->route($fleet,$this->position($fleet),$planet);
    }

    private function position(Fleet $fleet): Planet
    {
        $planet=$this->repo->find(Planet::class,$fleet->navigation['station']['position_planet_id']??0);
        if(!$planet || $planet->solarSystem->galaxy->universe->id!==$fleet->playerUniverse->universe->id) throw new \DomainException('Position de la flotte indisponible.');
        return $planet;
    }

    private function route(Fleet $fleet,Planet $source,Planet $target): array
    {
        $ships=$this->hold->ships($fleet);
        if(!$ships) throw new \DomainException('Vaisseaux indisponibles.');
        $config=$this->catalog->forUniverse($fleet->playerUniverse->universe);
        $quote=$this->calculator->quote($this->coords($source),$this->coords($target),$ships,$fleet->speedRatio,
            $config['multipliers']['travel_time'],$this->catalog->rules()['fuel_ratio_policy']);
        if($source->id===$target->id) {$quote['seconds']=0;$quote['one_way_fuel']=0.0;}
        $tolerance=max(1e-9,abs($fleet->reservedFuel)*PHP_FLOAT_EPSILON*64);
        if(!is_finite((float)($fleet->cargo['hydrogen']??0)) || !is_finite($fleet->reservedFuel) || $fleet->reservedFuel<0 || $quote['one_way_fuel']>$fleet->reservedFuel+$tolerance
            || ($fleet->cargo['hydrogen']??0)+$tolerance<$fleet->reservedFuel) throw new \DomainException('Réserve insuffisante pour ce repli. Le fret d’hydrogène ne sert pas de carburant.');
        $quote['one_way_fuel']=min($quote['one_way_fuel'],$fleet->reservedFuel);
        return $quote+['ships'=>$ships,'target'=>$target,'arrival_at'=>$this->clock->now()->modify('+'.$quote['seconds'].' seconds'),
            'version'=>hash('sha256',json_encode([$fleet->dispatchToken,$target->id,$quote['one_way_fuel'],$quote['seconds'],$fleet->reservedFuel],JSON_THROW_ON_ERROR))];
    }

    private function start(Fleet $fleet,Planet $source,array $quote): void
    {
        $target=$quote['target'];$this->manager->lock($target);
        if($target->owner?->id!==$fleet->playerUniverse->id) throw new \DomainException('La colonie de repli ne vous appartient plus.');
        if($source->id===$target->id) {$this->install($fleet,$target);return;}
        $state=$fleet->navigation['station'];$state['leg_fuel']=$quote['one_way_fuel'];
        $fleet->navigation=$this->navigation->freeze($this->coords($source),$this->coords($target),$quote['ships'],
            $fleet->speedRatio,$this->catalog->rules()['fuel_ratio_policy'],$quote,$this->clock->now());
        $fleet->navigation['station']=$state;
        $fleet->navigation['station']['route_origin_id']=$source->id;
        $fleet->destination=$target;$fleet->destinationSystem=$target->solarSystem;$fleet->returnPlanet=$target;
        $fleet->status='returning';$fleet->departureAt=$this->clock->now();$fleet->travelSeconds=$quote['seconds'];$fleet->arrivalAt=$quote['arrival_at'];
        $this->navigation->beginLeg($fleet,$fleet->departureAt);$this->dispatch->schedule($fleet);
        $this->notifications->enqueue($fleet->playerUniverse,'Votre flotte de stationnement se replie vers '.$target->name.'.',journalKind:'arrival');
    }

    private function install(Fleet $fleet,Planet $planet): void
    {
        $this->hold->install($fleet,$planet);
        $this->notifications->enqueue($fleet->playerUniverse,'Votre flotte est désormais basée sur '.$planet->name.'. La réserve et le fret non déchargé restent à bord.',journalKind:'arrival');
    }

    private function coords(Planet $planet): array
    {
        $system=$planet->solarSystem;
        return [$system->id,$system->galaxy->id,$system->coordX,$system->coordY,$planet->position];
    }
}
