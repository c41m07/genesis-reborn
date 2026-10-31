<?php
namespace App\Service\Fleet;
use App\Entity\{Fleet,FleetShip,Planet,PlayerUniverse,SolarSystem,PlayerDiscovery,OutboxMessage,PlanetResource};
use App\Repository\GameRepository;
use App\Manager\GameManager;
use App\Service\Colony\{ActionGuard,ResourceService};
use App\Service\Catalog\GameCatalog;
use App\Service\Notification\NotificationService;
use Symfony\Component\Uid\Uuid;
use Psr\Clock\ClockInterface;
final class FleetService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ActionGuard $guard,private ResourceService $resources,private GameCatalog $catalog,private MovementCalculator $calculator,private NotificationService $notifications,private ClockInterface $clock,private \App\Service\Queue\QueueService $queues,private \App\Service\Combat\CombatEventService $combats) {}
    private function coords(SolarSystem $s,?int $position): array { return [$s->id,$s->galaxy->id,$s->coordX,$s->coordY,$position]; }
    public function quote(PlayerUniverse $m,Planet $origin,string $mission,int $targetId,array $composition,float $ratio,array $cargo=[],float $orbitHours=0): array
    {
        if($origin->owner?->id!==$m->id || $origin->solarSystem->galaxy->universe->id!==$m->universe->id) throw new \DomainException('Planète inaccessible.');
        if(!in_array($mission,['explore','colonize','transport','attack','conquer','defend','orbit'],true)) throw new \DomainException('Mission indisponible.');
        $target=$this->repo->find($mission==='explore'?SolarSystem::class:Planet::class,$targetId);
        if(!$target) throw new \DomainException('Destination inconnue.');
        $system=$target instanceof Planet?$target->solarSystem:$target;
        if($system->galaxy->universe->id!==$m->universe->id) throw new \DomainException('Destination hors de cet univers.');
        $known=$this->repo->one(PlayerDiscovery::class,['playerUniverse'=>$m,'solarSystem'=>$system]);
        if($mission!=='explore' && !$known) throw new \DomainException('Explorez ce système avant de cibler une planète.');
        if($target instanceof Planet && $target->id===$origin->id) throw new \DomainException('Choisissez une autre planète.');
        if($mission==='transport' && $target->owner?->id!==$m->id) throw new \DomainException('Le transport est limité à vos colonies.');
        if($mission==='attack' && !$this->combats->canAttack($m,$target instanceof Planet?$target:null)) throw new \DomainException('Cette planète ne peut pas être attaquée.');
        if($mission==='conquer' && !$this->combats->canConquer($m,$target instanceof Planet?$target:null)) throw new \DomainException('La conquête n’est pas activée ou cette planète ne peut pas être prise.');
        if($mission==='defend' && !$this->combats->canDefend($m,$target instanceof Planet?$target:null)) throw new \DomainException('Cette planète ne peut pas être défendue depuis cet univers.');
        if($mission==='orbit' && !$this->combats->canOrbit($m,$target instanceof Planet?$target:null)) throw new \DomainException('Une orbite est possible uniquement chez un allié de cet univers.');
        if(!is_finite($orbitHours) || $orbitHours<0 || $orbitHours>168 || ($mission==='orbit' && ($orbitHours<1 || floor($orbitHours)!==$orbitHours)) || ($mission!=='orbit' && $orbitHours!=0)) throw new \DomainException('Durée d’orbite invalide. Choisissez entre 1 et 168 heures.');
        if($mission==='colonize' && ($target->owner!==null || $target->npcPlanet)) throw new \DomainException('Cette planète doit être conquise ou n’est pas vierge.');
        $config=$this->catalog->get('universes',$m->universe->slug);
        if($mission==='conquer' && count($this->repo->planets($m->universe,$m)) >= $config['limits']['max_planets']) throw new \DomainException('Limite de colonies atteinte; une planète conquise occupe un emplacement.');
        if($mission==='colonize' && count($this->repo->planets($m->universe,$m)) >= $config['limits']['max_planets']) throw new \DomainException('Limite de colonies atteinte.');
        $idle=$this->repo->one(Fleet::class,['homePlanet'=>$origin,'playerUniverse'=>$m,'status'=>'idle']);$stock=[];
        if($idle) foreach($this->repo->all(FleetShip::class,['fleet'=>$idle]) as $s) $stock[$s->slug]=$s->quantity;
        $ships=[];$valid=[];
        foreach($composition as $slug=>$n) {
            $n=filter_var($n,FILTER_VALIDATE_INT);
            if($n===false || $n<0 || $n>100) throw new \DomainException('Quantité de vaisseaux invalide.');
            if(!$n) continue;
            if($n>($stock[$slug]??0)) throw new \DomainException('Vaisseaux indisponibles.');
            $ships[]=$this->catalog->get('ships',$slug)+['quantity'=>$n];$valid[$slug]=$n;
        }
        if(array_sum($valid)>$config['limits']['max_fleet_size']) throw new \DomainException('Flotte trop grande.');
        if($mission==='attack' && $target instanceof Planet && $target->owner?->id===$m->id) throw new \DomainException('Vous ne pouvez pas attaquer votre propre colonie.');
        if($mission==='explore' && empty($valid['probe'])) throw new \DomainException('Une sonde exploratrice est nécessaire.');
        if(in_array($mission,['colonize','conquer'],true) && empty($valid['colony_ship'])) throw new \DomainException('Un vaisseau colonie survivant est nécessaire à cette mission.');
        $windowHours=in_array($mission,['attack','conquer','defend'],true)?((int)($this->catalog->rules()['combat_window_seconds']??600)/3600):($mission==='orbit'?$orbitHours:0);
        $quote=$this->calculator->quote($this->coords($origin->solarSystem,$origin->position),$this->coords($system,$target instanceof Planet?$target->position:null),$ships,$ratio,$config['multipliers']['travel_time'],$this->catalog->rules()['fuel_ratio_policy'],$windowHours);
        $freight=[];
        foreach($cargo as $resource=>$amount) {
            if(!array_key_exists($resource,$this->catalog->rules()['resources'])) throw new \DomainException('Ressource inconnue.');
            $n=filter_var($amount,FILTER_VALIDATE_INT);
            if($n===false || $n<0 || $n>100000000) throw new \DomainException('Fret invalide.');
            if($n) $freight[$resource]=$n;
        }
        if($mission!=='transport' && array_sum($freight)>0) throw new \DomainException('Le fret est réservé aux missions de transport.');
        if($quote['total_fuel']>$quote['capacity']+1e-6) throw new \DomainException('Capacité insuffisante pour le carburant aller-retour. Réduisez la vitesse ou ajoutez un transporteur.');
        if(array_sum($freight)>$quote['freight_capacity']+1e-6) throw new \DomainException('Soute de fret insuffisante. Les petits réservoirs sont réservés au carburant.');
        return $quote+['target'=>$target,'system'=>$system,'composition'=>$valid,'freight'=>$freight,'idle'=>$idle];
    }
    public function preview(PlayerUniverse $m,Planet $origin,string $mission,int $targetId,array $composition,float $ratio,array $cargo=[],float $orbitHours=0): array
    {
        return $this->manager->transaction(function()use($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$orbitHours){
            $this->guard->lock($m,$origin);$this->queues->synchronize($m);
            $q=$this->quote($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$orbitHours);
            return array_intersect_key($q,array_flip(['distance','normal_distance','hyper_distance','seconds','one_way_fuel','orbit_fuel','total_fuel','fuel_capacity','cargo_capacity','freight_capacity']));
        });
    }
    public function send(PlayerUniverse $m,Planet $origin,string $mission,int $targetId,array $composition,float $ratio,array $cargo,string $key,float $orbitHours=0): void
    {
        $this->manager->transaction(function()use($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$key,$orbitHours){
            $this->guard->lock($m,$origin);$this->queues->synchronize($m);if(!$this->guard->receipt($m,$key)) return;
            $q=$this->quote($m,$origin,$mission,$targetId,$composition,$ratio,$cargo,$orbitHours);
            if($q['target'] instanceof Planet) $this->manager->lock($q['target']);
            $costs=$q['freight'];$costs['hydrogen']=($costs['hydrogen']??0)+$q['total_fuel'];$this->resources->consume($origin,$costs);
            $f=new Fleet();$f->playerUniverse=$m;$f->homePlanet=$origin;$f->destination=$q['target'] instanceof Planet?$q['target']:null;$f->destinationSystem=$q['system'];$f->status='traveling';$f->mission=$mission;$f->speedRatio=$ratio;$f->cargo=$costs;$f->reservedFuel=$q['total_fuel'];$f->orbitFuel=$q['orbit_fuel'];$f->orbitDurationSeconds=(int)round($orbitHours*3600);$f->travelSeconds=$q['seconds'];$f->departureAt=$this->clock->now();$f->arrivalAt=$this->clock->now()->modify('+'.$f->travelSeconds.' seconds');$this->manager->save($f);$this->manager->flush();
            foreach($q['composition'] as $slug=>$quantity) {
                $stock=$this->repo->one(FleetShip::class,['fleet'=>$q['idle'],'slug'=>$slug]);$stock->quantity-=$quantity;
                $ship=new FleetShip();$ship->fleet=$f;$ship->slug=$slug;$ship->quantity=$quantity;$this->manager->save($ship);
            }
            $this->schedule($f);
        });
    }
    private function schedule(Fleet $f): void
    {
        $f->dispatchToken=Uuid::v4()->toRfc4122();$o=new OutboxMessage();$o->kind='fleet';$o->targetId=$f->id;$o->token=$f->dispatchToken;$o->dueAt=$f->arrivalAt;$this->manager->save($o);
    }
    public function arrive(int $id,string $token): void
    {
        $this->manager->transaction(function()use($id,$token){
            $f=$this->repo->find(Fleet::class,$id);if(!$f) return;
            $m=$f->playerUniverse;$this->guard->lock($m,$f->homePlanet);$this->queues->synchronize($m);$this->manager->lock($f);
            if(!in_array($f->status,['traveling','returning','defending','orbiting'],true) || !hash_equals($f->dispatchToken,$token) || !$f->arrivalAt || $f->arrivalAt>$this->clock->now()) return;
            if(in_array($f->status,['defending','orbiting'],true)) {
                $f->cargo['hydrogen']=max(0,($f->cargo['hydrogen']??0)-$f->orbitFuel);$f->reservedFuel=max(0,$f->reservedFuel-$f->orbitFuel);$f->orbitFuel=0;
                $f->status='returning';$f->departureAt=$f->arrivalAt;$f->arrivalAt=$this->clock->now()->modify('+'.$f->travelSeconds.' seconds');$this->schedule($f);return;
            }
            if($f->status==='returning') {
                $f->cargo['hydrogen']=max(0,($f->cargo['hydrogen']??0)-$f->reservedFuel);
                $this->resources->deposit($f->homePlanet, $f->cargo);
                $idle=$this->repo->one(Fleet::class,['homePlanet'=>$f->homePlanet,'playerUniverse'=>$m,'status'=>'idle']);
                if(!$idle) { $idle=new Fleet();$idle->homePlanet=$f->homePlanet;$idle->playerUniverse=$m;$this->manager->save($idle);$this->manager->flush(); }
                foreach($this->repo->all(FleetShip::class,['fleet'=>$f]) as $s) {
                    $stock=$this->repo->one(FleetShip::class,['fleet'=>$idle,'slug'=>$s->slug])??new FleetShip();$stock->fleet=$idle;$stock->slug=$s->slug;$stock->quantity+=$s->quantity;$this->manager->save($stock);$this->manager->remove($s);
                }
                $f->cargo=[];$f->reservedFuel=0;$f->status='completed';$f->arrivalAt=null;$f->dispatchToken=Uuid::v4()->toRfc4122();
                $this->notifications->enqueue($m,'Votre flotte est rentrée sur '.$f->homePlanet->name.'.');return;
            }
            $oneWay=max(0,($f->reservedFuel-$f->orbitFuel)/2);$f->cargo['hydrogen']=max(0,($f->cargo['hydrogen']??0)-$oneWay);$f->reservedFuel-=$oneWay;
            if(in_array($f->mission,['attack','conquer'],true)) {
                if($this->combats->joinAttack($f)) return;
                $this->releaseOrbitFuel($f);$this->notifications->enqueue($m,'La fenêtre de combat était déjà close. Votre flotte repart.');
            } elseif($f->mission==='defend') {
                if($this->combats->arriveDefender($f)) return;
                $this->releaseOrbitFuel($f);$this->notifications->enqueue($m,'La fenêtre de combat était close. Votre flotte repart.');
            } elseif($f->mission==='orbit') {
                if($this->combats->orbitAutoAccepted($m,$f->destination)) {
                    if($this->combats->joinOpenDefense($f)) return;
                    $this->startOrbit($f);
                    $this->notifications->enqueue($m,'Votre flotte est acceptée automatiquement en orbite sur '.$f->destination->name.'.');
                    $this->notifications->enqueue($f->destination->owner,'Flotte alliée en orbite sur '.$f->destination->name.'.');
                    return;
                }
                $f->status='orbit_pending';$f->arrivalAt=null;$f->dispatchToken=Uuid::v4()->toRfc4122();$this->combats->notifyOrbitRequest($f);
                return;
            } elseif($f->mission==='explore') {
                $d=$this->repo->one(PlayerDiscovery::class,['playerUniverse'=>$m,'solarSystem'=>$f->destinationSystem]);
                if(!$d) { $d=new PlayerDiscovery();$d->playerUniverse=$m;$d->solarSystem=$f->destinationSystem;$this->manager->save($d); }
                $this->consumeShip($f,'probe');$this->notifications->enqueue($m,'Système découvert : '.$f->destinationSystem->name.'.');
            } elseif($f->mission==='colonize') {
                $target=$f->destination;$this->manager->lock($target);
                $limit=$this->catalog->get('universes',$m->universe->slug)['limits']['max_planets'];
                if(!$target->owner && !$target->npcPlanet && count($this->repo->planets($m->universe,$m))<$limit) { $target->owner=$m;$this->resources->initialize($target,true);$this->consumeShip($f,'colony_ship');$this->notifications->enqueue($m,'Nouvelle colonie : '.$target->name.'.'); }
                else $this->notifications->enqueue($m,'Colonisation impossible à l’arrivée. Votre flotte revient.');
            } elseif($f->mission==='transport') {
                $target=$f->destination;$this->manager->lock($target);
                if($target->owner?->id===$m->id) {
                    $rows=$this->resources->materialize($target);
                    foreach($f->cargo as $slug=>$n) { $freight=max(0,$n-($slug==='hydrogen'?$f->reservedFuel:0));$rows[$slug]->currentStock+=$freight;$f->cargo[$slug]-=$freight; }
                    $this->notifications->enqueue($m,'Transport livré sur '.$target->name.'.');
                }
            }
            $survivors=array_sum(array_map(fn($s)=>$s->quantity,$this->repo->all(FleetShip::class,['fleet'=>$f])));
            if($survivors===0) {
                // Consumed probes/colony ships and their unused fuel do not create an empty return fleet.
                $f->status='completed';$f->cargo=[];$f->reservedFuel=0;$f->arrivalAt=null;$f->dispatchToken=Uuid::v4()->toRfc4122();return;
            }
            $f->status='returning';$f->departureAt=$f->arrivalAt;$f->arrivalAt=$f->departureAt->modify('+'.$f->travelSeconds.' seconds');$this->schedule($f);
        });
    }
    public function decideOrbit(PlayerUniverse $host, int $fleetId, bool $accept): void
    {
        $this->manager->transaction(function () use ($host, $fleetId, $accept): void {
            $fleet = $this->repo->find(Fleet::class, $fleetId);
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

    private function startOrbit(Fleet $fleet): void
    {
        $fleet->status = 'orbiting';
        $fleet->departureAt = $this->clock->now();
        $fleet->arrivalAt = $this->clock->now()->modify('+' . max(1, $fleet->orbitDurationSeconds) . ' seconds');
        $this->schedule($fleet);
    }

    private function startReturn(Fleet $fleet): void
    {
        $fleet->status = 'returning';
        $fleet->departureAt = $this->clock->now();
        $fleet->arrivalAt = $this->clock->now()->modify('+' . $fleet->travelSeconds . ' seconds');
        $this->schedule($fleet);
    }

    private function releaseOrbitFuel(Fleet $f): void
    {
        $f->reservedFuel=max(0,$f->reservedFuel-$f->orbitFuel);$f->orbitFuel=0;
    }
    private function consumeShip(Fleet $f,string $slug): void
    {
        $s=$this->repo->one(FleetShip::class,['fleet'=>$f,'slug'=>$slug]);if(!$s || $s->quantity<1) throw new \LogicException('Vaisseau de mission absent.');--$s->quantity;
    }
}
