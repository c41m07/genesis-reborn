<?php
namespace App\Service\Combat;

use App\Entity\{CombatLog,OccupationEvent};
use App\Repository\GameRepository;

/** Complète l'affichage depuis le journal, sans modifier l'instantané du combat. */
final class OccupationReportState
{
    public function __construct(private GameRepository $repo) {}

    public function forReport(CombatLog $report): ?array
    {
        $capture=$report->snapshot['capture']??null;
        if(!is_array($capture)) return null;
        if(($capture['status']??null)!=='occupying' || !isset($capture['occupation_id'],$capture['captor_player_universe_id'],$capture['capture_at'])) return $capture;
        foreach($this->repo->all(OccupationEvent::class,['occupation'=>$capture['occupation_id']],['id'=>'DESC']) as $event) {
            $before=$event->snapshot['before']??null;
            if(!is_array($before) || $event->occupation->planet->id!==$report->combatEvent->planet->id
                || (int)($before['occupant_id']??0)!==(int)$capture['captor_player_universe_id']
                || ($before['capture_at']??null)!==$capture['capture_at']) continue;
            if(!in_array($event->action,['captured','failed','replaced'],true)) continue;
            $capture['status']=$event->action==='captured'?'captured':'failed';
            $capture['reason']=$event->action==='replaced'?'occupation_lost':($event->snapshot['after']['end_reason']??null);
            $capture[$event->action==='captured'?'captured_at':'ended_at']=$event->occurredAt->format(DATE_ATOM);
            return $capture;
        }
        return $capture;
    }
}
