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
use App\Service\Player\VacationCalendar;
final class QueueService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private GameCatalog $catalog,private RequirementChecker $requirements,private ResourceService $resources,private ActionGuard $guard,private ClockInterface $clock,private \App\Service\Notification\NotificationService $notifications,private OperationCalculator $calculator,private \App\Service\Requirement\CatalogAccess $access,private VacationCalendar $vacations) {}
    public function add(PlayerUniverse $m,Planet $planet,string $group,string $slug,int $quantity,string $requestKey,string $tier='basic'): void
    {
        $this->manager->transaction(function()use($m,$planet,$group,$slug,$quantity,$requestKey,$tier){
            $this->guard->lock($m,$planet);$this->vacations->assertCanManageQueues($m);$this->settleMembershipLocked($m);
            if(!$this->guard->receipt($m,$requestKey)) return;
            if(!in_array($group,['buildings','technologies','ships','defenses'],true)) throw new \DomainException('Type de construction invalide.');
            if(!in_array($tier,GameCatalog::TIERS,true)) throw new \DomainException('Rang de progression invalide.');
            if($quantity<1 || $quantity>100) throw new \DomainException('Quantité autorisée : 1 à 100.');
            if(!isset($this->catalog->all($group)[$slug])) throw new \DomainException('Élément indisponible.');
            $config=$this->catalog->forUniverse($m->universe);$base=$config['catalog'][$group][$slug]??$this->catalog->get($group,$slug);$d=$this->catalog->withTier($base,$tier);
            $type=match($group){'buildings'=>'building','technologies'=>'research',default=>'shipyard'};
            $queue=$this->repo->queue($planet,$type);
            if(count($queue)>=30) throw new \DomainException('File pleine (30 commandes maximum).');
            $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
            $techs=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$m]);
            $level=1;$depends=null;
            if(in_array($group,['buildings','technologies'],true)) {
                $quantity=1;$level=(int)($group==='buildings'?($buildings[$slug][$tier]??0):($techs[$slug][$tier]??0))+1;
                foreach($queue as $item) if($item->itemSlug===$slug&&$item->tier===$tier) {$level=($item->itemLevel??0)+1;$depends=$item;}
                $maxLevel=(int)($d['effect']['tier_max_levels'][$tier]??$d['max_level']??PHP_INT_MAX);
                if($level>$maxLevel) throw new \DomainException('Niveau maximum atteint.');
            }
            if($group==='technologies')foreach($this->repo->all(QueueItem::class,['playerUniverse'=>$m,'queueType'=>'research','itemSlug'=>$slug,'tier'=>$tier]) as $item)if($item->planet->id!==$planet->id)throw new \DomainException('Ce rang de technologie est déjà en recherche sur une autre planète.');
            $failures=$this->access->failures($m,$planet,$group,$d,$tier,$buildings,$techs);
            if($failures) throw new \DomainException('Déblocage initial : '.implode(', ',array_unique($failures)).'.');
            $plan=$this->calculator->calculate($d,$group,$level,$quantity,$buildings,$config,$techs);$duration=$plan['duration'];
            $this->resources->consume($planet,$plan['costs']);
            $item=new QueueItem();$item->planet=$planet;$item->playerUniverse=$m;$item->queueType=$type;$item->itemSlug=$slug;$item->tier=$tier;$item->itemLevel=in_array($group,['buildings','technologies'],true)?$level:null;$item->quantity=$quantity;$item->paidCosts=$plan['costs'];$item->durationSeconds=$duration;$item->queuedAt=$this->clock->now();$item->dependsOn=$depends;$item->dispatchToken=Uuid::v4()->toRfc4122();
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
            $this->guard->lockMembershipForProcessing($m);$this->settleMembershipLocked($m);
        });
    }
    private function settleMembershipLocked(PlayerUniverse $m,?\DateTimeImmutable $until=null): void
    {
        $this->access->captureLocked($m);
        foreach($this->repo->planets($m->universe,$m) as $planet) {
            $this->manager->lock($planet);$this->settlePlanetLocked($planet,$until??$this->clock->now());
        }
        $this->access->captureLocked($m);
    }
    private function settlePlanetLocked(Planet $planet,\DateTimeImmutable $until): void
    {
        if ($planet->owner !== null && $this->vacations->isActive($planet->owner,$until)) return;
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
            $b=$this->repo->one(PlanetBuilding::class,['planet'=>$planet,'slug'=>$item->itemSlug,'tier'=>$item->tier])??new PlanetBuilding();
            $b->planet=$planet;$b->slug=$item->itemSlug;$b->tier=$item->tier;$b->level=$item->itemLevel;
            $this->manager->save($b);$this->manager->flush();$this->resources->refreshProduction($planet);
        } elseif($item->queueType==='research') {
            $t=$this->repo->one(PlayerTechnology::class,['playerUniverse'=>$m,'slug'=>$item->itemSlug,'tier'=>$item->tier])??new PlayerTechnology();
            $t->playerUniverse=$m;$t->slug=$item->itemSlug;$t->tier=$item->tier;$t->level=$item->itemLevel;$this->manager->save($t);
        } elseif(isset($this->catalog->all('defenses')[$item->itemSlug])) {
            $d=$this->repo->one(PlanetDefense::class,['planet'=>$planet,'slug'=>$item->itemSlug,'tier'=>$item->tier])??new PlanetDefense();
            $d->planet=$planet;$d->slug=$item->itemSlug;$d->tier=$item->tier;$d->quantity+=$item->quantity;$this->manager->save($d);
        } else {
            $fleet=$this->repo->one(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$m,'status'=>'idle']);
            if(!$fleet) { $fleet=new Fleet();$fleet->playerUniverse=$m;$fleet->homePlanet=$planet;$this->manager->save($fleet);$this->manager->flush(); }
            $ship=$this->repo->one(FleetShip::class,['fleet'=>$fleet,'slug'=>$item->itemSlug,'tier'=>$item->tier])??new FleetShip();
            $ship->fleet=$fleet;$ship->slug=$item->itemSlug;$ship->tier=$item->tier;$ship->quantity+=$item->quantity;$this->manager->save($ship);
        }
        foreach($this->repo->all(QueueItem::class,['dependsOn'=>$item]) as $dependent) $dependent->dependsOn=null;
        $group=match($item->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$item->itemSlug])?'defenses':'ships'};
        $this->notifications->enqueue($m,'Terminé : '.$this->catalog->getTierForUniverse($m->universe,$group,$item->itemSlug,$item->tier)['label'].' sur '.$planet->name.'.');
        $type=$item->queueType;$this->manager->remove($item);$this->manager->flush();
        $next=$this->repo->queue($planet,$type)[0]??null;
        if($next) $this->start($next,$finish);else { $field=$type.'QueueStartedAt';$planet->$field=null; }
    }
    public function complete(int $id,string $token): void
    {
        $this->manager->transaction(function()use($id,$token){
            $item=$this->repo->find(QueueItem::class,$id);if(!$item) return;
            $planet=$item->planet;$this->guard->lockForCompletion($item->playerUniverse,$planet);
            $head=$this->repo->queue($planet,$item->queueType)[0]??null;
            if(!$head || $head->id!==$id || !hash_equals($head->dispatchToken,$token) || !$head->startedAt) return;
            $this->settleMembershipLocked($item->playerUniverse);
        });
    }
    public function applyPlayerBoost(QueueItem $item, float $divisor): void
    {
        $this->vacations->assertCanManageQueues($item->playerUniverse);
        if (!$item->startedAt || $divisor <= 1) {
            throw new \DomainException('Opération active introuvable.');
        }
        $elapsed = max(0, $this->clock->now()->getTimestamp() - $item->startedAt->getTimestamp());
        $item->durationSeconds = QueueTiming::boostedDuration($item->durationSeconds, $elapsed, $divisor);
        $this->start($item, $item->startedAt);
    }

    /** Operator-granted boosts only: no unspecified player currency or free public endpoint. */
    public function boost(PlayerUniverse $m,int $id,float $divisor,string $key): void
    {
        $this->manager->transaction(function()use($m,$id,$divisor,$key){
            $this->guard->lockMembership($m);
            $this->vacations->assertCanManageQueues($m);
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
            $this->guard->lock($m,$item->planet);$this->vacations->assertCanManageQueues($m);$this->settleMembershipLocked($m);
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
    /** Appelé avec la participation verrouillée par le service de vacances. */
    public function pauseForVacationLocked(PlayerUniverse $membership,\DateTimeImmutable $at,\DateTimeImmutable $until): void
    {
        $this->settleMembershipLocked($membership,$at);
        $this->shiftVacationHeadsLocked($membership,$until->getTimestamp()-$at->getTimestamp());
    }

    /** Annule seulement la partie inutilisée des 90 jours déjà reportés. */
    public function resumeAfterVacationLocked(PlayerUniverse $membership,int $unusedSeconds): void
    {
        if($unusedSeconds>0) $this->shiftVacationHeadsLocked($membership,-$unusedSeconds);
    }

    private function shiftVacationHeadsLocked(PlayerUniverse $membership,int $seconds): void
    {
        foreach($this->repo->all(QueueItem::class,['playerUniverse'=>$membership]) as $item) {
            if($item->startedAt===null) continue;
            $this->start($item,$item->startedAt->setTimestamp($item->startedAt->getTimestamp()+$seconds));
        }
    }

    private function removeTree(QueueItem $item): void
    {
        foreach($this->repo->all(QueueItem::class,['dependsOn'=>$item]) as $dependent) $this->removeTree($dependent);
        $group=match($item->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$item->itemSlug])?'defenses':'ships'};
        $paid=$item->paidCosts??$this->catalog->costs($this->catalog->getTierForUniverse($item->playerUniverse->universe,$group,$item->itemSlug,$item->tier),$item->itemLevel??1,$item->quantity,$group);
        $this->resources->refund($item->planet,$paid,$this->catalog->rules()['cancellation_refund_ratio']);
        $this->manager->remove($item);
    }
    /** Annulation de capture sans remboursement ; les commandes échues ont déjà été traitées sous verrou. */
    public function cancelForCaptureLocked(Planet $planet,?PlayerUniverse $formerOwner): array
    {
        $items=[];$collect=function(QueueItem $item)use(&$collect,&$items,$formerOwner): void {
            if(isset($items[$item->id])) return;
            if($formerOwner===null || $item->playerUniverse->id!==$formerOwner->id) throw new \DomainException('Une commande de cette planète appartient à un autre empire.');
            $items[$item->id]=$item;
            foreach($this->repo->all(QueueItem::class,['dependsOn'=>$item]) as $dependent) $collect($dependent);
        };
        foreach($this->repo->queue($planet) as $item) $collect($item);
        $journal=[];$planets=[$planet->id=>$planet];
        foreach($items as $item) {
            $journal[]=['id'=>$item->id,'planet_id'=>$item->planet->id,'type'=>$item->queueType,'slug'=>$item->itemSlug,'tier'=>$item->tier,
                'level'=>$item->itemLevel,'quantity'=>$item->quantity,'paid_costs'=>$item->paidCosts];
            $planets[$item->planet->id]=$item->planet;$this->manager->remove($item);
        }
        $this->manager->flush();
        foreach($planets as $affected) foreach(['building','research','shipyard'] as $type) {
            $head=$this->repo->queue($affected,$type)[0]??null;
            if($head!==null && $head->startedAt===null) $this->start($head,$this->clock->now());
            $field=$type.'QueueStartedAt';$affected->$field=$head?->startedAt;
        }
        return $journal;
    }
}

