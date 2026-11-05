<?php
namespace App\Repository;
use App\Entity\{Player,PlayerUniverse,Universe,Galaxy,SolarSystem,Planet,PlanetResource,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlayerDiscovery,QueueItem,OutboxMessage,CombatLog,CombatParticipant,PlayerBoost,BoostEvent,BoostEventContribution,GameEvent};
use Doctrine\ORM\EntityManagerInterface;
final class GameRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function find(string $class, int $id): ?object { return $this->em->find($class, $id); }
    public function one(string $class, array $criteria): ?object { return $this->em->getRepository($class)->findOneBy($criteria); }
    public function all(string $class, array $criteria = [], array $order = ['id' => 'ASC']): array { return $this->em->getRepository($class)->findBy($criteria, $order); }
    /** @return list<GameEvent> */
    public function visibleGameEvents(PlayerUniverse $membership, \DateTimeImmutable $now): array
    {
        return $this->em->createQueryBuilder()->select('e')->from(GameEvent::class, 'e')
            ->where('e.status IN (:statuses)')->andWhere('e.visibleToPlayers = true')->andWhere('e.endsAt > :now')
            ->andWhere('(e.universe IS NULL OR e.universe = :universe)')->setParameter('statuses', ['active', 'planned'])->setParameter('now', $now)->setParameter('universe', $membership->universe)
            ->orderBy('e.endsAt', 'ASC')->getQuery()->getResult();
    }
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

    /** @return list<Fleet> */
    public function activeFleetsInUniverse(Universe $universe): array
    {
        return $this->em->createQueryBuilder()->select('f', 'pu', 'home', 'homeSystem', 'homeGalaxy', 'destination', 'destinationSystem', 'destinationGalaxy', 'directSystem', 'directGalaxy')
            ->from(Fleet::class, 'f')
            ->join('f.playerUniverse', 'pu')
            ->join('f.homePlanet', 'home')
            ->join('home.solarSystem', 'homeSystem')
            ->join('homeSystem.galaxy', 'homeGalaxy')
            ->leftJoin('f.destination', 'destination')
            ->leftJoin('destination.solarSystem', 'destinationSystem')
            ->leftJoin('destinationSystem.galaxy', 'destinationGalaxy')
            ->leftJoin('f.destinationSystem', 'directSystem')
            ->leftJoin('directSystem.galaxy', 'directGalaxy')
            ->where('pu.universe = :universe')
            ->andWhere('f.status IN (:statuses)')
            ->setParameter('universe', $universe)
            ->setParameter('statuses', ['traveling', 'returning', 'attacking', 'defending', 'orbiting', 'orbit_pending'])
            ->orderBy('f.arrivalAt', 'ASC')
            ->getQuery()
            ->getResult();
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
    /** @return array<string,array<string,int>> */
    public function levelsByTier(string $class, array $criteria): array
    {
        $levels=[];
        foreach ($this->all($class,$criteria) as $row) $levels[$row->slug][$row->tier]=$row->level;
        return $levels;
    }
    public function messageContacts(PlayerUniverse $viewer): array
    {
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(m.sender) AS senderId, IDENTITY(m.recipient) AS recipientId, MAX(m.id) AS latest, SUM(CASE WHEN m.recipient = :viewer AND m.readAt IS NULL THEN 1 ELSE 0 END) AS unread')
            ->from(\App\Entity\SocialMessage::class, 'm')->where('m.guild IS NULL')
            ->andWhere('(m.sender = :viewer OR m.recipient = :viewer)')->setParameter('viewer', $viewer)
            ->groupBy('m.sender, m.recipient')->getQuery()->getArrayResult();
        $contacts = [];
        foreach ($rows as $row) {
            $id = (int) $row['senderId'] === $viewer->id ? (int) $row['recipientId'] : (int) $row['senderId'];
            $contacts[$id] ??= ['member' => $this->find(PlayerUniverse::class, $id), 'unread' => 0, 'latest' => 0];
            $contacts[$id]['unread'] += (int) $row['unread'];
            $contacts[$id]['latest'] = max($contacts[$id]['latest'], (int) $row['latest']);
        }
        $contacts = array_filter($contacts, static fn (array $contact): bool => $contact['member']?->universe->id === $viewer->universe->id);
        usort($contacts, static fn (array $a, array $b): int => [(int) ($b['unread'] > 0), $b['latest']] <=> [(int) ($a['unread'] > 0), $a['latest']]);
        return $contacts;
    }

    public function searchMessageRecipients(PlayerUniverse $viewer, string $query): array
    {
        if (mb_strlen($query) < 2) return [];
        return $this->em->createQueryBuilder()->select('m', 'p')->from(PlayerUniverse::class, 'm')->join('m.player', 'p')
            ->where('m.universe = :universe')->andWhere('m != :viewer')->andWhere("LOWER(p.username) LIKE :query ESCAPE '!' ")
            ->setParameter('universe', $viewer->universe)->setParameter('viewer', $viewer)
            ->setParameter('query', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)) . '%')
            ->orderBy('p.username', 'ASC')->setMaxResults(20)->getQuery()->getResult();
    }

    public function unreadGuildMessageCount(PlayerUniverse $viewer, \App\Entity\Guild $guild, int $lastRead): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(m.id)')->from(\App\Entity\SocialMessage::class, 'm')
            ->where('m.guild = :guild')->andWhere('m.sender != :viewer')->andWhere('m.id > :lastRead')
            ->setParameter('guild', $guild)->setParameter('viewer', $viewer)->setParameter('lastRead', $lastRead)->getQuery()->getSingleScalarResult();
    }

    public function privateConversation(PlayerUniverse $a, PlayerUniverse $b, int $limit = 100): array
    {
        $rows = $this->em->createQueryBuilder()->select('m')->from(\App\Entity\SocialMessage::class, 'm')
            ->where('m.guild IS NULL')
            ->andWhere('((m.sender = :a AND m.recipient = :b) OR (m.sender = :b AND m.recipient = :a))')
            ->setParameter('a', $a)->setParameter('b', $b)
            ->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)))->getQuery()->getResult();

        return array_reverse($rows);
    }
    public function markPrivateConversationRead(PlayerUniverse $reader, PlayerUniverse $sender, \DateTimeImmutable $readAt): int
    {
        return $this->em->createQueryBuilder()->update(\App\Entity\SocialMessage::class, 'm')
            ->set('m.readAt', ':readAt')->where('m.recipient = :reader')->andWhere('m.sender = :sender')
            ->andWhere('m.readAt IS NULL')->setParameter('readAt', $readAt)
            ->setParameter('reader', $reader)->setParameter('sender', $sender)->getQuery()->execute();
    }
    public function unreadPrivateMessageCount(PlayerUniverse $recipient): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(m.id)')->from(\App\Entity\SocialMessage::class, 'm')
            ->where('m.recipient = :recipient')->andWhere('m.readAt IS NULL')
            ->setParameter('recipient', $recipient)->getQuery()->getSingleScalarResult();
    }
    public function guildConversation(\App\Entity\Guild $guild, int $limit = 100): array
    {
        $rows = $this->em->createQueryBuilder()->select('m')->from(\App\Entity\SocialMessage::class, 'm')
            ->where('m.guild = :guild')->setParameter('guild', $guild)
            ->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')
            ->setMaxResults(max(1, min(500, $limit)))->getQuery()->getResult();

        return array_reverse($rows);
    }


    public function activeUniverses(): array
    {
        return $this->all(Universe::class, ['status' => 'active'], ['id' => 'ASC']);
    }

    public function boostEventByKey(string $eventKey): ?BoostEvent
    {
        $event = $this->one(BoostEvent::class, ['eventKey' => $eventKey]);
        return $event instanceof BoostEvent ? $event : null;
    }

    public function boostEvents(string $status): array
    {
        return $this->all(BoostEvent::class, ['status' => $status], ['scheduledAt' => 'ASC', 'id' => 'ASC']);
    }


    public function boostEventsForUniverse(string $status, Universe $universe): array
    {
        return $this->all(BoostEvent::class, ['status' => $status, 'universe' => $universe], ['scheduledAt' => 'ASC', 'id' => 'ASC']);
    }

    public function activeBoostEventsFor(PlayerUniverse $membership, string $templateKey, \DateTimeImmutable $now): array
    {
        return $this->em->createQueryBuilder()->select('e')->from(BoostEvent::class, 'e')
            ->where('e.universe = :universe')->andWhere('e.templateKey = :template')->andWhere('e.status = :status')
            ->andWhere('e.startedAt <= :now')->andWhere('e.endsAt > :now')
            ->setParameter('universe', $membership->universe)->setParameter('template', $templateKey)
            ->setParameter('status', 'active')->setParameter('now', $now)->orderBy('e.startedAt', 'ASC')
            ->getQuery()->getResult();
    }

    public function boostEventContributionExists(BoostEvent $event, string $sourceKey): bool
    {
        return $this->one(BoostEventContribution::class, ['event' => $event, 'sourceKey' => $sourceKey]) !== null;
    }


    public function boostEventContributionTotal(BoostEvent $event, PlayerUniverse $membership): float
    {
        return (float) $this->em->createQueryBuilder()->select('COALESCE(SUM(c.value), 0)')
            ->from(BoostEventContribution::class, 'c')->where('c.event = :event')->andWhere('c.playerUniverse = :membership')
            ->setParameter('event', $event)->setParameter('membership', $membership)->getQuery()->getSingleScalarResult();
    }

    public function boostEventContributions(BoostEvent $event): array
    {
        return $this->all(BoostEventContribution::class, ['event' => $event], ['id' => 'ASC']);
    }

    public function outbox(bool $notifications): array
    {
        return $this->em->createQueryBuilder()->select('o')->from(OutboxMessage::class,'o')
            ->where($notifications?'o.kind = :kind':'o.kind IN (:kind)')->setParameter('kind',$notifications?'notification':['queue','fleet','combat','universe'])
            ->orderBy('o.id','ASC')->setMaxResults(100)->getQuery()->getResult();
    }
    public function activeQueueItems(): array
    {
        return $this->em->createQueryBuilder()->select('q')->from(QueueItem::class,'q')->where('q.startedAt IS NOT NULL')->getQuery()->getResult();
    }

    public function activeQueueItemsFor(PlayerUniverse $membership): array
    {
        return $this->em->createQueryBuilder()->select('q')->from(QueueItem::class, 'q')
            ->where('q.playerUniverse = :membership')->andWhere('q.startedAt IS NOT NULL')
            ->setParameter('membership', $membership)->orderBy('q.startedAt', 'ASC')->getQuery()->getResult();
    }

    public function activeFleetsForBoost(PlayerUniverse $membership): array
    {
        return $this->em->createQueryBuilder()->select('f')->from(Fleet::class, 'f')
            ->where('f.playerUniverse = :membership')->andWhere('f.status IN (:statuses)')->andWhere('f.arrivalAt IS NOT NULL')
            ->setParameter('membership', $membership)->setParameter('statuses', ['traveling', 'returning', 'defending', 'orbiting'])
            ->orderBy('f.arrivalAt', 'ASC')->getQuery()->getResult();
    }

    public function boostsFor(PlayerUniverse $membership): array
    {
        return $this->all(PlayerBoost::class, ['playerUniverse' => $membership], ['earnedAt' => 'ASC', 'id' => 'ASC']);
    }

    public function countBoosts(PlayerUniverse $membership, string $status): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(b.id)')->from(PlayerBoost::class, 'b')
            ->where('b.playerUniverse = :membership')->andWhere('b.status = :status')
            ->setParameter('membership', $membership)->setParameter('status', $status)->getQuery()->getSingleScalarResult();
    }

    public function oldestBoost(PlayerUniverse $membership, string $status): ?PlayerBoost
    {
        return $this->em->getRepository(PlayerBoost::class)->findOneBy(
            ['playerUniverse' => $membership, 'status' => $status],
            ['earnedAt' => 'ASC', 'id' => 'ASC'],
        );
    }

    public function riskBoostCountSince(PlayerUniverse $membership, \DateTimeImmutable $from, \DateTimeImmutable $until): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(b.id)')->from(PlayerBoost::class, 'b')
            ->where('b.playerUniverse = :membership')->andWhere('b.sourceType = :source')
            ->andWhere('b.earnedAt >= :from')->andWhere('b.earnedAt < :until')
            ->setParameter('membership', $membership)->setParameter('source', 'risk_mission')
            ->setParameter('from', $from)->setParameter('until', $until)->getQuery()->getSingleScalarResult();
    }
}
