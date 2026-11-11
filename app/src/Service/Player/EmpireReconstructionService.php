<?php
namespace App\Service\Player;

use App\Entity\{CombatEvent,EmpireReconstruction,PlanetBuilding,PlayerTechnology};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Queue\{OperationCalculator,QueueService};
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class EmpireReconstructionService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private EmpireReconstructionScheduler $scheduler,
        private QueueService $queues,private ResourceService $resources,private GameCatalog $catalog,private OperationCalculator $calculator,
        private ClockInterface $clock,#[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled) {}

    public function rebuild(int $id,string $token): string
    {
        if(!$this->enabled) return 'disabled';
        return $this->manager->transaction(function()use($id,$token): string {
            $state=$this->repo->find(EmpireReconstruction::class,$id);
            if($state===null) return 'not_found';
            $membership=$state->membership;$planet=$state->planet;
            $this->manager->lock($membership);$this->manager->lock($membership->universe);
            $this->manager->lock($planet);$this->manager->lock($state);
            if(!hash_equals($state->dispatchToken,$token) || $state->nextAt===null) return 'stale';
            if(!$membership->isForgotten() || $planet->owner?->id!==$membership->id) return 'owner_changed';
            if(!$membership->universe->isAccessible($this->clock->now())) return 'universe_closed';
            if($this->repo->one(CombatEvent::class,['planet'=>$planet,'status'=>'pending'])!==null) return 'combat_pending';
            if($state->nextAt>$this->clock->now()) {
                $this->scheduler->scheduleLocked($state,$state->nextAt);
                return 'early';
            }
            $state->nextAt=null;
            $this->queues->synchronize($membership);
            $state->nextAt=null;
            if($state->queueItem!==null) {
                $this->queues->resumeReconstructionLocked($state->queueItem);
                return 'resumed';
            }
            $queue=$this->repo->queue($planet,'shipyard');
            if($queue!==[]) {
                $this->scheduler->scheduleLocked($state,max($this->clock->now()->modify('+60 seconds'),$queue[0]->finishAt()??$this->clock->now()));
                return 'shipyard_busy';
            }
            $inventory=$this->scheduler->inventory($planet,$membership);
            $buildings=$this->repo->levelsByTier(PlanetBuilding::class,['planet'=>$planet]);
            $technologies=$this->repo->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$membership]);
            $config=$this->catalog->forUniverse($membership->universe);$candidates=[];
            foreach($state->reference as $group=>$models) foreach($models as $slug=>$tiers) foreach($tiers as $tier=>$quantity) {
                $missing=max(0,$quantity-($inventory[$group][$slug][$tier]??0));
                if($missing<1) continue;
                $definition=$this->catalog->getTierForUniverse($membership->universe,$group,$slug,$tier);
                $plan=$this->calculator->calculate($definition,$group,1,1,$buildings,$config,$technologies);
                $value=0;foreach(['metal'=>2,'nano'=>4,'antimatter'=>8,'hydrogen'=>1] as $resource=>$weight) $value+=($plan['costs'][$resource]??0)*$weight;
                $candidates[]=['group'=>$group,'slug'=>$slug,'tier'=>$tier,'missing'=>$missing,'definition'=>$definition,'value'=>$value,'duration'=>$plan['duration']];
            }
            usort($candidates,static fn(array $a,array $b): int=>($a['value']<=>$b['value'])?:($a['duration']<=>$b['duration'])?:strcmp($a['slug'],$b['slug'])?:strcmp($a['tier'],$b['tier'])?:strcmp($a['group'],$b['group']));
            $stocks=$this->resources->snapshot($planet);
            foreach($candidates as $candidate) {
                $quantity=$this->calculator->maximum($candidate['definition'],$candidate['group'],$stocks,$buildings,$config,$technologies,1);
                if($quantity<1) continue;
                // Une unité par commande : livraison et interruption sans accélération par lot.
                $state->queueItem=$this->queues->addReconstructionLocked($membership,$planet,$candidate['group'],$candidate['slug'],$candidate['tier']);
                return 'started';
            }
            if($candidates!==[]) {
                $this->scheduler->scheduleLocked($state,$this->clock->now()->modify('+60 seconds'));
                return 'resources_missing';
            }
            return 'complete';
        });
    }
}
