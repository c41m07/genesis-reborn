<?php
namespace App\Service\Fleet;

use App\Entity\{Fleet,FleetShip,Planet,PlayerTechnology,PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ActionGuard,ResourceService};
use App\Service\Combat\TechnologyEffectCalculator;

final class FleetHold
{
    public function __construct(private GameRepository $repo, private GameManager $manager,
        private GameCatalog $catalog, private TechnologyEffectCalculator $effects,
        private ResourceService $resources, private ActionGuard $guard) {}

    public function ships(Fleet $fleet, array $removed = []): array
    {
        $ships = [];
        foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet]) as $ship) {
            $quantity = $ship->quantity - ($removed[$ship->slug][$ship->tier] ?? 0);
            if ($quantity < 0) throw new \DomainException('Composition indisponible.');
            if ($quantity === 0) continue;
            $definition = $this->catalog->getTierForUniverse($fleet->playerUniverse->universe, 'ships', $ship->slug, $ship->tier);
            $definition['quantity'] = $quantity;$ships[] = $definition;
        }
        $levels = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $fleet->playerUniverse]);
        return $this->effects->applyToShips($ships, $levels, $fleet->playerUniverse->universe);
    }

    public function departure(?Fleet $idle, array $composition, float $fuel): array
    {
        if (!$idle) return ['stored_fuel_used' => 0.0, 'planet_fuel' => $fuel];
        if (!is_finite($idle->storedFuel) || $idle->storedFuel < 0) throw new \DomainException('Réserve embarquée invalide.');
        $used = min($idle->storedFuel, $fuel);$remaining = $idle->storedFuel - $used;
        $tanks = $bays = 0.0;
        foreach ($this->ships($idle, $composition) as $ship) {
            $tanks += ($ship['fuel_capacity'] ?? 0) * $ship['quantity'];
            $bays += $ship['cargo_capacity'] * $ship['quantity'];
        }
        $freight = array_sum($idle->cargo);
        $tolerance = max(1e-9, max($fuel, $remaining, $freight) * PHP_FLOAT_EPSILON * 64);
        if ($remaining > $tanks + $bays + $tolerance || $freight > $bays - max(0, $remaining - $tanks) + $tolerance) {
            throw new \DomainException('Les vaisseaux restants ne peuvent pas porter la réserve et le fret conservés. Déchargez-les ou modifiez la composition.');
        }
        return ['stored_fuel_used' => $used, 'planet_fuel' => $fuel - $used];
    }

    public function unload(PlayerUniverse $member, int $planetId, string $kind, string $key): array
    {
        return $this->manager->transaction(function () use ($member, $planetId, $kind, $key): array {
            $planet = $this->repo->find(Planet::class, $planetId);
            if (!$planet) throw new \DomainException('Colonie inaccessible.');
            $this->guard->lock($member, $planet);
            if ($member->player->lockedAt !== null) throw new \DomainException('Compte verrouillé.');
            if (!in_array($kind, ['fuel', 'cargo'], true)) throw new \DomainException('Déchargement invalide.');
            if (!$this->guard->receipt($member, $key)) return [];
            $idle = $this->repo->one(Fleet::class, ['playerUniverse' => $member, 'homePlanet' => $planet, 'status' => 'idle']);
            if (!$idle) throw new \DomainException('Aucune réserve embarquée.');
            $this->manager->lock($idle);
            $payload = $kind === 'fuel' ? ['hydrogen' => $idle->storedFuel] : $idle->cargo;
            $result = $this->resources->deposit($planet, $payload);
            if ($kind === 'fuel') $idle->storedFuel = $result['overflow']['hydrogen'] ?? 0;
            else $idle->cargo = array_filter($result['overflow'], static fn($amount) => $amount > 0);
            return $result['delivered'];
        });
    }

    /** Arrivée sous les verrous de participation, planète et flotte. */
    public function install(Fleet $fleet, Planet $planet): void
    {
        if($planet->owner?->id!==$fleet->playerUniverse->id || $planet->solarSystem->galaxy->universe->id!==$fleet->playerUniverse->universe->id) {
            throw new \DomainException('La nouvelle base ne vous appartient pas dans cet univers.');
        }
        $freight = $fleet->cargo;
        $freight['hydrogen'] = max(0.0, ($freight['hydrogen'] ?? 0) - $fleet->reservedFuel);
        $result = $this->resources->deposit($planet, $freight);
        $idle = $this->repo->one(Fleet::class, ['playerUniverse' => $fleet->playerUniverse, 'homePlanet' => $planet, 'status' => 'idle']);
        if (!$idle) {
            $idle = new Fleet();$idle->playerUniverse = $fleet->playerUniverse;$idle->homePlanet = $planet;
            $this->manager->save($idle);$this->manager->flush();
        } else $this->manager->lock($idle);
        $idle->storedFuel += $fleet->reservedFuel;
        foreach ($result['overflow'] as $slug => $amount) if ($amount > 0) $idle->cargo[$slug] = ($idle->cargo[$slug] ?? 0) + $amount;
        foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleet]) as $ship) {
            $stock = $this->repo->one(FleetShip::class, ['fleet' => $idle, 'slug' => $ship->slug, 'tier' => $ship->tier]) ?? new FleetShip();
            $stock->fleet = $idle;$stock->slug = $ship->slug;$stock->tier = $ship->tier;$stock->quantity += $ship->quantity;
            $this->manager->save($stock);$this->manager->remove($ship);
        }
        $fleet->cargo = [];$fleet->reservedFuel = 0;$fleet->storedFuel = 0;
        $fleet->status = 'completed';$fleet->arrivalAt = null;
        $fleet->dispatchToken = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
    }
}
