<?php
namespace App\Service\Player;

use App\Entity\{AllianceContract,EmpireConversion,Guild,GuildMember,GuildRelation,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Fleet\FleetService;
use App\Service\Queue\QueueService;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/** Conversion sous verrou ; l'application par lots reste soumise au drapeau de fonctionnalité. */
final class EmpireConversionService
{
    public function __construct(private GameManager $manager,private GameRepository $repo,private ActionGuard $guard,
        private EmpireActivityService $activity,private FleetService $fleets,private QueueService $queues,private ClockInterface $clock,private \App\Service\Combat\OccupationService $occupations,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled, private GuildProjectService $projects,private EmpireReconstructionScheduler $reconstruction) {}

    public function convert(PlayerUniverse $membership): array
    {
        if(!$this->enabled) throw new \DomainException('Les conversions d’empires sont désactivées.');
        return $this->convertMembership($membership,false);
    }

    public function convertForAccountDeletion(PlayerUniverse $membership): array
    {
        return $this->convertMembership($membership,true);
    }

    private function convertMembership(PlayerUniverse $membership,bool $accountDeletion): array
    {
        return $this->manager->transaction(function()use($membership,$accountDeletion): array {
            if ($accountDeletion) { $this->manager->lock($membership);$this->manager->lock($membership->universe); }
            else $this->guard->lockMembershipForProcessing($membership);
            if($membership->isForgotten()) return ['status'=>'already_converted'];
            $now=$this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
            $state=$this->activity->snapshot($membership,$now);
            if(!$membership->isPlayerControlled() || (!$accountDeletion && !$state['conversion_due'])) return ['status'=>'not_due'];
            $inventory=$this->repo->forgottenEmpirePreviewInventory($membership);
            if($inventory['open_combats']>0) return ['status'=>'deferred_combat'];
            $this->manager->lock($membership->player);
            $log=new EmpireConversion();$log->membership=$membership;$log->activityAt=$membership->lastActivityAt??$membership->joinedAt;
            $log->dueAt=$accountDeletion?$now:new \DateTimeImmutable($state['conversion_due_at']);$log->convertedAt=$now;
            $log->effects['inventory_before']=$inventory;
            if ($accountDeletion) $log->effects['reason']='account_deletion';
            $supportReturns=[];
            foreach($this->repo->all(\App\Entity\PlanetOccupation::class,['occupant'=>$membership,'status'=>'active']) as $occupation) {
                $this->manager->lock($occupation->planet);$this->manager->lock($occupation);
                $supportReturns=[...$supportReturns,...$this->fleets->returnOccupationFleetsLocked($occupation->planet,$membership,$occupation->dispatchToken)];
            }
            $log->effects['cancelled_occupation_ids']=$this->occupations->cancelForConversionLocked($membership);
            $log->effects['returned_fleet_ids']=array_values(array_unique([...$supportReturns,...$this->fleets->returnFleetsForEmpireConversionLocked($membership)]));
            $log->effects['social']=$this->leaveSocialLinks($membership,$now);
            if ($accountDeletion) {
                foreach ($this->repo->all(\App\Entity\VacationPeriod::class,['membership'=>$membership,'endedAt'=>null]) as $vacation) {
                    $this->manager->lock($vacation);
                    $vacation->endedAt=min($now,$vacation->scheduledEndAt);
                    $this->queues->resumeAfterVacationLocked($membership,$vacation->scheduledEndAt->getTimestamp()-$vacation->endedAt->getTimestamp());
                }
                $this->manager->flush();
            }
            if ($membership->universe->isAccessible($now)) $this->queues->synchronize($membership);
            $this->reconstruction->captureReferenceLocked($membership);
            $membership->detachPlayer($now);
            $this->manager->save($log);
            return ['status'=>'converted'];
        });
    }

    private function leaveSocialLinks(PlayerUniverse $membership,\DateTimeImmutable $now): array
    {
        $ended=[];
        foreach($this->repo->all(AllianceContract::class,['playerA'=>$membership]) as $contract) $ended[$contract->id]=$contract;
        foreach($this->repo->all(AllianceContract::class,['playerB'=>$membership]) as $contract) $ended[$contract->id]=$contract;
        $allianceIds=[];
        foreach($ended as $contract) {
            if(!in_array($contract->status,AllianceContract::ACTIVE_STATUSES,true)) continue;
            $contract->status='ended';$allianceIds[]=$contract->id;
        }
        $result=['ended_alliance_ids'=>$allianceIds,'guilds'=>[]];
        foreach($this->repo->all(Guild::class,['leader'=>$membership,'archivedAt'=>null]) as $guild) {
            $this->manager->lock($guild);
            $members=$this->repo->all(GuildMember::class,['guild'=>$guild],['id'=>'ASC']);
            $successor=null;
            foreach($members as $member) {
                $candidate=$member->playerUniverse;
                if($candidate->id===$membership->id || !$candidate->isPlayerControlled() || $candidate->player->lockedAt!==null) continue;
                $seconds=$this->activity->inactiveSeconds($candidate,$now);
                if($seconds!==null && $seconds<EmpireActivityService::INACTIVE_AFTER_SECONDS) { $successor=$candidate;break; }
            }
            if($successor!==null) {
                $guild->leader=$successor;
                $result['guilds'][]=['id'=>$guild->id,'successor_id'=>$successor->id,'archived'=>false];
            } else {
                $guild->archivedAt=$now;
                $this->projects->abandonForGuild($guild);
                $roster=array_map(static fn(GuildMember $row): array=>['member_id'=>$row->id,'membership_id'=>$row->playerUniverse->id],$members);
                $result['guilds'][]=['id'=>$guild->id,'successor_id'=>null,'archived'=>true,'roster'=>$roster];
                foreach($members as $member) $this->manager->remove($member);
                foreach($this->repo->all(GuildRelation::class,['guildA'=>$guild]) as $relation) { $relation->status='neutral';$relation->proposal=null; }
                foreach($this->repo->all(GuildRelation::class,['guildB'=>$guild]) as $relation) { $relation->status='neutral';$relation->proposal=null; }
            }
        }
        $member=$this->repo->one(GuildMember::class,['playerUniverse'=>$membership]);
        if($member!==null) { $result['left_guild_id']=$member->guild->id;$this->manager->remove($member); }
        return $result;
    }
}
