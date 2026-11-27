<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Service\Catalog\GameCatalog;

/** Le quai de construction débloque le premier entrepôt sans créer de ressources. */
final class GuildHallStockPolicy
{
    /**
     * Tant qu'aucun entrepôt n'est construit, seuls les matériaux du premier chantier
     * tiennent au quai. Les montants proviennent du barème du Hall, pas du fret demandé.
     *
     * @param array<string, int> $firstWarehouseCost
     * @param array<string, int> $warehouseCapacity
     * @return array<string, int>
     */
    public static function capacity(int $warehouseLevel, array $firstWarehouseCost, array $warehouseCapacity): array
    {
        if ($warehouseLevel < 0) {
            throw new \DomainException('Niveau d’entrepôt invalide.');
        }
        $first = self::materials($firstWarehouseCost);
        if (array_sum($first) <= 0) {
            throw new \DomainException('Le premier chantier doit avoir un coût en matériaux.');
        }

        return $warehouseLevel === 0 ? $first : self::materials($warehouseCapacity);
    }

    /**
     * Le règlement de la flotte gardera le surplus à bord. Le stock de construction
     * n’existe pas avant l’arrivée et ne doit pas compter dans le stock d’une colonie.
     *
     * @param array<string, int> $stock
     * @param array<string, int> $capacity
     * @param array<string, int> $cargo
     * @return array{stock:array<string,int>,delivered:array<string,int>,overflow:array<string,int>}
     */
    public static function receive(array $stock, array $capacity, array $cargo): array
    {
        $stock = self::materials($stock);
        $capacity = self::materials($capacity);
        $cargo = self::materials($cargo);
        $delivered = $overflow = [];
        foreach ($cargo as $slug => $quantity) {
            $amount = min($quantity, max(0, $capacity[$slug] - $stock[$slug]));
            $delivered[$slug] = $amount;
            $stock[$slug] += $amount;
            $overflow[$slug] = $quantity - $amount;
        }

        return ['stock' => $stock, 'delivered' => $delivered, 'overflow' => $overflow];
    }

    /** @param array<string, int> $stock @param array<string, int> $cost @return array<string, int> */
    public static function consume(array $stock, array $cost): array
    {
        $stock = self::materials($stock);
        foreach (self::materials($cost) as $slug => $quantity) {
            if ($stock[$slug] < $quantity) {
                throw new \DomainException('Le Hall n’a pas reçu assez de matériaux pour cette construction.');
            }
            $stock[$slug] -= $quantity;
        }

        return $stock;
    }

    /** @param array<string, int> $amounts @return array<string, int> */
    private static function materials(array $amounts): array
    {
        $supported = array_fill_keys(array_keys(GameCatalog::MATERIAL_WEIGHTS), 0);
        foreach ($amounts as $slug => $quantity) {
            if (!array_key_exists($slug, $supported) || !is_int($quantity) || $quantity < 0) {
                throw new \DomainException('Seuls les matériaux de guilde sont acceptés.');
            }
            $supported[$slug] = $quantity;
        }

        return $supported;
    }
}
