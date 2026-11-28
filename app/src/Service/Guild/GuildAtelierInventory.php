<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Service\Catalog\GameCatalog;

/** Calcul indépendant de la persistance ; les entrées sont normalisées avant la moindre mutation du stock. */
final class GuildAtelierInventory
{
    private const MAX_PER_RANK = 2147483647;

    /**
     * @param array<string, array<string, int>> $stock
     * @param array<string, array<string, int>> $composition
     * @return array<string, array<string, int>>
     */
    public static function receive(array $stock, array $composition): array
    {
        return self::apply($stock, $composition, true);
    }

    /**
     * @param array<string, array<string, int>> $stock
     * @param array<string, array<string, int>> $composition
     * @return array<string, array<string, int>>
     */
    public static function reserve(array $stock, array $composition): array
    {
        return self::apply($stock, $composition, false);
    }

    private static function apply(array $stock, array $composition, bool $increase): array
    {
        $next = self::clean($stock);
        $composition = self::clean($composition);
        if ($composition === []) {
            throw new \DomainException('Sélectionnez au moins un vaisseau.');
        }
        foreach ($composition as $slug => $ranks) {
            foreach ($ranks as $rank => $quantity) {
                $before = $next[$slug][$rank] ?? 0;
                if (!$increase && $before < $quantity) {
                    throw new \DomainException('L’Atelier ne possède pas assez de vaisseaux de ce rang.');
                }
                if ($increase && $before > self::MAX_PER_RANK - $quantity) {
                    throw new \DomainException('Ce dépôt dépasserait la capacité de stockage de l’Atelier.');
                }
                $next[$slug][$rank] = $increase ? $before + $quantity : $before - $quantity;
            }
        }

        return self::clean($next);
    }

    private static function clean(array $composition): array
    {
        $values = [];
        foreach ($composition as $slug => $ranks) {
            if (!is_string($slug) || !preg_match('/^[a-z][a-z0-9_-]{0,63}$/D', $slug) || !is_array($ranks)) {
                throw new \DomainException('Composition de l’Atelier invalide.');
            }
            foreach ($ranks as $rank => $quantity) {
                if (!in_array($rank, GameCatalog::TIERS, true)
                    || !is_int($quantity) || $quantity < 0 || $quantity > self::MAX_PER_RANK) {
                    throw new \DomainException('Composition de l’Atelier invalide.');
                }
                if ($quantity > 0) {
                    $values[$slug][$rank] = $quantity;
                }
            }
        }

        return $values;
    }
}
