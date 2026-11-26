<?php

declare(strict_types=1);

namespace App\Service\Market;

/** Indice en unités d’hydrogène, sans monnaie intermédiaire ni crédit virtuel. */
final class MarketPricing
{
    public const WEIGHTS = ['metal' => 2, 'nano' => 4, 'hydrogen' => 1, 'antimatter' => 8];
    public const SCALE = 10_000;
    public const MAX_LOT = 1_000_000;
    public const MAX_STOCK = 1_000_000_000;
    public const FEE_PERCENT = 2;

    /**
     * Prix marginal variable de 50 à 150 % de l’étalon. La profondeur de la
     * courbe suit la cible ; les apports marchands n'affectent pas la pression.
     */
    public static function index(string $resource, int $pressure, int $depth): int
    {
        $depth = self::depth($depth);
        $weight = self::WEIGHTS[$resource] * self::SCALE;
        return $weight + intdiv(max(-$depth, min($depth, $pressure)) * $weight, 2 * $depth);
    }

    /**
     * Coûts additifs entre deux positions de la courbe, même si on fractionne un
     * lot. Les entiers évitent une inversion de prix par arrondi flottant.
     *
     * @return array{received: int, before_fee: int}
     */
    public static function exchange(
        string $sold, string $bought, int $quantity,
        array $pressure, array $targets, int $available,
    ): array {
        if (!isset(self::WEIGHTS[$sold], self::WEIGHTS[$bought]) || $sold === $bought || $quantity < 1 || $quantity > self::MAX_LOT) {
            throw new \DomainException('Choisissez deux matériaux distincts et une quantité valide.');
        }
        $paidPosition = $pressure[$sold] ?? 0;
        $boughtPosition = $pressure[$bought] ?? 0;
        $proceeds = self::value($sold, $paidPosition - $quantity, $paidPosition, $targets[$sold] ?? 0);
        $fill = static function (int $budget) use ($bought, $boughtPosition, $targets): int {
            $low = 0;
            $high = self::MAX_STOCK + 1;
            while ($low + 1 < $high) {
                $q = intdiv($low + $high, 2);
                if (self::value($bought, $boughtPosition, $boughtPosition + $q, $targets[$bought] ?? 0) <= $budget) {
                    $low = $q;
                } else {
                    $high = $q;
                }
            }
            return $low;
        };
        $received = $fill(intdiv($proceeds * (100 - self::FEE_PERCENT), 100));
        if ($received < 1) {
            throw new \DomainException('Le lot est trop petit pour obtenir une unité après frais.');
        }
        if ($received > $available) {
            throw new \DomainException('Le stock disponible de la station ne couvre pas ce lot. Réduisez votre échange.');
        }

        return ['received' => $received, 'before_fee' => $fill($proceeds)];
    }

    private static function depth(int $target): int
    {
        return max(100, min(self::MAX_LOT, $target));
    }

    private static function value(string $resource, int $from, int $until, int $target): int
    {
        $weight = self::WEIGHTS[$resource] * self::SCALE;
        $depth = self::depth($target);
        return self::primitive($until, $depth, $weight) - self::primitive($from, $depth, $weight);
    }

    private static function primitive(int $position, int $depth, int $weight): int
    {
        $inside = max(-$depth, min($depth, $position));
        // Factoriser avant multiplication : le plus grand produit intermédiaire
        // reste petit même pour les plus gros entrepôts administrables.
        $square = $inside * $inside;
        $denominator = 4 * $depth;
        $smooth = intdiv($square, $denominator) * $weight
            + intdiv(($square % $denominator) * $weight, $denominator);
        $edgeSlope = $position < 0 ? intdiv($weight, 2) : intdiv($weight * 3, 2);
        return $inside * $weight + $smooth + ($position - $inside) * $edgeSlope;
    }
}
