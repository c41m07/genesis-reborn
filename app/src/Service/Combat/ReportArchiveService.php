<?php
namespace App\Service\Combat;

use App\Entity\{CombatLog, EspionageReport, PlayerUniverse, ReportArchive, TransportReport};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Fleet\EspionageReportReader;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class ReportArchiveService
{
    private const TYPES = [
        'combat'=>[CombatLog::class, 'app_combat_report'],
        'espionage'=>[EspionageReport::class, 'app_espionage_report'],
        'economy'=>[TransportReport::class, 'app_transport_report'],
    ];

    public function __construct(private EntityManagerInterface $em, private GameManager $manager,
        private GameRepository $repo, private CombatReportReader $combats,
        private EspionageReportReader $espionage, private ClockInterface $clock) {}

    public function page(PlayerUniverse $member, string $filter, bool $archived, int $page): array
    {
        $page = max(1, min(1000000, $page));
        $queries = [
            'combat'=>"SELECT 'combat' AS kind, r.id, r.resolved_at AS happened_at FROM combat_log r JOIN combat_event e ON e.id=r.combat_event_id JOIN planet p ON p.id=e.planet_id JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=:universe AND (p.owner_id=:member OR EXISTS (SELECT 1 FROM combat_participant cp WHERE cp.combat_event_id=e.id AND cp.player_universe_id=:member))",
            'espionage'=>"SELECT 'espionage' AS kind, r.id, r.created_at AS happened_at FROM espionage_report r JOIN player_universe sender ON sender.id=r.sender_id WHERE r.sender_id=:member AND sender.universe_id=:universe",
            'economy'=>"SELECT 'economy' AS kind, r.id, r.created_at AS happened_at FROM transport_report r JOIN player_universe sender ON sender.id=r.sender_id JOIN player_universe recipient ON recipient.id=r.recipient_id WHERE (r.sender_id=:member OR r.recipient_id=:member) AND sender.universe_id=:universe AND recipient.universe_id=:universe",
        ];
        foreach ($queries as $kind=>&$sql) {
            $sql .= ($archived ? ' AND EXISTS' : ' AND NOT EXISTS')." (SELECT 1 FROM report_archive a WHERE a.player_universe_id=:member AND a.kind='".$kind."' AND a.report_id=r.id AND a.archived_at IS NOT NULL)";
        }
        unset($sql);
        $all = implode(' UNION ALL ', $queries);
        $selected = $filter === 'all' ? $all : $queries[$filter];
        $params = ['member'=>$member->id, 'universe'=>$member->universe->id];
        $connection = $this->em->getConnection();
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM ('.$all.') all_reports', $params);
        $count = $filter === 'all' ? $total : (int) $connection->fetchOne('SELECT COUNT(*) FROM ('.$selected.') selected_reports', $params);
        $pages = max(1, (int) ceil($count / 50));
        $page = min($page, $pages);
        $rows = $connection->fetchAllAssociative('SELECT * FROM ('.$selected.') reports ORDER BY happened_at DESC, id DESC, kind ASC LIMIT 50 OFFSET '.(($page - 1) * 50), $params);
        $entries = [];
        foreach ($rows as $row) {
            [$class, $route] = self::TYPES[$row['kind']];
            $entries[] = ['kind'=>$row['kind'], 'report'=>$this->repo->find($class, (int) $row['id']), 'route'=>$route,
                'key'=>$row['kind'].':'.$row['id']];
        }
        return ['entries'=>$entries, 'report_count'=>$total, 'page'=>$page, 'pages'=>$pages];
    }

    public function change(PlayerUniverse $member, array $keys, bool $archive): void
    {
        $keys = array_values(array_unique($keys, SORT_REGULAR));
        if (!$keys || count($keys) > 50) throw new \DomainException('Sélectionnez entre 1 et 50 rapports.');
        $this->manager->transaction(function () use ($member, $keys, $archive): void {
            $this->manager->lock($member);
            $reports = [];
            foreach ($keys as $key) {
                if (!is_string($key) || !preg_match('/^(combat|espionage|economy):([1-9][0-9]{0,9})$/D', $key, $match)) throw new \DomainException('Sélection de rapports invalide.');
                $kind = $match[1];$id = (int) $match[2];
                $report = match ($kind) {
                    'combat'=>$this->combats->findVisible($member, $id),
                    'espionage'=>$this->espionage->findVisible($member, $id),
                    'economy'=>$this->repo->find(TransportReport::class, $id),
                };
                $allowed = $report !== null && match ($kind) {
                    'combat'=>$report->combatEvent->planet->solarSystem->galaxy->universe->id === $member->universe->id,
                    'espionage'=>$report->sender->universe->id === $member->universe->id,
                    'economy'=>in_array($member->id, [$report->sender->id, $report->recipient->id], true)
                        && $report->sender->universe->id === $member->universe->id && $report->recipient->universe->id === $member->universe->id,
                };
                if (!$allowed) throw new \DomainException('Un rapport sélectionné est inaccessible. Aucun changement appliqué.');
                $reports[] = [$kind, $id];
            }
            foreach ($reports as [$kind, $id]) {
                $state = $this->repo->one(ReportArchive::class, ['playerUniverse'=>$member, 'kind'=>$kind, 'reportId'=>$id]);
                if (!$state && !$archive) continue;
                if (!$state) {
                    $state = new ReportArchive();$state->playerUniverse = $member;$state->kind = $kind;$state->reportId = $id;
                    $this->manager->save($state);
                }
                if ($archive && $state->archivedAt === null) $state->archivedAt = $this->clock->now();
                if (!$archive) $state->archivedAt = null;
            }
        });
    }
}
