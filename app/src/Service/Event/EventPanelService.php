<?php
namespace App\Service\Event;

use App\Entity\{BoostEvent, PlayerUniverse};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class EventPanelService
{
    public function __construct(private GameEventService $events,private ChallengeQueryService $challenges,private EntityManagerInterface $em,private ClockInterface $clock) {}

    public function forPlayer(PlayerUniverse $membership): array
    {
        $items=[];
        foreach ($this->events->visibleFor($membership) as $event) {
            if (!empty($event->parameters['challenge_id'])) continue;
            $items[]=['name'=>$event->name,'description'=>mb_substr($event->description,0,220),'startsAt'=>$event->startsAt,'endsAt'=>$event->endsAt,
                'pending'=>false,'challenge'=>null,'bonus'=>$event->type==='production_bonus'?($event->parameters['percentage'] ?? null):null];
        }
        $events=$this->em->createQueryBuilder()->select('e')->from(BoostEvent::class,'e')->where('e.universe=:universe')->andWhere('e.status IN (:states)')
            ->andWhere('(e.configuration IS NULL OR e.configuration NOT LIKE :archived)')->setParameter('universe',$membership->universe)
            ->setParameter('states',['scheduled','active'])->setParameter('archived','%"archived_at"%')->orderBy('e.scheduledAt','ASC')->getQuery()->getResult();
        foreach ($events as $event) {
            if (!($event->configuration['guided'] ?? false) && !str_starts_with($event->eventKey,'weekly-')) continue;
            $view=$this->challenges->view($event,$membership);
            $items[]=['name'=>$view['name'],'description'=>$view['objective']['label'],'startsAt'=>$event->startedAt ?? $event->scheduledAt,'endsAt'=>$view['expected_end'],
                'pending'=>$event->status==='scheduled' && $event->scheduledAt<=$this->clock->now(),'challenge'=>$view,'bonus'=>null];
        }
        return $items;
    }
}
