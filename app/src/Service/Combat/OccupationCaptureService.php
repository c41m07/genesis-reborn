<?php
namespace App\Service\Combat;

use App\Entity\{CombatEvent,CombatLog,FleetShip,PlanetOccupation};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ResourceService;
use App\Service\Fleet\FleetService;
use App\Service\Queue\QueueService;
use App\Service\Boost\BoostGrantService;
use App\Service\Notification\NotificationService;
use App\Service\Universe\PlanetNameGenerator;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Capture sous verrou, appelée par l'échéance différée et les reprises après combat. */
final class OccupationCaptureService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private OccupationReadiness $readiness,
        private OccupationPolicy $policy,private QueueService $queues,private ResourceService $resources,private FleetService $fleets,
        private BoostGrantService $boosts,private NotificationService $notifications,private PlanetNameGenerator $planetNames,private ClockInterface $clock,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled) {}

    public function capture(int $id,string $token): array
    {
        if(!$this->enabled) throw new \DomainException('Les captures après occupation sont désactivées.');
        return $this->manager->transaction(function()use($id,$token): array {
            $occupation=$this->repo->find(PlanetOccupation::class,$id);
            if($occupation===null) return ['status'=>'not_found'];
            $expectedOccupant=$occupation->occupant->id;$expectedOwner=$occupation->planet->owner?->id;
            $members=[$occupation->occupant->id=>$occupation->occupant];
            if($occupation->planet->owner!==null) $members[$occupation->planet->owner->id]=$occupation->planet->owner;
            ksort($members,SORT_NUMERIC);foreach($members as $member) $this->manager->lock($member);
            $this->manager->lock($occupation->occupant->universe);$this->manager->lock($occupation->planet);$this->manager->lock($occupation);
            if(!$occupation->reservesSlot()) return ['status'=>'already_finished'];
            if(!hash_equals($occupation->dispatchToken,$token)) return ['status'=>'stale'];
            if($occupation->occupant->id!==$expectedOccupant || $occupation->planet->owner?->id!==$expectedOwner) return ['status'=>'stale'];
            if(!$occupation->occupant->universe->isAccessible($this->clock->now())) return ['status'=>'universe_closed'];
            $planet=$occupation->planet;$member=$occupation->occupant;$former=$planet->owner;
            if($this->repo->one(CombatEvent::class,['planet'=>$planet,'status'=>'pending'])!==null) return ['status'=>'combat_pending'];
            // Les commandes échues restent acquises à l'ancien empire avant l'évaluation des forces.
            if($former?->isForgotten()) $this->queues->synchronize($former);
            $facts=$this->readiness->inspect($occupation);$decision=$facts['decision'];
            if($decision!=='ready') {
                if(!in_array($decision,OccupationPolicy::FAILURE_REASONS,true)) return ['status'=>$decision];
                $event=$this->policy->finish($occupation,$decision);
                $event->snapshot['returned_fleet_ids']=$this->fleets->returnOccupationFleetsLocked($planet,$member,$token);
                $this->manager->save($event);return ['status'=>'failed','reason'=>$decision];
            }
            $colony=$this->repo->find(FleetShip::class,$facts['colony_ship_ids'][0]);$this->manager->lock($colony);
            if($colony->quantity<1) throw new \DomainException('Le vaisseau de colonisation n’est plus disponible.');
            $this->queues->synchronize($member);
            $cancelled=$this->queues->cancelForCaptureLocked($planet,$former);
            $this->resources->materialize($planet,$this->clock->now());
            --$colony->quantity;$planet->acquireBy($member,$this->clock->now());$planet->name=$this->planetNames->generate($member);
            $event=$this->policy->finish($occupation,'ready');
            $event->snapshot['capture']=['former_owner_id'=>$former?->id,'colony_ship_id'=>$colony->id,'cancelled_orders'=>$cancelled];
            $this->manager->save($event);$this->manager->flush();
            $this->resources->refreshProduction($planet);
            $report=$this->repo->one(CombatLog::class,['combatEvent'=>$occupation->originCombat]);
            if($report!==null && ($report->snapshot['capture']['occupation_id']??null)===$occupation->id) {
                $report->snapshot['capture']['status']='captured';$report->snapshot['capture']['reason']=null;
                $report->snapshot['capture']['captured_at']=$this->clock->now()->format(DATE_ATOM);
            }
            $this->boosts->awardRiskMission($member,$colony->fleet);
            $event->snapshot['returned_fleet_ids']=$this->fleets->returnOccupationFleetsLocked($planet,$member,$token);
            $this->notifications->enqueue($member,'Planète conquise après occupation : '.$planet->name.'. Bâtiments et stocks conservés.',journalKind:'conquest');
            return ['status'=>'captured','planet_id'=>$planet->id];
        });
    }
}
