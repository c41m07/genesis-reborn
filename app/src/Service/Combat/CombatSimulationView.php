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
                        $definition = $this->catalog->withTier($choices[$category][$slug], $tier);
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

        foreach ($batch['trials'] as &$trial) {
            $survivors = [];
            // Chaque entrée du moteur est un survivant : les points de structure ne sont pas un effectif.
            foreach ($trial['survivors'] as $unit) {
                $key = $unit['camp'].':'.$unit['slug'].':'.$unit['tier'];
                $survivors[$key] = ($survivors[$key] ?? 0) + 1;
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
