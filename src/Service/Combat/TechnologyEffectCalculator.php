<?php
namespace App\Service\Combat;

use App\Service\Catalog\GameCatalog;

/** Applique aux unités de flotte les bonus technologiques déclarés dans le catalogue. */
final class TechnologyEffectCalculator
{
    public function __construct(private GameCatalog $catalog) {}

    public function applyToShips(array $ships, array $levels): array
    {
        foreach ($levels as $slug => $level) {
            if (!is_string($slug) || !is_int($level) || $level < 1) {
                continue;
            }

            $definition = $this->catalog->get('technologies', $slug);
            $effect = $definition['effect'] ?? null;
            if (!is_array($effect) || ($effect['type'] ?? null) !== 'stat_bonus' || ($effect['targets'] ?? null) !== 'all_ships') {
                continue;
            }
            if (($effect['formula'] ?? null) !== 'flat') {
                throw new \DomainException('Formule de bonus technologique non prise en charge : ' . $slug . '.');
            }

            $stat = $effect['stat'] ?? null;
            $valuePerLevel = $effect['value_per_level'] ?? null;
            if (!is_string($stat) || !is_numeric($valuePerLevel) || !is_finite((float) $valuePerLevel)) {
                throw new \DomainException('Effet technologique invalide : ' . $slug . '.');
            }

            $bonus = (float) $valuePerLevel * min($level, (int) ($definition['max_level'] ?? $level));
            foreach ($ships as &$ship) {
                if (!array_key_exists($stat, $ship) || !is_numeric($ship[$stat])) {
                    throw new \DomainException('Statistique de vaisseau absente pour l\'effet : ' . $slug . '.');
                }
                $ship[$stat] = (float) $ship[$stat] + $bonus;
            }
            unset($ship);
        }

        return $ships;
    }
}
