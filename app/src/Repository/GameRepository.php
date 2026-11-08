<?php
namespace App\Repository;
use App\Entity\{Player,PlayerUniverse,Universe,Galaxy,SolarSystem,Planet,PlanetResource,PlanetBuilding,PlayerTechnology,Fleet,FleetShip,PlayerDiscovery,QueueItem,OutboxMessage,CombatLog,CombatParticipant,PlayerBoost,BoostEvent,BoostEventContribution,GameEvent};
use Doctrine\ORM\EntityManagerInterface;
final class GameRepository
{
    public function __construct(private EntityManagerInterface $em) {}
    public function find(string $class, int $id): ?object { return $this->em->find($class, $id); }
    public function one(string $class, array $criteria): ?object { return $this->em->getRepository($class)->findOneBy($criteria); }
    public function occupationReservations(PlayerUniverse $membership): int
    {
        return $this->em->getRepository(\App\Entity\PlanetOccupation::class)->count(['occupant'=>$membership,'status'=>'active']);
    }
    /** @return list<\App\Entity\PlanetOccupation> */
    public function occupationResumeCandidates(int $limit,int $afterId): array
    {
        return $this->em->createQueryBuilder()->select('o')->from(\App\Entity\PlanetOccupation::class,'o')
            ->where('o.status = :status')->andWhere('o.id > :after')->setParameter('status','active')->setParameter('after',$afterId)
            ->orderBy('o.id','ASC')->setMaxResults($limit)->getQuery()->getResult();
    }
    /** @return list<Fleet> */
    public function fleetsPresentForOccupation(Planet $planet): array
    {
        return $this->em->createQueryBuilder()->select('DISTINCT f')->from(Fleet::class,'f')->join(FleetShip::class,'s','WITH','s.fleet = f')
            ->where('s.quantity > 0')->andWhere('(f.homePlanet = :planet AND f.status = :idle) OR (f.destination = :planet AND f.status IN (:stationed))')
            ->setParameter('planet',$planet)->setParameter('idle','idle')->setParameter('stationed',['attacking','defending','orbiting','orbit_pending','occupying'])
            ->orderBy('f.id','ASC')->getQuery()->getResult();
    }
    /** @return list<Fleet> */
    public function fleetsAwayFromOccupation(Planet $planet): array
    {
        return $this->em->createQueryBuilder()->select('DISTINCT f')->from(Fleet::class,'f')->join(FleetShip::class,'s','WITH','s.fleet = f')
            ->where('f.homePlanet = :planet')->andWhere('s.quantity > 0')->andWhere('f.status IN (:away)')
            ->setParameter('planet',$planet)->setParameter('away',['traveling','returning','attacking','defending','orbiting','orbit_pending'])
            ->orderBy('f.id','ASC')->getQuery()->getResult();
    }
    public function hasPlanetHostileUnits(Planet $planet): bool
    {
        foreach([\App\Entity\PlanetDefense::class,\App\Entity\PlanetNpcShip::class] as $class) {
            if($this->em->createQueryBuilder()->select('u.id')->from($class,'u')->where('u.planet = :planet')->andWhere('u.quantity > 0')
                ->setParameter('planet',$planet)->setMaxResults(1)->getQuery()->getOneOrNullResult()!==null) return true;
        }
        return false;
    }
    public function all(string $class, array $criteria = [], array $order = ['id' => 'ASC']): array { return $this->em->getRepository($class)->findBy($criteria, $order); }
    /** @return list<PlayerUniverse> */
    public function forgottenEmpirePreviewCandidates(\DateTimeImmutable $now, int $conversionSeconds, int $limit, int $afterId, \DateTimeImmutable $cutoff): array
    {
        // Déduire les vacances avant LIMIT, comme pour les avertissements J14/J27.
        $sql = <<<'SQL'
SELECT candidate.id FROM (
    SELECT m.id, TIMESTAMPDIFF(SECOND, m.last_activity_at, :cutoff) - COALESCE((
        SELECT SUM(GREATEST(0, TIMESTAMPDIFF(SECOND,
            GREATEST(v.started_at, m.last_activity_at),
            LEAST(COALESCE(v.ended_at, v.scheduled_end_at), v.scheduled_end_at, :cutoff)
        ))) FROM vacation_period v
        WHERE v.membership_id = m.id AND v.started_at < :cutoff AND v.scheduled_end_at > m.last_activity_at
    ), 0) AS inactive_seconds
    FROM player_universe m JOIN universe u ON u.id = m.universe_id
    WHERE m.id > :afterId AND m.player_id IS NOT NULL AND m.forgotten_at IS NULL AND m.last_activity_at <= :inactiveBefore
        AND u.status = 'active' AND (u.starts_at IS NULL OR u.starts_at <= :now)
        AND (u.ends_at IS NULL OR u.ends_at > :now)
        AND NOT EXISTS (SELECT 1 FROM vacation_period active_v
            WHERE active_v.membership_id = m.id AND active_v.started_at <= :now
                AND LEAST(COALESCE(active_v.ended_at, active_v.scheduled_end_at), active_v.scheduled_end_at) > :now)
) candidate WHERE candidate.inactive_seconds >= :conversionSeconds
ORDER BY candidate.id ASC LIMIT :limit
SQL;
        $ids=array_map('intval',$this->em->getConnection()->executeQuery($sql,[
            'now'=>$now,'cutoff'=>$cutoff,'inactiveBefore'=>$cutoff->modify('-'.$conversionSeconds.' seconds'),
            'conversionSeconds'=>$conversionSeconds,'limit'=>$limit,'afterId'=>$afterId,
        ],['now'=>\Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,'cutoff'=>\Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,'inactiveBefore'=>\Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,
            'limit'=>\Doctrine\DBAL\ParameterType::INTEGER,'afterId'=>\Doctrine\DBAL\ParameterType::INTEGER])->fetchFirstColumn());
        if($ids===[]) return [];
        return $this->em->createQueryBuilder()->select('m','p','u')->from(PlayerUniverse::class,'m')
            ->join('m.player','p')->join('m.universe','u')->where('m.id IN (:ids)')->setParameter('ids',$ids)
            ->orderBy('m.id','ASC')->getQuery()->getResult();
    }

