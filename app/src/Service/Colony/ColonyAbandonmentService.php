<?php
namespace App\Service\Colony;

use App\Entity\{AdminAuditLog,CombatEvent,EmpireReconstruction,Fleet,FleetShip,Planet,PlanetBuilding,PlanetDefense,PlanetNpcShip,PlanetOccupation,PlanetResource,PlayerUniverse,QueueItem};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Notification\NotificationService;
use App\Service\Queue\QueueService;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class ColonyAbandonmentService
{
    public function __construct(private GameRepository $repo,private GameManager $manager,private ActionGuard $guard,
        private QueueService $queues,private ResourceService $resources,private GameCatalog $catalog,
        private NotificationService $notifications,private ClockInterface $clock) {}

    public function preview(PlayerUniverse $member,Planet $planet): array
    {
        return $this->manager->transaction(function()use($member,$planet): array {
            $this->guard->lock($member,$planet);$this->assertAccount($member);$this->queues->synchronize($member);
            return $this->inspect($member,$planet);
        });
    }

    public function abandon(PlayerUniverse $member,Planet $planet,string $name,string $requestKey,string $version): bool
    {
        return $this->manager->transaction(function()use($member,$planet,$name,$requestKey,$version): bool {
            $this->guard->lock($member,$planet);$this->assertAccount($member);
            if(!hash_equals($planet->name,trim($name))) throw new \DomainException('Le nom saisi ne correspond pas à celui de la colonie. Aucun abandon effectué.');
            $this->queues->synchronize($member);
            $preview=$this->inspect($member,$planet);
            if($preview['blockers']!==[]) throw new \DomainException(implode(' ',$preview['blockers']));
            if(!hash_equals($preview['version'],$version)) throw new \DomainException('Le contenu de la colonie a changé. Relisez le bilan actualisé avant de confirmer.');
            if(!$this->guard->receipt($member,$requestKey)) return false;
            $this->resources->materialize($planet,$this->clock->now());
            $preview=$this->inspect($member,$planet);
            // Les états historiques d’anciens propriétaires restent conservés, sans programmation active.
            foreach($this->repo->all(EmpireReconstruction::class,['planet'=>$planet]) as $state) {
                $this->manager->lock($state);$state->queueItem=null;$state->nextAt=null;$state->dispatchToken=Uuid::v4()->toRfc4122();
            }
            $cancelled=$this->queues->cancelForCaptureLocked($planet,$member);
            foreach([PlanetBuilding::class,PlanetResource::class,PlanetDefense::class,PlanetNpcShip::class] as $class) {
                foreach($this->repo->all($class,['planet'=>$planet],['id'=>'ASC']) as $row) $this->manager->remove($row);
            }
            foreach($this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$member,'status'=>'idle'],['id'=>'ASC']) as $fleet) {
                $this->manager->lock($fleet);
                foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet],['id'=>'ASC']) as $ship) $this->manager->remove($ship);
                $fleet->status='completed';$fleet->cargo=[];$fleet->reservedFuel=0;$fleet->orbitFuel=0;
                $fleet->arrivalAt=null;$fleet->dispatchToken=Uuid::v4()->toRfc4122();
            }
            $entry=new AdminAuditLog();$entry->actor=$member->player;$entry->action='player_colony_abandoned';
            $entry->targetType='planet';$entry->targetId=$planet->id;$entry->createdAt=$this->clock->now();
            $entry->details=['universe_id'=>$member->universe->id,'membership_id'=>$member->id,
                'planet'=>['id'=>$planet->id,'name'=>$planet->name,'coordinates'=>$planet->coordinates(),
                    'acquired_at'=>$planet->acquiredAt?->format(DATE_ATOM),'was_npc'=>$planet->npcPlanet],
                'confirmed_version'=>$version,'losses'=>$preview['losses'],'orders'=>$preview['orders'],'cancelled_orders'=>$cancelled];
            $this->manager->save($entry);
            $planet->owner=null;$planet->acquiredAt=null;$planet->npcPlanet=false;$planet->npcGarrisonInitialized=false;
            $planet->buildingQueueStartedAt=null;$planet->researchQueueStartedAt=null;$planet->shipyardQueueStartedAt=null;
            $this->notifications->enqueue($member,'Colonie abandonnée : '.$planet->name.'. Les actifs présents sont perdus et les files ont été annulées sans remboursement.',journalKind:'conquest');
            return true;
        });
    }

    private function assertAccount(PlayerUniverse $member): void
    {
        if($member->player?->lockedAt!==null) throw new \DomainException('Cette colonie est inaccessible pour ce compte.');
    }

    private function inspect(PlayerUniverse $member,Planet $planet): array
    {
        $blockers=[];
        if(count($this->repo->planets($member->universe,$member))<2) $blockers[]='Vous devez conserver au moins une planète dans cet univers.';
        if($this->repo->planetHasActiveFleet($planet)
            || $this->repo->one(CombatEvent::class,['planet'=>$planet,'status'=>'pending'])!==null
            || $this->repo->one(PlanetOccupation::class,['planet'=>$planet,'status'=>'active'])!==null) {
            // Ne jamais révéler la nature, les effectifs ou l’origine d’une opération invisible.
            $blockers[]='Une opération en cours empêche actuellement l’abandon de cette colonie.';
        }
        foreach($this->repo->all(Fleet::class,['homePlanet'=>$planet,'status'=>'idle']) as $fleet) {
            if($fleet->playerUniverse->id===$member->id) continue;
            $quantity=array_sum(array_map(static fn(FleetShip $s):int=>$s->quantity,$this->repo->all(FleetShip::class,['fleet'=>$fleet])));
            if($quantity>0 || $fleet->reservedFuel>0 || array_sum($fleet->cargo)>0) {
                $blockers[]='Une opération en cours empêche actuellement l’abandon de cette colonie.';break;
            }
        }
        $orders=[];$seen=[];$foreign=false;
        $collect=function(QueueItem $item)use(&$collect,&$orders,&$seen,&$foreign,$member): void {
            if(isset($seen[$item->id])) return;$seen[$item->id]=true;
            if($item->playerUniverse->id!==$member->id || $item->planet->solarSystem->galaxy->universe->id!==$member->universe->id) {
                $foreign=true;return;
            }
            $orders[]=$this->order($member,$item);
            foreach($this->repo->all(QueueItem::class,['dependsOn'=>$item]) as $dependent) $collect($dependent);
        };
        foreach($this->repo->queue($planet) as $item) $collect($item);
        $dependencies=array_values(array_filter($orders,static fn(array $q):bool=>$q['planet_id']!==$planet->id));
        if($dependencies!==[]) $blockers[]='Une commande d’une autre colonie dépend de cette planète. Gérez les commandes indiquées avant l’abandon.';
        if($foreign) $blockers[]='Une commande ne peut pas être annulée dans cet univers. Aucun abandon possible.';
        $preview=['blockers'=>$blockers,'dependencies'=>$dependencies,'orders'=>array_values(array_filter($orders,static fn(array $q):bool=>$q['planet_id']===$planet->id)),
            'losses'=>$this->losses($member,$planet)];
        $discrete=$preview;unset($discrete['losses']['resources']);
        $preview['version']=hash('sha256',json_encode(['abandon-without-self-kit-v1',$planet->id,$member->id,$member->universe->id,$planet->name,$discrete],JSON_THROW_ON_ERROR));
        return $preview;
    }

    private function losses(PlayerUniverse $member,Planet $planet): array
    {
        $losses=['resources'=>[],'buildings'=>[],'defenses'=>[],'ships'=>[],'fleet_cargo'=>[]];
        foreach($this->resources->snapshot($planet) as $slug=>$resource) $losses['resources'][]=['slug'=>$slug,'label'=>$resource['label'],'quantity'=>$resource['stock']];
        foreach([PlanetBuilding::class=>'buildings',PlanetDefense::class=>'defenses',PlanetNpcShip::class=>'ships'] as $class=>$group) {
            foreach($this->repo->all($class,['planet'=>$planet],['id'=>'ASC']) as $row) {
                $quantity=$class===PlanetBuilding::class?$row->level:$row->quantity;if($quantity<1) continue;
                $losses[$group][]=['id'=>$row->id,'slug'=>$row->slug,'tier'=>$row->tier,
                    'label'=>$this->label($member,$group,$row->slug,$row->tier),'quantity'=>$quantity];
            }
        }
        foreach($this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$member,'status'=>'idle'],['id'=>'ASC']) as $fleet) {
            foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet],['id'=>'ASC']) as $ship) if($ship->quantity>0) {
                $losses['ships'][]=['id'=>$ship->id,'fleet_id'=>$fleet->id,'slug'=>$ship->slug,'tier'=>$ship->tier,
                    'label'=>$this->label($member,'ships',$ship->slug,$ship->tier),'quantity'=>$ship->quantity];
            }
            if($fleet->cargo!==[]) $losses['fleet_cargo'][]=['fleet_id'=>$fleet->id,'cargo'=>$fleet->cargo];
        }
        return $losses;
    }

    private function order(PlayerUniverse $member,QueueItem $item): array
    {
        $group=match($item->queueType){'building'=>'buildings','research'=>'technologies',default=>isset($this->catalog->all('defenses')[$item->itemSlug])?'defenses':'ships'};
        return ['id'=>$item->id,'planet_id'=>$item->planet->id,'planet_name'=>$item->planet->name,'type'=>$item->queueType,
            'slug'=>$item->itemSlug,'tier'=>$item->tier,'level'=>$item->itemLevel,'quantity'=>$item->quantity,
            'label'=>$this->label($member,$group,$item->itemSlug,$item->tier),
            'started_at'=>$item->startedAt?->format(DATE_ATOM),'finish_at'=>$item->finishAt()?->format(DATE_ATOM)];
    }

    private function label(PlayerUniverse $member,string $group,string $slug,string $tier): string
    {
        $definition=$this->catalog->forUniverse($member->universe)['catalog'][$group][$slug]??null;
        return $definition!==null && in_array($tier,GameCatalog::TIERS,true)?$this->catalog->withTier($definition,$tier)['label']:str_replace('_',' ',$slug);
    }
}
