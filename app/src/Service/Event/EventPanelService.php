<?php
namespace App\Service\Event;

use App\Entity\PlayerUniverse;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

final class EventPanelService
{
    public function __construct(private GameEventService $events,private ChallengeQueryService $challenges,private GameRepository $repo,private ClockInterface $clock) {}

    public function forPlayer(PlayerUniverse $membership): array
    {
        $items=[];
        foreach ($this->events->visibleFor($membership) as $event) {
            if (!empty($event->parameters['challenge_id'])) continue;
            $items[]=['name'=>$event->name,'description'=>mb_substr($event->description,0,220),'startsAt'=>$event->startsAt,'endsAt'=>$event->endsAt,
                'pending'=>false,'challenge'=>null,'bonus'=>$event->type==='production_bonus'?($event->parameters['percentage'] ?? null):null];
        }
        // Le panneau garde toutes ses occurrences en cours ou programmées, avec les mêmes critères que la liste.
        $events = $this->repo->playerChallengeEvents($membership, ['scheduled', 'active'], null, false);
        foreach ($events as $event) {
            $view=$this->challenges->view($event,$membership);
            $items[]=['name'=>$view['name'],'description'=>$view['objective']['label'],'startsAt'=>$event->startedAt ?? $event->scheduledAt,'endsAt'=>$view['expected_end'],
                'pending'=>$event->status==='scheduled' && $event->scheduledAt<=$this->clock->now(),'challenge'=>$view,'bonus'=>null];
        }
        return $items;
    }
}
