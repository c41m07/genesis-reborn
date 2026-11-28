<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Service\Catalog\GameCatalog;

/** Tarification de la modernisation, indépendante de la réservation des coques. */
final class GuildDockPricing
{
    /**
     * Les deux devis proviennent du constructeur normal, pour le même lot et la même colonie.
     * Ils ne contiennent ni attente dans une file ni trajet.
     *
     * @param array{duration:int,costs:array<string,int>} $source
     * @param array{duration:int,costs:array<string,int>} $target
     * @return array{costs:array<string,int>,duration:int,source_duration:int,target_duration:int}
     */
    public static function fromConstructionQuotes(array $source, array $target): array
    {
        $sourceValue = self::materialValue($source['costs']);
        $targetValue = self::materialValue($target['costs']);
        $budget = self::plusTenPercent($targetValue) - $sourceValue;
        $duration = self::plusTenPercent($target['duration']) - $source['duration'];
        if ($budget <= 0 || $duration <= 0 || $source['duration'] < 1) {
            throw new \DomainException('La modernisation ne possède pas de devis positif pour ces rangs.');
        }

        if ($duration > 2147483647) {
            throw new \DomainException('La durée de modernisation dépasse la capacité d’une commande.');
        }

        $costs = [];
        foreach (GameCatalog::MATERIAL_WEIGHTS as $material => $weight) {
            $amount = $target['costs'][$material] ?? 0;
            // Le budget suit la composition du rang cible. Arrondir chaque matériau
            // au lot entier préserve le plancher de 110 %, même si une matière est absente.
            $costs[$material] = self::ceilDivide(self::multiply($amount, $budget), $targetValue);
        }
        $costs['electricity'] = 0;

        return [
            'costs' => $costs,
            'duration' => $duration,
            'source_duration' => $source['duration'],
            'target_duration' => $target['duration'],
        ];
    }

    /** @param array<string,int> $costs */
    private static function materialValue(array $costs): int
    {
        $total = 0;
        foreach (GameCatalog::MATERIAL_WEIGHTS as $material => $weight) {
            $value = self::multiply($costs[$material] ?? 0, $weight);
            if ($value > PHP_INT_MAX - $total) {
                throw new \DomainException('Le prix du lot dépasse la capacité du devis.');
            }
            $total += $value;
        }

        return $total;
    }

    private static function plusTenPercent(int $value): int
    {
        $premium = self::ceilDivide($value, 10);
        if ($value > PHP_INT_MAX - $premium) {
            throw new \DomainException('Le montant majoré dépasse la capacité du devis.');
        }

        return $value + $premium;
    }

    private static function multiply(int $left, int $right): int
    {
        if ($left < 0 || $right < 0 || ($right > 0 && $left > intdiv(PHP_INT_MAX, $right))) {
            throw new \DomainException('Le prix du lot dépasse la capacité du devis.');
        }

        return $left * $right;
    }

    private static function ceilDivide(int $value, int $divisor): int
    {
        return intdiv($value, $divisor) + ($value % $divisor === 0 ? 0 : 1);
    }
}
