<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AdminOperationsService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
        #[Autowire('%kernel.project_dir%/var/run')] private readonly string $heartbeatDirectory,
    ) {
    }

    public function snapshot(): array
    {
        $databaseHealthy = false;
        try {
            $databaseHealthy = (int) $this->connection->fetchOne('SELECT 1') === 1;
        } catch (\Throwable) {
            $databaseHealthy = false;
        }

        $now = $this->clock->now();
        $outbox = ['queue' => 0, 'fleet' => 0, 'combat' => 0, 'universe' => 0, 'notification' => 0, 'guild_project' => 0, 'other' => 0];
        $overdue = 0;
        $outboxAvailable = false;
        $pending = [];
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT kind, COUNT(*) AS total, SUM(due_at <= :now) AS overdue FROM outbox_message GROUP BY kind',
                ['now' => $now->format('Y-m-d H:i:s')],
            );
            foreach ($rows as $row) {
                $kind = array_key_exists((string) $row['kind'], $outbox) ? (string) $row['kind'] : 'other';
                $outbox[$kind] += (int) $row['total'];
                $overdue += (int) $row['overdue'];
            }
            $details = $this->connection->fetchAllAssociative(
                'SELECT id, kind, target_id, due_at FROM outbox_message ORDER BY due_at ASC, id ASC LIMIT 20',
            );
            foreach ($details as $row) {
                $dueAt = new \DateTimeImmutable((string) $row['due_at'], new \DateTimeZone('UTC'));
                $pending[] = [
                    'id' => (int) $row['id'],
                    'kind' => (string) $row['kind'],
                    'target_id' => (int) $row['target_id'],
                    'due_at' => $dueAt,
                    'overdue_seconds' => max(0, $now->getTimestamp() - $dueAt->getTimestamp()),
                    'due' => $dueAt <= $now,
                ];
            }
            $outboxAvailable = true;
        } catch (\Throwable) {
            // Une collecte échouée ne prouve pas que la file est vide.
            $pending = [];
        }

        $processes = [];
        foreach (['worker' => 'Worker de jeu', 'relay' => 'Relais d’événements', 'notifier' => 'Relais de notifications'] as $name => $label) {
            $file = $this->heartbeatDirectory.'/'.$name.'.heartbeat';
            clearstatcache(true, $file);
            $mtime = is_file($file) ? filemtime($file) : false;
            $ageSeconds = $mtime === false ? null : max(0, $now->getTimestamp() - $mtime);
            $unknown = is_file($file) && (!is_readable($file) || $mtime === false || $mtime > $now->getTimestamp());
            $processes[] = [
                'name' => $label,
                'status' => $unknown ? 'unknown' : ($ageSeconds === null ? 'missing' : ($ageSeconds < 45 ? 'healthy' : 'stale')),
                'age_seconds' => $ageSeconds,
                'last_seen' => $mtime === false ? null : (new \DateTimeImmutable('@'.$mtime))->setTimezone(new \DateTimeZone('UTC')),
            ];
        }

        return [
            'database_healthy' => $databaseHealthy,
            'processes' => $processes,
            'outbox' => $outbox,
            'outbox_available' => $outboxAvailable,
            'outbox_total' => $outboxAvailable ? array_sum($outbox) : null,
            'outbox_overdue' => $outboxAvailable ? $overdue : null,
            'outbox_pending' => $pending,
            'fleet_health' => $this->fleetHealth($now),
            'captured_at' => $now,
        ];
    }

    private function fleetHealth(\DateTimeImmutable $now): array
    {
        $unknown = ['available'=>false, 'total'=>null, 'unscheduled'=>null, 'overdue'=>null, 'pending'=>[]];
        try {
            $threshold = "GREATEST(1, COALESCE(NULLIF(CAST(JSON_UNQUOTE(JSON_EXTRACT(u.game_settings, '$.admin_surveillance.late_minutes')) AS SIGNED), 0), :late_minutes))";
            $missing = "(f.dispatch_token IS NULL OR TRIM(f.dispatch_token) = '')";
            $late = "(f.arrival_at < TIMESTAMPADD(MINUTE, -($threshold), :now))";
            $from = " FROM fleet f INNER JOIN player_universe m ON m.id=f.player_universe_id INNER JOIN universe u ON u.id=m.universe_id WHERE f.status IN ('traveling','returning') AND f.arrival_at IS NOT NULL";
            $parameters = ['now'=>$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), 'late_minutes'=>AdminSurveillanceService::DEFAULTS['late_minutes']];
            $totals = $this->connection->fetchAssociative("SELECT COALESCE(SUM($missing OR $late),0) AS total, COALESCE(SUM($missing),0) AS unscheduled, COALESCE(SUM($late),0) AS overdue".$from, $parameters);
            if ($totals === false || !isset($totals['total'], $totals['unscheduled'], $totals['overdue'])) return $unknown;
            $pending = [];
            if ((int) $totals['total'] > 0) {
                $rows = $this->connection->fetchAllAssociative("SELECT f.id, f.mission, f.status, f.arrival_at, $missing AS unscheduled, $late AS overdue, $threshold AS late_minutes".$from." AND ($missing OR $late) ORDER BY f.arrival_at, f.id LIMIT 20", $parameters);
                foreach ($rows as $row) {
                    $pending[] = ['id'=>(int) $row['id'], 'mission'=>$row['mission'], 'status'=>$row['status'],
                        'due_at'=>new \DateTimeImmutable($row['arrival_at'], new \DateTimeZone('UTC')),
                        'unscheduled'=>(bool) $row['unscheduled'], 'overdue'=>(bool) $row['overdue'], 'late_minutes'=>(int) $row['late_minutes']];
                }
            }
            return ['available'=>true, 'total'=>(int) $totals['total'], 'unscheduled'=>(int) $totals['unscheduled'], 'overdue'=>(int) $totals['overdue'], 'pending'=>$pending];
        } catch (\Throwable) {
            // Ne pas présenter une collecte indisponible comme une absence de missions bloquées.
            return $unknown;
        }
    }

}
