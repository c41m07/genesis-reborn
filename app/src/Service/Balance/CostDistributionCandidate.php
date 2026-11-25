<?php

namespace App\Service\Balance;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
/** Prépare une variante d'étude en mémoire ; ne modifie ni catalogue ni univers persisté. */
final class CostDistributionCandidate
{
    public const DEFAULT_TIERS = ['basic', 'intermediate'];
    public const DEFAULT_PROFILES = [
        'basic' => ['metal' => 50, 'nano' => 50, 'antimatter' => 0, 'hydrogen' => 0],
        'intermediate' => ['metal' => 40, 'nano' => 40, 'antimatter' => 20, 'hydrogen' => 0],
    ];
    public const WEIGHTS = GameCatalog::MATERIAL_WEIGHTS;
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
    /**
     * @param list<'basic'|'intermediate'|'superior'> $tiers Rangs modifiés uniquement dans l'univers temporaire.
     * @param array<string,array<string,int|float>> $profiles Parts de valeur en pourcentages, total 100 par rang.
     * @return array{overrides:array,profiles:list<array>,maximum_share_error_points:float}
     */
    public function compare(Universe $universe, array $tiers = self::DEFAULT_TIERS, array $profiles = []): array
    {
        if ($tiers === [] || array_diff($tiers, GameCatalog::TIERS) !== []) {
            throw new \InvalidArgumentException('Rang candidat inconnu.');
        }
        $shares = $this->shares($profiles);
        foreach ($tiers as $tier) {
            if (!isset($shares[$tier])) {
                throw new \InvalidArgumentException('Le rang ' . $tier . ' nécessite un profil explicite.');
            }
        }
        $overrides = [];
        $rows = [];
        $maxError = 0.0;
        foreach ($this->catalog->forUniverse($universe)['catalog'] as $group => $definitions) {
            foreach ($definitions as $slug => $definition) {
                foreach (GameCatalog::TIERS as $tier) {
                    // La variante modifie la composition du profil, avant le calcul du budget d’entrée.
                    $current = $this->catalog->costs($this->catalog->withTier($definition, $tier), 1, 1);
                    $budget = self::value($current);
                    // L'autre rang conserve son profil effectif, sans nouvelle répartition ni arrondi.
                    $selected = in_array($tier, $tiers, true);
                    $candidate = $selected ? $this->distribute($budget, $shares[$tier]) : $current;
                    if (self::value($candidate) !== $budget) {
                        throw new \LogicException('Le budget du profil a changé.');
                    }
                    $overrides[$group][$slug]['cost_profiles'][$tier] = $candidate;
                    $target = $shares[$tier] ?? [];
                    $error = 0.0;
                    if ($selected && $budget > 0) {
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

    /** @return array<string,array<string,float>> */
    private function shares(array $profiles): array
    {
        $result = [];
        foreach (array_replace(self::DEFAULT_PROFILES, $profiles) as $tier => $percentages) {
            if (!in_array($tier, GameCatalog::TIERS, true) || !is_array($percentages)
                || array_diff(array_keys($percentages), array_keys(self::WEIGHTS)) !== []) {
                throw new \InvalidArgumentException('Profil de ressources candidat invalide.');
            }
            foreach ($percentages as $percent) {
                if ((!is_int($percent) && !is_float($percent)) || !is_finite((float) $percent) || $percent < 0 || $percent > 100) {
                    throw new \InvalidArgumentException('Pourcentage candidat invalide.');
                }
            }
            // Ne pas normaliser silencieusement une grille incomplète ou dont la somme dépasse 100.
            if (abs(array_sum($percentages) - 100) > 1e-7) {
                throw new \InvalidArgumentException('Chaque profil candidat doit totaliser 100 %.');
            }
            foreach (self::WEIGHTS as $resource => $weight) {
                $result[$tier][$resource] = (float) ($percentages[$resource] ?? 0) / 100;
            }
        }
        return $result;
    }

    /** @param array<string,float> $shares */
    private function distribute(int $budget, array $shares): array
    {
        if ($budget < 0 || ($shares['hydrogen'] === 0.0 && $budget % 2 !== 0)) {
            throw new \DomainException('Budget incompatible sans hydrogène : une valeur impaire exige une ressource de poids 1.');
        }
        $targets = [];
        foreach (self::WEIGHTS as $resource => $weight) {
            $targets[$resource] = $budget * $shares[$resource] / $weight;
        }
        $antiValues = $shares['antimatter'] > 0
            ? range(max(0, (int) floor($targets['antimatter']) - 1), (int) ceil($targets['antimatter']) + 1) : [0];
        $nanoValues = $shares['nano'] > 0
            ? range(max(0, (int) floor($targets['nano']) - 2), (int) ceil($targets['nano']) + 2) : [0];
        $best = null;
        $bestError = INF;
        // Sans hydrogène, conserver l'arrondi conjoint historique : le métal absorbe le reliquat pair.
        // Avec hydrogène, explorer aussi les arrondis du métal et garder le reliquat entier en H.
        // Cette ressource de poids 1 permet de conserver les budgets impairs du Supérieur.
        foreach ($antiValues as $antimatter) {
            foreach ($nanoValues as $nano) {
                $remaining = $budget - 8 * $antimatter - 4 * $nano;
                if ($remaining < 0) {
                    continue;
                }
                if ($shares['hydrogen'] > 0) {
                    $metalValues = $shares['metal'] > 0
                        ? range(max(0, (int) floor($targets['metal']) - 2), (int) ceil($targets['metal']) + 2) : [0];
                } else {
                    $metalValues = [intdiv($remaining, 2)];
                }
                foreach ($metalValues as $metal) {
                    $hydrogen = $remaining - 2 * $metal;
                    if ($hydrogen < 0) {
                        continue;
                    }
                    $profile = ['metal' => $metal, 'nano' => $nano, 'antimatter' => $antimatter, 'hydrogen' => $hydrogen];
                    $error = 0.0;
                    foreach ($shares as $resource => $share) {
                        $error += ($profile[$resource] * self::WEIGHTS[$resource] - $budget * $share) ** 2;
                    }
                    // L'ordre des valeurs départage les égalités de façon reproductible.
                    if ($error < $bestError) {
                        $best = $profile;
                        $bestError = $error;
                    }
                }
            }
        }
        return $best ?? throw new \LogicException('Répartition du budget impossible.');
    }
}
