<?php
namespace App\Service\Queue;
use App\Entity\{PlayerUniverse,Planet,PlanetBuilding,PlayerTechnology,QueueItem,Fleet,FleetShip,OutboxMessage,PlanetDefense};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;
use App\Service\Colony\{ResourceService,ActionGuard};
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;
final class QueueService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private GameCatalog $catalog,private RequirementChecker $requirements,private ResourceService $resources,private ActionGuard $guard,private ClockInterface $clock,private \App\Service\Notification\NotificationService $notifications,private OperationCalculator $calculator) {}
    public function add(PlayerUniverse $m,Planet $planet,string $group,string $slug,int $quantity,string $requestKey): void
    {
        $this->manager->transaction(function()use($m,$planet,$group,$slug,$quantity,$requestKey){
            $this->guard->lock($m,$planet);$this->settleMembershipLocked($m);
            if(!$this->guard->receipt($m,$requestKey)) return;
            if(!in_array($group,['buildings','technologies','ships','defenses'],true)) throw new \DomainException('Type de construction invalide.');
            if($quantity<1 || $quantity>100) throw new \DomainException('Quantité autorisée : 1 à 100.');
            if(!isset($this->catalog->all($group)[$slug])) throw new \DomainException('Élément indisponible.');
            $d=$this->catalog->get($group,$slug);
            $type=match($group){'buildings'=>'building','technologies'=>'research',default=>'shipyard'};
            $queue=$this->repo->queue($planet,$type);
            if(count($queue)>=30) throw new \DomainException('File pleine (30 commandes maximum).');
            $buildings=$this->repo->levels(PlanetBuilding::class,['planet'=>$planet]);
            $techs=$this->repo->levels(PlayerTechnology::class,['playerUniverse'=>$m]);
            $level=1;$depends=null;
            if(in_array($group,['buildings','technologies'],true)) {
                $quantity=1;$level=($group==='buildings' ? ($buildings[$slug]??0) : ($techs[$slug]??0))+1;
                foreach($queue as $item) if($item->itemSlug===$slug) { $level=$item->itemLevel+1;$depends=$item; }
                if($level>$d['max_level']) throw new \DomainException('Niveau maximum atteint.');
            }
            if($group==='technologies') {
                if(($buildings['lab']??0)<1) throw new \DomainException('Un laboratoire est nécessaire.');
                foreach($this->repo->all(QueueItem::class,['playerUniverse'=>$m,'queueType'=>'research','itemSlug'=>$slug]) as $item) if($item->planet->id!==$planet->id) throw new \DomainException('Cette technologie est déjà en recherche sur une autre planète.');
            }
            if($group==='ships' && ($buildings['shipyard']??0)<1) throw new \DomainException('Un chantier spatial est nécessaire.');
            $failures=$this->requirements->check($this->requirements->forLevel($d,$level),$buildings,$techs);
            if($failures) throw new \DomainException('Prérequis : '.implode(', ',$failures).'.');
            $config=$this->catalog->get('universes',$m->universe->slug);
            $plan=$this->calculator->calculate($d,$group,$level,$quantity,$buildings,$config);$duration=$plan['duration'];
            $this->resources->consume($planet,$plan['costs']);
            $item=new QueueItem();$item->planet=$planet;$item->playerUniverse=$m;$item->queueType=$type;$item->itemSlug=$slug;$item->itemLevel=in_array($group,['buildings','technologies'],true)?$level:null;$item->quantity=$quantity;$item->paidCosts=$plan['costs'];$item->durationSeconds=$duration;$item->queuedAt=$this->clock->now();$item->dependsOn=$depends;$item->dispatchToken=Uuid::v4()->toRfc4122();
            $this->manager->save($item);$this->manager->flush();
            if(!$queue) $this->start($item,$this->clock->now());
        });
    }
    private function start(QueueItem $item,\DateTimeImmutable $at): void
    {
        $item->startedAt=$at;$field=$item->queueType.'QueueStartedAt';$item->planet->$field=$at;
        $item->dispatchToken=Uuid::v4()->toRfc4122();
        $o=new OutboxMessage();$o->kind='queue';$o->targetId=$item->id;$o->token=$item->dispatchToken;$o->dueAt=$item->finishAt();$this->manager->save($o);
    }
    /** Reconcile all elapsed work before any spending, preview or gameplay read. */
    public function synchronize(PlayerUniverse $m): void
    {
        $this->manager->transaction(function()use($m){
            $this->guard->lockMembership($m);$this->settleMembershipLocked($m);
        });
    }
    private function settleMembershipLocked(PlayerUniverse $m): void
    {
        foreach($this->repo->planets($m->universe,$m) as $planet) {
            $this->manager->lock($planet);$this->settlePlanetLocked($planet,$this->clock->now());
        }
    }
    private function settlePlanetLocked(Planet $planet,\DateTimeImmutable $until): void
    {
        while(true) {
            $due=[];
            foreach(['building','research','shipyard'] as $type) {
                $head=$this->repo->queue($planet,$type)[0]??null;
                if($head?->startedAt && $head->finishAt()<=$until) $due[]=$head;
            }
            if(!$due) return;
            usort($due,fn($a,$b)=>($a->finishAt()<=>$b->finishAt())?:($a->id<=>$b->id));
            $this->applyCompletion($due[0]);
        }
    }
    private function applyCompletion(QueueItem $item): void
    {
        $planet=$item->planet;$m=$item->playerUniverse;$finish=$item->finishAt();
        if($item->queueType==='building') {
            // Settle using the OLD production and storage capacity exactly at the event date.
            $this->resources->materialize($planet,$finish);
            $b=$this->repo->one(PlanetBuilding::class,['planet'=>$planet,'slug'=>$item->itemSlug])??new PlanetBuilding();
            $b->planet=$planet;$b->slug=$item->itemSlug;$b->level=$item->itemLevel;
            $this->manager->save($b);$this->manager->flush();$this->resources->refreshProduction($planet);
        } elseif($item->queueType==='research') {
            $t=$this->repo->one(PlayerTechnology::class,['playerUniverse'=>$m,'slug'=>$item->itemSlug])??new PlayerTechnology();
            $t->playerUniverse=$m;$t->slug=$item->itemSlug;$t->level=$item->itemLevel;$this->manager->save($t);
        } elseif(isset($this->catalog->all('defenses')[$item->itemSlug])) {
            $d=$this->repo->one(PlanetDefense::class,['planet'=>$planet,'slug'=>$item->itemSlug])??new PlanetDefense();
            $d->planet=$planet;$d->slug=$item->itemSlug;$d->quantity+=$item->quantity;$this->manager->save($d);
        } else {
            $fleet=$this->repo->one(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$m,'status'=>'idle']);
            if(!$fleet) { $fleet=new Fleet();$fleet->playerUniverse=$m;$fleet->homePlanet=$planet;$this->manager->save($fleet);$this->manager->flush(); }
            $ship=$this->repo->one(FleetShip::class,['fleet'=>$fleet,'slug'=>$item->itemSlug])??new FleetShip();
            $ship->fleet=$fleet;$ship->slug=$item->itemSlug;$ship->quantity+=$item->quantity;$this->manager->save($ship);
        }
        foreach($this->repo->all(QueueItem::class,['dependsOn'=>$item]) as $dependent) $dependent->dependsOn=null;
        $group=match($item->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$item->itemSlug])?'defenses':'ships'};
        $this->notifications->enqueue($m,'Terminé : '.$this->catalog->get($group,$item->itemSlug)['label'].' sur '.$planet->name.'.');
        $type=$item->queueType;$this->manager->remove($item);$this->manager->flush();
        $next=$this->repo->queue($planet,$type)[0]??null;
        if($next) $this->start($next,$finish);else { $field=$type.'QueueStartedAt';$planet->$field=null; }
    }
    public function complete(int $id,string $token): void
    {
        $this->manager->transaction(function()use($id,$token){
            $item=$this->repo->find(QueueItem::class,$id);if(!$item) return;
            $planet=$item->planet;$this->guard->lock($item->playerUniverse,$planet);
            $head=$this->repo->queue($planet,$item->queueType)[0]??null;
            if(!$head || $head->id!==$id || !hash_equals($head->dispatchToken,$token) || !$head->startedAt) return;
            $this->settleMembershipLocked($item->playerUniverse);
        });
    }
    /** Operator-granted boosts only: no unspecified player currency or free public endpoint. */
    public function boost(PlayerUniverse $m,int $id,float $divisor,string $key): void
    {
        $this->manager->transaction(function()use($m,$id,$divisor,$key){
            $this->guard->lockMembership($m);
            if(!$this->guard->receipt($m,$key)) return;
            $this->settleMembershipLocked($m);$item=$this->repo->find(QueueItem::class,$id);
            if(!$item || $item->playerUniverse->id!==$m->id || !$item->startedAt) throw new \DomainException('Opération active introuvable.');
            $elapsed=max(0,$this->clock->now()->getTimestamp()-$item->startedAt->getTimestamp());
            $item->durationSeconds=QueueTiming::boostedDuration($item->durationSeconds,$elapsed,$divisor);
            $this->start($item,$item->startedAt);
        });
    }
    public function cancel(PlayerUniverse $m,int $id,string $key): void
    {
        $this->manager->transaction(function()use($m,$id,$key){
            $item=$this->repo->find(QueueItem::class,$id);
            if(!$item) return;
            $this->guard->lock($m,$item->planet);$this->settleMembershipLocked($m);
            $item=$this->repo->find(QueueItem::class,$id);if(!$item) return;
            if(!$this->guard->receipt($m,$key)) return;
            if($item->playerUniverse->id!==$m->id) throw new \DomainException('Commande inaccessible.');
            $planet=$item->planet;$type=$item->queueType;$active=$item->startedAt!==null;
            $this->removeTree($item);$this->manager->flush();
            if($active) {
                $next=$this->repo->queue($planet,$type)[0]??null;
                if($next) $this->start($next,$this->clock->now());else { $field=$type.'QueueStartedAt';$planet->$field=null; }
            }
        });
    }
    private function removeTree(QueueItem $item): void
    {
        foreach($this->repo->all(QueueItem::class,['dependsOn'=>$item]) as $dependent) $this->removeTree($dependent);
        $group=match($item->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$item->itemSlug])?'defenses':'ships'};
        $paid=$item->paidCosts??$this->catalog->costs($this->catalog->get($group,$item->itemSlug),$item->itemLevel??1,$item->quantity);
        $this->resources->refund($item->planet,$paid,$this->catalog->rules()['cancellation_refund_ratio']);
        $this->manager->remove($item);
    }
}
