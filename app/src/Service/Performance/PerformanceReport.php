<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class PerformanceReport
{
    public const WINDOWS = [24 => '24 h', 168 => '7 j', 720 => '30 j'];

    public function __construct(
        private readonly PerformanceStore $store,
        private readonly ClockInterface $clock,
        #[Autowire('%env(int:APP_PERFORMANCE_SAMPLE_PERCENT)%')]
        private readonly int $samplePercent,
    ) {
    }

    public function read(int $hours, string $device, string $requestedPage, ?\DateTimeImmutable $periodStart = null, ?\DateTimeImmutable $periodEnd = null): array
    {
        $hours = isset(self::WINDOWS[$hours]) ? $hours : 168;
        $device = in_array($device, ['mobile', 'desktop'], true) ? $device : 'all';
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $now = $now->setTime((int) $now->format('H'), 0);
        $since = $now->modify('-' . ($hours - 1) . ' hours');
        if ($periodEnd !== null && $periodEnd <= $now) {
            $now = $periodEnd->modify('-1 hour');
            $since = $now->modify('-' . ($hours - 1) . ' hours');
        }
        if ($periodStart !== null && $periodStart > $since) {
            $since = $periodStart;
        }
        $until = $now->modify('+1 hour');
        $groups = $this->histograms($this->store->read($since, null, null, $until));
        // Un filtre appareil concerne seulement les mesures du navigateur, pas les services partagés.
        $filtered = $device === 'all' ? $groups : $this->histograms($this->store->read($since, $device, null, $until));

        $overview = [];
        $rows = [];
        foreach (PerformanceMetrics::PAGES as $key => $label) {
            $histogram = $filtered[$key]['lcp'] ?? [];
            foreach ($histogram as $ceiling => $count) {
                $overview[$ceiling] = ($overview[$ceiling] ?? 0) + $count;
            }
            $lcp = PerformancePriority::summarize($histogram);
            $server = $groups[$key] ?? [];
            $paired = [
                'count' => array_sum($server['php_pair'] ?? []),
                'sql_dominant' => array_sum($server['sql_dominates'] ?? []),
                'twig_dominant' => array_sum($server['twig_dominates'] ?? []),
                'twig_count' => array_sum($server['twig_pair'] ?? []),
            ];
            $rows[] = ['key' => $key, 'label' => $label] + $lcp + PerformancePriority::page($lcp, $paired);
        }
        usort($rows, static fn (array $a, array $b): int => [
            (int) ($b['status'] === 'review'), $b['p95'] ?? -1, $b['count'],
        ] <=> [
            (int) ($a['status'] === 'review'), $a['p95'] ?? -1, $a['count'],
        ]);
        $page = $rows[0];
        foreach ($rows as $row) {
            if ($row['key'] === $requestedPage) {
                $page = $row;
                break;
            }
        }
        $detail = [];
        foreach (['ttfb', 'html_transfer', 'lcp', 'http_php', 'http_sql', 'http_twig'] as $metric) {
            $source = str_starts_with($metric, 'http_') ? $groups : $filtered;
            $detail[$metric] = PerformancePriority::summarize($source[$page['key']][$metric] ?? []);
        }

        $rabbit = $groups['rabbitmq'] ?? [];
        $worker = $groups['worker'] ?? [];
        $mercure = $groups['mercure'] ?? [];
        $services = [
            'outbox_ready' => PerformancePriority::summarize($rabbit['outbox_ready'] ?? []),
            'broker_publish' => PerformancePriority::summarize($rabbit['broker_publish'] ?? []),
            'worker_wait' => PerformancePriority::summarize($worker['worker_wait'] ?? []),
            'worker_execute' => PerformancePriority::summarize($worker['worker_execute'] ?? []),
            'mercure_publish' => PerformancePriority::summarize($mercure['mercure_publish'] ?? []),
        ];
        $errors = [
            'rabbitmq' => array_sum($rabbit['broker_error'] ?? []),
            'worker' => array_sum($worker['worker_error'] ?? []),
            'mercure' => array_sum($mercure['mercure_error'] ?? []),
        ];
        $infrastructure = PerformancePriority::queue($services['broker_publish'], $services['worker_wait'], $services['worker_execute']);
        // Une cause ne doit pas être inventée lorsque le hub accepte la publication mais que la réception n'est pas mesurée.
        if (($services['mercure_publish']['p95'] ?? 0) > 500 || $errors['mercure'] > 0) {
            $servicesAction = 'Examiner les erreurs ou latences de publication Mercure, puis vérifier la connexion temps réel côté navigateur.';
        } elseif ($errors['rabbitmq'] > 0 || $errors['worker'] > 0) {
            $servicesAction = 'Examiner les erreurs RabbitMQ ou worker avant d’ajuster le nombre de consommateurs.';
        } else {
            $servicesAction = $infrastructure['action'];
        }

        return [
            'enabled' => $this->samplePercent > 0,
            'percent' => max(0, min(100, $this->samplePercent)),
            'hours' => $hours, 'device' => $device, 'windows' => self::WINDOWS,
            'rows' => $rows, 'page' => $page, 'detail' => $detail,
            'overview' => PerformancePriority::summarize($overview),
            'flagged' => count(array_filter($rows, static fn (array $row) => $row['status'] === 'review')),
            'services' => $services, 'errors' => $errors, 'servicesAction' => $servicesAction,
            'trend' => $this->trend($since, $now, $page['key'], $device, $hours === 24),
        ];
    }

    private function histograms(array $rows): array
    {
        $result = [];
        foreach ($rows as $row) {
            $result[$row['scope']][$row['metric']][(int) $row['ceiling_ms']] = (int) $row['observations'];
        }

        return $result;
    }

    private function trend(\DateTimeImmutable $since, \DateTimeImmutable $now, string $key, string $device, bool $hourly): array
    {
        $buckets = [];
        $start = $since;
        $end = $now->modify('+1 hour');
        foreach ($this->store->trend($since, $key, $device, $hourly, $end) as $row) {
            $buckets[$row['period']][(int) $row['ceiling_ms']] = (int) $row['observations'];
        }
        if (!$hourly) {
            $since = $since->setTime(0, 0);
            $now = $now->setTime(0, 0);
        }
        $rows = [];
        $max = 2500;
        for ($date = $since; $date <= $now; $date = $date->modify($hourly ? '+1 hour' : '+1 day')) {
            $stats = PerformancePriority::summarize($buckets[$date->format($hourly ? 'Y-m-d H:i:s' : 'Y-m-d')] ?? []);
            $rows[] = ['date' => $date->format($hourly ? 'd/m H\h' : 'd/m'), 'at' => $date->getTimestamp()] + $stats;
            $max = max($max, $stats['median'] ?? 0, $stats['p95'] ?? 0);
        }
        $paths = ['median' => '', 'p95' => ''];
        foreach ($paths as $series => &$path) {
            $connected = false;
            foreach ($rows as $index => $row) {
                if ($row[$series] === null) {
                    $connected = false;
                    continue;
                }
                $x = 55 + (int) round(695 * $index / max(1, count($rows) - 1));
                $y = 170 - (int) round(145 * $row[$series] / $max);
                $path .= ($connected ? ' L' : ' M') . $x . ' ' . $y;
                $connected = true;
            }
        }
        unset($path);

        return ['rows' => $rows, 'paths' => $paths, 'max' => $max, 'from' => $start, 'until' => $end, 'step' => $hourly ? 3600 : 86400, 'available' => $paths['median'] !== '' || $paths['p95'] !== ''];
    }
}
