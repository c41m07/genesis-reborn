<?php
namespace App\Service\Player;

use App\Entity\PlayerUniverse;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

final class PlayerProtection
{
    public function __construct(private GameCatalog $catalog, private ClockInterface $clock) {}

    public function status(PlayerUniverse $membership, int $points): array
    {
        $now=$this->clock->now();
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
