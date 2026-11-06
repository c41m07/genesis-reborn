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
        $outbox = ['queue' => 0, 'fleet' => 0, 'combat' => 0, 'universe' => 0, 'notification' => 0];
        $overdue = 0;
        try {
            $rows = $this->connection->fetchAllAssociative(
                'SELECT kind, COUNT(*) AS total, SUM(due_at <= :now) AS overdue FROM outbox_message GROUP BY kind',
                ['now' => $now->format('Y-m-d H:i:s')],
            );
            foreach ($rows as $row) {
                $kind = (string) $row['kind'];
                if (array_key_exists($kind, $outbox)) {
                    $outbox[$kind] = (int) $row['total'];
                    $overdue += (int) $row['overdue'];
                }
            }
        } catch (\Throwable) {
            // La base reste visible dans son propre indicateur si la table de relais est absente.
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
            'outbox_total' => array_sum($outbox),
            'outbox_overdue' => $overdue,
            'captured_at' => $now,
        ];
    }
}
