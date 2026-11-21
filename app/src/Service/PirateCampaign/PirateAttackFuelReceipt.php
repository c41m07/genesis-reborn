<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Preuve de départ privée, distincte de toute future attestation du lieu des survivants. */
#[Exclude]
final readonly class PirateAttackFuelReceipt
{
    private const ORIGINAL_CAPACITY_ONLY = 1;
    private const NATIVE_MOVEMENT = 2;
    private const MOTION_FIELDS = [
        'speed_system' => true, 'speed_galaxy' => true,
        'fuel_travel_system' => false, 'fuel_travel_galaxy' => false, 'fuel_orbit' => false,
    ];

    private function __construct(
        private int $version,
        public int $attempt,
        public int $originId,
        public array $origin,
        public array $site,
        public array $porters,
        public PirateCampaignFuel $budget,
        public float $fromHold,
        public float $fromPlanet,
    ) {
    }

    public static function forDeparture(int $attempt, int $originId, array $origin, array $site, array $profiles, PirateCampaignFuel $budget, float $fromHold, float $fromPlanet): self
    {
        $porters = array_map(static fn (array $ship): array => [
            'slug' => $ship['slug'], 'tier' => $ship['tier'], 'quantity' => $ship['quantity'],
            'cargo_capacity' => $ship['cargo_capacity'], 'fuel_capacity' => $ship['fuel_capacity'] ?? 0,
        ] + array_intersect_key($ship, self::MOTION_FIELDS), $profiles);
        $record = new self(self::NATIVE_MOVEMENT, $attempt, $originId, $origin, $site, $porters, $budget, $fromHold, $fromPlanet);

        return self::read($record->checkpoint());
    }

    public static function read(array $record): self
    {
        $version = $record['version'] ?? null;
        $origin = $record['origin'] ?? null;
        $site = $record['site'] ?? null;
        $total = self::amount($record['total'] ?? null);
        $leg = self::amount($record['leg'] ?? null);
        $hold = self::amount($record['hold'] ?? null);
        $planet = self::amount($record['planet'] ?? null);
        if (!in_array($version, [self::ORIGINAL_CAPACITY_ONLY, self::NATIVE_MOVEMENT], true) || !is_int($record['attempt'] ?? null) || $record['attempt'] < 1
            || !is_int($record['planet_id'] ?? null) || $record['planet_id'] < 1
            || !self::coords($origin, 5) || !self::coords($site, 4) || $origin[1] !== $site[1]
            || $total === null || $leg === null || $hold === null || $planet === null
            || $hold > $total || $planet !== $total - $hold) {
            throw new \DomainException('Le prélèvement de l’expédition pilote n’est pas attesté.');
        }
        $budget = PirateCampaignFuel::expedition([
            'leg_seconds' => $record['leg_seconds'] ?? null,
            'one_way_hydrogen' => $leg,
            'round_trip_hydrogen' => 2.0 * $leg,
            'orbit_hydrogen' => $record['orbit'] ?? null,
            'round_trip_with_orbit_hydrogen' => $total,
            'total_capacity' => $record['capacity'] ?? null,
        ]);

        $porters = $record['porters'] ?? null;
        if (!is_array($porters) || !array_is_list($porters) || $porters === []) {
            throw new \DomainException('Profils de portage de départ absents.');
        }
        $seen = [];
        $capacity = 0.0;
        foreach ($porters as $index => $ship) {
            if (!is_array($ship) || !is_string($ship['slug'] ?? null) || !is_string($ship['tier'] ?? null)
                || !is_int($ship['quantity'] ?? null) || $ship['quantity'] < 1
                || self::amount($ship['cargo_capacity'] ?? null) === null
                || self::amount($ship['fuel_capacity'] ?? null) === null
                || isset($seen[$ship['slug']][$ship['tier']])) {
                throw new \DomainException('Profil de portage de départ invalide.');
            }
            if ($version === self::NATIVE_MOVEMENT) {
                foreach (self::MOTION_FIELDS as $field => $mustBePositive) {
                    $value = self::amount($ship[$field] ?? null);
                    if ($value === null || ($mustBePositive && $value <= 0.0)) {
                        throw new \DomainException('Les paramètres de mouvement natifs du départ ne sont pas attestés.');
                    }
                }
            }
            $allowed = array_fill_keys(['slug', 'tier', 'quantity', 'cargo_capacity', 'fuel_capacity'], true);
            if ($version === self::NATIVE_MOVEMENT) {
                $allowed += self::MOTION_FIELDS;
            }
            // Une preuve historique ne devient pas éligible en relisant des champs ajoutés a posteriori.
            $porters[$index] = array_intersect_key($ship, $allowed);
            $seen[$ship['slug']][$ship['tier']] = true;
            $capacity += ($ship['cargo_capacity'] + $ship['fuel_capacity']) * $ship['quantity'];
        }
        if (!is_finite($capacity) || abs($capacity - $budget->totalCapacity) > max(1e-9, $capacity * PHP_FLOAT_EPSILON * 64)) {
            throw new \DomainException('La capacité de départ ne correspond pas aux porteurs figés.');
        }

        return new self($version, $record['attempt'], $record['planet_id'], $origin, $site, $porters, $budget, $hold, $planet);
    }

    public function checkpoint(): array
    {
        return [
            'version' => $this->version, 'attempt' => $this->attempt, 'planet_id' => $this->originId,
            'origin' => $this->origin, 'site' => $this->site, 'porters' => $this->porters,
            'leg_seconds' => $this->budget->legSeconds, 'leg' => $this->budget->legHydrogen,
            'orbit' => $this->budget->orbitHydrogen, 'total' => $this->budget->initialHydrogen,
            'capacity' => $this->budget->totalCapacity,
            'hold' => $this->fromHold, 'planet' => $this->fromPlanet,
        ];
    }

    /** @return list<array<string,mixed>> Capacités du départ, quantités réellement survivantes. */
    public function survivorPorters(array $survivors): array
    {
        $matched = [];
        $porters = [];
        foreach ($this->porters as $ship) {
            $quantity = $survivors[$ship['slug']][$ship['tier']] ?? null;
            if ($quantity === null) {
                continue;
            }
            if (!is_int($quantity) || $quantity < 1 || $quantity > $ship['quantity']) {
                throw new \LogicException('Le lot survivant ne correspond pas à la réservation de départ.');
            }
            $matched[$ship['slug']][$ship['tier']] = $quantity;
            $porters[] = array_replace($ship, ['quantity' => $quantity]);
        }
        if ($matched != $survivors) {
            throw new \LogicException('Le lot survivant ne correspond pas aux porteurs figés.');
        }

        return $porters;
    }

    /**
     * Données serveur uniquement pour un futur repli. Le reçu historique reste utilisable
     * pour terminer son retour initial, sans reconstruire une vitesse ou un coût de mouvement.
     * @return list<array<string,mixed>>|null
     */
    public function survivorMovement(array $survivors): ?array
    {
        return $this->version === self::NATIVE_MOVEMENT ? $this->survivorPorters($survivors) : null;
    }

    private static function coords(mixed $value, int $size): bool
    {
        return is_array($value) && array_is_list($value) && count($value) === $size
            && count(array_filter($value, is_int(...))) === $size && $value[0] >= 1 && $value[1] >= 1
            && ($size !== 5 || ($value[4] >= 1 && $value[4] <= 9));
    }

    private static function amount(mixed $value): ?float
    {
        return (is_int($value) || is_float($value)) && is_finite($value) && $value >= 0 ? (float) $value : null;
    }
}
