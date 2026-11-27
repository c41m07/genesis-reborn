<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Service\Catalog\GameCatalog;

/** Adapte le fret de flotte au stock entier du Hall, sans toucher au carburant. */
final class GuildHallCargo
{
    /**
     * Le modèle Fleet place la réserve de retour dans la même soute que l’hydrogène
     * commercial (FleetHold::install). Le Hall ne peut recevoir que des unités entières.
     *
     * @param array<string,int|float> $cargo
     * @return array<string,int>
     */
    public static function deliverable(array $cargo, float $reservedFuel): array
    {
        if (!is_finite($reservedFuel) || $reservedFuel < 0) {
            throw new \DomainException('Réserve de carburant invalide.');
        }

        $amounts = [];
        foreach (GameCatalog::MATERIAL_WEIGHTS as $slug => $_) {
            $amount = $cargo[$slug] ?? 0;
            if ((!is_int($amount) && !is_float($amount)) || !is_finite($amount) || $amount < 0) {
                throw new \DomainException('Fret du convoi invalide.');
            }
            if ($slug === 'hydrogen') {
                $amount = max(0.0, $amount - $reservedFuel);
            }
            // Ne jamais arrondir vers le haut : la fraction reste dans le convoi.
            // Les colonnes du stock sont bornées, même si la soute peut transporter plus.
            $amounts[$slug] = (int) min(2147483647, floor($amount));
        }

        return $amounts;
    }

    /**
     * Appelé avec le crédit exact renvoyé par le stock dans la même transaction que
     * l’acquittement de la flotte ; ne reconstruit jamais sa soute à partir du seul surplus entier.
     *
     * @param array<string,int|float> $cargo
     * @param array<string,int> $credited
     * @return array<string,int|float>
     */
    public static function retain(array $cargo, float $reservedFuel, array $credited): array
    {
        $deliverable = self::deliverable($cargo, $reservedFuel);
        foreach ($credited as $slug => $amount) {
            if (!isset($deliverable[$slug]) || !is_int($amount) || $amount < 0 || $amount > $deliverable[$slug]) {
                throw new \DomainException('Le crédit dépasse le fret livrable au Hall.');
            }
            if ($amount > 0) {
                $cargo[$slug] -= $amount;
                if ($cargo[$slug] === 0 || $cargo[$slug] === 0.0) {
                    unset($cargo[$slug]);
                }
            }
        }

        return $cargo;
    }
}
