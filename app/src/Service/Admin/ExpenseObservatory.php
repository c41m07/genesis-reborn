<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Repository\AdminExpenseReadRepository;
use App\Service\Catalog\GameCatalog;
use Psr\Clock\ClockInterface;

final readonly class ExpenseObservatory
{
    public function __construct(private AdminExpenseReadRepository $read, private ClockInterface $clock)
    {
    }

    public function read(
        int $universeId,
        int $days,
        ?\DateTimeImmutable $periodStart,
        ?\DateTimeImmutable $periodEnd,
        int $selectedId,
        string $query,
    ): array {
        $query = mb_substr(trim($query), 0, 64);
        $commanders = $this->read->commanders($universeId, $query, $selectedId);
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $until = $periodEnd ?? $now->modify('+1 second');
        $from = ($periodEnd?->modify('-1 second') ?? $now)->setTime(0, 0)->modify('-'.($days - 1).' days');
        if ($periodStart !== null) {
            $from = max($from, $periodStart);
        }

        $starts = ['catalogue' => $this->read->catalogueAvailableSince(), 'fuel' => $this->read->fuelAvailableSince()];
        $view = $commanders + [
            'query' => $query,
            'from' => $from,
            'until' => $until,
            'starts' => $starts,
            'partial' => $starts['catalogue'] === null || $starts['fuel'] === null || $from < max($starts),
            'requestedId' => $selectedId,
            'rows' => [],
            'totals' => [],
            'weighted' => [],
            'maxMagnitude' => 1.0,
            'valuesAvailable' => false,
        ];
        if ($view['selected'] === null || $from >= $until) {
            return $view;
        }

        $zeros = array_fill_keys(AdminExpenseReadRepository::RESOURCES, 0.0);
        $kinds = ['paid', 'refunded', 'net'];
        $totals = array_fill_keys($kinds, $zeros);
        $weighted = array_fill_keys($kinds, 0.0);
        $hasCatalogue = $starts['catalogue'] !== null && $starts['catalogue'] < $until;
        $hasFuel = $starts['fuel'] !== null && $starts['fuel'] < $until;
        $rows = $hasCatalogue ? $this->read->catalogueForMembership($universeId, $selectedId, max($from, $starts['catalogue']), $until) : [];
        if ($hasFuel) {
            $fuel = $this->read->fuelForMembership($universeId, $selectedId, max($from, $starts['fuel']), $until);
            $fuelResources = array_replace($zeros, ['hydrogen' => $fuel]);
            $rows['fuel'] = ['paid' => $fuelResources, 'refunded' => $zeros, 'net' => $fuelResources, 'material_net' => $fuel];
        }
        foreach ($rows as $row) {
            foreach ($kinds as $kind) {
                foreach ($row[$kind] as $resource => $value) {
                    $totals[$kind][$resource] += $value;
                    $weighted[$kind] += $value * (GameCatalog::MATERIAL_WEIGHTS[$resource] ?? 0);
                }
            }
        }
        $view['rows'] = $rows;
        $view['totals'] = $totals;
        $view['weighted'] = $weighted;
        $view['valuesAvailable'] = $hasCatalogue || $hasFuel;
        $view['maxMagnitude'] = max([1.0, ...array_map('abs', array_column($rows, 'material_net'))]);

        return $view;
    }
}
