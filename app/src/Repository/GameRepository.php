<?php

namespace App\Repository;

use App\Entity\{Player, PlayerUniverse, Universe, Galaxy, SolarSystem, Planet, PlanetResource, PlanetBuilding, PlayerTechnology, Fleet, FleetShip, PlayerDiscovery, QueueItem, OutboxMessage, PirateReconnaissance, CombatLog, CombatParticipant, PlayerBoost, BoostEvent, BoostEventContribution, GameEvent, Guild, GuildProject, GuildProjectDefinition, GuildProjectContribution};
use Doctrine\DBAL\{ArrayParameterType, Query\QueryBuilder};
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\Query\ResultSetMappingBuilder;

final class GameRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    /** @return list<GuildProject> Une ligne supplémentaire permet de proposer la page suivante. */
    public function guildProjectsPage(Guild $guild, bool $history, int $beforeId): array
    {
        $query = $this->em->createQueryBuilder()->select('project')->from(GuildProject::class, 'project')
            ->where('project.guildId = :guild')->andWhere('project.universe = :universe')
            ->andWhere($history ? 'project.status != :active' : 'project.status = :active')
            ->setParameter('guild', $guild->id)->setParameter('universe', $guild->universe)->setParameter('active', 'active');
        if ($beforeId > 0) $query->andWhere('project.id < :before')->setParameter('before', $beforeId);
        return $query->orderBy('project.id', 'DESC')->setMaxResults(26)->getQuery()->getResult();
    }

    /** @return list<GuildProjectDefinition> Les modèles déjà lancés restent exclus, quel que soit leur état. */
    public function availableGuildProjectDefinitions(Guild $guild, int $beforeId): array
    {
        $query = $this->em->createQueryBuilder()->select('definition')->from(GuildProjectDefinition::class, 'definition')
            ->where('definition.universe = :universe')->andWhere('definition.enabled = true')
            ->andWhere('NOT EXISTS (SELECT launched.id FROM '.GuildProject::class.' launched WHERE launched.definition = definition AND launched.guildId = :guild AND launched.universe = :universe)')
            ->setParameter('universe', $guild->universe)->setParameter('guild', $guild->id);
        if ($beforeId > 0) $query->andWhere('definition.id < :before')->setParameter('before', $beforeId);
        return $query->orderBy('definition.id', 'DESC')->setMaxResults(26)->getQuery()->getResult();
    }

    /** @param list<int> $projectIds @return array<int, array<string, int>> Dons personnels des seuls projets affichés. */
    public function ownGuildProjectContributions(PlayerUniverse $actor, array $projectIds): array
    {
        if ($projectIds === []) return [];
        $rows = $this->em->createQueryBuilder()->select('contribution')->from(GuildProjectContribution::class, 'contribution')
            ->join('contribution.project', 'project')->where('project.id IN (:projects)')
            ->andWhere('project.universe = :universe')->andWhere('contribution.playerUniverseId = :actor')
            ->setParameter('projects', $projectIds)->setParameter('universe', $actor->universe)->setParameter('actor', $actor->id)
            ->getQuery()->getResult();
        $result = [];
        foreach ($rows as $row) $result[$row->project->id] = $row->resources;
        return $result;
    }

    /** @return list<GuildProjectContribution> Le projet est autorisé par le contrôleur avant cette lecture. */
    public function guildProjectContributionsPage(GuildProject $project, int $beforeId): array
    {
        $query = $this->em->createQueryBuilder()->select('contribution')->from(GuildProjectContribution::class, 'contribution')
            ->where('contribution.project = :project')->setParameter('project', $project);
        if ($beforeId > 0) $query->andWhere('contribution.id < :before')->setParameter('before', $beforeId);
        return $query->orderBy('contribution.id', 'DESC')->setMaxResults(26)->getQuery()->getResult();
    }

    /** @param list<string> $keys @return list<string> Inclut les récompenses utilisées ou liées à un ancien empire. */
    public function existingBoostSourceKeys(array $keys): array
    {
        if ($keys === []) return [];
        return array_column($this->em->createQueryBuilder()->select('boost.sourceKey AS sourceKey')->from(PlayerBoost::class, 'boost')
            ->where('boost.sourceKey IN (:keys)')->setParameter('keys', $keys)->getQuery()->getScalarResult(), 'sourceKey');
    }

    public function find(string $class, int $id): ?object
    {
        return $this->em->find($class, $id);
    }

    public function one(string $class, array $criteria): ?object
    {
        return $this->em->getRepository($class)->findOneBy($criteria);
    }

    /** @return list<FleetShip> Stocks civils locaux et flottes de stationnement entrantes uniquement. */
    public function economicUnitStocks(Planet $planet, array $slugs): array
    {
        if ($planet->owner === null || $slugs === []) return [];
        return $this->em->createQueryBuilder()->select('ship', 'fleet')->from(FleetShip::class, 'ship')
            ->join('ship.fleet', 'fleet')->where('ship.slug IN (:slugs)')->andWhere('ship.quantity > 0')
            ->andWhere('fleet.playerUniverse = :owner')
            ->andWhere('((fleet.homePlanet = :planet AND fleet.status IN (:local)) OR '
                .'(fleet.mission = :station AND ((fleet.status = :outbound AND fleet.destination = :planet) OR '
                .'(fleet.status = :returning AND (fleet.returnPlanet = :planet OR '
                .'(fleet.returnPlanet IS NULL AND fleet.homePlanet = :planet))))))')
            ->setParameter('slugs', $slugs)->setParameter('owner', $planet->owner)->setParameter('planet', $planet)
            ->setParameter('local', ['idle', 'defending'])->setParameter('station', 'station')
            ->setParameter('outbound', 'traveling')->setParameter('returning', 'returning')
            ->orderBy('fleet.id', 'ASC')->getQuery()->getResult();
    }

    /** @return array{membershipId: int, planetId: int}|null */
    public function queueContext(int $id): ?array
    {
        // Prélecture des identités pour les verrous ; elle ne remplace pas le contrôle de la commande sous verrou.
        $row = $this->em->getConnection()->fetchAssociative(
            'SELECT player_universe_id, planet_id FROM queue_item WHERE id = ?',
            [$id],
        );
        return $row === false ? null : [
            'membershipId' => (int) $row['player_universe_id'],
            'planetId' => (int) $row['planet_id'],
        ];
    }

    public function occupationReservations(PlayerUniverse $membership): int
    {
        return $this->em->getRepository(\App\Entity\PlanetOccupation::class)->count(['occupant' => $membership, 'status' => 'active']);
    }

    /** @return list<\App\Entity\PlanetOccupation> */
    public function occupationResumeCandidates(int $limit, int $afterId): array
    {
        return $this->em->createQueryBuilder()->select('o')->from(\App\Entity\PlanetOccupation::class, 'o')->where('o.status = :status')->andWhere('o.id > :after')->setParameter('status', 'active')->setParameter('after', $afterId)->orderBy('o.id', 'ASC')->setMaxResults($limit)->getQuery()->getResult();
    }

    /** @return list<Fleet> */
    public function fleetsPresentForOccupation(Planet $planet): array
    {
        return $this->em->createQueryBuilder()->select('DISTINCT f')->from(Fleet::class, 'f')->join(FleetShip::class, 's', 'WITH', 's.fleet = f')->where('s.quantity > 0')->andWhere('(f.homePlanet = :planet AND f.status = :idle) OR (f.destination = :planet AND f.status IN (:stationed))')->setParameter('planet', $planet)->setParameter('idle', 'idle')->setParameter('stationed', [
            'attacking',
            'defending',
            'orbiting',
            'orbit_pending',
            'occupying',
        ])->orderBy('f.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<Fleet> */
    public function fleetsAwayFromOccupation(Planet $planet): array
    {
        return $this->em->createQueryBuilder()->select('DISTINCT f')->from(Fleet::class, 'f')->join(FleetShip::class, 's', 'WITH', 's.fleet = f')->where('f.homePlanet = :planet')->andWhere('s.quantity > 0')->andWhere('f.status IN (:away)')->setParameter('planet', $planet)->setParameter('away', [
            'traveling',
            'returning',
            'attacking',
            'defending',
            'orbiting',
            'orbit_pending',
        ])->orderBy('f.id', 'ASC')->getQuery()->getResult();
    }

    public function hasPlanetHostileUnits(Planet $planet): bool
    {
        foreach ([\App\Entity\PlanetDefense::class, \App\Entity\PlanetNpcShip::class] as $class) {
            if ($this->em->createQueryBuilder()->select('u.id')->from($class, 'u')->where('u.planet = :planet')->andWhere('u.quantity > 0')->setParameter('planet', $planet)->setMaxResults(1)->getQuery()->getOneOrNullResult() !== null) {
                return true;
            }
        }
        return false;
    }

    /** @return array{fleets:list<Fleet>,count:int,page:int,pages:int} Missions de la colonie, bornées avant enrichissement. */
    public function fleetMissionPage(PlayerUniverse $owner, ?Planet $colony, int $page, int $limit = 30, int $focusId = 0): array
    {
        if (!$colony) {
            return ['fleets' => [], 'count' => 0, 'page' => 1, 'pages' => 1];
        }
        $limit = max(1, min(30, $limit));
        $query = $this->em->createQueryBuilder()->select('fleet')->from(Fleet::class, 'fleet')
            ->where('fleet.playerUniverse = :owner')->andWhere('fleet.homePlanet = :colony')
            ->andWhere('fleet.status IN (:states)')->setParameter('owner', $owner)->setParameter('colony', $colony)
            ->setParameter('states', ['traveling', 'returning', 'attacking', 'defending', 'orbiting', 'orbit_pending', 'occupying']);
        $count = (int) (clone $query)->select('COUNT(fleet.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($count / $limit));
        if ($focusId > 0) {
            $focused = (clone $query)->andWhere('fleet.id = :focus')->setParameter('focus', $focusId)->getQuery()->getOneOrNullResult();
            if ($focused) {
                // Reconstituer la page d’un lien direct avant de borner les lectures détaillées.
                $before = (clone $query)->select('COUNT(fleet.id)')->setParameter('focus', $focusId);
                if ($focused->arrivalAt === null) {
                    $before->andWhere('fleet.arrivalAt IS NULL AND fleet.id < :focus');
                } else {
                    $before->andWhere('(fleet.arrivalAt IS NULL OR fleet.arrivalAt < :arrival OR (fleet.arrivalAt = :arrival AND fleet.id < :focus))')
                        ->setParameter('arrival', $focused->arrivalAt);
                }
                $page = intdiv((int) $before->getQuery()->getSingleScalarResult(), $limit) + 1;
            }
        }
        $page = max(1, min($pages, $page));
        $fleets = $query->orderBy('fleet.arrivalAt', 'ASC')->addOrderBy('fleet.id', 'ASC')
            ->setFirstResult(($page - 1) * $limit)->setMaxResults($limit)->getQuery()->getResult();
        return ['fleets' => $fleets, 'count' => $count, 'page' => $page, 'pages' => $pages];
    }

    public function guildTreatyHistory(\App\Entity\Guild $a, \App\Entity\Guild $b): array
    {
        if ($a->universe->id !== $b->universe->id) {
            return [];
        }
        if ($a->id > $b->id) {
            [$a, $b] = [$b, $a];
        }
        return $this->em->getRepository(\App\Entity\GuildTreatyEvent::class)->findBy(['universeId' => $a->universe->id, 'guildAId' => $a->id, 'guildBId' => $b->id], ['id' => 'DESC'], 10);
    }

    public function all(string $class, array $criteria = [], array $order = ['id' => 'ASC']): array
    {
        return $this->em->getRepository($class)->findBy($criteria, $order);
    }

    public function count(string $class, array $criteria = []): int
    {
        return $this->em->getRepository($class)->count($criteria);
    }

    /** Identités complètes, y compris les anciens membres, limitées à l’univers demandé. */
    public function membershipIdentities(Universe $universe, ?array $ids = null): array
    {
        if ($ids === []) {
            return [];
        }
        $query = $this->em->createQueryBuilder()->select('membership', 'player', 'former')->from(PlayerUniverse::class, 'membership')->leftJoin('membership.player', 'player')->leftJoin('membership.formerPlayer', 'former')->where('membership.universe = :universe')->setParameter('universe', $universe)->orderBy('membership.id', 'ASC');
        if ($ids !== null) {
            $query->andWhere('membership.id IN (:ids)')->setParameter('ids', $ids);
        }
        return $query->getQuery()->getResult();
    }

    public function personalContracts(PlayerUniverse $viewer): array
    {
        return $this->em->createQueryBuilder()->select('contract', 'a', 'b', 'playerA', 'playerB')->from(\App\Entity\AllianceContract::class, 'contract')->join('contract.playerA', 'a')->join('contract.playerB', 'b')->leftJoin('a.player', 'playerA')->leftJoin('b.player', 'playerB')->where('a = :viewer OR b = :viewer')->andWhere('a.universe = :universe AND b.universe = :universe')->setParameter('viewer', $viewer)->setParameter('universe', $viewer->universe)->orderBy('contract.id', 'ASC')->getQuery()->getResult();
    }

    /** @return array<int,\App\Entity\GuildRelation> */
    public function guildRelations(\App\Entity\Guild $guild): array
    {
        $relations = $this->em->createQueryBuilder()->select('relation', 'a', 'b')->from(\App\Entity\GuildRelation::class, 'relation')->join('relation.guildA', 'a')->join('relation.guildB', 'b')->where('a = :guild OR b = :guild')->andWhere('a.universe = :universe AND b.universe = :universe')->setParameter('guild', $guild)->setParameter('universe', $guild->universe)->getQuery()->getResult();
        $byPeer = [];
        foreach ($relations as $relation) {
            $byPeer[$relation->guildA->id === $guild->id ? $relation->guildB->id : $relation->guildA->id] = $relation;
        }
        return $byPeer;
    }

    /** @return array<string, int> Compteurs de la vue générale sans équipages ni historiques. */
    public function guildRelationCounts(\App\Entity\Guild $guild): array
    {
        $rows = $this->em->createQueryBuilder()->select('relation.status AS status', 'COUNT(relation.id) AS total')
            ->from(\App\Entity\GuildRelation::class, 'relation')->join('relation.guildA', 'a')->join('relation.guildB', 'b')
            ->where('a = :guild OR b = :guild')->andWhere('a.universe = :universe AND b.universe = :universe')
            ->andWhere('a.archivedAt IS NULL AND b.archivedAt IS NULL')->setParameter('guild', $guild)
            ->setParameter('universe', $guild->universe)->groupBy('relation.status')->getQuery()->getArrayResult();
        return array_map('intval', array_column($rows, 'total', 'status'));
    }

    /** @return list<array{id: int, name: string}> */
    public function recruitingGuildDirectory(PlayerUniverse $viewer): array
    {
        // Dans le modèle actuel, le recrutement est ouvert aux guildes actives
        // du même univers. La découverte ne charge ni dirigeants ni membres.
        return $this->em->createQueryBuilder()->select('guild.id AS id', 'guild.name AS name')
            ->from(\App\Entity\Guild::class, 'guild')
            ->where('guild.universe = :universe')->andWhere('guild.archivedAt IS NULL')
            ->setParameter('universe', $viewer->universe)->orderBy('guild.id', 'ASC')
            ->setMaxResults(6)->getQuery()->getArrayResult();
    }

    /** @return list<\App\Entity\Guild> Recherche bornée avant chargement des équipages. */
    public function searchGuilds(PlayerUniverse $viewer, string $search, ?\App\Entity\Guild $own): array
    {
        if (mb_strlen($search) < 2) {
            return [];
        }
        // LOCATE conserve la recherche littérale historique, y compris pour % et _.
        $query = $this->em->createQueryBuilder()->select('guild', 'leader', 'player')->from(\App\Entity\Guild::class, 'guild')
            ->join('guild.leader', 'leader')->leftJoin('leader.player', 'player')
            ->where('guild.universe = :universe')->andWhere('guild.archivedAt IS NULL')
            ->andWhere('LOCATE(:search, LOWER(guild.name)) > 0')->setParameter('search', mb_strtolower($search))
            ->setParameter('universe', $viewer->universe)->orderBy('guild.id', 'ASC')->setMaxResults(20);
        if ($own) {
            $query->andWhere('guild != :own')->setParameter('own', $own);
        }
        return $query->getQuery()->getResult();
    }

    /**
     * @param list<\App\Entity\AllianceContract> $contracts
     * @param list<int> $guildIds Guildes déjà validées dans l’univers du lecteur.
     * @return list<PlayerUniverse> Au plus 51 identités, dont une pour détecter la page suivante.
     */
    public function personalDiplomacyPlayers(PlayerUniverse $viewer, array $contracts, array $guildIds, int $afterId): array
    {
        $peerIds = [];
        foreach ($contracts as $contract) {
            $peerIds[] = $contract->playerA->id === $viewer->id ? $contract->playerB->id : $contract->playerA->id;
        }
        if ($peerIds === [] && $guildIds === []) {
            return [];
        }
        $query = $this->em->createQueryBuilder()->select('DISTINCT membership', 'player')->from(PlayerUniverse::class, 'membership')
            ->leftJoin('membership.player', 'player')->leftJoin(\App\Entity\GuildMember::class, 'peerMember', 'WITH', 'peerMember.playerUniverse = membership')
            ->where('membership.universe = :universe')->andWhere('membership.id != :viewer')->andWhere('membership.id > :after')
            ->andWhere('membership.forgottenAt IS NULL')->andWhere('player.id IS NOT NULL')
            ->andWhere('membership.id IN (:peers) OR peerMember.guild IN (:guilds)')
            ->setParameter('universe', $viewer->universe)->setParameter('viewer', $viewer->id)->setParameter('after', max(0, $afterId))
            ->setParameter('peers', $peerIds ?: [0])->setParameter('guilds', $guildIds ?: [0])
            ->orderBy('membership.id', 'ASC')->setMaxResults(51);
        return $query->getQuery()->getResult();
    }

    public function guildMemberships(array $memberships, Universe $universe): array
    {
        if ($memberships === []) {
            return [];
        }
        return $this->em->createQueryBuilder()->select('gm', 'guild')->from(\App\Entity\GuildMember::class, 'gm')->join('gm.guild', 'guild')->join('gm.playerUniverse', 'membership')->where('membership IN (:memberships)')->andWhere('membership.universe = :universe AND guild.universe = :universe')->andWhere('guild.archivedAt IS NULL')->setParameter('memberships', $memberships)->setParameter('universe', $universe)->getQuery()->getResult();
    }

    /** @return list<PlayerUniverse> */
    public function forgottenEmpirePreviewCandidates(
        \DateTimeImmutable $now,
        int $conversionSeconds,
        int $limit,
        int $afterId,
        \DateTimeImmutable $cutoff,
    ): array
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
        $ids = array_map('intval', $this->em->getConnection()->executeQuery($sql, [
            'now' => $now,
            'cutoff' => $cutoff,
            'inactiveBefore' => $cutoff->modify('-' . $conversionSeconds . ' seconds'),
            'conversionSeconds' => $conversionSeconds,
            'limit' => $limit,
            'afterId' => $afterId,
        ], [
            'now' => \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,
            'cutoff' => \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,
            'inactiveBefore' => \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE,
            'limit' => \Doctrine\DBAL\ParameterType::INTEGER,
            'afterId' => \Doctrine\DBAL\ParameterType::INTEGER,
        ])->fetchFirstColumn());
        if ($ids === []) {
            return [];
        }
        return $this->em->createQueryBuilder()->select('m', 'p', 'u')->from(PlayerUniverse::class, 'm')->join('m.player', 'p')->join('m.universe', 'u')->where('m.id IN (:ids)')->setParameter('ids', $ids)->orderBy('m.id', 'ASC')->getQuery()->getResult();
    }

    /** @return array<string,int> */
    public function forgottenEmpirePreviewInventory(PlayerUniverse $membership): array
    {
        $sql = <<<'SQL'
        SELECT
            (SELECT COUNT(*) FROM planet WHERE owner_id = :membership) AS colonies,
            (SELECT COUNT(*) FROM fleet WHERE player_universe_id = :membership AND status IN ('traveling','returning','attacking','defending','orbiting','orbit_pending','occupying','station_waiting')) AS deployed_fleets,
            (SELECT COUNT(*) FROM fleet f JOIN planet p ON p.id = f.destination_id
                WHERE p.owner_id = :membership AND f.player_universe_id <> :membership AND f.status IN ('traveling','attacking','defending','orbiting','orbit_pending','occupying')) AS incoming_or_stationed_fleets,
            (SELECT COUNT(*) FROM queue_item WHERE player_universe_id = :membership) AS queued_orders,
            (SELECT COUNT(*) FROM player_technology WHERE player_universe_id = :membership) AS technologies,
            (SELECT COUNT(*) FROM combat_event e JOIN planet p ON p.id = e.planet_id WHERE e.status = 'pending'
                AND (p.owner_id = :membership OR EXISTS (SELECT 1 FROM combat_participant cp WHERE cp.combat_event_id = e.id AND cp.player_universe_id = :membership))) AS open_combats,
            (SELECT COUNT(*) FROM alliance_contract WHERE (player_a_id = :membership OR player_b_id = :membership) AND status IN ('pending','accepted','enemies','peace_pending')) AS alliances,
            (SELECT COUNT(*) FROM guild_member WHERE player_universe_id = :membership) AS guild_memberships,
            (SELECT COUNT(*) FROM guild WHERE leader_id = :membership) AS led_guilds
        SQL;
        return array_map('intval', $this->em->getConnection()->fetchAssociative($sql, ['membership' => $membership->id], ['membership' => \Doctrine\DBAL\ParameterType::INTEGER]));
    }

    public function latestVacationPeriod(PlayerUniverse $membership): ?\App\Entity\VacationPeriod
    {
        return $this->em->getRepository(\App\Entity\VacationPeriod::class)->findOneBy(
            ['membership' => $membership], ['startedAt' => 'DESC', 'id' => 'DESC'],
        );
    }

    public function hasVacationFleetBlocker(PlayerUniverse $membership): bool
    {
        $fleet = $this->em->createQueryBuilder()->select('f.id')->from(Fleet::class, 'f')->leftJoin('f.destination', 'p')->where('(f.playerUniverse = :membership AND f.status IN (:active)) OR (p.owner = :membership AND f.mission IN (:hostile) AND f.status IN (:incoming))')->setParameter('membership', $membership)->setParameter('active', [
            'traveling',
            'returning',
            'attacking',
            'defending',
            'orbiting',
            'orbit_pending',
            'occupying',
            'station_waiting',
        ])->setParameter('hostile', ['attack', 'conquer'])->setParameter('incoming', ['traveling', 'attacking'])->setMaxResults(1)->getQuery()->getOneOrNullResult();
        if ($fleet !== null) {
            return true;
        }
        return $this->em->createQueryBuilder()->select('e.id')->from(\App\Entity\CombatEvent::class, 'e')->join('e.planet', 'p')->where('p.owner = :membership')->andWhere('e.status = :pending')->setParameter('membership', $membership)->setParameter('pending', 'pending')->setMaxResults(1)->getQuery()->getOneOrNullResult() !== null;
    }

    /** @return list<Fleet> */
    public function fleetsForEmpireConversion(PlayerUniverse $membership): array
    {
        return $this->em->createQueryBuilder()->select('f')->from(Fleet::class, 'f')->leftJoin('f.destination', 'p')->where('(f.playerUniverse = :membership AND f.status IN (:ownStatuses)) OR (p.owner = :membership AND f.playerUniverse != :membership AND f.status IN (:guestStatuses))')->setParameter('membership', $membership)->setParameter('ownStatuses', [
            'traveling',
            'attacking',
            'defending',
            'orbiting',
            'orbit_pending',
            'occupying',
        ])->setParameter('guestStatuses', ['defending', 'orbiting', 'orbit_pending'])->orderBy('f.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<Fleet> */
    public function pendingOrbitsFor(PlayerUniverse $host): array
    {
        return $this->em->createQueryBuilder()->select('f')->from(Fleet::class, 'f')->join('f.destination', 'p')->where('p.owner = :host')->andWhere('f.status = :pending')->setParameter('host', $host)->setParameter('pending', 'orbit_pending')->orderBy('f.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<PlayerUniverse> */
    public function inactivityWarningCandidates(
        \DateTimeImmutable $now,
        int $inactiveSeconds,
        int $reminderSeconds,
        int $conversionSeconds,
        int $limit,
    ): array
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
            'now' => $now,
            'inactiveBefore' => $now->modify('-' . $inactiveSeconds . ' seconds'),
            'inactiveSeconds' => $inactiveSeconds,
            'reminderSeconds' => $reminderSeconds,
            'conversionSeconds' => $conversionSeconds,
            'limit' => $limit,
        ], ['now' => \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE, 'inactiveBefore' => \Doctrine\DBAL\Types\Types::DATETIME_IMMUTABLE, 'limit' => \Doctrine\DBAL\ParameterType::INTEGER])->fetchFirstColumn());
        if ($ids === []) {
            return [];
        }
        $memberships = $this->em->createQueryBuilder()->select('m', 'p', 'u')->from(PlayerUniverse::class, 'm')->join('m.player', 'p')->join('m.universe', 'u')->where('m.id IN (:ids)')->setParameter('ids', $ids)->getQuery()->getResult();
        $byId = [];
        foreach ($memberships as $membership) {
            $byId[$membership->id] = $membership;
        }
        $ordered = [];
        foreach ($ids as $id) {
            if (isset($byId[$id])) {
                $ordered[] = $byId[$id];
            }
        }
        return $ordered;
    }

    public function passwordResetTokenForUpdate(string $hash): ?\App\Entity\PasswordResetToken
    {
        return $this->em->getRepository(\App\Entity\PasswordResetToken::class)->createQueryBuilder('t')->where('t.tokenHash = :hash')->setParameter('hash', $hash)->getQuery()->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
    }

    /** @return list<GameEvent> Le panneau commun conserve tous les événements applicables. */
    public function visibleGameEvents(PlayerUniverse $membership, \DateTimeImmutable $now): array
    {
        return $this->eventEntities($this->visibleGameEventsQuery($membership, $now)->orderBy('e.ends_at', 'ASC'), GameEvent::class);
    }

    public function playerGameEventsPage(PlayerUniverse $membership, \DateTimeImmutable $now, bool $upcoming, int $page): array
    {
        $query = $this->visibleGameEventsQuery($membership, $now)
            ->andWhere('NOT '.$this->nonEmptyJsonValue("JSON_EXTRACT(e.parameters, '$.challenge_id')"))
            ->andWhere($upcoming ? 'e.starts_at > :now' : 'e.starts_at <= :now')
            ->orderBy('e.ends_at', 'ASC')->addOrderBy('e.id', 'ASC');
        return $this->eventPage($query, GameEvent::class, $page);
    }

    private function visibleGameEventsQuery(PlayerUniverse $membership, \DateTimeImmutable $now): QueryBuilder
    {
        return $this->em->getConnection()->createQueryBuilder()->from('game_event', 'e')
            ->where('e.status IN (:statuses)')->andWhere('e.visible_to_players = 1')->andWhere('e.ends_at > :now')
            ->andWhere('(e.universe_id IS NULL OR e.universe_id = :universe)')
            ->setParameter('statuses', ['active', 'planned'], ArrayParameterType::STRING)
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)->setParameter('universe', $membership->universe->id);
    }

    public function playerChallengesPage(PlayerUniverse $membership, int $page): array
    {
        $query = $this->playerChallengesQuery($membership, ['scheduled', 'active', 'closed', 'cancelled'])
            ->orderBy('e.scheduled_at', 'DESC')->addOrderBy('e.id', 'DESC');
        return $this->eventPage($query, BoostEvent::class, $page);
    }

    /** @param list<string> $statuses @return list<BoostEvent> Le panneau garde son périmètre actuel, les listes sont bornées. */
    public function playerChallengeEvents(PlayerUniverse $membership, array $statuses, ?int $limit = 50, bool $newestFirst = true): array
    {
        $direction = $newestFirst ? 'DESC' : 'ASC';
        $query = $this->playerChallengesQuery($membership, $statuses)->orderBy('e.scheduled_at', $direction)->addOrderBy('e.id', $direction);
        if ($limit !== null) $query->setMaxResults(max(1, min(50, $limit)));
        return $this->eventEntities($query, BoostEvent::class);
    }

    private function playerChallengesQuery(PlayerUniverse $membership, array $statuses): QueryBuilder
    {
        return $this->em->getConnection()->createQueryBuilder()->from('boost_event', 'e')
            ->where('e.universe_id = :universe')->andWhere('e.status IN (:statuses)')
            ->andWhere('(e.configuration IS NULL OR e.configuration NOT LIKE :archived)')
            ->andWhere('('.$this->nonEmptyJsonValue("JSON_EXTRACT(e.configuration, '$.guided')").' OR BINARY e.event_key LIKE :weekly)')
            ->setParameter('universe', $membership->universe->id)->setParameter('statuses', $statuses, ArrayParameterType::STRING)
            ->setParameter('archived', '%"archived_at"%')->setParameter('weekly', 'weekly-%');
    }

    /**
     * Reproduit !empty() sur une valeur JSON avant de compter ou paginer les résultats.
     * Les expressions proviennent exclusivement de colonnes et chemins constants, jamais de saisies.
     */
    private function nonEmptyJsonValue(string $value): string
    {
        return "($value IS NOT NULL AND NOT (JSON_TYPE($value) = 'NULL'"
            ." OR (JSON_TYPE($value) IN ('INTEGER', 'DOUBLE') AND $value + 0 = 0)"
            ." OR (JSON_TYPE($value) = 'BOOLEAN' AND $value = 'false')"
            ." OR (JSON_TYPE($value) = 'STRING' AND BINARY JSON_UNQUOTE($value) IN ('', '0'))"
            ." OR (JSON_TYPE($value) IN ('ARRAY', 'OBJECT') AND JSON_LENGTH($value) = 0)))";
    }

    /** @param class-string $class */
    private function eventPage(QueryBuilder $query, string $class, int $page): array
    {
        $total = (int) (clone $query)->select('COUNT(*)')->resetOrderBy()->executeQuery()->fetchOne();
        $pages = max(1, (int) ceil($total / 25));
        $page = min($pages, max(1, $page));
        return ['rows' => $this->eventEntities($query->setFirstResult(($page - 1) * 25)->setMaxResults(25), $class),
            'total' => $total, 'page' => $page, 'pages' => $pages];
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return list<T>
     */
    private function eventEntities(QueryBuilder $query, string $class): array
    {
        // Le mapping Doctrine conserve les types JSON, dates et associations sans seconde lecture non filtrée.
        $mapping = new ResultSetMappingBuilder($this->em);
        $mapping->addRootEntityFromClassMetadata($class, 'e');
        $native = $this->em->createNativeQuery($query->select('e.*')->getSQL(), $mapping);
        foreach ($query->getParameters() as $name => $value) $native->setParameter($name, $value, $query->getParameterTypes()[$name] ?? null);
        return $native->getResult();
    }

    public function transportReportsFor(?PlayerUniverse $membership, int $page = 1): array
    {
        $query = $this->em->createQueryBuilder()->select('r', 's', 'p')->from(\App\Entity\TransportReport::class, 'r')->join('r.sender', 's')->join('r.recipient', 'p');
        if ($membership) {
            $query->where('r.sender = :member OR r.recipient = :member')->setParameter('member', $membership);
        }
        return $query->orderBy('r.createdAt', 'DESC')->addOrderBy('r.id', 'DESC')->setFirstResult((max(1, $page) - 1) * 50)->setMaxResults(50)->getQuery()->getResult();
    }

    /**
     * @param list<int> $fleetIds
     * @return array<int, int> Identifiant du rapport par flotte entrante.
     */
    public function incomingTransportReportIds(PlayerUniverse $recipient, array $fleetIds): array
    {
        if ($fleetIds === []) {
            return [];
        }

        // Le propriétaire actuel de la colonie peut voir l'arrivée sans être le
        // destinataire enregistré du fret. Ne charger ni le contenu ni les autres rapports.
        $rows = $this->em->createQueryBuilder()
            ->select('IDENTITY(report.fleet) AS fleetId', 'report.id AS reportId')
            ->from(\App\Entity\TransportReport::class, 'report')
            ->join('report.sender', 'sender')
            ->where('IDENTITY(report.fleet) IN (:fleets)')
            ->andWhere('report.recipient = :recipient')
            ->andWhere('sender.universe = :universe')
            ->setParameter('fleets', $fleetIds)
            ->setParameter('recipient', $recipient)
            ->setParameter('universe', $recipient->universe)
            ->getQuery()->getArrayResult();

        $ids = [];
        foreach ($rows as $row) {
            $ids[(int) $row['fleetId']] = (int) $row['reportId'];
        }
        return $ids;
    }

    /**
     * @param list<int> $fleetIds
     * @return array<int, array{ships:array, freight:array}> Seules les quantités au départ.
     */
    public function incomingFleetDepartureDetails(PlayerUniverse $recipient, array $fleetIds): array
    {
        if ($fleetIds === []) {
            return [];
        }

        // Relire l’hôte actuel et l’identité au départ dans la même requête. La
        // trace complète (carburant, coordonnées, etc.) ne quitte pas la base.
        $rows = $this->em->getConnection()->fetchAllAssociative(
            <<<'SQL'
                SELECT f.id, JSON_EXTRACT(trace.snapshot, '$.ships') AS ships,
                       JSON_EXTRACT(trace.snapshot, '$.freight') AS freight
                FROM fleet_mission_trace trace
                JOIN fleet f ON f.id = trace.fleet_id AND f.player_universe_id = trace.sender_id
                JOIN planet target ON target.id = f.destination_id AND target.owner_id = trace.recipient_id
                WHERE f.id IN (:fleets)
                  AND trace.universe_id = :universe AND trace.recipient_id = :recipient
                  AND trace.target_was_player = 1 AND trace.mission = f.mission
                  AND f.status = 'traveling' AND f.mission IN ('transport', 'defend', 'orbit')
                  AND (f.mission <> 'transport' OR EXISTS (
                      SELECT 1 FROM transport_report r
                      WHERE r.fleet_id = f.id AND r.sender_id = f.player_universe_id
                        AND r.recipient_id = trace.recipient_id AND r.status = 'traveling'
                  ))
                SQL,
            ['fleets' => $fleetIds, 'universe' => $recipient->universe->id, 'recipient' => $recipient->id],
            ['fleets' => ArrayParameterType::INTEGER],
        );

        $details = [];
        foreach ($rows as $row) {
            $ships = json_decode((string) $row['ships'], true);
            $freight = json_decode((string) $row['freight'], true);
            if (is_array($ships) && array_is_list($ships) && is_array($freight)) {
                $details[(int) $row['id']] = ['ships' => $ships, 'freight' => $freight];
            }
        }
        return $details;
    }

    public function espionageReportsFor(PlayerUniverse $membership, int $limit = 50): array
    {
        return $this->em->createQueryBuilder()->select('r')->from(\App\Entity\EspionageReport::class, 'r')->where('r.sender = :membership')->setParameter('membership', $membership)->orderBy('r.createdAt', 'DESC')->setMaxResults(max(1, min(100, $limit)))->getQuery()->getResult();
    }

    public function combatLogsFor(PlayerUniverse $membership, int $limit = 50): array
    {
        return $this->em->createQueryBuilder()->select('DISTINCT l')->from(CombatLog::class, 'l')->join('l.combatEvent', 'e')->join('e.planet', 'p')->leftJoin(CombatParticipant::class, 'cp', 'WITH', 'cp.combatEvent = e')->where('cp.playerUniverse = :membership')->orWhere('p.owner = :membership')->setParameter('membership', $membership)->orderBy('l.resolvedAt', 'DESC')->setMaxResults(max(1, min(100, $limit)))->getQuery()->getResult();
    }

    /** @return list<PlanetResource> Lecture courante pour un dépôt sous verrou de planète. */
    public function lockedPlanetResources(Planet $planet): array
    {
        return $this->em->createQueryBuilder()->select('resource')->from(PlanetResource::class, 'resource')
            ->where('resource.planet = :planet')->setParameter('planet', $planet)->orderBy('resource.id', 'ASC')
            ->getQuery()->setLockMode(\Doctrine\DBAL\LockMode::PESSIMISTIC_WRITE)
            ->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getResult();
    }

    public function planets(Universe $u, ?PlayerUniverse $owner = null, bool $freeOnly = false): array
    {
        $q = $this->em->createQueryBuilder()->select('p', 's', 'g')->from(Planet::class, 'p')->join('p.solarSystem', 's')->join('s.galaxy', 'g')->where('g.universe = :u')->setParameter('u', $u)->orderBy('p.id', 'ASC');
        if ($owner) {
            $q->andWhere('p.owner = :owner')->setParameter('owner', $owner)->orderBy('p.acquiredAt', 'ASC')->addOrderBy('p.id', 'ASC');
        }
        if ($freeOnly) {
            $q->andWhere('p.owner IS NULL');
        }
        return $q->getQuery()->getResult();
    }

    public function systems(Universe $u): array
    {
        return $this->em->createQueryBuilder()->select('s', 'g')->from(SolarSystem::class, 's')->join('s.galaxy', 'g')->where('g.universe = :u')->setParameter('u', $u)->orderBy('s.id', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param list<int>|null $systemIds
     * @return list<Planet>
     */
    public function discoveredPlanets(PlayerUniverse $membership, ?array $systemIds = null): array
    {
        if ($systemIds === []) {
            return [];
        }
        // La découverte et l’univers sont filtrés en base, avant toute sérialisation.
        $query = $this->em->createQueryBuilder()->select('p', 's', 'g', 'owner', 'player', 'former')->from(Planet::class, 'p')->join('p.solarSystem', 's')->join('s.galaxy', 'g')->leftJoin('p.owner', 'owner')->leftJoin('owner.player', 'player')->leftJoin('owner.formerPlayer', 'former')->join(PlayerDiscovery::class, 'd', 'WITH', 'd.solarSystem = s AND d.playerUniverse = :membership')->where('g.universe = :universe')->setParameter('membership', $membership)->setParameter('universe', $membership->universe);
        if ($systemIds !== null) {
            $query->andWhere('s.id IN (:systems)')->setParameter('systems', $systemIds);
        }
        return $query->orderBy('p.id', 'ASC')->getQuery()->getResult();
    }

    /**
     * @param list<string> $missions
     * @return list<Fleet>
     */
    public function incomingFleets(PlayerUniverse $viewer, array $missions): array
    {
        // Limiter la lecture aux arrivées étrangères vers les colonies actuellement
        // possédées, dans l’univers de la session. Les retours restent dans les vols du propriétaire.
        return $this->em->createQueryBuilder()
            ->select('f', 'sender', 'senderPlayer', 'target', 'targetSystem', 'targetGalaxy', 'home', 'homeSystem', 'homeGalaxy')
            ->from(Fleet::class, 'f')->join('f.destination', 'target')
            ->join('f.playerUniverse', 'sender')->leftJoin('sender.player', 'senderPlayer')
            ->join('target.solarSystem', 'targetSystem')->join('targetSystem.galaxy', 'targetGalaxy')
            ->join('f.homePlanet', 'home')->join('home.solarSystem', 'homeSystem')->join('homeSystem.galaxy', 'homeGalaxy')
            ->where('target.owner = :viewer')->andWhere('sender.universe = :universe')
            ->andWhere('f.playerUniverse <> :viewer')->andWhere('f.mission IN (:missions)')
            ->andWhere('(f.status = :traveling OR (f.mission = :orbit AND f.status = :pending))')
            ->setParameter('viewer', $viewer)->setParameter('universe', $viewer->universe)
            ->setParameter('missions', $missions)->setParameter('traveling', 'traveling')
            ->setParameter('orbit', 'orbit')->setParameter('pending', 'orbit_pending')
            ->orderBy('f.arrivalAt', 'ASC')->addOrderBy('f.id', 'ASC')->getQuery()->getResult();
    }

    /** @return list<Fleet> */
    public function planetHasActiveFleet(Planet $planet): bool
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(f.id)')->from(Fleet::class, 'f')->where('(f.homePlanet = :planet OR f.destination = :planet OR f.returnPlanet = :planet)')->andWhere('f.status NOT IN (:finished)')->setParameter('planet', $planet)->setParameter('finished', ['idle', 'completed', 'failed'])->getQuery()->getSingleScalarResult() > 0;
    }

    /** @return list<Fleet> */
    public function orbitRequestsFor(PlayerUniverse $viewer, ?Planet $colony = null): array
    {
        $query = $this->em->createQueryBuilder()->select('f', 'sender', 'target')->from(Fleet::class, 'f')
            ->join('f.playerUniverse', 'sender')->join('f.destination', 'target')
            ->where('sender.universe = :universe')->andWhere('f.mission = :mission')
            ->andWhere('f.status IN (:statuses)')->andWhere('(sender = :viewer OR target.owner = :viewer)')
            ->setParameter('universe', $viewer->universe)->setParameter('viewer', $viewer)
            ->setParameter('mission', 'orbit')->setParameter('statuses', ['traveling', 'orbit_pending']);
        if ($colony) {
            // Une orbite personnelle est classée côté départ, même si sa destination appartient au même empire.
            $query->andWhere('((sender = :viewer AND f.homePlanet = :colony) OR (sender <> :viewer AND target = :colony AND target.owner = :viewer))')
                ->setParameter('colony', $colony);
        }
        return $query->orderBy('f.arrivalAt', 'ASC')->addOrderBy('f.id', 'ASC')->getQuery()->getResult();
    }

    public function activeFleetsInUniverse(Universe $universe): array
    {
        return $this->em->createQueryBuilder()->select(
            'f',
            'pu',
            'home',
            'homeSystem',
            'homeGalaxy',
            'destination',
            'destinationSystem',
            'destinationGalaxy',
            'directSystem',
            'directGalaxy'
        )->from(Fleet::class, 'f')->join('f.playerUniverse', 'pu')->join('f.homePlanet', 'home')->join('home.solarSystem', 'homeSystem')->join('homeSystem.galaxy', 'homeGalaxy')->leftJoin('f.destination', 'destination')->leftJoin('destination.solarSystem', 'destinationSystem')->leftJoin('destinationSystem.galaxy', 'destinationGalaxy')->leftJoin('f.destinationSystem', 'directSystem')->leftJoin('directSystem.galaxy', 'directGalaxy')->where('pu.universe = :universe')->andWhere('f.status IN (:statuses)')->setParameter('universe', $universe)->setParameter('statuses', [
            'traveling',
            'returning',
            'attacking',
            'defending',
            'orbiting',
            'orbit_pending',
            'occupying',
            'station_waiting',
        ])->orderBy('f.arrivalAt', 'ASC')->getQuery()->getResult();
    }

    /** @return array<int,\App\Entity\CombatEvent> */
    public function fleetCombatEvents(PlayerUniverse $membership, array $fleets): array
    {
        if ($fleets === []) {
            return [];
        }
        // Conserver le premier participant historique utilisé par la vue existante.
        $participants = $this->em->createQueryBuilder()->select('participant', 'event')->from(\App\Entity\CombatParticipant::class, 'participant')->join('participant.combatEvent', 'event')->join('participant.fleet', 'fleet')->join('event.planet', 'planet')->join('planet.solarSystem', 'system')->join('system.galaxy', 'galaxy')->where('fleet IN (:fleets)')->andWhere('fleet.playerUniverse = :membership')->andWhere('galaxy.universe = :universe')->andWhere('event.status = :pending')->andWhere('participant.id = (SELECT MIN(firstParticipant.id) FROM App\Entity\CombatParticipant firstParticipant WHERE firstParticipant.fleet = fleet)')->setParameter('fleets', $fleets)->setParameter('membership', $membership)->setParameter('universe', $membership->universe)->setParameter('pending', 'pending')->getQuery()->getResult();
        $events = [];
        foreach ($participants as $participant) {
            $events[$participant->fleet->id] = $participant->combatEvent;
        }
        return $events;
    }

    public function queue(Planet $p, ?string $type = null): array
    {
        return $this->all(QueueItem::class, $type ? ['planet' => $p, 'queueType' => $type] : ['planet' => $p]);
    }

    /** @return array<int,int> */
    public function queueCounts(PlayerUniverse $membership, array $planets): array
    {
        // Des identifiants fournis ne suffisent pas : borner aussi aux colonies possédées dans cet univers.
        if ($planets === []) {
            return [];
        }
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(q.planet) AS planetId', 'COUNT(q.id) AS total')->from(QueueItem::class, 'q')->join('q.planet', 'p')->join('p.solarSystem', 's')->join('s.galaxy', 'g')->where('p IN (:planets)')->andWhere('p.owner = :membership')->andWhere('g.universe = :universe')->setParameter('planets', $planets)->setParameter('membership', $membership)->setParameter('universe', $membership->universe)->groupBy('q.planet')->getQuery()->getArrayResult();
        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['planetId']] = (int) $row['total'];
        }
        return $counts;
    }

    /** @return list<QueueItem> */
    public function researchQueue(PlayerUniverse $membership): array
    {
        // L’ordre est commun aux colonies de la participation, indépendamment de la planète affichée.
        return $this->all(QueueItem::class, ['playerUniverse' => $membership, 'queueType' => 'research'], ['id' => 'ASC']);
    }

    public function levels(string $class, array $criteria): array
    {
        $levels = [];
        foreach ($this->all($class, $criteria) as $row) {
            $levels[$row->slug] = $row->level;
        }
        return $levels;
    }

    /** @return array<string,array<string,int>> */
    public function levelsByTier(string $class, array $criteria): array
    {
        $levels = [];
        foreach ($this->all($class, $criteria) as $row) {
            $levels[$row->slug][$row->tier] = $row->level;
        }
        return $levels;
    }

    public function messageContacts(PlayerUniverse $viewer): array
    {
        // Réunir les deux sens des échanges privés ; les canaux guilde et Global ne sont pas des contacts privés.
        $rows = $this->em->createQueryBuilder()->select('IDENTITY(m.sender) AS senderId, IDENTITY(m.recipient) AS recipientId, MAX(m.id) AS latest, SUM(CASE WHEN m.recipient = :viewer AND m.readAt IS NULL THEN 1 ELSE 0 END) AS unread')->from(\App\Entity\SocialMessage::class, 'm')->where('m.guild IS NULL')->andWhere('m.recipient IS NOT NULL')->andWhere('(m.sender = :viewer OR m.recipient = :viewer)')->setParameter('viewer', $viewer)->groupBy('m.sender, m.recipient')->getQuery()->getArrayResult();
        $contacts = [];
        $peerIds = [];
        foreach ($rows as $row) {
            $peerIds[] = (int) $row['senderId'] === $viewer->id ? (int) $row['recipientId'] : (int) $row['senderId'];
        }
        $memberships = [];
        if ($peerIds !== []) {
            $peers = $this->em->createQueryBuilder()->select('m', 'p', 'former')->from(PlayerUniverse::class, 'm')->leftJoin('m.player', 'p')->leftJoin('m.formerPlayer', 'former')->where('m.universe = :universe')->andWhere('m.id IN (:ids)')->setParameter('universe', $viewer->universe)->setParameter('ids', array_unique($peerIds))->getQuery()->getResult();
            foreach ($peers as $peer) {
                $memberships[$peer->id] = $peer;
            }
        }
        foreach ($rows as $row) {
            $id = (int) $row['senderId'] === $viewer->id ? (int) $row['recipientId'] : (int) $row['senderId'];
            if (!isset($memberships[$id])) {
                continue;
            }
            $contacts[$id] ??= ['member' => $memberships[$id], 'unread' => 0, 'latest' => 0];
            $contacts[$id]['unread'] += (int) $row['unread'];
            $contacts[$id]['latest'] = max($contacts[$id]['latest'], (int) $row['latest']);
        }
        $contacts = array_filter($contacts, static fn(array $contact): bool => $contact['member']?->universe->id === $viewer->universe->id);
        usort($contacts, static fn(array $a, array $b): int => [(int) ($b['unread'] > 0), $b['latest']] <=> [(int) ($a['unread'] > 0), $a['latest']]);
        return $contacts;
    }

    public function searchMessageRecipients(PlayerUniverse $viewer, string $query): array
    {
        if (mb_strlen($query) < 2) {
            return [];
        }
        return $this->em->createQueryBuilder()->select('m', 'p')->from(PlayerUniverse::class, 'm')->join('m.player', 'p')->where('m.universe = :universe')->andWhere('m != :viewer')->andWhere("LOWER(p.username) LIKE :query ESCAPE '!' ")->setParameter('universe', $viewer->universe)->setParameter('viewer', $viewer)->setParameter('query', '%' . str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($query)) . '%')->orderBy('p.username', 'ASC')->setMaxResults(20)->getQuery()->getResult();
    }

    public function unreadGuildMessageCount(PlayerUniverse $viewer, \App\Entity\Guild $guild, int $lastRead): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(m.id)')->from(\App\Entity\SocialMessage::class, 'm')->where('m.guild = :guild')->andWhere('m.sender != :viewer')->andWhere('m.id > :lastRead')->setParameter('guild', $guild)->setParameter('viewer', $viewer)->setParameter('lastRead', $lastRead)->getQuery()->getSingleScalarResult();
    }

    public function privateConversation(PlayerUniverse $a, PlayerUniverse $b, int $limit = 100): array
    {
        $rows = $this->em->createQueryBuilder()->select('m')->from(\App\Entity\SocialMessage::class, 'm')->where('m.guild IS NULL')->andWhere('((m.sender = :a AND m.recipient = :b) OR (m.sender = :b AND m.recipient = :a))')->setParameter('a', $a)->setParameter('b', $b)->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')->setMaxResults(max(1, min(500, $limit)))->getQuery()->getResult();
        return array_reverse($rows);
    }

    public function markPrivateConversationRead(PlayerUniverse $reader, PlayerUniverse $sender, \DateTimeImmutable $readAt): int
    {
        return $this->em->createQueryBuilder()->update(\App\Entity\SocialMessage::class, 'm')->set('m.readAt', ':readAt')->where('m.recipient = :reader')->andWhere('m.sender = :sender')->andWhere('m.readAt IS NULL')->setParameter('readAt', $readAt)->setParameter('reader', $reader)->setParameter('sender', $sender)->getQuery()->execute();
    }

    public function unreadPrivateMessageCount(PlayerUniverse $recipient): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(m.id)')->from(\App\Entity\SocialMessage::class, 'm')->where('m.recipient = :recipient')->andWhere('m.readAt IS NULL')->setParameter('recipient', $recipient)->getQuery()->getSingleScalarResult();
    }

    public function globalConversation(Universe $universe, int $limit = 100): array
    {
        $rows = $this->em->createQueryBuilder()->select('m', 'sender', 'player')->from(\App\Entity\SocialMessage::class, 'm')->join('m.sender', 'sender')->leftJoin('sender.player', 'player')->where('m.guild IS NULL')->andWhere('m.recipient IS NULL')->andWhere('sender.universe = :universe')->setParameter('universe', $universe)->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')->setMaxResults(max(1, min(500, $limit)))->getQuery()->getResult();
        return array_reverse($rows);
    }

    public function guildConversation(\App\Entity\Guild $guild, int $limit = 100): array
    {
        $rows = $this->em->createQueryBuilder()->select('m')->from(\App\Entity\SocialMessage::class, 'm')->where('m.guild = :guild')->setParameter('guild', $guild)->orderBy('m.createdAt', 'DESC')->addOrderBy('m.id', 'DESC')->setMaxResults(max(1, min(500, $limit)))->getQuery()->getResult();
        return array_reverse($rows);
    }

    public function activeUniverses(): array
    {
        return $this->all(Universe::class, ['status' => 'active'], ['id' => 'ASC']);
    }

    public function weeklyBoostEventFor(Universe $universe, \DateTimeImmutable $monday): ?BoostEvent
    {
        return $this->em->createQueryBuilder()->select('e')->from(BoostEvent::class, 'e')->where('e.universe = :universe')->andWhere('e.eventKey LIKE :prefix')->setParameter('universe', $universe)->setParameter('prefix', 'weekly-' . $monday->format('Ymd') . '-u' . $universe->id . '-%')->setMaxResults(1)->getQuery()->getOneOrNullResult();
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
        return $this->em->createQueryBuilder()->select('e')->from(BoostEvent::class, 'e')->where('e.universe = :universe')->andWhere('e.templateKey = :template')->andWhere('e.status = :status')->andWhere('e.startedAt <= :now')->andWhere('e.endsAt > :now')->setParameter('universe', $membership->universe)->setParameter('template', $templateKey)->setParameter('status', 'active')->setParameter('now', $now)->orderBy('e.startedAt', 'ASC')->getQuery()->getResult();
    }

    public function boostEventContributionExists(BoostEvent $event, string $sourceKey): bool
    {
        return $this->one(BoostEventContribution::class, ['event' => $event, 'sourceKey' => $sourceKey]) !== null;
    }

    public function boostEventContributionTotal(BoostEvent $event, PlayerUniverse $membership): float
    {
        return (float) $this->em->createQueryBuilder()->select('COALESCE(SUM(c.value), 0)')->from(BoostEventContribution::class, 'c')->where('c.event = :event')->andWhere('c.playerUniverse = :membership')->setParameter('event', $event)->setParameter('membership', $membership)->getQuery()->getSingleScalarResult();
    }

    public function boostEventContributions(BoostEvent $event): array
    {
        return $this->all(BoostEventContribution::class, ['event' => $event], ['id' => 'ASC']);
    }

    /** Lecture restreinte avec contrôle du propriétaire courant, même si l'appelant possède une ancienne participation en mémoire. */
    public function ownPirateReconnaissance(PlayerUniverse $membership, string $missionKey, \DateTimeImmutable $now): ?PirateReconnaissance
    {
        return $this->em->createQueryBuilder()
            ->select('mission')->from(PirateReconnaissance::class, 'mission')
            ->join('mission.membership', 'participation')->join('participation.universe', 'universe')
            ->where('participation.id = :membership')
            ->andWhere('mission.missionKey = :missionKey')
            ->andWhere('mission.owner = :owner AND participation.player = :owner AND participation.forgottenAt IS NULL')
            ->andWhere('universe.id = :universe AND universe.status = :active')
            ->andWhere('universe.startsAt IS NULL OR universe.startsAt <= :now')
            ->andWhere('universe.endsAt IS NULL OR universe.endsAt > :now')
            ->setParameter('membership', $membership->id)
            ->setParameter('missionKey', $missionKey)
            ->setParameter('owner', $membership->player)
            ->setParameter('universe', $membership->universe->id)
            ->setParameter('active', 'active')
            ->setParameter('now', $now, Types::DATETIME_IMMUTABLE)
            ->getQuery()
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }

    public function outbox(bool $notifications): array
    {
        return $this->em->createQueryBuilder()->select('o')->from(OutboxMessage::class, 'o')->where($notifications ? 'o.kind = :kind' : 'o.kind IN (:kind)')->setParameter('kind', $notifications ? 'notification' : [
            'queue',
            'fleet',
            'combat',
            'universe',
            'occupation',
            'guild_project',
            'reconstruction',
            \App\Message\PirateReconnaissanceDue::KIND,
        ])->orderBy('o.id', 'ASC')->setMaxResults(100)->getQuery()->getResult();
    }

    public function activeQueueItems(): array
    {
        return $this->em->createQueryBuilder()->select('q')->from(QueueItem::class, 'q')->where('q.startedAt IS NOT NULL')->getQuery()->getResult();
    }

    public function activeQueueItemsFor(PlayerUniverse $membership): array
    {
        return $this->em->createQueryBuilder()->select('q')->from(QueueItem::class, 'q')->where('q.playerUniverse = :membership')->andWhere('q.startedAt IS NOT NULL')->setParameter('membership', $membership)->orderBy('q.startedAt', 'ASC')->getQuery()->getResult();
    }

    public function boostsFor(PlayerUniverse $membership): array
    {
        return $this->all(PlayerBoost::class, ['playerUniverse' => $membership], ['earnedAt' => 'ASC', 'id' => 'ASC']);
    }

    /** @return list<array{status:string,kind:string,quantity:int|string}> Comptages privés sans charger l’historique utilisé. */
    public function inventoryCounts(PlayerUniverse $membership): array
    {
        return $this->em->createQueryBuilder()->select('b.status', 'b.kind', 'COUNT(b.id) AS quantity')
            ->from(PlayerBoost::class, 'b')->where('b.playerUniverse = :membership')
            ->andWhere('b.status IN (:statuses)')->setParameter('membership', $membership)
            ->setParameter('statuses', ['available', 'pending'])->groupBy('b.status', 'b.kind')
            ->getQuery()->getArrayResult();
    }

    /** @return list<PlayerBoost> Les objets disponibles partagent une capacité maximale de 100. */
    public function availableResourceBoxes(PlayerUniverse $membership): array
    {
        return $this->em->getRepository(PlayerBoost::class)->findBy(
            ['playerUniverse' => $membership, 'status' => 'available', 'kind' => 'resource_box'],
            ['earnedAt' => 'ASC', 'id' => 'ASC'], 100,
        );
    }

    /** @return list<PlayerBoost> La file d’attente reste illimitée en stockage, sa lecture est paginée. */
    public function pendingInventoryItems(PlayerUniverse $membership, int $page, int $limit = 25): array
    {
        $limit = max(1, min(25, $limit));
        return $this->em->getRepository(PlayerBoost::class)->findBy(
            ['playerUniverse' => $membership, 'status' => 'pending'],
            ['earnedAt' => 'ASC', 'id' => 'ASC'], $limit, (max(1, $page) - 1) * $limit,
        );
    }

    public function countBoosts(PlayerUniverse $membership, string $status): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(b.id)')->from(PlayerBoost::class, 'b')->where('b.playerUniverse = :membership')->andWhere('b.status = :status')->setParameter('membership', $membership)->setParameter('status', $status)->getQuery()->getSingleScalarResult();
    }

    public function oldestBoost(PlayerUniverse $membership, string $status, ?string $kind = null): ?PlayerBoost
    {
        return $this->em->getRepository(PlayerBoost::class)->findOneBy(['playerUniverse' => $membership, 'status' => $status] + ($kind === null ? [] : ['kind' => $kind]), ['earnedAt' => 'ASC', 'id' => 'ASC']);
    }

    public function riskBoostCountSince(PlayerUniverse $membership, \DateTimeImmutable $from, \DateTimeImmutable $until): int
    {
        return (int) $this->em->createQueryBuilder()->select('COUNT(b.id)')->from(PlayerBoost::class, 'b')->where('b.playerUniverse = :membership')->andWhere('b.sourceType = :source')->andWhere('b.earnedAt >= :from')->andWhere('b.earnedAt < :until')->setParameter('membership', $membership)->setParameter('source', 'risk_mission')->setParameter('from', $from)->setParameter('until', $until)->getQuery()->getSingleScalarResult();
    }
}
