<?php
namespace App\Service\Fleet;
use App\Entity\{Fleet,FleetShip,Planet,PlayerUniverse,SolarSystem,PlayerDiscovery,OutboxMessage,PlanetResource,PlayerTechnology};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Colony\{ActionGuard,ResourceService};
use App\Service\Catalog\GameCatalog;
use App\Service\Boost\BoostGrantService;
use App\Service\Boost\BoostEventService;
use App\Service\Notification\NotificationService;
use Symfony\Component\Uid\Uuid;
use Psr\Clock\ClockInterface;
use App\Service\Player\VacationCalendar;
final class FleetService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ActionGuard $guard,private ResourceService $resources,private GameCatalog $catalog,private MovementCalculator $calculator,private NotificationService $notifications,private ClockInterface $clock,private \App\Service\Queue\QueueService $queues,private \App\Service\Combat\CombatEventService $combats,private EspionageService $espionage,private BoostGrantService $boostGrants,private BoostEventService $boostEvents,private \App\Service\Universe\PlanetNameGenerator $planetNames,private \App\Service\Combat\TechnologyEffectCalculator $technologyEffects,private TransportService $transports,private VacationCalendar $vacations,private \App\Service\Combat\OccupationService $occupations, private \App\Service\Admin\FleetMissionTraceService $traces,private FleetNavigation $navigation,private OrbitPolicy $orbitPolicy,private OrbitRequestState $orbitRequests,private FleetRecallPlanner $recallPlanner,private \App\Service\Colony\ColonizationKitPolicy $colonizationKits) {}
    private function coords(SolarSystem $s,?int $position): array { return [$s->id,$s->galaxy->id,$s->coordX,$s->coordY,$position]; }
    public function quote(PlayerUniverse $m,Planet $origin,string $mission,int $targetId,array $composition,float $ratio,array $cargo=[],float $orbitHours=0): array
    {
        if($origin->owner?->id!==$m->id || $origin->solarSystem->galaxy->universe->id!==$m->universe->id) throw new \DomainException('Planète inaccessible.');
        $this->vacations->assertCanSendFleet($m);
        if(!in_array($mission,['explore','colonize','transport','attack','conquer','defend','orbit','spy'],true)) throw new \DomainException('Mission indisponible.');
        $target=$this->repo->find($mission==='explore'?SolarSystem::class:Planet::class,$targetId);
        if(!$target) throw new \DomainException('Destination inconnue.');
        $system=$target instanceof Planet?$target->solarSystem:$target;
        if($system->galaxy->universe->id!==$m->universe->id) throw new \DomainException('Destination hors de cet univers.');
        $known=$this->repo->one(PlayerDiscovery::class,['playerUniverse'=>$m,'solarSystem'=>$system]);
        if($mission!=='explore' && !$known) throw new \DomainException('Explorez ce système avant de cibler une planète.');
        if($target instanceof Planet && $target->id===$origin->id) throw new \DomainException('Choisissez une autre planète.');
        if($target instanceof Planet && $target->owner!==null && $this->vacations->isActive($target->owner)) throw new \DomainException('Cet empire est protégé par le mode vacances.');
        if($mission==='spy' && (!$target instanceof Planet || $target->owner?->id===$m->id)) throw new \DomainException('L’espionnage cible une planète occupée par un autre joueur ou un monde PNJ connu.');
        if($mission==='transport' && !$this->transports->canReceive($m,$target)) throw new \DomainException('Cette planète n’accepte pas vos transports.');
        if($mission==='attack' && !$this->combats->canAttack($m,$target instanceof Planet?$target:null)) throw new \DomainException('Cette planète ne peut pas être attaquée.');
        if($mission==='conquer' && !$this->combats->canConquer($m,$target instanceof Planet?$target:null)) throw new \DomainException('La conquête n’est pas activée ou cette planète ne peut pas être prise.');
        if($mission==='defend' && !$this->combats->canDefend($m,$target instanceof Planet?$target:null)) throw new \DomainException('Cette planète ne peut pas être défendue depuis cet univers.');
        if($mission==='orbit' && !$this->orbitPolicy->canRequest($m,$target instanceof Planet?$target:null)) throw new \DomainException('L’orbite nécessite une planète alliée ou neutre appartenant à un joueur actif de cet univers.');
        if(!is_finite($orbitHours) || $orbitHours<0 || $orbitHours>168 || ($mission==='orbit' && ($orbitHours<1 || floor($orbitHours)!==$orbitHours)) || ($mission!=='orbit' && $orbitHours!=0)) throw new \DomainException('Durée d’orbite invalide. Choisissez entre 1 et 168 heures.');
        if($mission==='colonize' && ($target->owner!==null || $target->npcPlanet)) throw new \DomainException('Cette planète doit être conquise ou n’est pas vierge.');
        $config=$this->catalog->forUniverse($m->universe);$planetLimit=$this->planetLimit($m,$config);
        if($mission==='conquer' && count($this->repo->planets($m->universe,$m)) >= $planetLimit) throw new \DomainException('Limite de colonies atteinte; une planète conquise occupe un emplacement.');
        if($mission==='colonize' && count($this->repo->planets($m->universe,$m)) >= $planetLimit) throw new \DomainException('Limite de colonies atteinte.');
        $idle=$this->repo->one(Fleet::class,['homePlanet'=>$origin,'playerUniverse'=>$m,'status'=>'idle']);$stock=[];
        if($idle) foreach($this->repo->all(FleetShip::class,['fleet'=>$idle]) as $s) $stock[$s->slug][$s->tier]=$s->quantity;
        $ships=[];$valid=[];$adjusted=false;$remaining=max(0,(int)$config['limits']['max_fleet_size']);
        foreach($composition as $slug=>$ranks){if(!isset($config['catalog']['ships'][$slug]))throw new \DomainException('Vaisseau inconnu.');if(!is_array($ranks))$ranks=['basic'=>$ranks];foreach($ranks as $tier=>$amount){
            if(!in_array($tier,GameCatalog::TIERS,true))throw new \DomainException('Rang de vaisseau invalide.');
            $requested=filter_var($amount,FILTER_VALIDATE_INT);if($requested===false||$requested<0)throw new \DomainException('Quantité de vaisseaux invalide.');
            $n=min($requested,$stock[$slug][$tier]??0,$remaining);$remaining-=$n;$adjusted=$adjusted||$n!==$requested;if(!$n)continue;
            $ship=$this->catalog->getTierForUniverse($m->universe,'ships',(string)$slug,(string)$tier);$ship['quantity']=$n;$ship['tier']=$tier;$ships[]=$ship;$valid[$slug][$tier]=$n;
        }}
        $fleetSize=0;foreach($valid as $ranks)foreach($ranks as $amount)$fleetSize+=$amount;if($fleetSize>$config['limits']['max_fleet_size'])throw new \DomainException('Flotte trop grande.');
        $hasSlug=static fn(string $slug):bool=>array_sum($valid[$slug]??[])>0;
        if($mission==='attack' && $target instanceof Planet && $target->owner?->id===$m->id) throw new \DomainException('Vous ne pouvez pas attaquer votre propre colonie.');
        if($mission==='explore' && !$hasSlug('probe')) throw new \DomainException('Une sonde exploratrice est nécessaire.');
        if($mission==='spy' && (empty($valid['probe']) || count(array_filter($valid,static fn($ranks)=>array_sum($ranks)>0))!==1)) throw new \DomainException('Une mission d’espionnage exige une ou plusieurs sondes, sans escorte.');
        if(in_array($mission,['colonize','conquer'],true) && !$hasSlug('colony_ship')) throw new \DomainException('Un vaisseau colonie survivant est nécessaire à cette mission.');
        $reinforcement=$mission==='defend' && $target instanceof Planet && $this->occupations->activeFor($target)!==null;
        $windowHours=$reinforcement?$this->occupations->reinforcementHours($target):($mission==='conquer' && $this->occupations->isEnabled()?24:(in_array($mission,['attack','conquer','defend'],true)?((int)($this->catalog->rules()['combat_window_seconds']??600)/3600):($mission==='orbit'?$orbitHours:0)));
        $techLevels=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$m]);
        $ships=$this->technologyEffects->applyToShips($ships,$techLevels,$m->universe);
        $quote=$this->calculator->quote($this->coords($origin->solarSystem,$origin->position),$this->coords($system,$target instanceof Planet?$target->position:null),$ships,$ratio,$config['multipliers']['travel_time'],$this->catalog->rules()['fuel_ratio_policy'],$windowHours);
        $requestedCargo=[];
        foreach($cargo as $resource=>$amount) {
            if(!array_key_exists($resource,$this->catalog->rules()['resources'])) throw new \DomainException('Ressource inconnue.');
            $n=filter_var($amount,FILTER_VALIDATE_INT);
            if($n===false || $n<0) throw new \DomainException('Fret invalide.');
            $requestedCargo[$resource]=$n;
        }
        if($mission!=='transport' && array_sum($requestedCargo)>0) throw new \DomainException('Le fret est réservé aux missions de transport.');
        if($quote['total_fuel']>$quote['capacity']+1e-6) throw new \DomainException('Capacité insuffisante pour le carburant aller-retour. Réduisez la vitesse ou ajoutez un transporteur.');
        $snapshot=$this->resources->snapshot($origin);$available=[];$freight=[];$space=$quote['freight_capacity'];
        foreach($this->catalog->rules()['resources'] as $resource=>$label) {
            $available[$resource]=(int)floor(min(100000000,max(0,($snapshot[$resource]['stock']??0)-($resource==='hydrogen'?$quote['total_fuel']:0))));
        }
        foreach($requestedCargo as $resource=>$requested) {
            $n=(int)min($requested,$available[$resource],max(0,floor($space)));
            $adjusted=$adjusted||$n!==$requested;
            if($n) $freight[$resource]=$n;
            $space-=$n;
        }
        $maximum=[];$loaded=array_sum($freight);
        foreach($available as $resource=>$amount) $maximum[$resource]=(int)min($amount,max(0,floor($quote['freight_capacity']-$loaded+($freight[$resource]??0))));
        return $quote+['movement_ships'=>$ships,'target'=>$target,'system'=>$system,'composition'=>$valid,'freight'=>$freight,'idle'=>$idle,'cargo_available'=>$available,'cargo_maximum'=>$maximum,'ship_available'=>$stock,'adjusted'=>$adjusted];
    }

    private function planetLimit(PlayerUniverse $membership,array $config): int
    {
        $definition=$config['catalog']['technologies']['colonial_expansion']??null;$effect=$definition['effect']??null;
        if(!is_array($effect)||($effect['type']??null)!=='planet_limit')return max(0,(int)$config['limits']['max_planets']-$this->occupations->reservedSlots($membership));
        $ranks=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership])['colonial_expansion']??[];
        return max(0,GameCatalog::colonyLimit($ranks,$effect,(int)$config['limits']['max_planets'])-$this->occupations->reservedSlots($membership));
    }

    public function preview(PlayerUniverse $m,Planet $origin,string $mission,int $targetId,array $composition,float $ratio,array $cargo=[],float $orbitHours=0): array
    {
        return $this->manager->transaction(function()use($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$orbitHours){
            $this->guard->lock($m,$origin);$this->queues->synchronize($m);
            $q=$this->quote($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$orbitHours);
            return ['arrival_at'=>$this->clock->now()->modify('+'.$q['seconds'].' seconds')->format(DATE_ATOM)]+array_intersect_key($q,array_flip(['distance','normal_distance','hyper_distance','seconds','one_way_fuel','orbit_fuel','total_fuel','fuel_capacity','cargo_capacity','freight_capacity','composition','freight','cargo_available','cargo_maximum','ship_available','adjusted']));
        });
    }
    public function send(PlayerUniverse $m,Planet $origin,string $mission,int $targetId,array $composition,float $ratio,array $cargo,string $key,float $orbitHours=0): bool
    {
        return $this->manager->transaction(function()use($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$key,$orbitHours){
            $this->guard->lock($m,$origin);$this->queues->synchronize($m);if(!$this->guard->receipt($m,$key)) return false;
            $q=$this->quote($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$orbitHours);
            if($q['target'] instanceof Planet) $this->manager->lock($q['target']);
            if($mission==='transport') { if(!$q['target']->owner) throw new \DomainException('Cette planète n’accepte plus vos transports.');$this->manager->lock($q['target']->owner->player);if(!$this->transports->canReceive($m,$q['target'])) throw new \DomainException('Cette planète n’accepte plus vos transports.'); }
            $costs=$q['freight'];$costs['hydrogen']=($costs['hydrogen']??0)+$q['total_fuel'];$this->resources->consume($origin,$costs);
            $f=new Fleet();$f->playerUniverse=$m;$f->homePlanet=$origin;$f->destination=$q['target'] instanceof Planet?$q['target']:null;$f->destinationSystem=$q['system'];$f->status='traveling';$f->mission=$mission;$f->speedRatio=$ratio;$f->cargo=$costs;$f->reservedFuel=$q['total_fuel'];$f->orbitFuel=$q['orbit_fuel'];$f->orbitDurationSeconds=$mission==='defend' && $q['target'] instanceof Planet && $this->occupations->activeFor($q['target'])!==null?(int)round($this->occupations->reinforcementHours($q['target'])*3600):($mission==='conquer' && $this->occupations->isEnabled()?\App\Service\Combat\OccupationPolicy::DURATION_SECONDS:(int)round($orbitHours*3600));$f->travelSeconds=$q['seconds'];$f->departureAt=$this->clock->now();$f->arrivalAt=$this->clock->now()->modify('+'.$f->travelSeconds.' seconds');$f->navigation=$this->navigation->freeze($this->coords($origin->solarSystem,$origin->position),$this->coords($q['system'],$q['target'] instanceof Planet?$q['target']->position:null),$q['movement_ships'],$ratio,$this->catalog->rules()['fuel_ratio_policy'],$q,$f->departureAt);$this->manager->save($f);$this->manager->flush();
            foreach($q['composition'] as $slug=>$tiers)foreach($tiers as $tier=>$quantity) {
                $stock=$this->repo->one(FleetShip::class,['fleet'=>$q['idle'],'slug'=>$slug,'tier'=>$tier]);$stock->quantity-=$quantity;
                $ship=new FleetShip();$ship->fleet=$f;$ship->slug=$slug;$ship->tier=$tier;$ship->quantity=$quantity;$this->manager->save($ship);
            }
            if($mission==='orbit') {
                if(!$this->orbitPolicy->canRequest($m,$f->destination)) throw new \DomainException('Cette planète n’accepte plus les demandes d’orbite.');
                $automatic=$this->orbitPolicy->automatic($m,$f->destination);
                $this->orbitRequests->open($f,$automatic);
                $this->notifications->enqueue($f->destination->owner,
                    $automatic?'Une flotte alliée de '.$m->getDisplayName().' se dirige vers '.$f->destination->name.'. Son orbite est acceptée automatiquement.'
                        :'Demande d’orbite de '.$m->getDisplayName().' vers '.$f->destination->name.'. Répondez depuis Flottes avant son arrivée.',
                    journalKind:'combat');
            }
            if($mission==='transport') $this->transports->recordDeparture($f,$q['freight']);
            $this->traces->recordDeparture($f, $q['composition'], $q['freight']);
            $this->occupations->recordReinforcementDeparture($f);
            $this->schedule($f);
            return $q['adjusted'];
        });
    }
    public function reschedulePlayerBoost(Fleet $fleet, \DateTimeImmutable $arrivalAt): void
    {
        $this->vacations->assertCanSendFleet($fleet->playerUniverse);
        if (!in_array($fleet->status, ['traveling', 'returning', 'defending', 'orbiting'], true) || !$fleet->arrivalAt) {
            throw new \DomainException('Mission chronométrée active introuvable.');
        }
        $this->navigation->reschedule($fleet,$this->clock->now());
        $fleet->arrivalAt = $arrivalAt;
        $this->schedule($fleet);
    }

    private function schedule(Fleet $f): void
    {
        $this->orbitRequests->reschedule($f);
        $f->dispatchToken=Uuid::v4()->toRfc4122();$o=new OutboxMessage();$o->kind='fleet';$o->targetId=$f->id;$o->token=$f->dispatchToken;$o->dueAt=$f->arrivalAt;$this->manager->save($o);
    }

    /** Appelé sous les verrous de participation, univers, planète et flotte. */
    public function rescheduleForAdministrationLocked(Fleet $fleet, \DateTimeImmutable $arrival): void
    {
        if (!in_array($fleet->status, ['traveling', 'returning', 'orbiting'], true) || !$fleet->arrivalAt) throw new \DomainException('Cette mission ne permet pas un changement d’échéance.');
        $this->navigation->reschedule($fleet,$this->clock->now());
        $fleet->arrivalAt = $arrival;
        $this->schedule($fleet);
    }

    /** La participation, l’univers, les planètes et la flotte sont déjà verrouillés. */
    public function startPlayerRecallLocked(Fleet $fleet,Planet $returnPlanet,array $quote): void
    {
        $fleet->cargo['hydrogen']=max(0.0,($fleet->cargo['hydrogen']??0)-$quote['spent_now']);
        $fleet->reservedFuel=$quote['return_fuel'];$fleet->orbitFuel=0;
        $fleet->returnPlanet=$returnPlanet;$fleet->navigation=$quote['navigation'];
        $this->orbitRequests->close($fleet,'withdrawn',$quote['at']);
        $fleet->navigation['recall']=['at'=>$quote['at']->format(DATE_ATOM),'progress'=>$quote['progress'],
            'position'=>$quote['position'],'return_planet_id'=>$returnPlanet->id,'origin_planet_id'=>$fleet->homePlanet->id,
            'spent_now'=>$quote['spent_now'],'return_fuel'=>$quote['return_fuel'],'seconds'=>$quote['seconds']];
        $fleet->travelSeconds=$quote['seconds'];$fleet->status='returning';
        $fleet->departureAt=$quote['at'];$fleet->arrivalAt=$fleet->departureAt->modify('+'.$fleet->travelSeconds.' seconds');
        $this->navigation->beginLeg($fleet,$fleet->departureAt);
        $this->transports->recordConversionRecall($fleet,'player_recall');
        $this->schedule($fleet);
    }

    /** Rappel sans livraison ; le carburant aller est consommé comme au traitement normal. */
    public function recallForAdministrationLocked(Fleet $fleet): void
    {
        if (!in_array($fleet->status, ['traveling', 'orbiting', 'orbit_pending'], true)) throw new \DomainException('Cette mission ne peut plus être rappelée dans son état courant.');
        if ($fleet->status === 'traveling') {
            $oneWay = max(0, ($fleet->reservedFuel - $fleet->orbitFuel) / 2);
            $fleet->cargo['hydrogen'] = max(0, ($fleet->cargo['hydrogen'] ?? 0) - $oneWay);
            $fleet->reservedFuel -= $oneWay;
        } elseif ($fleet->status === 'orbiting' && $fleet->orbitFuel > 0 && $fleet->departureAt && $fleet->arrivalAt) {
            $duration = max(1, $fleet->arrivalAt->getTimestamp() - $fleet->departureAt->getTimestamp());
            $fraction = min(1, max(0, ($this->clock->now()->getTimestamp() - $fleet->departureAt->getTimestamp()) / $duration));
            $spent = $fleet->orbitFuel * $fraction;
            $fleet->cargo['hydrogen'] = max(0, ($fleet->cargo['hydrogen'] ?? 0) - $spent);
            $fleet->reservedFuel = max(0, $fleet->reservedFuel - $spent);
            $fleet->orbitFuel -= $spent;
        }
        $this->releaseOrbitFuel($fleet);
        $this->transports->recordConversionRecall($fleet, 'admin_recall');
        $this->startReturn($fleet);
    }
    /** Retour d’une mission échue, sous les mêmes verrous et contrôles que le rappel administratif. */
    public function forceReturnForAdministrationLocked(Fleet $fleet): void
    {
        if (!$fleet->arrivalAt || $fleet->arrivalAt > $this->clock->now() || !in_array($fleet->status, ['traveling', 'returning', 'orbiting'], true)) {
            throw new \DomainException('Cette mission ne permet pas un retour forcé.');
        }
        if ($fleet->status === 'returning') {
            // Le carburant aller a déjà été consommé : reprogrammer sans le prélever à nouveau.
            $this->startReturn($fleet);
            return;
        }
        $this->recallForAdministrationLocked($fleet);
    }

    public function arrive(int $id,string $token): void
    {
        $this->manager->transaction(function()use($id,$token){
            $f=$this->repo->find(Fleet::class,$id);if(!$f) return;
            $m=$f->playerUniverse;$this->guard->lockMembershipForProcessing($m);$this->manager->lock($f);
            if(!in_array($f->status,['traveling','returning','defending','orbiting'],true) || !hash_equals($f->dispatchToken,$token) || !$f->arrivalAt || $f->arrivalAt>$this->clock->now()) return;
            $processingPlanet=$f->status==='returning'?($f->returnPlanet??$f->homePlanet):$f->homePlanet;
            $this->guard->lockForCompletion($m,$processingPlanet);$this->queues->synchronize($m);
            if(in_array($f->status,['defending','orbiting'],true)) {
                $f->cargo['hydrogen']=max(0,($f->cargo['hydrogen']??0)-$f->orbitFuel);$f->reservedFuel=max(0,$f->reservedFuel-$f->orbitFuel);$f->orbitFuel=0;
                $f->status='returning';$f->departureAt=$f->arrivalAt;$f->arrivalAt=$this->clock->now()->modify('+'.$f->travelSeconds.' seconds');$this->navigation->beginLeg($f,$f->departureAt);$this->schedule($f);return;
            }
            if($f->status==='returning') {
                $f->cargo['hydrogen']=max(0,($f->cargo['hydrogen']??0)-$f->reservedFuel);
                $deposit=$this->resources->deposit($processingPlanet, $f->cargo);
                if($f->mission==='transport') $this->transports->recordReturn($f,$deposit);
                $idle=$this->repo->one(Fleet::class,['homePlanet'=>$processingPlanet,'playerUniverse'=>$m,'status'=>'idle']);
                if(!$idle) { $idle=new Fleet();$idle->homePlanet=$processingPlanet;$idle->playerUniverse=$m;$this->manager->save($idle);$this->manager->flush(); }
                foreach($this->repo->all(FleetShip::class,['fleet'=>$f]) as $s) {
                    $stock=$this->repo->one(FleetShip::class,['fleet'=>$idle,'slug'=>$s->slug,'tier'=>$s->tier])??new FleetShip();$stock->fleet=$idle;$stock->slug=$s->slug;$stock->tier=$s->tier;$stock->quantity+=$s->quantity;$this->manager->save($stock);$this->manager->remove($s);
                }
                $f->cargo=[];$f->reservedFuel=0;$f->status='completed';$f->arrivalAt=null;$f->dispatchToken=Uuid::v4()->toRfc4122();
                $this->notifications->enqueue($m,'Votre flotte est rentrée sur '.$processingPlanet->name.'.',journalKind:'arrival');return;
            }
            $oneWay=max(0,($f->reservedFuel-$f->orbitFuel)/2);$f->cargo['hydrogen']=max(0,($f->cargo['hydrogen']??0)-$oneWay);$f->reservedFuel-=$oneWay;
            if($m->isForgotten()) {
                $this->releaseOrbitFuel($f);$this->transports->recordConversionRecall($f);$this->startReturn($f,$f->arrivalAt);return;
            }
            if($f->mission!=='transport' && ($this->vacations->isActive($m) || ($f->destination?->owner!==null && $this->vacations->isActive($f->destination->owner)))) {
                $this->releaseOrbitFuel($f);$this->startReturn($f,$f->arrivalAt);
                $this->notifications->enqueue($m,'Mission annulée : un empire concerné est en vacances. Votre flotte rentre.',journalKind:'arrival');
                return;
            }
            if(in_array($f->mission,['attack','conquer'],true)) {
                if($this->combats->joinAttack($f)) return;
                $this->releaseOrbitFuel($f);$this->notifications->enqueue($m,'La fenêtre de combat était déjà close. Votre flotte repart.',journalKind:'arrival');
            } elseif($f->mission==='defend') {
                if($this->combats->arriveDefender($f)) return;
                $this->releaseOrbitFuel($f);$this->notifications->enqueue($m,'La fenêtre de combat était close. Votre flotte repart.',journalKind:'arrival');
            } elseif($f->mission==='orbit') {
                if(($request=$this->orbitRequests->read($f))!==null) {
                    $this->manager->lock($f->destination);
                    if(!$this->orbitPolicy->consentValid($m,$f->destination,$request)) {
                        $reason=$this->orbitPolicy->canRequest($m,$f->destination)
                            && ($request['recipient_id']??null)===$f->destination->owner->id
                            && ($request['status']??null)==='pending'?'expired':'invalid';
                        $this->orbitRequests->close($f,$reason,$this->clock->now());
                        $this->releaseOrbitFuel($f);$this->startReturn($f,$f->arrivalAt);
                        $this->notifications->enqueue($m,'Orbite impossible sur '.$f->destination->name.' : aucun accord valide à l’arrivée. Votre flotte rentre.',journalKind:'arrival');
                        return;
                    }
                    $this->orbitRequests->close($f,'arrived',$this->clock->now());
                    if(!$this->combats->joinOpenDefense($f)) $this->startOrbit($f);
                    $this->notifications->enqueue($m,'Votre flotte rejoint l’orbite autorisée de '.$f->destination->name.'.',journalKind:'arrival');
                    $this->notifications->enqueue($f->destination->owner,'Une flotte autorisée de '.$m->getDisplayName().' est arrivée sur '.$f->destination->name.'.',journalKind:'arrival');
                    return;
                }
                if(!$this->combats->canOrbit($m,$f->destination)) { $this->releaseOrbitFuel($f);$this->startReturn($f,$f->arrivalAt);return; }
                if($this->combats->orbitAutoAccepted($m,$f->destination)) {
                    if($this->combats->joinOpenDefense($f)) return;
                    $this->startOrbit($f);
                    $this->notifications->enqueue($m,'Votre flotte est acceptée automatiquement en orbite sur '.$f->destination->name.'.',journalKind:'arrival');
                    $this->notifications->enqueue($f->destination->owner,'Flotte alliée en orbite sur '.$f->destination->name.'.',journalKind:'arrival');
                    return;
                }
                $f->status='orbit_pending';$f->arrivalAt=null;$f->dispatchToken=Uuid::v4()->toRfc4122();$this->combats->notifyOrbitRequest($f);
                return;
            } elseif($f->mission==='spy') {
                $probes=$this->repo->all(FleetShip::class,['fleet'=>$f,'slug'=>'probe']);
                $probeCount=array_sum(array_map(static fn($probe)=>$probe->quantity,$probes));
                if($probeCount<1) throw new \LogicException('Sondes d’espionnage absentes à l’arrivée.');
                $report = $this->espionage->resolve($f,$probeCount);
                foreach($probes as $probe)$probe->quantity=0;
                if ($report->result === 'success' && $report->intelCategories > 0) $this->boostGrants->awardRiskMission($m,$f);
            } elseif($f->mission==='explore') {
                $d=$this->repo->one(PlayerDiscovery::class,['playerUniverse'=>$m,'solarSystem'=>$f->destinationSystem]);
                $newDiscovery = !$d;
                if(!$d) { $d=new PlayerDiscovery();$d->playerUniverse=$m;$d->solarSystem=$f->destinationSystem;$this->manager->save($d); }
                $this->consumeShip($f,'probe');$this->notifications->enqueue($m,'Système découvert : '.$f->destinationSystem->name.'.',journalKind:'arrival');
                if ($newDiscovery) { $this->boostGrants->awardRiskMission($m,$f); $this->boostEvents->recordExplorationDiscovery($m,$f->destinationSystem); }
            } elseif($f->mission==='colonize') {
                $target=$f->destination;$this->manager->lock($target);
                $limit=$this->planetLimit($m,$this->catalog->forUniverse($m->universe));
                if(!$target->owner && !$target->npcPlanet && count($this->repo->planets($m->universe,$m))<$limit) { $target->name=$this->planetNames->generate($m);$target->acquireBy($m,$this->clock->now());$colonyTier=$this->consumeShip($f,'colony_ship');$this->resources->initialize($target,true,stockMultiplier:GameCatalog::tierFactor($colonyTier)*$this->colonizationKits->multiplier($m,$target));$this->notifications->enqueue($m,'Nouvelle colonie : '.$target->name.'.',journalKind:'arrival'); }
                else $this->notifications->enqueue($m,'Colonisation impossible à l’arrivée. Votre flotte revient.',journalKind:'arrival');
            } elseif($f->mission==='transport') {
                $this->transports->deliver($f);
            }
            $survivors=array_sum(array_map(fn($s)=>$s->quantity,$this->repo->all(FleetShip::class,['fleet'=>$f])));
            if($survivors===0) {
                // Consumed probes/colony ships and their unused fuel do not create an empty return fleet.
                $f->status='completed';$f->cargo=[];$f->reservedFuel=0;$f->arrivalAt=null;$f->dispatchToken=Uuid::v4()->toRfc4122();return;
            }
            $f->status='returning';$f->departureAt=$f->arrivalAt;$f->arrivalAt=$f->departureAt->modify('+'.$f->travelSeconds.' seconds');$this->navigation->beginLeg($f,$f->departureAt);$this->schedule($f);
        });
    }
    public function decideOrbit(PlayerUniverse $host, int $fleetId, bool $accept, string $requestKey=''): void
    {
        $this->manager->transaction(function () use ($host, $fleetId, $accept, $requestKey): void {
            $this->guard->lockMembership($host);
            $this->vacations->assertCanSendFleet($host);
            $fleet = $this->repo->find(Fleet::class, $fleetId);
            if(!$fleet || !$fleet->destination || $fleet->destination->owner?->id!==$host->id
                || $fleet->playerUniverse->universe->id!==$host->universe->id
                || $fleet->destination->solarSystem->galaxy->universe->id!==$host->universe->id) {
                throw new \DomainException('Cette demande d’orbite n’est plus disponible.');
            }
            if($this->orbitRequests->read($fleet)!==null) {
                $this->manager->lock($fleet->destination);$this->manager->lock($fleet->homePlanet);$this->manager->lock($fleet);
                $this->orbitRequests->assertDecision($fleet,$host->id,$requestKey,$this->clock->now());
                if($accept && !$this->orbitPolicy->canRequest($fleet->playerUniverse,$fleet->destination)) throw new \DomainException('Cette flotte ne peut plus être acceptée en orbite.');
                $quote=$accept?null:$this->recallPlanner->plan($fleet->playerUniverse,$fleet,null,$this->clock->now());
                $this->orbitRequests->decide($fleet,$host->id,$requestKey,$accept,$this->clock->now());
                if(!$accept) {
                    // Le plan a été calculé avant la réponse ; préserver la décision dans son état privé.
                    $quote['navigation']=$fleet->navigation;
                    $this->startPlayerRecallLocked($fleet,$quote['return_planet'],$quote);
                }
                $this->notifications->enqueue($fleet->playerUniverse,$accept
                    ?'Votre demande d’orbite sur '.$fleet->destination->name.' est acceptée. La flotte poursuit son trajet.'
                    :'Votre demande d’orbite sur '.$fleet->destination->name.' est refusée. La flotte rentre.',journalKind:'combat');
                return;
            }
            if (!$fleet || $fleet->status !== 'orbit_pending' || !$fleet->destination || $fleet->destination->owner?->id !== $host->id) {
                throw new \DomainException('Cette demande d’orbite n’est plus disponible.');
            }
            $this->manager->lock($fleet->destination);
            $this->manager->lock($fleet);
            if (!$this->combats->canOrbit($fleet->playerUniverse, $fleet->destination)) {
                throw new \DomainException('Cette flotte n’est plus autorisée à demander une orbite chez vous.');
            }
            if (!$accept) {
                $this->releaseOrbitFuel($fleet);
                $this->startReturn($fleet);
                $this->notifications->enqueue($fleet->playerUniverse, 'Votre demande d’orbite sur ' . $fleet->destination->name . ' a été refusée; la flotte rentre.');
                return;
            }
            if ($this->combats->joinOpenDefense($fleet)) {
                $this->notifications->enqueue($fleet->playerUniverse, 'Votre flotte a été acceptée et rejoint la défense sur ' . $fleet->destination->name . '.');
                return;
            }
            $this->startOrbit($fleet);
            $this->notifications->enqueue($fleet->playerUniverse, 'Votre flotte a été acceptée en orbite sur ' . $fleet->destination->name . '.');
        });
    }

    /** Appelé avant l'enregistrement des vacances, avec la participation et l'univers verrouillés. */
    public function returnPendingOrbitsForVacationLocked(PlayerUniverse $host): void
    {
        foreach($this->repo->pendingOrbitsFor($host) as $fleet) {
            $this->manager->lock($fleet->destination);$this->manager->lock($fleet);
            if($fleet->status!=='orbit_pending' || $fleet->destination->owner?->id!==$host->id) continue;
            $this->releaseOrbitFuel($fleet);$this->startReturn($fleet);
            $this->notifications->enqueue($fleet->playerUniverse,'Demande d’orbite refusée : l’empire d’accueil passe en vacances. Votre flotte rentre.');
        }
    }

    private function startOrbit(Fleet $fleet): void
    {
        $fleet->status = 'orbiting';
        $fleet->departureAt = $this->clock->now();
        $fleet->arrivalAt = $this->clock->now()->modify('+' . max(1, $fleet->orbitDurationSeconds) . ' seconds');
        $this->navigation->beginLeg($fleet,$fleet->departureAt);
        $this->schedule($fleet);
    }

    /** L'empire et l'univers sont verrouillés ; aucun combat concerné ne doit être ouvert. */
    public function returnFleetsForEmpireConversionLocked(PlayerUniverse $membership): array
    {
        $returned=[];
        foreach($this->repo->fleetsForEmpireConversion($membership) as $fleet) {
            $this->manager->lock($fleet);
            $own=$fleet->playerUniverse->id===$membership->id;
            if(!$own && $fleet->destination?->owner?->id!==$membership->id) continue;
            if($fleet->status==='traveling') {
                $oneWay=max(0,($fleet->reservedFuel-$fleet->orbitFuel)/2);
                $fleet->cargo['hydrogen']=max(0,($fleet->cargo['hydrogen']??0)-$oneWay);$fleet->reservedFuel-=$oneWay;
            } elseif(in_array($fleet->status,['orbiting','occupying'],true) && $fleet->orbitFuel>0 && $fleet->departureAt!==null && $fleet->arrivalAt!==null) {
                $duration=max(1,$fleet->status==='occupying' && $fleet->orbitDurationSeconds>0?$fleet->orbitDurationSeconds:$fleet->arrivalAt->getTimestamp()-$fleet->departureAt->getTimestamp());
                $fraction=min(1,max(0,($this->clock->now()->getTimestamp()-$fleet->departureAt->getTimestamp())/$duration));
                $spent=$fleet->orbitFuel*$fraction;
                $fleet->cargo['hydrogen']=max(0,($fleet->cargo['hydrogen']??0)-$spent);$fleet->reservedFuel=max(0,$fleet->reservedFuel-$spent);
                $fleet->orbitFuel-=$spent;
            }
            $this->releaseOrbitFuel($fleet);
            $this->transports->recordConversionRecall($fleet);
            $this->startReturn($fleet);
            $returned[]=$fleet->id;
        }
        return $returned;
    }

    private function startReturn(Fleet $fleet, ?\DateTimeImmutable $at = null): void
    {
        $this->orbitRequests->close($fleet,'invalid',$this->clock->now());
        $fleet->status = 'returning';
        $fleet->departureAt = $at ?? $this->clock->now();
        $fleet->arrivalAt = $fleet->departureAt->modify('+' . $fleet->travelSeconds . ' seconds');
        $this->navigation->beginLeg($fleet,$fleet->departureAt);
        $this->schedule($fleet);
    }

    /** Fin d'occupation sous verrou : retour normal des survivants, sans réutiliser les anciens messages. */
    public function returnOccupationFleetsLocked(Planet $planet,PlayerUniverse $membership,?string $claimToken=null): array
    {
        $returned=[];
        foreach($this->repo->all(Fleet::class,['destination'=>$planet,'status'=>'occupying']) as $fleet) {
            $deployment=$claimToken===null?null:$this->repo->one(\App\Entity\OccupationDeployment::class,['fleet'=>$fleet,'token'=>$claimToken]);
            if($fleet->playerUniverse->id!==$membership->id && $deployment===null) continue;
            $this->manager->lock($fleet);
            if($fleet->status!=='occupying') continue;
            if($fleet->orbitFuel>0 && $fleet->departureAt!==null && $fleet->arrivalAt!==null) {
                $duration=max(1,$fleet->status==='occupying' && $fleet->orbitDurationSeconds>0?$fleet->orbitDurationSeconds:$fleet->arrivalAt->getTimestamp()-$fleet->departureAt->getTimestamp());
                $spent=$fleet->orbitFuel*min(1,max(0,($this->clock->now()->getTimestamp()-$fleet->departureAt->getTimestamp())/$duration));
                $fleet->cargo['hydrogen']=max(0,($fleet->cargo['hydrogen']??0)-$spent);$fleet->reservedFuel=max(0,$fleet->reservedFuel-$spent);$fleet->orbitFuel-=$spent;
            }
            $this->releaseOrbitFuel($fleet);
            $quantity=array_sum(array_map(static fn(FleetShip $ship): int=>$ship->quantity,$this->repo->all(FleetShip::class,['fleet'=>$fleet])));
            if($quantity>0) $this->startReturn($fleet);
            else { $fleet->status='completed';$fleet->arrivalAt=null;$fleet->cargo=[];$fleet->reservedFuel=0;$fleet->dispatchToken=Uuid::v4()->toRfc4122(); }
            $returned[]=$fleet->id;
        }
        return $returned;
    }

    private function releaseOrbitFuel(Fleet $f): void
    {
        $f->reservedFuel=max(0,$f->reservedFuel-$f->orbitFuel);$f->orbitFuel=0;
    }
    private function consumeShip(Fleet $f,string $slug): string
    {
        $ships=$this->repo->all(FleetShip::class,['fleet'=>$f,'slug'=>$slug]);usort($ships,static fn($a,$b)=>array_search($a->tier,GameCatalog::TIERS,true)<=>array_search($b->tier,GameCatalog::TIERS,true));foreach($ships as $ship)if($ship->quantity>0){--$ship->quantity;return $ship->tier;}throw new \LogicException('Vaisseau de mission absent.');
    }
}

