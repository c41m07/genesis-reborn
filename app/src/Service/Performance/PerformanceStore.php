<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;

final class PerformanceStore
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /** @param array<string,int> $values */
    public function record(\DateTimeImmutable $when, string $scope, string $device, array $values): void
    {
        $tuples = [];
        $params = [];
        $hour = $when->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
        // Trier garantit le même ordre de verrouillage lors d'insertions concurrentes.
        ksort($values);
        foreach ($values as $metric => $milliseconds) {
            if (!PerformanceMetrics::accepts($scope, $device, $metric) || $milliseconds < 0) {
                continue;
            }
            $tuples[] = '(?, ?, ?, ?, ?, 1)';
            array_push($params, $hour, $scope, $device, $metric, PerformanceMetrics::bucket($milliseconds));
        }
        if ($tuples === []) {
            return;
        }
        $this->connection->executeStatement(
            'INSERT INTO performance_bucket (hour_utc, scope, device, metric, ceiling_ms, observations) VALUES '
                . implode(', ', $tuples) . ' ON DUPLICATE KEY UPDATE observations = observations + 1',
            $params,
        );
    }

    /** @param array{route:string,issued:int,receipt:string} $sample
     *  @param array<string,int> $values
     */
    public function recordBrowser(array $sample, string $device, array $values): void
    {
        // Le reçu et les compteurs avancent ensemble. Une retransmission ne compte jamais deux fois.
        $this->connection->transactional(function () use ($sample, $device, $values): void {
            $at = (new \DateTimeImmutable('@' . $sample['issued']))->setTimezone(new \DateTimeZone('UTC'));
            $expiry = $at->modify('+' . (BrowserSampleToken::LIFETIME_SECONDS + 60) . ' seconds');
            try {
                $this->connection->insert('performance_receipt', [
                    'receipt' => $sample['receipt'],
                    'expires_at' => $expiry->format('Y-m-d H:i:s'),
                ]);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                return;
            }
            $this->record($at, $sample['route'], $device, $values);
        });
    }

    public function prune(\DateTimeImmutable $now): int
    {
        $cutoff = $now->setTimezone(new \DateTimeZone('UTC'))->modify('-35 days')->format('Y-m-d H:00:00');

        $this->connection->executeStatement(
            'DELETE FROM performance_receipt WHERE expires_at < ? ORDER BY expires_at LIMIT 20000',
            [$now->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );

        return (int) $this->connection->executeStatement(
            'DELETE FROM performance_bucket WHERE hour_utc < ? ORDER BY hour_utc LIMIT 20000',
            [$cutoff],
        );
    }

    /** @return list<array{scope:string,metric:string,ceiling_ms:int,observations:int|string}> */
    public function read(\DateTimeImmutable $since, ?string $device = null, ?string $scope = null, ?\DateTimeImmutable $until = null): array
    {
        $where = ['hour_utc >= :since'];
        $params = ['since' => $since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00')];
        $types = ['since' => ParameterType::STRING];
        if ($until !== null) {
            $where[] = 'hour_utc < :until';
            $params['until'] = $until->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00');
            $types['until'] = ParameterType::STRING;
        }
        if ($device !== null && in_array($device, PerformanceMetrics::DEVICES, true)) {
            $where[] = 'device = :device';
            $params['device'] = $device;
            $types['device'] = ParameterType::STRING;
        }
        if ($scope !== null && (isset(PerformanceMetrics::PAGES[$scope]) || in_array($scope, PerformanceMetrics::BACKGROUND, true))) {
            $where[] = 'scope = :scope';
            $params['scope'] = $scope;
            $types['scope'] = ParameterType::STRING;
        }

        return $this->connection->fetchAllAssociative(
            'SELECT scope, metric, ceiling_ms, SUM(observations) AS observations FROM performance_bucket WHERE '
                . implode(' AND ', $where) . ' GROUP BY scope, metric, ceiling_ms',
            $params,
            $types,
        );
    }
    public function trend(\DateTimeImmutable $since, string $scope, ?string $device, bool $hourly, \DateTimeImmutable $until): array
    {
        if (!isset(PerformanceMetrics::PAGES[$scope])) {
            return [];
        }
        $interval = $hourly ? 'hour_utc' : 'DATE(hour_utc)';
        $where = 'hour_utc >= ? AND hour_utc < ? AND scope = ? AND metric = ?';
        $params = [$since->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:00:00'), $until->format('Y-m-d H:00:00'), $scope, 'lcp'];
        if ($device !== null && in_array($device, ['mobile', 'desktop'], true)) {
            $where .= ' AND device = ?';
            $params[] = $device;
        }

        return $this->connection->fetchAllAssociative(
            'SELECT ' . $interval . ' AS period, ceiling_ms, SUM(observations) AS observations
             FROM performance_bucket WHERE ' . $where . ' GROUP BY ' . $interval . ', ceiling_ms ORDER BY period',
            $params,
        );
    }

}
