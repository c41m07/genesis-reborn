<?php
namespace App\Service\Player;

use App\Entity\{CombatEvent,EmpireReconstruction,Fleet,FleetShip,OutboxMessage,Planet,PlanetDefense,PlayerUniverse,QueueItem};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\Uid\Uuid;

final class EmpireReconstructionScheduler
{
    public const COMBAT_DELAY_SECONDS = 300;

    public function __construct(private GameManager $manager,private GameRepository $repo,private ClockInterface $clock) {}

    /** L'appelant détient les verrous de conversion ; les commandes payées restent hors référence. */
    public function captureReferenceLocked(PlayerUniverse $membership): void
    {
        foreach($this->repo->planets($membership->universe,$membership) as $planet) {
            $state=new EmpireReconstruction();$state->planet=$planet;$state->membership=$membership;
            $state->reference=$this->inventory($planet,$membership);
            $this->manager->save($state);
        }
    }

    /** Inclut les retours et les flottes encore déployées, rattachés à leur planète d'origine. */
    public function inventory(Planet $planet,PlayerUniverse $membership): array
    {
        $units=[];
        foreach($this->repo->all(PlanetDefense::class,['planet'=>$planet]) as $defense) {
            $units['defenses'][$defense->slug][$defense->tier]=max(0,$defense->quantity);
        }
        foreach($this->repo->all(Fleet::class,['homePlanet'=>$planet,'playerUniverse'=>$membership]) as $fleet) {
            if($fleet->status==='completed') continue;
            foreach($this->repo->all(FleetShip::class,['fleet'=>$fleet]) as $ship) {
                $units['ships'][$ship->slug][$ship->tier]=($units['ships'][$ship->slug][$ship->tier]??0)+max(0,$ship->quantity);
            }
        }
        return $units;
    }

    public function forPlanet(Planet $planet): ?EmpireReconstruction
    {
        if(!$planet->owner?->isForgotten()) return null;
        return $this->repo->one(EmpireReconstruction::class,['planet'=>$planet,'membership'=>$planet->owner]);
    }

    /** Appelé avant l'ouverture du combat, après règlement des commandes antérieures à son arrivée. */
    public function pauseLocked(Planet $planet,\DateTimeImmutable $at): void
    {
        $state=$this->forPlanet($planet);
        if($state===null) return;
        $this->manager->lock($state);
        $item=$state->queueItem;
        if($item!==null && $item->startedAt!==null) {
            $elapsed=max(0,$at->getTimestamp()-$item->startedAt->getTimestamp());
            $item->durationSeconds=max(1,$item->durationSeconds-$elapsed);
            $item->startedAt=null;$item->dispatchToken=Uuid::v4()->toRfc4122();
            $planet->shipyardQueueStartedAt=null;
        }
        $state->nextAt=null;$state->dispatchToken=Uuid::v4()->toRfc4122();
    }

    public function afterCombatLocked(Planet $planet): void
    {
        $state=$this->forPlanet($planet);
        if($state===null) return;
        $this->manager->lock($state);
        $this->scheduleLocked($state,$this->clock->now()->modify('+'.self::COMBAT_DELAY_SECONDS.' seconds'));
    }

    public function afterCompletionLocked(QueueItem $item): void
    {
        if(!$item->playerUniverse->isForgotten()) return;
        $state=$this->forPlanet($item->planet);
        if($state===null) return;
        if($state->queueItem?->id===$item->id) $state->queueItem=null;
        // Un ancien ordre payé peut finir pendant le combat, sans réveiller la reconstruction.
        if($this->repo->one(CombatEvent::class,['planet'=>$item->planet,'status'=>'pending'])!==null) return;
        if($state->nextAt===null) $this->scheduleLocked($state,$this->clock->now());
    }

    public function scheduleLocked(EmpireReconstruction $state,\DateTimeImmutable $at): void
    {
        $state->nextAt=$at;$state->dispatchToken=Uuid::v4()->toRfc4122();
        $outbox=new OutboxMessage();$outbox->kind='reconstruction';$outbox->targetId=$state->id;
        $outbox->token=$state->dispatchToken;$outbox->dueAt=$at;$this->manager->save($outbox);
    }

    public function stopLocked(Planet $planet): void
    {
        $state=$this->forPlanet($planet);
        if($state===null) return;
        $this->manager->lock($state);
        $state->queueItem=null;$state->nextAt=null;$state->dispatchToken=Uuid::v4()->toRfc4122();
    }
}
