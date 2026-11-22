<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{Fleet, FleetShip, Planet, PlayerTechnology, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Combat\CombatUnitFactory;

/** Remise indivisible d'un lot réservé, sans Fleet active, stock planétaire ni contrat de transport. */
final readonly class PirateRecoveryHangar
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private CombatUnitFactory $factory,
    ) {
    }

    /**
     * Le worker verrouille déjà l'agrégat et la planète ; false ne modifie aucun inventaire.
     * Les réservoirs sont dédiés, le fret existant garde sa place en soute, comme FleetHold::departure.
     */
    public function install(PlayerUniverse $owner, Planet $target, array $incoming, float $remainder): bool
    {
        if ($incoming === [] || !is_finite($remainder) || $remainder < 0.0
            || $target->owner?->id !== $owner->id || $target->solarSystem->galaxy->universe->id !== $owner->universe->id) {
            return false;
        }
        $idle = $this->repository->one(Fleet::class, ['playerUniverse' => $owner, 'homePlanet' => $target, 'status' => 'idle']);
        $stock = $combined = [];
        if ($idle instanceof Fleet) {
            $this->manager->lockCurrent($idle);
            foreach ($this->repository->all(FleetShip::class, ['fleet' => $idle]) as $row) {
                $this->manager->lockCurrent($row);
                if ($row->quantity < 0 || isset($stock[$row->slug][$row->tier])) {
                    return false;
                }
                $stock[$row->slug][$row->tier] = $row;
                $combined[$row->slug][$row->tier] = $row->quantity;
            }
        }
        foreach ($incoming as $slug => $tiers) {
            foreach ($tiers as $tier => $quantity) {
                $previous = $combined[$slug][$tier] ?? 0;
                if (!is_int($quantity) || $quantity < 1 || $quantity > 2147483647 - $previous) {
                    return false;
                }
                $combined[$slug][$tier] = $previous + $quantity;
            }
        }

        $oldReserve = $idle?->storedFuel ?? 0.0;
        $fuel = $oldReserve + $remainder;
        if (!is_finite($oldReserve) || $oldReserve < 0.0 || !is_finite($fuel) || ($remainder > 0.0 && $fuel <= $oldReserve)) {
            return false;
        }
        $freight = 0.0;
        foreach ($idle?->cargo ?? [] as $value) {
            if ((!is_int($value) && !is_float($value)) || !is_finite($value) || $value < 0.0) {
                return false;
            }
            $freight += $value;
        }
        try {
            $levels = $this->repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $owner]);
            $profiles = $this->factory->ships($owner->universe, $combined, $levels);
        } catch (\DomainException) {
            return false;
        }
        $tanks = $bays = 0.0;
        foreach ($profiles as $ship) {
            $tank = $ship['fuel_capacity'] ?? 0;
            $bay = $ship['cargo_capacity'] ?? null;
            if ((!is_int($tank) && !is_float($tank)) || (!is_int($bay) && !is_float($bay))
                || !is_finite($tank) || !is_finite($bay) || $tank < 0 || $bay < 0) {
                return false;
            }
            $tanks += $tank * $ship['quantity'];
            $bays += $bay * $ship['quantity'];
        }
        if (!is_finite($freight) || !is_finite($tanks + $bays)
            || $fuel > $tanks + $bays || $freight > $bays - max(0.0, $fuel - $tanks)) {
            return false;
        }

        if ($idle === null) {
            $idle = new Fleet();
            $idle->homePlanet = $target;
            $idle->playerUniverse = $owner;
            $this->manager->save($idle);
        }
        $idle->storedFuel = $fuel;
        foreach ($incoming as $slug => $tiers) {
            foreach ($tiers as $tier => $quantity) {
                $row = $stock[$slug][$tier] ?? new FleetShip();
                $row->fleet = $idle;
                $row->slug = $slug;
                $row->tier = $tier;
                $row->quantity = $combined[$slug][$tier];
                $this->manager->save($row);
            }
        }

        return true;
    }
}
