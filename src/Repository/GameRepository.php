<?php
namespace App\Repository;
use App\Entity\{Player,PlayerUniverse,Universe,Galaxy,SolarSystem,Planet,PlanetResource,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlayerDiscovery,QueueItem,OutboxMessage,CombatLog,CombatParticipant};
use Doctrine\ORM\EntityManagerInterface;
final class GameRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function find(string $class, int $id): ?object { return $this->em->find($class, $id); }
    public function one(string $class, array $criteria): ?object { return $this->em->getRepository($class)->findOneBy($criteria); }
    public function all(string $class, array $criteria = [], array $order = ['id' => 'ASC']): array { return $this->em->getRepository($class)->findBy($criteria, $order); }
    public function espionageReportsFor(PlayerUniverse $membership, int $limit = 50): array
    {
        return $this->em->createQueryBuilder()->select('r')->from(\App\Entity\EspionageReport::class, 'r')
            ->where('r.sender = :membership')->setParameter('membership', $membership)
            ->orderBy('r.createdAt', 'DESC')->setMaxResults(max(1, min(100, $limit)))->getQuery()->getResult();
    }

    public function combatLogsFor(PlayerUniverse $membership, int $limit = 50): array
    {
        return $this->em->createQueryBuilder()->select('DISTINCT l')->from(CombatLog::class, 'l')
            ->join('l.combatEvent', 'e')->join('e.planet', 'p')
            ->leftJoin(CombatParticipant::class, 'cp', 'WITH', 'cp.combatEvent = e')
            ->where('cp.playerUniverse = :membership')->orWhere('p.owner = :membership')
            ->setParameter('membership', $membership)->orderBy('l.resolvedAt', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)))->getQuery()->getResult();
    }
    public function planets(Universe $u, ?PlayerUniverse $owner = null, bool $freeOnly = false): array
    {
        $q = $this->em->createQueryBuilder()->select('p', 's', 'g')->from(Planet::class, 'p')->join('p.solarSystem','s')->join('s.galaxy','g')->where('g.universe = :u')->setParameter('u',$u)->orderBy('p.id','ASC');
        if ($owner) $q->andWhere('p.owner = :owner')->setParameter('owner',$owner);
        if ($freeOnly) $q->andWhere('p.owner IS NULL');
        return $q->getQuery()->getResult();
    }
    public function systems(Universe $u): array
    {
        return $this->em->createQueryBuilder()->select('s','g')->from(SolarSystem::class,'s')->join('s.galaxy','g')->where('g.universe = :u')->setParameter('u',$u)->orderBy('s.id','ASC')->getQuery()->getResult();
    }
    public function queue(Planet $p, ?string $type = null): array
    {
        return $this->all(QueueItem::class, $type ? ['planet'=>$p,'queueType'=>$type] : ['planet'=>$p]);
    }
    public function levels(string $class, array $criteria): array
    {
        $levels=[];
        foreach ($this->all($class,$criteria) as $row) $levels[$row->slug]=$row->level;
        return $levels;
    }
    public function outbox(bool $notifications): array
    {
        return $this->em->createQueryBuilder()->select('o')->from(OutboxMessage::class,'o')
            ->where($notifications?'o.kind = :kind':'o.kind IN (:kind)')->setParameter('kind',$notifications?'notification':['queue','fleet','combat'])
            ->orderBy('o.id','ASC')->setMaxResults(100)->getQuery()->getResult();
    }
    public function activeQueueItems(): array
    {
        return $this->em->createQueryBuilder()->select('q')->from(QueueItem::class,'q')->where('q.startedAt IS NOT NULL')->getQuery()->getResult();
    }
}
