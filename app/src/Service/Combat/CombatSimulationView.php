<?php

namespace App\Service\Combat;

use App\Service\Catalog\GameCatalog;

/** Prépare le bilan d’affichage sans modifier les résultats du moteur de combat. */
final class CombatSimulationView
{
    public function __construct(private readonly GameCatalog $catalog)
    {
    }

    /**
     * @param array{trials: list<array<string, mixed>>} $batch Résultats bruts du moteur, conservés dans le retour.
     * @param array<string, array<string, mixed>> $forces Effectifs par camp, catégorie, unité et rang.
     * @param array<string, array<string, array<string, mixed>>> $choices Définitions autorisées dans l’univers.
     * @return array<string, mixed> Lot enrichi de bilans par camp avec effectifs engagés, perdus et survivants.
     */
    public function prepare(array $batch, array $forces, array $choices): array
    {
        return $this->prepareRows($batch, $this->initialRows($forces, $choices));
    }

    /** Fige les libellés de ce résultat si le catalogue change pendant un calcul en file. */
    public function initialRows(array $forces, array $choices): array
    {
        $initialRows = [];
        foreach (['attacker', 'defender'] as $camp) {
            foreach (['ships', 'defenses'] as $category) {
                if ($camp === 'attacker' && $category === 'defenses') {
                    continue;
                }
                foreach ($forces[$camp][$category] ?? [] as $slug => $tiers) {
                    foreach ($tiers as $tier => $amount) {
                        $quantity = (int) $amount;
                        if ($quantity < 1) {
                            continue;
                        }
                        // Une famille peut avoir quitté le catalogue depuis le relevé pirate.
                        $definition = isset($choices[$category][$slug])
                            ? $this->catalog->withTier($choices[$category][$slug], $tier)
                            : ['label' => $slug];
                        $initialRows[] = [
                            'camp' => $camp,
                            'slug' => $slug,
                            'tier' => $tier,
                            'label' => $definition['label'],
                            'initial' => $quantity,
                            'arrival' => $category === 'defenses' ? 1 : (int) ($forces[$camp]['arrival'] ?? 1),
                        ];
                    }
                }
            }
        }

        return $initialRows;
    }

    public function prepareRows(array $batch, array $initialRows): array
    {
        foreach ($batch['trials'] as &$trial) {
            $survivors = [];
            // Le moteur compact regroupe les survivants par type ; le format historique en compte un par entrée.
            foreach ($trial['survivors'] as $unit) {
                $key = $unit['camp'].':'.$unit['slug'].':'.$unit['tier'];
                $survivors[$key] = ($survivors[$key] ?? 0) + ($unit['quantity'] ?? 1);
            }
            $report = [];
            foreach (['attacker', 'defender'] as $camp) {
                $report[$camp] = ['rows' => [], 'initial' => 0, 'lost' => 0, 'remaining' => 0];
            }
            foreach ($initialRows as $row) {
                $remaining = $survivors[$row['camp'].':'.$row['slug'].':'.$row['tier']] ?? 0;
                if ($remaining > $row['initial']) {
                    throw new \LogicException('Le bilan des survivants dépasse les forces simulées.');
                }
                $row['remaining'] = $remaining;
                $row['lost'] = $row['initial'] - $remaining;
                $report[$row['camp']]['rows'][] = $row;
                foreach (['initial', 'lost', 'remaining'] as $field) {
                    $report[$row['camp']][$field] += $row[$field];
                }
            }
            $trial['report'] = $report;
        }
        unset($trial);

        return $batch;
    }
}
