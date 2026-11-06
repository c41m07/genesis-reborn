<?php
namespace App\Service\Operations;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AlertEvaluator
{
    public function __construct(
        #[Autowire('%env(int:OPS_ALERT_SIGNAL_SECONDS)%')] private int $signalSeconds=120,
        #[Autowire('%env(int:OPS_ALERT_RELAY_SECONDS)%')] private int $relaySeconds=300,
    ) { if ($signalSeconds<1 || $relaySeconds<1) throw new \InvalidArgumentException('Seuil d’alerte invalide.'); }
    public function evaluate(array $snapshot, array $states, ?int $failedCount): array
    {
        $conditions=[];$unknown=false;
        foreach ($snapshot['processes'] as $index=>$process) {
            $key=['worker','relay','notifier'][$index];$state=$states[$key]??null;
            if (in_array($state,['exited','dead','paused'],true)) {
                $conditions[$key]=['status'=>'alert','message'=>$key.' : processus arrêté ou en pause (supervision : '.$state.').','grace'=>0];
            } elseif ($state!=='running' && $state!=='restarting') {
                $conditions[$key]=['status'=>'unknown','message'=>$key.' : supervision indisponible.'];$unknown=true;
            } elseif ($process['status']==='unknown') {
                $conditions[$key]=['status'=>'unknown','message'=>$key.' : signal illisible ou horodatage incohérent.'];$unknown=true;
            } elseif ($state==='restarting' || $process['status']==='missing') {
                $conditions[$key]=['status'=>'alert','message'=>$key.' : redémarrage ou aucun signal reçu depuis le début de l’observation.','grace'=>$this->signalSeconds];
            } else {
                $late=$process['age_seconds']>=$this->signalSeconds;
                $conditions[$key]=['status'=>$late?'alert':'ok','message'=>$key.' : dernier signal il y a '.$process['age_seconds'].' secondes.','grace'=>0];
            }
        }
        if (!$snapshot['outbox_available']) {
            $conditions['outbox']=['status'=>'unknown','message'=>'Collecte des relais indisponible.'];$unknown=true;
        } else {
            $delay=$snapshot['outbox_pending'][0]['overdue_seconds']??0;
            $conditions['outbox']=['status'=>$delay>=$this->relaySeconds?'alert':'ok','message'=>'Retard maximal des publications en attente : '.$delay.' secondes.','grace'=>0];
        }
        $conditions['failed']=['status'=>$failedCount===null?'unknown':($failedCount>0?'alert':'ok'),'message'=>$failedCount===null?'Transport d’échecs indisponible.':$failedCount.' message(s) dans le transport d’échecs.','grace'=>0];
        $unknown=$unknown||$failedCount===null||!$snapshot['database_healthy'];
        $conditions['collection']=['status'=>$unknown?'alert':'ok','message'=>$unknown?'Un relevé de supervision, de base ou de transport est indisponible ; les états inconnus ne prouvent pas un rétablissement.':'Toutes les collectes sont disponibles.','grace'=>0];
        return $conditions;
    }
}
