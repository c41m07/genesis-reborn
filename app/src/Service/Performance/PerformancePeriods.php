<?php

declare(strict_types=1);

namespace App\Service\Performance;

use App\Entity\AdminAuditLog;
use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PatchNoteRepository;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final class PerformancePeriods
{
    public function __construct(
        private readonly Connection $connection,
        private readonly EntityManagerInterface $em,
        private readonly PatchNoteRepository $patchNotes,
        private readonly PerformanceStore $store,
        private readonly ClockInterface $clock,
    ) {
    }

    public function start(Player $actor): void
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $boundary = $now->setTime((int) $now->format('H'), 0)->modify('+1 hour');
        // Les mesures sont déjà agrégées par heure. Ne pas remettre le compteur à zéro au milieu d'un agrégat.
        // La même tranche ne peut être ouverte qu'une fois, même après plusieurs clics.
        $this->connection->transactional(function () use ($actor, $boundary, $now): void {
            try {
                $this->connection->insert('performance_period', ['starts_at' => $boundary->format('Y-m-d H:i:s')]);
            } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
                return;
            }
            $entry = new AdminAuditLog();
            $entry->actor = $actor;
            $entry->action = 'observatory_period_started';
            $entry->targetType = 'observatory';
            $entry->targetId = 0;
            $entry->details = ['starts_at_utc' => $boundary->format(DATE_ATOM)];
            $entry->createdAt = $now;
            $this->em->persist($entry);
            $this->em->flush();
        });
    }

    public function boundaries(): array
    {
        // Conserver la limite courante même quand les agrégats plus anciens ont été purgés.
        $rows = $this->connection->fetchFirstColumn(
            'SELECT starts_at FROM performance_period ORDER BY starts_at DESC LIMIT 100',
        );

        return array_map(static fn (string $date): \DateTimeImmutable => new \DateTimeImmutable($date, new \DateTimeZone('UTC')), $rows);
    }

    /** @return array{starts:list<\DateTimeImmutable>, start:?\DateTimeImmutable, end:?\DateTimeImmutable, pending:?\DateTimeImmutable} */
    public function selection(int $requested): array
    {
        $now = $this->clock->now();
        $boundaries = $this->boundaries();
        $starts = array_values(array_filter($boundaries, static fn (\DateTimeImmutable $start): bool => $start <= $now));
        $pending = isset($boundaries[0]) && $boundaries[0] > $now ? $boundaries[0] : null;
        $start = $starts[0] ?? null;
        $end = null;
        if ($requested === -1) {
            $end = $starts === [] ? null : $starts[array_key_last($starts)];
            $start = null;
        } else {
            foreach ($starts as $index => $candidate) {
                if ($candidate->getTimestamp() === $requested) {
                    $start = $candidate;
                    $end = $index > 0 ? $starts[$index - 1] : null;
                    break;
                }
            }
        }

        return ['starts' => $starts, 'start' => $start, 'end' => $end, 'pending' => $pending];
    }

    public function markers(array $trend, array $notes, int $left = 55, int $width = 695): array
    {
        $first = $trend['rows'][0]['at'] ?? null;
        if ($first === null) {
            return [];
        }
        $markers = [];
        foreach ($notes as $note) {
            $at = $note->publishedAt;
            if ($at === null || $at < $trend['from'] || $at >= $trend['until']) {
                continue;
            }
            $offset = ($at->getTimestamp() - $first) / $trend['step'];
            $x = $left + round($width * min(count($trend['rows']) - 1, $offset) / max(1, count($trend['rows']) - 1));
            $markers[] = ['x' => $x, 'note' => $note];
        }

        return $markers;
    }

    public function releases(?int $requested): array
    {
        $notes = $this->patchNotes->recentPublished();
        $selected = $notes[0] ?? null;
        foreach ($notes as $note) {
            if ($note->id === $requested) {
                $selected = $note;
                break;
            }
        }

        return ['notes' => $notes, 'selected' => $selected];
    }

    public function windows(\DateTimeImmutable $published, int $retentionDays = 35): array
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $lastComplete = $now->setTime((int) $now->format('H'), 0);
        $reference = $published->setTimezone(new \DateTimeZone('UTC'));
        $beforeEnd = $reference->setTime((int) $reference->format('H'), 0);
        // Si la note a été publiée au milieu d'une heure, cette heure peut mélanger avant et après.
        $afterStart = $reference == $beforeEnd ? $beforeEnd : $beforeEnd->modify('+1 hour');
        $retainedFrom = $lastComplete->modify('-' . $retentionDays . ' days');
        $hours = max(0, min(168,
            intdiv($lastComplete->getTimestamp() - $afterStart->getTimestamp(), 3600),
            intdiv($beforeEnd->getTimestamp() - $retainedFrom->getTimestamp(), 3600),
        ));
        return ['hours' => $hours, 'gap' => $afterStart != $beforeEnd, 'range' => [
            'before' => [$beforeEnd->modify('-' . $hours . ' hours'), $beforeEnd],
            'after' => [$afterStart, $afterStart->modify('+' . $hours . ' hours')],
        ]];
    }

    public function compare(\DateTimeImmutable $published, string $page, string $device): array
    {
        $window = $this->windows($published);
        $hours = $window['hours'];
        $stats = [];
        foreach ($window['range'] as $key => [$from, $to]) {
            $histogram = [];
            if ($hours > 0) {
                foreach ($this->store->read($from, $device, $page, $to) as $row) {
                    if ($row['metric'] === 'lcp') {
                        $histogram[(int) $row['ceiling_ms']] = (int) $row['observations'];
                    }
                }
            }
            $stats[$key] = ['from' => $from, 'to' => $to] + PerformancePriority::summarize($histogram);
        }
        $before = $stats['before']['p95'];
        $after = $stats['after']['p95'];

        return $stats + [
            'hours' => $hours,
            'chart' => $this->comparisonChart($window['range'], $hours, $page, $device),
            'gap' => $window['gap'],
            'delta' => $before !== null && $after !== null && $before > 0 ? ($after / $before - 1) * 100 : null,
        ];
    }
    private function comparisonChart(array $range, int $hours, string $page, string $device): array
    {
        if ($hours === 0) {
            return ['max' => 0, 'paths' => [], 'points' => []];
        }
        // Les deux courbes utilisent la même échelle et ne se rejoignent jamais pendant la mise à jour.
        $step = $hours > 48 ? 24 : ($hours > 12 ? 6 : 1);
        $max = 1500;
        $series = [];
        foreach ($range as $side => [$from, $to]) {
            $buckets = [];
            foreach ($this->store->trend($from, $page, $device, true, $to) as $row) {
                $hour = intdiv(strtotime($row['period'].' UTC') - $from->getTimestamp(), 3600);
                $index = intdiv($hour, $step);
                $ceiling = (int) $row['ceiling_ms'];
                $buckets[$index][$ceiling] = ($buckets[$index][$ceiling] ?? 0) + (int) $row['observations'];
            }
            for ($index = 0; $index * $step < $hours; ++$index) {
                $summary = PerformancePriority::summarize($buckets[$index] ?? []);
                $series[$side][] = $summary['p95'];
                $max = max($max, $summary['p95'] ?? 0);
            }
        }

        $paths = [];
        $points = [];
        foreach ($series as $side => $values) {
            $left = $side === 'before' ? 55 : 416;
            $connected = false;
            foreach ($values as $index => $value) {
                if ($value === null) {
                    $connected = false;
                    continue;
                }
                $hourMiddle = ($index * $step + min($hours, ($index + 1) * $step)) / 2;
                $x = $left + (int) round(334 * $hourMiddle / $hours);
                $y = 170 - (int) round(145 * $value / $max);
                $paths[$side] = ($paths[$side] ?? '').($connected ? ' L' : ' M').$x.' '.$y;
                $points[] = ['x' => $x, 'y' => $y];
                $connected = true;
            }
        }

        return ['max' => $max, 'paths' => array_values($paths), 'points' => $points, 'step' => $step];
    }

}
