<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Budget figé privé, séparé du fret. Les montants sont les fractions du devis de mouvement natif. */
#[Exclude]
final readonly class PirateCampaignFuel
{
    private function __construct(
        public int $legSeconds,
        public float $legHydrogen,
        public float $orbitHydrogen,
        public float $initialHydrogen,
        private bool $scouting,
    ) {
    }

    /** Même devis que pour le vol courant, sans accepter le choix « aller seul ». */
    public static function scout(array $nativeQuote): self
    {
        return self::fromQuote($nativeQuote, true);
    }

    /** Devis natif calculé avec la fenêtre orbitale explicite de l'univers, pas avec la latence du worker. */
    public static function expedition(array $nativeQuote): self
    {
        return self::fromQuote($nativeQuote, false);
    }

    private static function fromQuote(array $quote, bool $scouting): self
    {
        $seconds = $quote['leg_seconds'] ?? null;
        $leg = self::quantity($quote['one_way_hydrogen'] ?? null);
        $orbit = self::quantity($quote['orbit_hydrogen'] ?? null);
        $roundTrip = self::quantity($quote['round_trip_hydrogen'] ?? null);
        $total = self::quantity($quote['round_trip_with_orbit_hydrogen'] ?? null);
        $capacity = self::quantity($quote['total_capacity'] ?? null);
        if (!is_int($seconds) || $seconds < 30 || $seconds > 2147483647
            || $leg === null || $orbit === null || $roundTrip === null || $total === null || $capacity === null
            || ($scouting && $orbit !== 0.0) || $roundTrip !== 2.0 * $leg
            || $total !== $roundTrip + $orbit || $total > $capacity) {
            throw new \DomainException('Devis de carburant privé incomplet ou non transportable.');
        }

        return new self($seconds, $leg, $orbit, $total, $scouting);
    }

    /**
     * À fournir au futur lancement sous verrou, avec les validations restantes dans FleetHold::departure.
     * Le champ n'accepte ni fret ni stock au sol ; FleetHold calcule le complément sur la colonie d'origine.
     */
    public function amountForDeparture(): float
    {
        return $this->initialHydrogen;
    }

    /** Quand le rapport confirme la perte définitive de la sonde, le budget AR est entièrement perdu. */
    public function scoutsForfeitedHydrogen(): float
    {
        if (!$this->scouting) {
            throw new \LogicException('Cette réserve appartient à une expédition.');
        }

        return $this->initialHydrogen;
    }

    /**
     * Après prélèvement aller et budget orbital natif, seule demeure la réservation du retour.
     * On borne à la capacité des vaisseaux effectivement survivants ; aucune attribution historique par coque.
     * La future campagne n'embarque aucun fret ; le fret d'une autre flotte ne libère pas de capacité ici.
     * @param array<string,array<string,int>> $composition Lot survivant courant.
     * @param list<array<string,mixed>> $survivors Profils de porteurs figés depuis cette composition.
     */
    public function afterCombat(array $composition, array $survivors): float
    {
        if ($this->scouting) {
            throw new \LogicException('La reconnaissance perdue n’a pas de réserve à réutiliser.');
        }

        $cap = 0.0;
        $seen = [];
        foreach ($survivors as $ship) {
            if (!is_array($ship)) {
                throw new \DomainException('La capacité des porteurs survivants n’est pas attestée.');
            }
            $slug = $ship['slug'] ?? null;
            $tier = $ship['tier'] ?? null;
            $quantity = $ship['quantity'] ?? null;
            $bays = self::quantity($ship['cargo_capacity'] ?? null);
            $tanks = self::quantity($ship['fuel_capacity'] ?? 0);
            if (!is_string($slug) || !is_string($tier) || !is_int($quantity) || $quantity < 1
                || isset($seen[$slug][$tier]) || ($composition[$slug][$tier] ?? null) !== $quantity
                || $bays === null || $tanks === null || ($ship['mobile'] ?? true) !== true) {
                throw new \DomainException('La capacité des porteurs survivants n’est pas attestée.');
            }
            $seen[$slug][$tier] = $quantity;
            $cap += ($bays + $tanks) * $quantity;
        }
        if (!is_finite($cap) || $seen != $composition) {
            throw new \DomainException('La capacité des porteurs survivants ne correspond pas au lot courant.');
        }

        // Utiliser la composante figée plutôt que soustraire deux flottants et créer un faux petit reliquat.
        return min($cap, $this->legHydrogen);
    }

    /** Un tronçon retour effectivement parcouru a consommé sa réservation ; aucun reliquat natif à inventer. */
    public function arrivalHydrogen(float $carriedBeforeReturn): ?float
    {
        if ($this->scouting || self::quantity($carriedBeforeReturn) === null
            || $carriedBeforeReturn !== $this->legHydrogen) {
            return null;
        }

        return 0.0;
    }

    private static function quantity(mixed $value): ?float
    {
        return (is_int($value) || is_float($value)) && is_finite($value) && $value >= 0 ? (float) $value : null;
    }
}
