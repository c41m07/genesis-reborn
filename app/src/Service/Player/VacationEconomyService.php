<?php
namespace App\Service\Player;

use App\Entity\{PlayerUniverse, VacationPeriod};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Queue\QueueService;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class VacationEconomyService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private ActionGuard $guard,
        private ResourceService $resources,
        private QueueService $queues,
        private VacationPolicy $policy,
        private EmpireActivityService $activity,
        private \App\Service\Fleet\FleetService $fleets,
        private ClockInterface $clock,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $enabled,
    ) {}

    public function settings(PlayerUniverse $membership): array
    {
        $now=$this->clock->now();
        $latest=$this->repo->all(VacationPeriod::class,['membership'=>$membership],['startedAt'=>'DESC','id'=>'DESC'])[0]??null;
        $active=$latest?->isActiveAt($now)?$latest:null;
        $nextActivation=$latest!==null?$this->policy->nextActivationAt($latest):null;
        $earliestEnd=$active?->startedAt->modify('+'.VacationPolicy::MINIMUM_SECONDS.' seconds');
        $blocker=null;
        if(!$this->enabled) $blocker='Le mode vacances n’est pas disponible actuellement.';
        elseif($active!==null) $blocker='Le mode vacances est déjà actif.';
        elseif($nextActivation!==null && $nextActivation>$now) $blocker='Attendez la fin du délai de 15 jours avant une nouvelle activation.';
        elseif($this->repo->hasVacationFleetBlocker($membership)) $blocker='Toutes vos flottes doivent être rentrées. Une attaque entrante ou un combat en cours empêche aussi les vacances.';
        return ['enabled'=>$this->enabled,'active'=>$active,'can_start'=>$blocker===null,'start_blocker'=>$blocker,
            'can_finish'=>$active!==null && $earliestEnd<=$now,'earliest_end_at'=>$earliestEnd,
            'next_activation_at'=>$nextActivation,'cooldown'=>$active===null && $nextActivation!==null && $nextActivation>$now];
    }

    public function start(PlayerUniverse $membership): VacationPeriod
    {
        if (!$this->enabled) throw new \DomainException('Le mode vacances n’est pas encore disponible.');
        return $this->manager->transaction(function () use ($membership): VacationPeriod {
            $this->guard->lockMembership($membership);
            $previous = $this->repo->all(VacationPeriod::class, ['membership' => $membership], ['startedAt' => 'DESC', 'id' => 'DESC'])[0] ?? null;
            $period = $this->policy->plan($membership, $previous);
            if ($this->repo->hasVacationFleetBlocker($membership)) {
                throw new \DomainException('Toutes vos flottes doivent être rentrées. Une attaque entrante ou un combat en cours empêche aussi les vacances.');
            }
            $this->fleets->returnPendingOrbitsForVacationLocked($membership);
            $this->queues->pauseForVacationLocked($membership, $period->startedAt, $period->scheduledEndAt);
            $planets = $this->repo->planets($membership->universe, $membership);
            foreach ($planets as $planet) $this->resources->materialize($planet, $period->startedAt);
            $this->manager->save($period);
            $this->manager->flush();
            foreach ($planets as $planet) $this->resources->refreshProduction($planet);
            return $period;
        });
    }

    public function finish(PlayerUniverse $membership): VacationPeriod
    {
        return $this->manager->transaction(function () use ($membership): VacationPeriod {
            $this->guard->lockMembership($membership);
            $period = $this->repo->all(VacationPeriod::class, ['membership' => $membership], ['startedAt' => 'DESC', 'id' => 'DESC'])[0] ?? null;
            if (!$period instanceof VacationPeriod) throw new \DomainException('Aucune période de vacances à terminer.');
            $this->manager->lock($period);
            if ($period->endedAt !== null) return $period;
            $this->policy->finish($period);
            $this->manager->flush();
            $unusedSeconds = $period->scheduledEndAt->getTimestamp() - $period->endedAt->getTimestamp();
            $this->queues->resumeAfterVacationLocked($membership, $unusedSeconds);
            $this->manager->flush();
            $this->queues->synchronize($membership);
            foreach ($this->repo->planets($membership->universe, $membership) as $planet) {
                $this->resources->materialize($planet, $this->clock->now());
                $this->resources->refreshProduction($planet);
            }
            return $period;
        });
    }
}
