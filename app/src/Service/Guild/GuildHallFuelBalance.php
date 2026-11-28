<?php

declare(strict_types=1);

namespace App\Service\Guild;

/** Le stock de construction reste entier, sans arrondir le carburant à chaque trajet. */
final class GuildHallFuelBalance
{
    /** @return array{whole: int, fraction: float} */
    public static function debit(int $whole, float $fraction, float $fuel): array
    {
        if ($whole < 0 || !is_finite($fraction) || $fraction < 0 || $fraction >= 1
            || !is_finite($fuel) || $fuel < 0 || $fuel >= $whole + 1.0) {
            throw new \DomainException('Réserve d’hydrogène du Hall insuffisante ou invalide.');
        }

        // Soustraire les fractions séparément pour ne pas perdre leur précision
        // lorsque le Hall détient une quantité entière importante.
        $fuelWhole = (int) floor($fuel);
        $remainingWhole = $whole - $fuelWhole;
        $remainingFraction = $fraction - ($fuel - $fuelWhole);
        if ($remainingFraction < 0.0) {
            if ($remainingWhole === 0) {
                throw new \DomainException('Réserve d’hydrogène du Hall insuffisante.');
            }
            --$remainingWhole;
            $remainingFraction += 1.0;
            // Une fraction négative infinitésimale peut s’arrondir à 1 en double.
            if ($remainingFraction >= 1.0) {
                $remainingFraction = 1.0 - PHP_FLOAT_EPSILON;
            }
        }

        return ['whole' => $remainingWhole, 'fraction' => $remainingFraction];
    }

    /**
     * Réintègre du carburant non consommé sans convertir le stock entier en
     * flottant. L’appelant doit garantir que la restitution ne se fait qu’une fois.
     *
     * @return array{whole: int, fraction: float}
     */
    public static function credit(int $whole, float $fraction, float $fuel): array
    {
        if ($whole < 0 || !is_finite($fraction) || $fraction < 0 || $fraction >= 1
            || !is_finite($fuel) || $fuel < 0 || $fuel >= (float) PHP_INT_MAX) {
            throw new \DomainException('Restitution d’hydrogène du Hall invalide.');
        }

        $fuelWhole = (int) floor($fuel);
        $creditedFraction = $fraction + ($fuel - $fuelWhole);
        $carry = $creditedFraction >= 1.0 ? 1 : 0;
        if ($fuelWhole > PHP_INT_MAX - $whole - $carry) {
            throw new \DomainException('Stock d’hydrogène du Hall trop élevé.');
        }

        return ['whole' => $whole + $fuelWhole + $carry, 'fraction' => $creditedFraction - $carry];
    }
}
