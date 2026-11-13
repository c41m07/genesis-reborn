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
    public function __construct(private GameManager $manager,private GameRepository $repo,private GameCatalog $catalog,private RequirementChecker $requirements,private ResourceService $resources,private ActionGuard $guard,private ClockInterface $clock,private \App\Service\Notification\NotificationService $notifications,private OperationCalculator $calculator,private \App\Service\Requirement\CatalogAccess $access,private VacationCalendar $vacations,private \App\Service\Player\EmpireReconstructionScheduler $reconstruction) {}
    public function add(PlayerUniverse $m,Planet $planet,string $group,string $slug,int $quantity,string $requestKey,string $tier='basic'): int
    {
        return $this->manager->transaction(function()use($m,$planet,$group,$slug,$quantity,$requestKey,$tier){
            $this->guard->lock($m,$planet);$this->vacations->assertCanManageQueues($m);$this->settleMembershipLocked($m);
            if(!$this->guard->receipt($m,$requestKey)) return 0;
            if(!in_array($group,['buildings','technologies','ships','defenses'],true)) throw new \DomainException('Type de construction invalide.');
            if(!in_array($tier,GameCatalog::TIERS,true)) throw new \DomainException('Rang de progression invalide.');
            if($quantity<1) throw new \DomainException('La quantité doit être un entier positif.');
            if(!isset($this->catalog->all($group)[$slug])) throw new \DomainException('Élément indisponible.');
            $config=$this->catalog->forUniverse($m->universe);$base=$config['catalog'][$group][$slug]??$this->catalog->get($group,$slug);$d=$this->catalog->withTier($base,$tier);
            $type=match($group){'buildings'=>'building','technologies'=>'research',default=>'shipyard'};
            $queue=$type==='research'?$this->repo->researchQueue($m):$this->repo->queue($planet,$type);
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
            $failures=$this->access->failures($m,$planet,$group,$d,$tier,$buildings,$techs);
            if($failures) throw new \DomainException('Déblocage initial : '.implode(', ',array_unique($failures)).'.');
            if(in_array($group,['ships','defenses'],true)) {
                $maximum=$this->unitMaximum($m,$planet,$group,$slug,$tier,$d,$buildings,$config,$techs);
                if($maximum<1) throw new \DomainException('Ressources insuffisantes pour construire une unité.');
                $quantity=min($quantity,$maximum);
            }
            $plan=$this->calculator->calculate($d,$group,$level,$quantity,$buildings,$config,$techs);$duration=$plan['duration'];
            $this->resources->consume($planet,$plan['costs']);
            $item=new QueueItem();$item->planet=$planet;$item->playerUniverse=$m;$item->queueType=$type;$item->itemSlug=$slug;$item->tier=$tier;$item->itemLevel=in_array($group,['buildings','technologies'],true)?$level:null;$item->quantity=$quantity;$item->paidCosts=$plan['costs'];$item->durationSeconds=$duration;$item->queuedAt=$this->clock->now();$item->dependsOn=$depends;$item->dispatchToken=Uuid::v4()->toRfc4122();
            $this->manager->save($item);$this->manager->flush();
            if(!$queue) $this->start($item,$this->clock->now());
            return $quantity;
        });
    }
    /** Réservé au traitement autonome, avec propriétaire et planète verrouillés. */
    public function addReconstructionLocked(PlayerUniverse $membership,Planet $planet,string $group,string $slug,string $tier): QueueItem
    {
        if(!$membership->isForgotten() || $planet->owner?->id!==$membership->id || !in_array($group,['ships','defenses'],true)
            || $this->repo->queue($planet,'shipyard')!==[]) throw new \DomainException('Reconstruction indisponible.');
        $definition=$this->catalog->getTierForUniverse($membership->universe,$group,$slug,$tier);
        $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $technologies=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership]);
        $plan=$this->calculator->calculate($definition,$group,1,1,$buildings,$this->catalog->forUniverse($membership->universe),$technologies);
        $this->resources->consume($planet,$plan['costs']);
        $item=new QueueItem();$item->planet=$planet;$item->playerUniverse=$membership;$item->queueType='shipyard';
        $item->itemSlug=$slug;$item->tier=$tier;$item->quantity=1;$item->paidCosts=$plan['costs'];$item->durationSeconds=$plan['duration'];
        $item->queuedAt=$this->clock->now();$item->dispatchToken=Uuid::v4()->toRfc4122();
        $this->manager->save($item);$this->manager->flush();$this->start($item,$this->clock->now());
        return $item;
    }

    public function resumeReconstructionLocked(QueueItem $item): void
    {
        if($item->startedAt===null) $this->start($item,$this->clock->now());
    }

    public function maximum(PlayerUniverse $membership, Planet $planet, string $group, string $slug, string $tier): int
    {
        return $this->manager->transaction(function () use ($membership, $planet, $group, $slug, $tier) {
            $this->guard->lock($membership, $planet);
            $this->vacations->assertCanManageQueues($membership);
            $this->settleMembershipLocked($membership);
            if (!in_array($group, ['ships', 'defenses'], true) || !in_array($tier, GameCatalog::TIERS, true) || !isset($this->catalog->all($group)[$slug])) throw new \DomainException('Construction indisponible.');
            $definition = $this->catalog->getTierForUniverse($membership->universe, $group, $slug, $tier);
            $buildings = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
            $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
            if (count($this->repo->queue($planet, 'shipyard')) >= 30 || $this->access->failures($membership, $planet, $group, $definition, $tier, $buildings, $technologies)) return 0;
            return $this->unitMaximum($membership, $planet, $group, $slug, $tier, $definition, $buildings, $this->catalog->forUniverse($membership->universe), $technologies);
        });
    }

    public function constructionPreview(PlayerUniverse $membership,Planet $planet,string $group,string $slug,string $tier,int $requested): array
    {
        $maximum=$this->maximum($membership,$planet,$group,$slug,$tier);
        $quantity=min(max(1,$requested),$maximum);
        $config=$this->catalog->forUniverse($membership->universe);
        $definition=$this->catalog->getTierForUniverse($membership->universe,$group,$slug,$tier);
        $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
        $technologies=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership]);
        $plan=$this->calculator->calculate($definition,$group,1,max(1,$quantity),$buildings,$config,$technologies);
        $reason='';$missing=[];
        if($maximum===0) {
            $failures=$this->access->failures($membership,$planet,$group,$definition,$tier,$buildings,$technologies);
            if(count($this->repo->queue($planet,'shipyard'))>=30) $reason='File pleine (30 commandes maximum).';
            elseif($failures) $reason='Prérequis manquants : '.implode(', ',$failures).'.';
            else {
                $stocks=$this->resources->snapshot($planet);
                foreach($plan['costs'] as $resource=>$amount) if($amount>($stocks[$resource]['stock']??0)) $missing[]=number_format((int)ceil($amount-($stocks[$resource]['stock']??0)),0,',',' ').' '.$this->catalog->rules()['resources'][$resource];
                $reason=$missing?'Ressources manquantes : '.implode(', ',$missing).'.':'Limite de quantité atteinte.';
            }
        }
        return ['maximum'=>$maximum,'quantity'=>$quantity,'costs'=>$quantity>0?$plan['costs']:[],'duration'=>$quantity>0?$plan['duration']:0,'reason'=>$reason,'unavailableLabel'=>$maximum===0?($missing?'Manque '.implode(' · ',$missing):$reason):''];
    }

    private function unitMaximum(PlayerUniverse $membership, Planet $planet, string $group, string $slug, string $tier, array $definition, array $buildings, array $config, array $technologies): int
    {
        $owned = $group === 'defenses'
            ? ($this->repo->one(PlanetDefense::class, ['planet' => $planet, 'slug' => $slug, 'tier' => $tier])?->quantity ?? 0)
            : ($this->repo->one(FleetShip::class, ['fleet' => $this->repo->one(Fleet::class, ['homePlanet' => $planet, 'playerUniverse' => $membership, 'status' => 'idle']), 'slug' => $slug, 'tier' => $tier])?->quantity ?? 0);
        $limit = 2147483647 - $owned;
        foreach ($this->repo->queue($planet, 'shipyard') as $item) {
            if ($item->itemSlug === $slug && $item->tier === $tier) $limit -= $item->quantity;
        }
        return $this->calculator->maximum($definition, $group, $this->resources->snapshot($planet), $buildings, $config, $technologies, max(0, $limit));
    }

    private function start(QueueItem $item,\DateTimeImmutable $at): void
    {
        $item->startedAt=$at;$field=$item->queueType.'QueueStartedAt';$item->planet->$field=$at;
        $item->dispatchToken=Uuid::v4()->toRfc4122();
        $o=new OutboxMessage();$o->kind='queue';$o->targetId=$item->id;$o->token=$item->dispatchToken;$o->dueAt=$item->finishAt();$this->manager->save($o);
    }
    /** Reconcile all elapsed work before any spending, preview or gameplay read. */
    public function synchronize(PlayerUniverse $m,?\DateTimeImmutable $until=null): void
    {
        $this->manager->transaction(function()use($m,$until){
            $this->guard->lockMembershipForProcessing($m);$this->settleMembershipLocked($m,$until);
        });
    }
    private function settleMembershipLocked(PlayerUniverse $m,?\DateTimeImmutable $until=null): void
    {
        $this->access->captureLocked($m);
        $planets=[];
        foreach($this->repo->planets($m->universe,$m) as $planet) {
            $this->manager->lock($planet);$planets[$planet->id]=$planet;
        }
        $until??=$this->clock->now();
        if(!$this->vacations->isActive($m,$until)) {
            $this->startNextResearch($m,$until);
            $items=[];
            foreach($this->repo->all(QueueItem::class,['playerUniverse'=>$m],['id'=>'ASC']) as $item) {
                if(isset($planets[$item->planet->id])) $items[$item->id]=$item;
            }
            // Les fins sont traitées dans l’ordre réel, même si la prochaine recherche vient d’une autre colonie.
            while(true) {
                $due=[];$heads=[];
                foreach($items as $item) {
                    if(!in_array($item->queueType,['building','research','shipyard'],true)) continue;
                    if($item->queueType!=='research') {
                        $key=$item->planet->id.':'.$item->queueType;
                        if(isset($heads[$key])) continue;
                        $heads[$key]=true;
                    }
                    if($item->startedAt!==null && $item->finishAt()<=$until) $due[]=$item;
                }
                if(!$due) break;
                usort($due,fn($a,$b)=>($a->finishAt()<=>$b->finishAt())?:($a->id<=>$b->id));
                $item=$due[0];$id=$item->id;$this->applyCompletion($item);unset($items[$id]);
            }
        }
        $this->access->captureLocked($m);
    }

    private function startNextResearch(PlayerUniverse $membership,\DateTimeImmutable $at): void
    {
        if($this->vacations->isActive($membership,$at)) return;
        $queue=$this->repo->researchQueue($membership);
        foreach($queue as $item) if($item->startedAt!==null) return;
        $next=$queue[0]??null;
        if($next!==null && $next->planet->owner?->id===$membership->id) {
            $this->start($next,$at<$next->queuedAt?$next->queuedAt:$at);
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
        $this->notifications->enqueue($m,'Terminé : '.$this->catalog->getTierForUniverse($m->universe,$group,$item->itemSlug,$item->tier)['label'].' sur '.$planet->name.'.',journalKind:'queue');
        $this->reconstruction->afterCompletionLocked($item);
        $type=$item->queueType;$this->manager->remove($item);$this->manager->flush();
        if($type==='research') {
            $planet->researchQueueStartedAt=null;
            $this->startNextResearch($m,$finish);
        } else {
            $next=$this->repo->queue($planet,$type)[0]??null;
            if($next) $this->start($next,$finish);else { $field=$type.'QueueStartedAt';$planet->$field=null; }
        }
    }
    public function complete(int $id,string $token): void
    {
        $this->manager->transaction(function()use($id,$token){
            $item=$this->repo->find(QueueItem::class,$id);if(!$item) return;
            $planet=$item->planet;$this->guard->lockForCompletion($item->playerUniverse,$planet);
            $head=$item->queueType==='research'?$item:($this->repo->queue($planet,$item->queueType)[0]??null);
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
    public function cancellationPreview(PlayerUniverse $membership,int $id): array
    {
        return $this->manager->transaction(function()use($membership,$id){
            $item=$this->repo->find(QueueItem::class,$id);
            if(!$item || $item->playerUniverse->id!==$membership->id || $item->playerUniverse->universe->id!==$membership->universe->id) throw new \DomainException('Commande inaccessible.');
            $this->guard->lock($membership,$item->planet);$this->vacations->assertCanManageQueues($membership);$this->settleMembershipLocked($membership);
            $item=$this->repo->find(QueueItem::class,$id);
            if(!$item) throw new \DomainException('Cette commande est déjà terminée.');
            $rows=[];$rooms=[];
            $collect=function(QueueItem $command)use(&$collect,&$rows,&$rooms,$membership):void{
                if($command->playerUniverse->id!==$membership->id || $command->planet->owner?->id!==$membership->id) throw new \DomainException('Commande inaccessible.');
                foreach($this->repo->all(QueueItem::class,['dependsOn'=>$command]) as $dependent) $collect($dependent);
                $group=match($command->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$command->itemSlug])?'defenses':'ships'};
                $definition=$this->catalog->getTierForUniverse($membership->universe,$group,$command->itemSlug,$command->tier);
                $paid=$command->paidCosts??$this->catalog->costs($definition,$command->itemLevel??1,$command->quantity,$group);
                if(!isset($rooms[$command->planet->id])) foreach($this->resources->snapshot($command->planet) as $slug=>$resource) $rooms[$command->planet->id][$slug]=max(0.0,$resource['capacity']-$resource['stock']);
                $refund=[];
                foreach($paid as $slug=>$amount) {
                    $refund[$slug]=min($rooms[$command->planet->id][$slug]??0,$amount*$this->catalog->rules()['cancellation_refund_ratio']);
                    $rooms[$command->planet->id][$slug]-=$refund[$slug];
                }
                $rows[]=['item'=>$command,'label'=>$definition['label'],'refund'=>$refund];
            };
            $collect($item);
            return ['item'=>$item,'rows'=>$rows,'resource_labels'=>$this->catalog->rules()['resources']];
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
            if($type==='research') {
                if($active) $planet->researchQueueStartedAt=null;
                $this->startNextResearch($m,$this->clock->now());
            } elseif($active) {
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
        $this->reconstruction->stopLocked($planet);
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
        foreach($planets as $affected) {
            foreach(['building','shipyard'] as $type) {
                $head=$this->repo->queue($affected,$type)[0]??null;
                if($head!==null && $head->startedAt===null) $this->start($head,$this->clock->now());
                $field=$type.'QueueStartedAt';$affected->$field=$head?->startedAt;
            }
            $affected->researchQueueStartedAt=null;
            foreach($this->repo->queue($affected,'research') as $research) {
                if($research->startedAt!==null) {$affected->researchQueueStartedAt=$research->startedAt;break;}
            }
        }
        if($formerOwner!==null) $this->startNextResearch($formerOwner,$this->clock->now());
        return $journal;
    }
}

