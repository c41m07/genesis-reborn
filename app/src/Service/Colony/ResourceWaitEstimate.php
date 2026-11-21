<?php

declare(strict_types=1);

namespace App\Service\Colony;

/** Estimation d'affichage à stocks, capacités et débits nets courants. */
final class ResourceWaitEstimate
{
    /**
     * @param array<string, int|float> $costs
     * @param array<string, array{label: string, stock: float, capacity: float, rate: float}> $resources
     * @return array{missing: array<string, array{label: string, amount: int}>, paymentWait: ?string, paymentEstimate: ?array{observedAt: int, deadline: int, resource: string}}
     */
    public static function forCosts(array $costs, array $resources, \DateTimeImmutable $observedAt): array
    {
        $missing = [];
        $blockers = [];
        $slowest = 0;
        $limitingResource = '';
        foreach ($costs as $slug => $cost) {
            $resource = $resources[$slug] ?? ['label' => $slug, 'stock' => 0.0, 'capacity' => 0.0, 'rate' => 0.0];
            $deficit = $cost - $resource['stock'];
            if ($deficit <= 0) {
                continue;
            }
            $label = $resource['label'];
            $missing[$slug] = ['label' => $label, 'amount' => (int) ceil($deficit)];

            if ($cost > $resource['capacity']) {
                $blockers[] = $label.' : stockage insuffisant';
                continue;
            }
            if ($resource['rate'] <= 0) {
                $blockers[] = $label.' : aucun gain net';
                continue;
            }

            // Le snapshot expose un débit par seconde. Le stock non arrondi
            // sert au délai ; l'arrondi des quantités affichées ne le modifie pas.
            $seconds = ceil($deficit / $resource['rate']);
            // Garder l'échéance représentable en secondes Unix, même dans le navigateur.
            if (!is_finite($seconds) || $seconds > 253402300799 - $observedAt->getTimestamp()) {
                $blockers[] = $label.' : délai non calculable';
                continue;
            }
            if ($seconds > $slowest) {
                $slowest = (int) $seconds;
                $limitingResource = $label;
            }
        }

        return [
            'missing' => $missing,
            'paymentWait' => $blockers !== []
                ? 'Délai indisponible : '.implode(' ; ', $blockers).'.'
                : ($slowest > 0 ? 'Délai estimé : '.self::duration($slowest).' ('.$limitingResource.').' : null),
            'paymentEstimate' => $blockers === [] && $slowest > 0 ? [
                'observedAt' => $observedAt->getTimestamp(),
                'deadline' => $observedAt->getTimestamp() + $slowest,
                'resource' => $limitingResource,
            ] : null,
        ];
    }

    private static function duration(int $seconds): string
    {
        $parts = [];
        foreach ([86400 => 'j', 3600 => 'h', 60 => 'min', 1 => 's'] as $unit => $label) {
            if ($seconds >= $unit) {
                $parts[] = intdiv($seconds, $unit).' '.$label;
                $seconds %= $unit;
            }
        }

        return implode(' ', $parts);
    }
}
