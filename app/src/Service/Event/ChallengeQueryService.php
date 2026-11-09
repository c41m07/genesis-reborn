<?php
namespace App\Service\Event;

use App\Entity\{BoostEvent, PlayerBoost, PlayerUniverse, Universe};
use App\Repository\GameRepository;
use App\Service\Admin\AdminEventPlanner;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ChallengeQueryService
{
    public const STATUSES=['scheduled'=>'Planifié','active'=>'En cours','closed'=>'Terminé','cancelled'=>'Annulé'];
    public function __construct(private EntityManagerInterface $em,private GameRepository $repo,private ClockInterface $clock) {}

    public function view(BoostEvent $event,?PlayerUniverse $membership=null): array
    {
        $guided=(bool)($event->configuration['guided'] ?? false);$objective=AdminEventPlanner::OBJECTIVES[$event->templateKey] ?? ['label'=>$event->templateKey,'help'=>''];
        $score=$membership ? $this->repo->boostEventContributionTotal($event,$membership) : 0;
        $reward=$event->configuration['reward'] ?? ['kind'=>'boost'];
        $status=self::STATUSES[$event->status] ?? $event->status;
        if ($event->status==='scheduled' && $event->scheduledAt<=$this->clock->now()) $status='En attente de démarrage';
        if ($event->status==='active' && $event->endsAt && $event->endsAt<=$this->clock->now()) $status='Clôture en cours';
        return ['event'=>$event,'guided'=>$guided,'name'=>$event->configuration['name'] ?? 'Rotation hebdomadaire · '.$objective['label'],
            'description'=>$event->configuration['description'] ?? '', 'objective'=>$objective,'status_label'=>$status,
            'goal'=>$event->globalGoal,'minimum'=>(float)($event->configuration['minimum_contribution'] ?? 0),'score'=>$score,
            'reward'=>$reward,'reward_label'=>($reward['kind'] ?? 'boost')==='resource_box' ? $reward['box']['label'] : 'Un boost de réduction de durée',
            'earned'=>$membership ? $this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceKey'=>'event-'.$event->eventKey.'-player-'.$membership->id]) : null,
            'goal_reached'=>$event->globalGoal!==null && $event->totalContribution >= $event->globalGoal,
            'progress'=>$event->globalGoal>0 ? min(100,100*$event->totalContribution/$event->globalGoal) : 0,
            'expected_end'=>$event->endsAt ?? $event->scheduledAt->modify('+'.$event->durationSeconds.' seconds')];
    }

    public function forPlayer(PlayerUniverse $membership): array
    {
        $events=$this->em->createQueryBuilder()->select('e')->from(BoostEvent::class,'e')->where('e.universe=:universe')->andWhere('e.configuration IS NOT NULL')
            ->andWhere('e.status IN (:states)')->setParameter('universe',$membership->universe)->setParameter('states',['scheduled','active','closed','cancelled'])->orderBy('e.scheduledAt','DESC')->setMaxResults(50)->getQuery()->getResult();
        return array_map(fn(BoostEvent $event):array=>$this->view($event),array_values(array_filter($events,static fn(BoostEvent $event):bool=>(bool)($event->configuration['guided'] ?? false))));
    }

    public function search(int $universe,string $status,int $page): array
    {
        $query=$this->em->createQueryBuilder()->select('e','u')->from(BoostEvent::class,'e')->join('e.universe','u');
        if ($universe>0) $query->andWhere('e.universe=:universe')->setParameter('universe',$universe);
        if ($status!=='') $query->andWhere('e.status=:status')->setParameter('status',$status);
        $total=(int)(clone $query)->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();$pages=max(1,(int)ceil($total/50));$page=min($pages,max(1,$page));
        $events=$query->orderBy('e.scheduledAt','DESC')->addOrderBy('e.id','DESC')->setFirstResult(($page-1)*50)->setMaxResults(50)->getQuery()->getResult();
        return ['rows'=>array_map(fn(BoostEvent $event):array=>$this->view($event),$events),'total'=>$total,'page'=>$page,'pages'=>$pages,'selected_universe'=>$universe,'selected_status'=>$status,'statuses'=>self::STATUSES];
    }

    public function contributors(BoostEvent $event,int $page): array
    {
        $connection=$this->em->getConnection();$total=(int)$connection->fetchOne('SELECT COUNT(DISTINCT player_universe_id) FROM boost_event_contribution WHERE event_id=?',[$event->id]);
        $pages=max(1,(int)ceil($total/50));$page=min($pages,max(1,$page));$offset=($page-1)*50;
        $rows=$connection->fetchAllAssociative("SELECT m.id AS membership_id,COALESCE(p.username,former.username,'Empire oublié') AS player_name,SUM(c.value) AS contribution FROM boost_event_contribution c INNER JOIN player_universe m ON m.id=c.player_universe_id LEFT JOIN player p ON p.id=m.player_id LEFT JOIN player former ON former.id=m.former_player_id WHERE c.event_id=:event GROUP BY m.id,p.username,former.username ORDER BY contribution DESC,m.id ASC LIMIT 50 OFFSET $offset",['event'=>$event->id]);
        $rewards=[];$keys=array_map(static fn(array $row):string=>'event-'.$event->eventKey.'-player-'.$row['membership_id'],$rows);
        if ($keys) foreach ($this->em->getRepository(PlayerBoost::class)->findBy(['sourceKey'=>$keys]) as $item) $rewards[$item->playerUniverse->id]=$item;
        foreach ($rows as &$row) $row['reward']=$rewards[$row['membership_id']] ?? null;
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
}
