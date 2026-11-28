<?php

declare(strict_types=1);

namespace App\Service\Market;

use DateTimeImmutable;
use DateTimeZone;

/** Calcule une livraison PNJ. Ne touche ni aux cours, ni aux volumes joueurs. */
final class WeeklyMerchantRestock
{
    /**
     * Vendredi 09:00 à Paris. Les bornes du journal sont UTC, début inclus, fin exclue.
     *
     * @return array{occurrence: string, from: DateTimeImmutable, until: DateTimeImmutable}
     */
    public function window(DateTimeImmutable $now): array
    {
        $paris = new DateTimeZone('Europe/Paris');
        $utc = new DateTimeZone('UTC');
        $local = $now->setTimezone($paris);
        $daysSinceFriday = ((int) $local->format('N') + 2) % 7;
        $end = $local->modify(sprintf('-%d days', $daysSinceFriday))->setTime(9, 0);
        if ($end > $now) {
            $end = $end->modify('-7 days');
        }

        return [
            'occurrence' => $end->format('Y-m-d'),
            'from' => $end->modify('-7 days')->setTimezone($utc),
            'until' => $end->setTimezone($utc),
        ];
    }

    /**
     * Le stock physique inclut le fret réservé mais pas encore parti de la station.
     * Les ventes sont uniquement les unités effectivement remises par la station.
     *
     * @param array<string, int> $physical
     * @param array<string, int> $targets
     * @param array<string, int> $executedStationSales
     * @return array<string, int> Quantités positives à inscrire au journal PNJ.
     */
    public function quantities(array $physical, array $targets, array $executedStationSales, int $percent = 50): array
    {
        $result = [];
        foreach (['metal', 'nano', 'hydrogen', 'antimatter'] as $resource) {
            $stock = $physical[$resource] ?? 0;
            $target = $targets[$resource] ?? 0;
            $sold = $executedStationSales[$resource] ?? 0;
            if ($stock < 0 || $target < 0 || $sold < 0) {
                throw new \DomainException('Une quantité de marché ne peut pas être négative.');
            }
            $share = intdiv($sold,100) * $percent + intdiv(($sold % 100) * $percent,100);
            $amount = min($share, max(0, $target - $stock));
            if ($amount > 0) {
                $result[$resource] = $amount;
            }
        }

        return $result;
    }
}
