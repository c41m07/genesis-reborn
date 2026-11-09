<?php
namespace App\Service\Player;

use App\Entity\PlayerUniverse;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PlayerProtection
{
    public function __construct(
        private GameCatalog $catalog,
        private ClockInterface $clock,
        private EmpireActivityService $activity,
        private VacationCalendar $vacations,
        #[Autowire('%env(bool:FORGOTTEN_EMPIRES_ENABLED)%')] private bool $forgottenEmpiresEnabled,
    ) {}

    public function suspendedForInactivity(PlayerUniverse $membership): bool
    {
        return $this->forgottenEmpiresEnabled && $this->activity->snapshot($membership)['status'] === 'inactive';
    }

    public function status(PlayerUniverse $membership, int $points): array
    {
        if(!$membership->isPlayerControlled()) return ['active'=>false,'until'=>null,'remaining'=>0,'reason'=>null];
        $now=$this->clock->now();
        $vacation=$this->vacations->currentPeriod($membership,$now);
        if($vacation!==null) {
            $until=$vacation->effectiveEndAt();
            return ['active'=>true,'until'=>$until->format(DATE_ATOM),'remaining'=>$until->getTimestamp()-$now->getTimestamp(),'reason'=>'Mode vacances'];
        }
        if ($this->suspendedForInactivity($membership)) {
            return ['active'=>false,'until'=>null,'remaining'=>0,'reason'=>null];
        }
        $rules=$this->catalog->rules()['new_player_protection']??[];
        $duration=max(0,(int)($rules['duration_seconds']??0));
        $end=null;$reason=null;
        $beginnerEnd=$membership->joinedAt->modify('+'.$duration.' seconds');
        if($duration>0 && $beginnerEnd>$now && $points<max(0,(int)($rules['score_limit']??0))) {
            $end=$beginnerEnd;$reason='Protection débutant';
        }
        if($membership->recoveryProtectedUntil!==null && $membership->recoveryProtectedUntil>$now && ($end===null || $membership->recoveryProtectedUntil>$end)) {
            $end=$membership->recoveryProtectedUntil;$reason='Protection après pertes';
        }
        return ['active'=>$end!==null,'until'=>$end?->format(DATE_ATOM),'remaining'=>$end===null?0:$end->getTimestamp()-$now->getTimestamp(),'reason'=>$reason];
    }
}
