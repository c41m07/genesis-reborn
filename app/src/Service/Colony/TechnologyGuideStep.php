<?php

namespace App\Service\Colony;

/** Sélectionne un jalon de déblocage ; ce n’est ni un devis ni une autorisation de lancement. */
final class TechnologyGuideStep
{
    /** @return array{state: 'acquired'|'prerequisites_met'|'unavailable'|'step', requirement?: array} */
    public static function resolve(string $key, callable $lookup): array
    {
        $node = $lookup($key);
        if ($node === null) {
            return ['state' => 'unavailable'];
        }
        if ($node['acquired']) {
            return ['state' => 'acquired'];
        }

        $step = self::find($key, $lookup);
        if ($step !== null) {
            return ['state' => 'step', 'requirement' => $step];
        }
        return ['state' => $node['canAdvance'] ? 'prerequisites_met' : 'unavailable'];
    }

    private static function find(string $key, callable $lookup, array $ancestors = []): ?array
    {
        $node = $lookup($key);
        if ($node === null || isset($ancestors[$key])) {
            return null;
        }
        $ancestors[$key] = true;
        // Un déblocage permanent ne réintroduit pas ses conditions historiques, mais le labo local reste nécessaire.
        $requirements = $node['acquired'] ? [] : $node['prerequisites'];
        $localLaboratory = $node['localRequirement'];
        if ($localLaboratory !== null && !in_array(self::key($localLaboratory), array_map(self::key(...), $requirements), true)) {
            // Ne jamais remplacer un seuil de catalogue supérieur par le minimum local du même labo.
            $requirements[] = $localLaboratory;
        }
        $missing = array_filter($requirements, static fn (array $requirement): bool => !$requirement['met']);
        // Le tri stable garde l’ordre du catalogue à écart égal ; une référence à un vaisseau n’a pas de niveau.
        usort($missing, static fn (array $left, array $right): int => self::gap($left) <=> self::gap($right));
        $blocked = [];
        foreach ($missing as $requirement) {
            $candidateKey = self::key($requirement);
            $candidate = $lookup($candidateKey);
            if ($candidate === null || ($requirement['level'] !== null && $requirement['level'] > $candidate['maximum'])) {
                continue;
            }
            if ($requirement['level'] !== null && $candidate['canAdvance']) {
                return $requirement;
            }
            $blocked[] = $candidateKey;
        }

        // N’explorer l’amont qu’après avoir écarté les jalons directs : le plus proche n’est pas toujours l’un des premiers du catalogue.
        foreach ($blocked as $candidateKey) {
            $earlier = self::find($candidateKey, $lookup, $ancestors);
            if ($earlier !== null) {
                return $earlier;
            }
        }

        return null;
    }

    private static function gap(array $requirement): int
    {
        return $requirement['level'] === null ? PHP_INT_MAX : (int) $requirement['level'] - $requirement['current'];
    }

    private static function key(array $requirement): string
    {
        return $requirement['group'] . ':' . $requirement['slug'] . '-' . $requirement['tier'];
    }
}
