<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Service\Fleet\MovementCalculator;

/** Comparaison privée et sans écriture. Aucun scénario de carburant n'est sélectionné. */
final readonly class PirateFlightQuote
{
    public function __construct(private MovementCalculator $movement)
    {
    }

    /**
     * Compare sonde sans retour et réservation native aller-retour pour les profils fournis.
     * L'appelant prépare côté serveur les profils catalogue avec recherches et multiplicateur réels.
     * @param array{int,int,int,int,int} $origin Planète réelle : système, galaxie, grille x/y, position 1–9.
     * @param array{int,int,int,int} $system Système du poste privé : jamais une position colonisable.
     * @param list<array<string,mixed>> $ships Profils natifs avec quantités.
     * @return array{leg_seconds:int, one_way_hydrogen:float, round_trip_hydrogen:float, orbit_hydrogen:float,
     *   round_trip_with_orbit_hydrogen:float, total_capacity:float}
     */
    public function compare(
        array $origin,
        array $system,
        array $ships,
        float $ratio,
        float $timeMultiplier,
        string $fuelPolicy,
        float $orbitHours = 0.0,
    ): array {
        if (!isset($origin[4]) || count($system) !== 4) {
            throw new \DomainException('Le devis exige une planète d’origine et un poste sans case.');
        }
        // null désigne l'entrée du système : le calcul natif utilise l'ancre géométrique privée 10.
        $target = [$system[0], $system[1], $system[2], $system[3], null];
        $quote = $this->movement->quote($origin, $target, $ships, $ratio, $timeMultiplier, $fuelPolicy, $orbitHours);

        return [
            'leg_seconds' => $quote['seconds'],
            'one_way_hydrogen' => $quote['one_way_fuel'],
            // Sans stationnement, la réservation native couvre deux trajets symétriques.
            'round_trip_hydrogen' => 2.0 * $quote['one_way_fuel'],
            'orbit_hydrogen' => $quote['orbit_fuel'],
            'round_trip_with_orbit_hydrogen' => $quote['total_fuel'],
            'total_capacity' => (float) $quote['capacity'],
        ];
    }
}
