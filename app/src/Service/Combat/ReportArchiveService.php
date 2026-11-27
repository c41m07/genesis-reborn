<?php

namespace App\Service\Combat;

use App\Entity\{CombatLog, DiplomaticReport, EspionageReport, PlayerUniverse, ReportArchive, TransportReport};
use App\Manager\GameManager;
use App\Repository\{GameRepository, PirateReportRepository};
use App\Service\Fleet\EspionageReportReader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ReportArchiveService
{
    private const TYPES = ['combat' => [CombatLog::class, 'app_combat_report'], 'espionage' => [EspionageReport::class, 'app_espionage_report'], 'economy' => [TransportReport::class, 'app_transport_report'], 'diplomatic' => [DiplomaticReport::class, 'app_diplomatic_report']];
    public function __construct(
        private PirateReportRepository $campaigns,
        private EntityManagerInterface $em,
        private GameManager $manager,
        private GameRepository $repo,
        private CombatReportReader $combats,
        private EspionageReportReader $espionage,
        private ClockInterface $clock,
        private \App\Repository\ReportSummaryRepository $summaries,
    )
    {
    }

    public function page(
        PlayerUniverse $member,
        string $filter,
        bool $archived,
        int $page,
        ?\App\Service\Search\SearchCriteria $criteria = null,
    ): array
    {
        $page = max(1, min(1000000, $page));
        $queries = ['combat' => "SELECT 'combat' AS kind, r.id, r.resolved_at AS happened_at FROM combat_log r JOIN combat_event e ON e.id=r.combat_event_id JOIN planet p ON p.id=e.planet_id JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=:universe AND (p.owner_id=:member OR EXISTS (SELECT 1 FROM combat_participant cp WHERE cp.combat_event_id=e.id AND cp.player_universe_id=:member))", 'espionage' => "SELECT 'espionage' AS kind, r.id, r.created_at AS happened_at FROM espionage_report r JOIN player_universe sender ON sender.id=r.sender_id WHERE r.sender_id=:member AND sender.universe_id=:universe", 'economy' => "SELECT 'economy' AS kind, r.id, r.created_at AS happened_at FROM transport_report r JOIN player_universe sender ON sender.id=r.sender_id JOIN player_universe recipient ON recipient.id=r.recipient_id WHERE (r.sender_id=:member OR r.recipient_id=:member) AND sender.universe_id=:universe AND recipient.universe_id=:universe",
            'diplomatic' => "SELECT 'diplomatic' AS kind, r.id, r.created_at AS happened_at
                FROM diplomatic_report r JOIN player_universe recipient ON recipient.id=r.recipient_id
                WHERE recipient.id=:member AND recipient.universe_id=:universe",
        ];
        if ($this->campaigns->admitted($member)) {
            $queries['campaign'] = "SELECT 'campaign' AS kind, r.id, r.created_at AS happened_at"
                .PirateReportRepository::SOURCE.' WHERE '.PirateReportRepository::OWNER
                .' AND seat.id=:member AND seat.universe_id=:universe';
        }
        $params = ['member' => $member->id, 'universe' => $member->universe->id];
        foreach ($queries as $kind => &$sql) {
            if ($criteria) {
                $date = $kind === 'combat' ? 'r.resolved_at' : 'r.created_at';
                if ($criteria->since) {
                    $sql .= ' AND ' . $date . '>=:since';
                    $params['since'] = $criteria->since;
                }
                if ($criteria->before) {
                    $sql .= ' AND ' . $date . '<:before';
                    $params['before'] = $criteria->before;
                }
                if ($criteria->values['planet'] !== '') {
                    $params['planet'] = \App\Service\Search\SearchCriteria::pattern($criteria->values['planet']);
                    $sql .= match ($kind) {
                        'campaign' => " AND LOWER(r.location) LIKE :planet ESCAPE '!'",
                        'combat' => " AND LOWER(p.name) LIKE :planet ESCAPE '!'",
                        'espionage' => " AND EXISTS (SELECT 1 FROM planet ep WHERE ep.id=r.target_id AND LOWER(ep.name) LIKE :planet ESCAPE '!')",
                        'diplomatic' => " AND LOWER(r.location) LIKE :planet ESCAPE '!'",
                        'economy' => " AND EXISTS (SELECT 1 FROM fleet ef JOIN planet ep ON ep.id=ef.home_planet_id OR ep.id=ef.destination_id WHERE ef.id=r.fleet_id AND LOWER(ep.name) LIKE :planet ESCAPE '!')",
                    };
                }
                if ($criteria->values['participant'] !== '') {
                    $params['person'] = \App\Service\Search\SearchCriteria::pattern($criteria->values['participant']);
                    $sql .= match ($kind) {
                        'campaign' => " AND (LOWER(account.username) LIKE :person ESCAPE '!' OR 'pirates' LIKE :person ESCAPE '!')",
                        'combat' => " AND EXISTS (SELECT 1 FROM combat_participant pc JOIN player_universe pm ON pm.id=pc.player_universe_id LEFT JOIN player pp ON pp.id=pm.player_id WHERE pc.combat_event_id=e.id AND pm.universe_id=:universe AND LOWER(COALESCE(pp.username,'Empire oublié')) LIKE :person ESCAPE '!')",
                        'espionage' => " AND EXISTS (SELECT 1 FROM player pp WHERE pp.id=sender.player_id AND LOWER(pp.username) LIKE :person ESCAPE '!')",
                        'diplomatic' => " AND EXISTS (SELECT 1 FROM player_universe counterparty JOIN player pp ON pp.id=counterparty.player_id WHERE counterparty.id=r.counterpart_id AND counterparty.universe_id=:universe AND counterparty.forgotten_at IS NULL AND LOWER(pp.username) LIKE :person ESCAPE '!')",
                        'economy' => " AND EXISTS (SELECT 1 FROM player pp WHERE (pp.id=sender.player_id OR pp.id=recipient.player_id) AND LOWER(pp.username) LIKE :person ESCAPE '!')",
                    };
                }
            }
            // Le retrait conserve sa date d’archivage et exclut le rapport des deux listes.
            $archiveState = $archived
                ? 'a.archived_at IS NOT NULL AND a.removed_at IS NULL'
                : '(a.archived_at IS NOT NULL OR a.removed_at IS NOT NULL)';
            $sql .= ($archived ? ' AND EXISTS' : ' AND NOT EXISTS')."
                (SELECT 1 FROM report_archive a WHERE a.player_universe_id=:member AND a.kind='".$kind."'
                 AND a.report_id=r.id AND ".$archiveState.')';
        }
        unset($sql);
        // Compter et paginer après les droits et les filtres, y compris dans l’onglet Tous.
        $all = implode(' UNION ALL ', $queries);
        $selected = $filter === 'all' ? $all : $queries[$filter];
        if (isset($queries['campaign']) && in_array($filter, ['espionage', 'combat', 'economy'], true)) {
            $selected .= ' UNION ALL '.$queries['campaign'].' AND r.category=:campaignCategory';
        }
        $connection = $this->em->getConnection();
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM (' . $all . ') all_reports', $params);
        if (str_contains($selected, ':campaignCategory')) {
            $params['campaignCategory'] = $filter;
        }
        $count = $filter === 'all' ? $total : (int) $connection->fetchOne('SELECT COUNT(*) FROM (' . $selected . ') selected_reports', $params);
        $pages = max(1, (int) ceil($count / 50));
        $page = min($page, $pages);
        $rows = $connection->fetchAllAssociative('SELECT * FROM (' . $selected . ') reports ORDER BY happened_at DESC, id DESC, kind ASC LIMIT 50 OFFSET ' . ($page - 1) * 50, $params);
        // Les droits, filtres et LIMIT sont déjà appliqués : charger seulement cette page.
        $idsByKind = [];
        foreach ($rows as $row) {
            $idsByKind[$row['kind']][] = (int) $row['id'];
        }
        $reportsByKind = [];
        foreach ($idsByKind as $kind => $ids) {
            $reportsByKind[$kind] = match ($kind) {
                'campaign' => $this->campaigns->summaries($member, $ids),
                'combat' => $this->summaries->combats($member, $ids),
                'espionage' => $this->summaries->espionage($member, $ids),
                'economy' => $this->summaries->transports($member, $ids),
                'diplomatic' => $this->summaries->diplomatic($member, $ids),
            };
        }
        $entries = [];
        foreach ($rows as $row) {
            $campaign = $row['kind'] === 'campaign';
            $route = $campaign ? 'app_pirate_mission_report' : self::TYPES[$row['kind']][1];
            $report = $reportsByKind[$row['kind']][(int) $row['id']] ?? null;
            if ($campaign && $report === null) {
                continue;
            }
            $entries[] = [
                'kind' => $campaign ? $report['category'] : $row['kind'],
                'report' => $campaign ? $report + ['id' => $row['id']] : $report,
                'route' => $route,
                'key' => $row['kind'] . ':' . $row['id'],
            ];
        }
        return [
            'entries' => $entries,
            'report_count' => $total,
            'page' => $page,
            'pages' => $pages,
        ];
    }

    public function change(PlayerUniverse $member, array $keys, bool $archive): void
    {
        $this->apply($member, $keys, $archive ? 'archive' : 'restore');
    }

    public function removeFromArchives(PlayerUniverse $member, array $keys): void
    {
        $this->apply($member, $keys, 'remove');
    }

    private function apply(PlayerUniverse $member, array $keys, string $operation): void
    {
        $keys = array_values(array_unique($keys, SORT_REGULAR));
        if (!$keys || count($keys) > 50) {
            throw new \DomainException('Sélectionnez entre 1 et 50 rapports.');
        }
        $this->manager->transaction(function () use ($member, $keys, $operation): void {
            // Sérialiser archivage, restauration et retrait en verrouillant le même empire.
            $this->manager->lock($member);
            $reports = [];
            foreach ($keys as $key) {
                if (!is_string($key) || !preg_match('/^(combat|espionage|economy|diplomatic|campaign):([1-9][0-9]{0,9})$/D', $key, $match)) {
                    throw new \DomainException('Sélection de rapports invalide.');
                }
                $kind = $match[1];
                $id = (int) $match[2];
                $report = match ($kind) {
                    'campaign' => $this->campaigns->visible($member, $id),
                    'combat' => $this->combats->findVisible($member, $id),
                    'espionage' => $this->espionage->findVisible($member, $id),
                    'economy' => $this->repo->find(TransportReport::class, $id),
                    'diplomatic' => $this->repo->one(DiplomaticReport::class, ['id' => $id, 'recipient' => $member]),
                };
                $allowed = $report !== null && match ($kind) {
                    'campaign' => true, // visible() a déjà vérifié le propriétaire et son univers.
                    'combat' => $report->combatEvent->planet->solarSystem->galaxy->universe->id === $member->universe->id,
                    'espionage' => $report->sender->universe->id === $member->universe->id,
                    'diplomatic' => $report->recipient->id === $member->id && $report->recipient->universe->id === $member->universe->id,
                    'economy' => in_array($member->id, [$report->sender->id, $report->recipient->id], true) && $report->sender->universe->id === $member->universe->id && $report->recipient->universe->id === $member->universe->id,
                };
                if (!$allowed) {
                    throw new \DomainException('Un rapport sélectionné est inaccessible. Aucun changement appliqué.');
                }
                $state = $this->repo->one(ReportArchive::class, ['playerUniverse' => $member, 'kind' => $kind, 'reportId' => $id]);
                if ($state?->removedAt !== null || ($operation === 'remove' && $state?->archivedAt === null)) {
                    throw new \DomainException('Un rapport sélectionné n’est pas disponible dans vos archives. Aucun changement appliqué.');
                }
                $reports[] = [$kind, $id, $state];
            }
            // Valider toute la sélection avant de toucher aux marqueurs personnels ou de les créer.
            foreach ($reports as [$kind, $id, $state]) {
                if ($operation === 'remove') {
                    $state->removedAt = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
                    continue;
                }
                if ($operation === 'restore') {
                    if ($state) {
                        $state->archivedAt = null;
                    }
                    continue;
                }
                if (!$state) {
                    $state = new ReportArchive();
                    $state->playerUniverse = $member;
                    $state->kind = $kind;
                    $state->reportId = $id;
                    $this->manager->save($state);
                }
                if ($state->archivedAt === null) {
                    $state->archivedAt = $this->clock->now();
                }
            }
        });
    }
}