    /** @return array<string,int> */
    public function forgottenEmpirePreviewInventory(PlayerUniverse $membership): array
    {
        $sql = <<<'SQL'
SELECT
    (SELECT COUNT(*) FROM planet WHERE owner_id = :membership) AS colonies,
    (SELECT COUNT(*) FROM fleet WHERE player_universe_id = :membership AND status IN ('traveling','returning','attacking','defending','orbiting','orbit_pending','occupying')) AS deployed_fleets,
    (SELECT COUNT(*) FROM fleet f JOIN planet p ON p.id = f.destination_id
        WHERE p.owner_id = :membership AND f.player_universe_id <> :membership AND f.status IN ('traveling','attacking','defending','orbiting','orbit_pending','occupying')) AS incoming_or_stationed_fleets,
    (SELECT COUNT(*) FROM queue_item WHERE player_universe_id = :membership) AS queued_orders,
    (SELECT COUNT(*) FROM player_technology WHERE player_universe_id = :membership) AS technologies,
    (SELECT COUNT(*) FROM combat_event e JOIN planet p ON p.id = e.planet_id WHERE e.status = 'pending'
        AND (p.owner_id = :membership OR EXISTS (SELECT 1 FROM combat_participant cp WHERE cp.combat_event_id = e.id AND cp.player_universe_id = :membership))) AS open_combats,
    (SELECT COUNT(*) FROM alliance_contract WHERE (player_a_id = :membership OR player_b_id = :membership) AND status IN ('pending','accepted')) AS alliances,
    (SELECT COUNT(*) FROM guild_member WHERE player_universe_id = :membership) AS guild_memberships,
    (SELECT COUNT(*) FROM guild WHERE leader_id = :membership) AS led_guilds
SQL;
        return array_map('intval',$this->em->getConnection()->fetchAssociative($sql,['membership'=>$membership->id],['membership'=>\Doctrine\DBAL\ParameterType::INTEGER]));
    }

