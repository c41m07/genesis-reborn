<?php

namespace App\Service\Colony;

use App\Entity\{Planet, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\VacationCalendar;
use Psr\Clock\ClockInterface;

final class ColonyComparisonActivities
{
    public function __construct(
        private GameRepository $repository,
        private GameCatalog $catalogue,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @param list<Planet> $planets
     * @return array{local: array<int, array<string, array>>, research: ?array, researchOrigin: ?int}
     */
    public function forPlanets(PlayerUniverse $membership, array $planets): array
    {
        $rows = $this->repository->comparisonQueueRows($membership, $planets);
        if ($rows === []) {
            return ['local' => [], 'research' => null, 'researchOrigin' => null];
        }

        $now = $this->clock->now();
        $paused = $this->vacations->isActive($membership, $now);
        $catalogue = $this->catalogue->forUniverse($membership->universe)['catalog'];
        $perPlanet = [];
        $research = [];
        $visible = array_fill_keys(array_map(static fn (Planet $planet): int => $planet->id, $planets), true);
        foreach ($rows as $row) {
            if ($row['kind'] === 'research') {
                $research[] = $row;
            } else {
                $perPlanet[(int) $row['originId']][$row['kind']][] = $row;
            }
        }

        $local = [];
        foreach ($perPlanet as $id => $kinds) {
            foreach ($kinds as $kind => $items) {
                $local[$id][$kind] = $this->summarize($items, $catalogue, $now, $paused);
            }
        }

        // La file commune garde son ordre natif : recherches actives par fin, sinon attente par ID.
        // L’ordre local reste celui de la file avec une seule tête.
        $active = array_values(array_filter($research, static fn (array $item): bool => $item['started'] !== null));
        usort($active, fn (array $a, array $b): int => $this->finish($a) <=> $this->finish($b) ?: $a['id'] <=> $b['id']);
        $head = $active[0] ?? $research[0] ?? null;
        $originId = (int) ($head['originId'] ?? 0);
        $researchOrigin = (int) ($head['ownerId'] ?? 0) === $membership->id && isset($visible[$originId])
            ? $originId
            : null;
        $researchSummary = $head === null ? null : $this->summarize($research, $catalogue, $now, $paused, $head);
        if ($researchSummary !== null && $researchOrigin === null) {
            // Le traitement ne termine pas une recherche dont l’origine n’appartient plus à cet empire.
            $researchSummary['status'] = 'waiting';
            $researchSummary['finish'] = null;
        }
        return [
            'local' => $local,
            'research' => $researchSummary,
            // Une ancienne origine n’est plus une colonie affichable et son identité ne sort pas du service.
            'researchOrigin' => $researchOrigin,
        ];
    }

    /**
     * Une échéance est l’instant enregistré de départ plus la durée de la commande, jamais une estimation
     * des commandes en attente. Si l’instant est passé, le traitement différé n’est pas encore confirmé.
     * @return array{label: string, level: ?int, quantity: ?int, status: 'waiting'|'paused'|'due'|'running', finish: ?\DateTimeImmutable, waiting: int, additionalActive: int}
     */
    private function summarize(array $items, array $catalogue, \DateTimeImmutable $now, bool $paused, ?array $head = null): array
    {
        $head ??= $items[0];
        $finish = $this->finish($head);
        $status = match (true) {
            $head['started'] === null, $head['started'] > $now, $finish === null => 'waiting',
            $paused => 'paused',
            $finish <= $now => 'due',
            default => 'running',
        };
        $label = 'Commande de catalogue indisponible';
        $slug = $head['slug'];
        $group = match ($head['kind']) {
            'building' => 'buildings',
            'research' => 'technologies',
            default => isset($catalogue['ships'][$slug]) ? 'ships' : 'defenses',
        };
        $definition = $catalogue[$group][$slug] ?? null;
        if ($definition !== null && in_array($head['tier'], GameCatalog::TIERS, true)) {
            $label = $this->catalogue->withTier($definition, $head['tier'])['label'];
        }
        $waiting = count(array_filter($items, static fn (array $item): bool => $item['started'] === null));
        return [
            'label' => $label,
            'level' => $head['level'] === null ? null : (int) $head['level'],
            'quantity' => $head['kind'] === 'shipyard' ? (int) $head['quantity'] : null,
            'status' => $status,
            'finish' => $status === 'running' ? $finish : null,
            'waiting' => $waiting - ($head['started'] === null ? 1 : 0),
            'additionalActive' => count($items) - $waiting - ($head['started'] === null ? 0 : 1),
        ];
    }

    /**
     * Deux véritables fins enregistrées, prises parmi les têtes locales et les recherches démarrées
     * en parallèle. Un délai déjà écoulé, suspendu ou sans colonie possédée n'est pas une échéance.
     *
     * @param list<Planet> $planets
     * @return list<array{kind: string, label: string, level: ?int, quantity: ?int, deadline: \DateTimeImmutable, planet: Planet}>
     */
    public function upcomingForEmpire(PlayerUniverse $membership, array $planets): array
    {
        $now = $this->clock->now();
        if ($planets === [] || $this->vacations->isActive($membership, $now)) {
            return [];
        }

        $owned = array_column($planets, null, 'id');
        $headsSeen = [];
        $upcoming = [];
        // La requête existante est groupée, privée à l’empire, et retourne les files locales par ordre d’ID.
        foreach ($this->repository->comparisonQueueRows($membership, $planets) as $row) {
            $planetId = (int) $row['originId'];
            if ((int) $row['ownerId'] !== $membership->id || !isset($owned[$planetId])) {
                continue;
            }
            if ($row['kind'] !== 'research') {
                $headKey = $planetId . ':' . $row['kind'];
                if (isset($headsSeen[$headKey])) {
                    continue;
                }
                $headsSeen[$headKey] = true;
            }
            $finish = $this->finish($row);
            if ($row['started'] !== null && $row['started'] <= $now && $finish !== null && $finish > $now) {
                $upcoming[] = $row + ['finish' => $finish];
            }
        }

        usort($upcoming, static fn (array $left, array $right): int =>
            $left['finish'] <=> $right['finish'] ?: $left['id'] <=> $right['id']
        );
        $catalogue = $this->catalogue->forUniverse($membership->universe)['catalog'];
        $result = [];
        foreach (array_slice($upcoming, 0, 2) as $row) {
            $display = $this->summarize([$row], $catalogue, $now, false);
            $result[] = [
                'kind' => $row['kind'],
                'label' => $display['label'],
                'level' => $display['level'],
                'quantity' => $display['quantity'],
                'deadline' => $row['finish'],
                'planet' => $owned[(int) $row['originId']],
            ];
        }

        return $result;
    }

    private function finish(array $row): ?\DateTimeImmutable
    {
        return $row['started']?->modify('+'.(int) $row['duration'].' seconds');
    }
}
