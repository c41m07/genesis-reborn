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
     * Le fret existant garde sa place en soute lors de la remise des survivants.
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
        $bays = 0.0;
        foreach ($profiles as $ship) {
            $bay = $ship['cargo_capacity'] ?? null;
            if ((!is_int($bay) && !is_float($bay)) || !is_finite($bay) || $bay < 0) {
                return false;
            }
            $bays += $bay * $ship['quantity'];
        }
        if (!is_finite($freight) || !is_finite($bays) || $freight > $bays) {
            return false;
        }

        if ($idle === null) {
            $idle = new Fleet();
            $idle->homePlanet = $target;
            $idle->playerUniverse = $owner;
            $this->manager->save($idle);
        }
        $idle->storedFuel = 0;
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
