<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\{Planet, PlanetBuilding, PlanetResource, Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final class UniverseMetricsService
{
    public const PAGE_SIZE = 25;

    public function __construct(
        private readonly Connection $connection,
        private readonly GameRepository $repo,
        private readonly ResourceService $resources,
        private readonly GameCatalog $catalog,
        private readonly ClockInterface $clock,
    ) {
    }

    public function snapshot(Universe $universe, int $page = 1): array
    {
        $now = $this->clock->now();
        $members = $this->connection->fetchAllAssociative(
            'SELECT joined_at, last_recovery_protection_at, recovery_protected_until FROM player_universe WHERE universe_id = ?',
            [$universe->id],
        );
        $ages = [];
        $invalidDates = 0;
        $protected = 0;
        $recoveryActivations = 0;
        foreach ($members as $member) {
            $joined = $this->timestamp($member['joined_at']);
            if ($joined <= $now->getTimestamp()) {
                $ages[] = $now->getTimestamp() - $joined;
            } else {
                ++$invalidDates;
            }
            $recoveryActivations += $member['last_recovery_protection_at'] !== null ? 1 : 0;
            $protected += $member['recovery_protected_until'] !== null && $this->timestamp($member['recovery_protected_until']) > $now->getTimestamp() ? 1 : 0;
        }
        $buildingLevels = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(b.level), 0) FROM planet_building b JOIN planet p ON p.id=b.planet_id JOIN player_universe pu ON pu.id=p.owner_id WHERE pu.universe_id=?',
            [$universe->id],
        );
        $researchLevels = (int) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(t.level), 0) FROM player_technology t JOIN player_universe pu ON pu.id=t.player_universe_id WHERE pu.universe_id=?',
            [$universe->id],
        );
        $queues = $this->connection->fetchAllAssociative(
            'SELECT q.queued_at, q.started_at, q.duration_seconds FROM queue_item q JOIN player_universe pu ON pu.id=q.player_universe_id WHERE pu.universe_id=?',
            [$universe->id],
        );
        $pendingAges = [];
        $startWaits = [];
        $remaining = [];
        $overdue = 0;
        $invalidQueues = 0;
        foreach ($queues as $queue) {
            $queued = $this->timestamp($queue['queued_at']);
            $started = $queue['started_at'] === null ? null : $this->timestamp($queue['started_at']);
            if ($queued > $now->getTimestamp() || ($started !== null && ($started < $queued || $started > $now->getTimestamp())) || (int) $queue['duration_seconds'] < 1) {
                ++$invalidQueues;
                continue;
            }
            if ($started === null) {
                $pendingAges[] = $now->getTimestamp() - $queued;
            } else {
                $startWaits[] = $started - $queued;
                $finish = $started + (int) $queue['duration_seconds'];
                $remaining[] = max(0, $finish - $now->getTimestamp());
                $overdue += $finish <= $now->getTimestamp() ? 1 : 0;
            }
        }
        $outcomes = $this->connection->fetchAllKeyValue(
            'SELECT l.outcome, COUNT(*) FROM combat_log l JOIN combat_event e ON e.id=l.combat_event_id JOIN planet p ON p.id=e.planet_id JOIN solar_system s ON s.id=p.solar_system_id JOIN galaxy g ON g.id=s.galaxy_id WHERE g.universe_id=? GROUP BY l.outcome',
            [$universe->id],
        );

        return [
            'observedAt' => $now,
            'closed' => $universe->status === 'closed',
            'progression' => ['members' => count($members), 'age' => $this->distribution($ages), 'invalidDates' => $invalidDates, 'buildingLevels' => $buildingLevels, 'researchLevels' => $researchLevels],
            'queues' => ['total' => count($queues), 'pending' => $this->distribution($pendingAges), 'startWait' => $this->distribution($startWaits), 'remaining' => $this->distribution($remaining), 'overdue' => $overdue, 'invalidDates' => $invalidQueues],
            'recovery' => ['protected' => $protected, 'membersWithRecordedProtection' => $recoveryActivations, 'outcomes' => array_map('intval', $outcomes)],
            'resources' => $this->resourceMetrics($universe, $page, $now),
        ];
    }

    private function resourceMetrics(Universe $universe, int $page, \DateTimeImmutable $now): array
    {
        $total = (int) $this->connection->fetchOne('SELECT COUNT(*) FROM planet p JOIN player_universe pu ON pu.id=p.owner_id WHERE pu.universe_id=?', [$universe->id]);
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($pages, $page));
        $ids = $this->connection->fetchFirstColumn(
            'SELECT p.id FROM planet p JOIN player_universe pu ON pu.id=p.owner_id WHERE pu.universe_id=? ORDER BY p.id LIMIT '.self::PAGE_SIZE.' OFFSET '.(($page - 1) * self::PAGE_SIZE),
            [$universe->id],
        );
        $rows = [];
        foreach ($this->catalog->rules()['resources'] as $slug => $label) {
            $rows[$slug] = ['label' => $label, 'empty' => 0, 'negativeRate' => 0, 'rateObserved' => 0, 'reducedProductivity' => 0, 'productivityObserved' => 0, 'stockPercentages' => []];
        }
        $observed = 0;
        $unavailable = 0;
        foreach ($ids as $id) {
            $planet = $this->repo->find(Planet::class, (int) $id);
            if (!$planet instanceof Planet || $planet->owner?->universe->id !== $universe->id) {
                ++$unavailable;
                continue;
            }
            $stored = [];
            foreach ($this->repo->all(PlanetResource::class, ['planet' => $planet]) as $resource) {
                if ($resource->lastUpdatedAt <= $now) {
                    $stored[$resource->resourceType] = $resource;
                }
            }
            if (array_diff_key($rows, $stored) !== []) {
                ++$unavailable;
                continue;
            }
            // Projection du moteur existant : ne pas matérialiser les stocks dans une page d'observation.
            if ($universe->status === 'closed') {
                $levels = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
                $snapshot = [];
                foreach ($rows as $slug => $row) {
                    $snapshot[$slug] = ['stock' => $stored[$slug]->currentStock, 'capacity' => $this->resources->capacity($planet, $slug, $levels), 'rate' => null, 'productivity' => null];
                }
            } else {
                $snapshot = $this->resources->snapshot($planet);
            }
            ++$observed;
            foreach ($rows as $slug => &$row) {
                $value = $snapshot[$slug];
                $row['empty'] += $value['stock'] <= 0 ? 1 : 0;
                if ($value['rate'] !== null) {
                    ++$row['rateObserved'];
                    $row['negativeRate'] += $value['rate'] < 0 ? 1 : 0;
                }
                if ($value['productivity'] !== null) {
                    ++$row['productivityObserved'];
                    $row['reducedProductivity'] += $value['productivity'] < 100 - 1e-6 ? 1 : 0;
                }
                if ($value['capacity'] > 0) {
                    $row['stockPercentages'][] = 100 * $value['stock'] / $value['capacity'];
                }
            }
            unset($row);
        }
        foreach ($rows as &$row) {
            $row['stockPercent'] = $this->distribution($row['stockPercentages'])['median'];
            unset($row['stockPercentages']);
        }
        unset($row);

        return ['total' => $total, 'page' => $page, 'pages' => $pages, 'observed' => $observed, 'unavailable' => $unavailable, 'rows' => $rows];
    }

    private function timestamp(string $value): int
    {
        return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
    }

    private function distribution(array $values): array
    {
        if ($values === []) {
            return ['count' => 0, 'median' => null, 'max' => null];
        }
        sort($values, SORT_NUMERIC);
        $middle = intdiv(count($values), 2);
        $median = count($values) % 2 === 1 ? $values[$middle] : ($values[$middle - 1] + $values[$middle]) / 2;

        return ['count' => count($values), 'median' => $median, 'max' => max($values)];
    }
}
