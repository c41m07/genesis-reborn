<?php

namespace App\Service\Balance;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
/** Prépare une variante d'étude en mémoire ; ne modifie ni catalogue ni univers persisté. */
final class CostDistributionCandidate
{
    public const WEIGHTS = ['metal' => 2, 'nano' => 4, 'antimatter' => 8, 'hydrogen' => 1];
    public function __construct(private GameCatalog $catalog)
    {
    }

    public static function value(array $costs): int
    {
        $value = 0;
        foreach (self::WEIGHTS as $resource => $weight) {
            $value += (int) ($costs[$resource] ?? 0) * $weight;
        }
        return $value;
    }
    /** @return array{overrides:array,profiles:list<array>,maximum_share_error_points:float} */
    public function compare(Universe $universe): array
    {
        $overrides = [];
        $rows = [];
        $maxError = 0.0;
        foreach ($this->catalog->forUniverse($universe)['catalog'] as $group => $definitions) {
            foreach ($definitions as $slug => $definition) {
                foreach (GameCatalog::TIERS as $tier) {
                    $current = $this->catalog->costs($this->catalog->withTier($definition, $tier), 1, 1, $group);
                    $budget = self::value($current);
                    $candidate = $tier === 'superior' ? $current : $this->distribute($budget, $tier);
                    if (self::value($candidate) !== $budget) {
                        throw new \LogicException('Le budget du profil a changé.');
                    }
                    $overrides[$group][$slug]['cost_profiles'][$tier] = $candidate;
                    $target = $tier === 'basic' ? ['metal' => 0.5, 'nano' => 0.5] : ['metal' => 0.4, 'nano' => 0.4, 'antimatter' => 0.2];
                    $error = 0.0;
                    if ($tier !== 'superior' && $budget > 0) {
                        foreach ($target as $resource => $share) {
                            $error = max($error, 100 * abs($candidate[$resource] * self::WEIGHTS[$resource] / $budget - $share));
                        }
                    }
                    $maxError = max($maxError, $error);
                    $rows[] = [
                        'group' => $group,
                        'slug' => $slug,
                        'tier' => $tier,
                        'value' => $budget,
                        'current' => $current,
                        'candidate' => $candidate,
                        'maximum_share_error_points' => $error,
                    ];
                }
            }
        }
        return ['overrides' => $overrides, 'profiles' => $rows, 'maximum_share_error_points' => $maxError];
    }

    private function distribute(int $budget, string $tier): array
    {
        if ($budget < 0 || $budget % 2 !== 0) {
            throw new \DomainException('Budget incompatible avec les ressources du candidat.');
        }
        $shares = $tier === 'basic' ? ['metal' => 0.5, 'nano' => 0.5, 'antimatter' => 0] : ['metal' => 0.4, 'nano' => 0.4, 'antimatter' => 0.2];
        $nanoTarget = $budget * $shares['nano'] / 4;
        $antiTarget = $budget * $shares['antimatter'] / 8;
        $best = null;
        $bestError = INF;
        // Arrondis conjoints au voisinage des parts : le métal absorbe le reliquat pair.
        // En cas d'égalité, l'ordre fixe des candidats rend la mesure reproductible.
        for ($antimatter = max(0, (int) floor($antiTarget) - 1); $antimatter <= (int) ceil($antiTarget) + 1; $antimatter++) {
            if ($tier === 'basic' && $antimatter !== 0) {
                continue;
            }
            for ($nano = max(0, (int) floor($nanoTarget) - 2); $nano <= (int) ceil($nanoTarget) + 2; $nano++) {
                $remaining = $budget - 8 * $antimatter - 4 * $nano;
                if ($remaining < 0) {
                    continue;
                }
                $profile = ['metal' => intdiv($remaining, 2), 'nano' => $nano, 'antimatter' => $antimatter, 'hydrogen' => 0];
                $error = 0.0;
                foreach ($shares as $resource => $share) {
                    $error += ($profile[$resource] * self::WEIGHTS[$resource] - $budget * $share) ** 2;
                }
                if ($error < $bestError) {
                    $best = $profile;
                    $bestError = $error;
                }
            }
        }
        return $best ?? throw new \LogicException('Répartition du budget impossible.');
    }
}
