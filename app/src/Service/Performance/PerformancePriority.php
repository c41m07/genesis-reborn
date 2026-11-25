<?php

declare(strict_types=1);

namespace App\Service\Performance;

/**
 * Les observations s'additionnent entre heures, pas les percentiles.
 * Cette classe n'infère jamais une cause certaine à partir de deux métriques.
 */
final class PerformancePriority
{
    private const MIN_MEDIAN = 30;
    private const MIN_TAIL = 100;
    private const LCP_TARGET_MS = 2500;

    /** @param array<int,int> $histogram ms de borne supérieure => effectif */
    public static function summarize(array $histogram): array
    {
        $count = array_sum($histogram);
        ksort($histogram, SORT_NUMERIC);

        return [
            'count' => $count,
            'median' => $count >= self::MIN_MEDIAN ? self::quantile($histogram, (int) ceil($count * .5)) : null,
            'p95' => $count >= self::MIN_TAIL ? self::quantile($histogram, (int) ceil($count * .95)) : null,
        ];
    }

    /** @param array<int,int> $histogram */
    private static function quantile(array $histogram, int $rank): ?int
    {
        foreach ($histogram as $limit => $count) {
            $rank -= $count;
            if ($rank <= 0) {
                return $limit;
            }
        }

        return null;
    }

    /**
     * @param array{count:int, median:?int, p95:?int} $lcp
     * @param array{count:int, sql_dominant:int, twig_dominant:int,twig_count?:int} $serverPaired
     */
    public static function page(array $lcp, array $serverPaired): array
    {
        if ($lcp['median'] === null) {
            return ['status' => 'insufficient', 'action' => 'Attendre davantage de chargements mesurés.'];
        }
        if (($lcp['p95'] ?? $lcp['median']) <= self::LCP_TARGET_MS) {
            return ['status' => $lcp['p95'] === null ? 'median_only' : 'within_target', 'action' => 'Pas de priorité détectée dans les mesures disponibles.'];
        }

        // Ces proportions proviennent de couples mesurés dans la même requête serveur ;
        // elles ne comparent pas le percentile SQL au percentile PHP.
        if ($serverPaired['count'] >= self::MIN_MEDIAN) {
            if ($serverPaired['sql_dominant'] >= $serverPaired['count'] * .6) {
                return ['status' => 'review', 'action' => 'Commencer par les requêtes SQL de cette page et vérifier les lectures répétées.'];
            }
            $twigCount = $serverPaired['twig_count'] ?? 0;
            if ($twigCount >= self::MIN_MEDIAN && $serverPaired['twig_dominant'] >= $twigCount * .6) {
                return ['status' => 'review', 'action' => 'Commencer par le rendu Twig de cette page et vérifier les listes et composants répétés.'];
            }
        }

        return ['status' => 'review', 'action' => 'Comparer mobile et ordinateur, puis vérifier le transfert et le rendu : la cause n’est pas isolée.'];
    }

    /**
     * @param array{count:int, median:?int, p95:?int} $publish
     * @param array{count:int, median:?int, p95:?int} $start
     * @param array{count:int, median:?int, p95:?int} $execute
     */
    public static function queue(array $publish, array $start, array $execute): array
    {
        if (($publish['p95'] ?? 0) > 500) {
            return ['status' => 'review', 'action' => 'Vérifier les erreurs et le temps de publication du relais vers RabbitMQ.'];
        }
        if (($start['p95'] ?? 0) > 5000) {
            return ['status' => 'review', 'action' => 'Vérifier les messages en attente dans RabbitMQ et la disponibilité des workers.'];
        }
        if (($execute['p95'] ?? 0) > 2500) {
            return ['status' => 'review', 'action' => 'Mesurer les traitements lents dans le worker avant d’augmenter le nombre de consommateurs.'];
        }

        return ['status' => 'insufficient_or_within_target', 'action' => 'Pas de cause prioritaire isolée par les mesures disponibles.'];
    }
}
