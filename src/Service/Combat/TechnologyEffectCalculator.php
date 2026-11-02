<?php
namespace App\Service\Combat;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;

/** Applique les effets technologiques configurés aux statistiques des unités. */
final class TechnologyEffectCalculator
{
    public function __construct(private GameCatalog $catalog) {}

    public function applyToShips(array $ships, array $levels, Universe $universe): array
    {
        return $this->apply($ships, $levels, $universe, ['all_ships', 'all_combat_units']);
    }

    public function applyToDefenses(array $defenses, array $levels, Universe $universe): array
    {
        return $this->apply($defenses, $levels, $universe, ['all_combat_units']);
    }

    private function apply(array $units, array $levels, Universe $universe, array $targets): array
    {
        foreach ($levels as $slug => $level) {
            if (!is_string($slug) || !is_int($level) || $level < 1) {
                continue;
            }
            $definition = $this->catalog->getForUniverse($universe, 'technologies', $slug);
            $effect = $definition['effect'] ?? null;
            if (!is_array($effect) || ($effect['type'] ?? null) !== 'stat_bonus' || !in_array($effect['targets'] ?? null, $targets, true)) {
                continue;
            }
            $stats = $effect['stats'] ?? [$effect['stat'] ?? null];
            $stats = array_values(array_filter($stats, 'is_string'));
            $maxLevel = max(1, (int) ($definition['max_level'] ?? $level));
            $effectiveLevel = min($level, $maxLevel);
            $valuePerLevel = $effect['value_per_level'] ?? null;
            $formula = $effect['formula'] ?? 'flat';
            if (!is_numeric($valuePerLevel) || !is_finite((float) $valuePerLevel) || !in_array($formula, ['flat', 'percent'], true)) {
                throw new \DomainException('Effet technologique invalide : ' . $slug . '.');
            }
            $bonus = (float) $valuePerLevel * $effectiveLevel;
            if (isset($effect['max_bonus'])) {
                $bonus = min($bonus, (float) $effect['max_bonus']);
            }
            foreach ($units as &$unit) {
                foreach ($stats as $stat) {
                    if (!array_key_exists($stat, $unit) || !is_numeric($unit[$stat])) {
                        throw new \DomainException('Statistique absente pour l’effet technologique : ' . $slug . '.');
                    }
                    $unit[$stat] = $formula === 'percent'
                        ? (float) $unit[$stat] * (1 + $bonus)
                        : (float) $unit[$stat] + $bonus;
                }
            }
            unset($unit);
        }

        return $units;
    }
}
