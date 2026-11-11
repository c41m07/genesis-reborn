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
            'captured_at' => $now,
        ];
    }
}
