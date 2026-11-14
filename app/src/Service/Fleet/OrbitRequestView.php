<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,Planet,PlayerDiscovery,PlayerUniverse};
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

final class OrbitRequestView
{
    public function __construct(private GameRepository $repo,private OrbitRequestState $requests,private ClockInterface $clock) {}

    public function forMember(PlayerUniverse $viewer,?Planet $planet=null): array
    {
        if(!$viewer->isPlayerControlled() || $viewer->player->lockedAt!==null) return [];
        if($planet && ($planet->owner?->id!==$viewer->id || $planet->solarSystem->galaxy->universe->id!==$viewer->universe->id)) return [];
        $known=[];
        foreach($this->repo->all(PlayerDiscovery::class,['playerUniverse'=>$viewer]) as $d) $known[$d->solarSystem->id]=true;
        $rows=[];
        foreach($this->repo->orbitRequestsFor($viewer) as $fleet) {
            if($fleet->mission!=='orbit' || !in_array($fleet->status,['traveling','orbit_pending'],true)
                || $fleet->homePlanet->solarSystem->galaxy->universe->id!==$viewer->universe->id
                || !$fleet->destination || $fleet->destination->solarSystem->galaxy->universe->id!==$viewer->universe->id) continue;
            $state=$this->requests->read($fleet);
            $outgoing=$fleet->playerUniverse->id===$viewer->id;
            $incoming=$fleet->destination->owner?->id===$viewer->id
                && ($state===null || ($state['recipient_id']??null)===$viewer->id);
            if(!$outgoing && !$incoming) continue;
            if($planet && ($outgoing?$fleet->homePlanet->id:$fleet->destination->id)!==$planet->id) continue;
            if($state===null && $fleet->status!=='orbit_pending') continue;
            if($state!==null && !in_array($state['status'],['pending','accepted_auto','accepted_manual'],true)) continue;
            $expired=$state!==null && (!$fleet->arrivalAt || $fleet->arrivalAt<=$this->clock->now());
            $originKnown=$outgoing || isset($known[$fleet->homePlanet->solarSystem->id]);
            $rows[]=['id'=>$fleet->id,'outgoing'=>$outgoing,'legacy'=>$state===null,
                'sender'=>$fleet->playerUniverse->getDisplayName(),
                'origin'=>$originKnown?$fleet->homePlanet->name.' · '.$fleet->homePlanet->coordinates():null,
                'target'=>$fleet->destination->name,'hours'=>$fleet->orbitDurationSeconds/3600,
                'status'=>$expired?'due':($state['status']??'pending'),
                'deadline'=>$state!==null?$fleet->arrivalAt?->format(DATE_ATOM):null,
                'can_respond'=>$incoming && !$expired && ($state===null || $state['status']==='pending'),
                'key'=>$incoming?($state['key']??''):'',
            ];
        }
        return $rows;
    }
}