    public function hasVacationFleetBlocker(PlayerUniverse $membership): bool
    {
        $fleet = $this->em->createQueryBuilder()->select('f.id')->from(Fleet::class, 'f')
            ->leftJoin('f.destination', 'p')
            ->where('(f.playerUniverse = :membership AND f.status IN (:active)) OR (p.owner = :membership AND f.mission IN (:hostile) AND f.status IN (:incoming))')
            ->setParameter('membership', $membership)
            ->setParameter('active', ['traveling', 'returning', 'attacking', 'defending', 'orbiting', 'orbit_pending', 'occupying'])
            ->setParameter('hostile', ['attack', 'conquer'])->setParameter('incoming', ['traveling', 'attacking'])
            ->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($fleet !== null) return true;
        return $this->em->createQueryBuilder()->select('e.id')->from(\App\Entity\CombatEvent::class, 'e')
            ->join('e.planet', 'p')->where('p.owner = :membership')->andWhere('e.status = :pending')
            ->setParameter('membership', $membership)->setParameter('pending', 'pending')
            ->setMaxResults(1)->getQuery()->getOneOrNullResult() !== null;
    }
    /** @return list<Fleet> */
    public function fleetsForEmpireConversion(PlayerUniverse $membership): array
    {
        return $this->em->createQueryBuilder()->select('f')->from(Fleet::class,'f')->leftJoin('f.destination','p')
            ->where('(f.playerUniverse = :membership AND f.status IN (:ownStatuses)) OR (p.owner = :membership AND f.playerUniverse != :membership AND f.status IN (:guestStatuses))')
            ->setParameter('membership',$membership)->setParameter('ownStatuses',['traveling','attacking','defending','orbiting','orbit_pending','occupying'])
            ->setParameter('guestStatuses',['defending','orbiting','orbit_pending'])->orderBy('f.id','ASC')->getQuery()->getResult();
    }
    /** @return list<Fleet> */
    public function pendingOrbitsFor(PlayerUniverse $host): array
    {
        return $this->em->createQueryBuilder()->select('f')->from(Fleet::class, 'f')->join('f.destination', 'p')
            ->where('p.owner = :host')->andWhere('f.status = :pending')
            ->setParameter('host', $host)->setParameter('pending', 'orbit_pending')->orderBy('f.id', 'ASC')->getQuery()->getResult();
    }
    /** @return list<PlayerUniverse> */
    public function inactivityWarningCandidates(\DateTimeImmutable $now, int $inactiveSeconds, int $reminderSeconds, int $conversionSeconds, int $limit): array
    {
        // Filtrer avant LIMIT évite qu'un lot soit rempli d'empires dont le compteur est suspendu.
        // Les périodes sont non chevauchantes grâce au démarrage sous verrou de participation.
        $sql = <<<'SQL'
SELECT candidate.id FROM (
    SELECT m.id, m.last_activity_at,
        TIMESTAMPDIFF(SECOND, m.last_activity_at, :now) - COALESCE((
            SELECT SUM(GREATEST(0, TIMESTAMPDIFF(SECOND,
                GREATEST(v.started_at, m.last_activity_at),
                LEAST(COALESCE(v.ended_at, v.scheduled_end_at), v.scheduled_end_at, :now)
            ))) FROM vacation_period v
            WHERE v.membership_id = m.id AND v.started_at < :now
                AND v.scheduled_end_at > m.last_activity_at
        ), 0) AS inactive_seconds
    FROM player_universe m JOIN player p ON p.id = m.player_id JOIN universe u ON u.id = m.universe_id
    WHERE m.forgotten_at IS NULL AND m.last_activity_at <= :inactiveBefore AND p.email_verified_at IS NOT NULL AND p.locked_at IS NULL
        AND u.status = 'active' AND (u.starts_at IS NULL OR u.starts_at <= :now)
        AND (u.ends_at IS NULL OR u.ends_at > :now)
        AND NOT EXISTS (SELECT 1 FROM vacation_period active_v
            WHERE active_v.membership_id = m.id AND active_v.started_at <= :now
                AND LEAST(COALESCE(active_v.ended_at, active_v.scheduled_end_at), active_v.scheduled_end_at) > :now)
) candidate
WHERE candidate.inactive_seconds >= :inactiveSeconds AND candidate.inactive_seconds < :conversionSeconds
    AND NOT EXISTS (SELECT 1 FROM empire_inactivity_warning w
        WHERE w.membership_id = candidate.id AND w.activity_at = candidate.last_activity_at AND w.sent_at IS NOT NULL
            AND w.threshold_days = CASE WHEN candidate.inactive_seconds >= :reminderSeconds THEN 27 ELSE 14 END)
ORDER BY candidate.last_activity_at ASC, candidate.id ASC LIMIT :limit
SQL;
        $ids = array_map('intval', $this->em->getConnection()->executeQuery($sql, [
            'now'=>$now, 'inactiveBefore'=>$now->modify('-'.$inactiveSeconds.' seconds'),
            'inactiveSeconds'=>$inactiveSeconds, 'reminderSeconds'=>$reminderSeconds,
            'conversionSeconds'=>$conversionSeconds, 'limit'=>$limit,
        ], ['now'=>\Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE, 'inactiveBefore'=>\Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,
            'limit'=>\Doctrine\DBAL\ParameterType::INTEGER])->fetchFirstColumn());
        if ($ids === []) return [];
        $memberships = $this->em->createQueryBuilder()->select('m', 'p', 'u')->from(PlayerUniverse::class, 'm')
            ->join('m.player', 'p')->join('m.universe', 'u')->where('m.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
        $byId = [];
        foreach ($memberships as $membership) $byId[$membership->id] = $membership;
        $ordered = [];
        foreach ($ids as $id) if (isset($byId[$id])) $ordered[] = $byId[$id];
        return $ordered;
    }
    public function passwordResetTokenForUpdate(string $hash): ?\App\Entity\PasswordResetToken
    {
        return $this->em->getRepository(\App\Entity\PasswordResetToken::class)->createQueryBuilder('t')
            ->where('t.tokenHash = :hash')->setParameter('hash', $hash)->getQuery()
            ->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
    }
    /** @return list<GameEvent> */
    public function visibleGameEvents(PlayerUniverse $membership, \DateTimeImmutable $now): array
    {
        return $this->em->createQueryBuilder()->select('e')->from(GameEvent::class, 'e')
            ->where('e.status IN (:statuses)')->andWhere('e.visibleToPlayers = true')->andWhere('e.endsAt > :now')
            ->andWhere('(e.universe IS NULL OR e.universe = :universe)')->setParameter('statuses', ['active', 'planned'])->setParameter('now', $now)->setParameter('universe', $membership->universe)
            ->orderBy('e.endsAt', 'ASC')->getQuery()->getResult();
    }
    public function transportReportsFor(?PlayerUniverse $membership, int $page = 1): array
    {
        $query = $this->em->createQueryBuilder()->select('r', 's', 'p')->from(\App\Entity\TransportReport::class, 'r')
            ->join('r.sender', 's')->join('r.recipient', 'p');
        if ($membership) $query->where('r.sender = :member OR r.recipient = :member')->setParameter('member', $membership);
        return $query->orderBy('r.createdAt', 'DESC')->addOrderBy('r.id', 'DESC')
            ->setFirstResult((max(1, $page) - 1) * 50)->setMaxResults(50)->getQuery()->getResult();
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
            ->setParameter('statuses', ['traveling', 'returning', 'attacking', 'defending', 'orbiting', 'orbit_pending', 'occupying'])
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
            ->where($notifications?'o.kind = :kind':'o.kind IN (:kind)')->setParameter('kind',$notifications?'notification':['queue','fleet','combat','universe','occupation'])
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
