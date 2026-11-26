<?php

declare(strict_types=1);

namespace App\Service\Market;

use App\Entity\{Fleet, FleetShip, Planet, PlayerTechnology, PlayerUniverse, ResourceMarket};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Fleet\{FleetHold, MovementCalculator};

final readonly class MarketTerms
{
    public const COORDINATE = 'A:0:0';
    public const LEG_SECONDS = 1800;

    public function __construct(
        private GameRepository $repo,
        private GameCatalog $catalog,
        private TechnologyEffectCalculator $effects,
        private ResourceService $resources,
        private FleetHold $hold,
        private MovementCalculator $movement,
    ) {
    }

    public function cargos(PlayerUniverse $member, Planet $planet): array
    {
        $idle = $this->idle($member, $planet);
        $stock = [];
        foreach ($idle === null ? [] : $this->repo->all(FleetShip::class, ['fleet' => $idle, 'slug' => 'transport']) as $ship) {
            $stock[$ship->tier] = $ship->quantity;
        }

        return $stock;
    }

    public function idle(PlayerUniverse $member, Planet $planet): ?Fleet
    {
        return $this->repo->one(Fleet::class, ['homePlanet' => $planet, 'playerUniverse' => $member, 'status' => 'idle']);
    }

    /** @param array{incoming: array<string,int>, outgoing: array<string,int>} $reserved */
    public function quote(
        PlayerUniverse $member, Planet $planet, ResourceMarket $market, array $reserved,
        string $pay, string $receive, int $quantity, string $rank, int $cargos,
    ): array {
        if (!$market->enabled || $market->universe->id !== $member->universe->id || $planet->owner?->id !== $member->id) {
            throw new \DomainException('La Bourse ou la colonie est inaccessible.');
        }
        $limit = $this->catalog->forUniverse($member->universe)['limits']['max_fleet_size'];
        if (!in_array($rank, GameCatalog::TIERS, true) || $cargos < 1 || $cargos > $limit || ($this->cargos($member, $planet)[$rank] ?? 0) < $cargos) {
            throw new \DomainException('Les cargos sélectionnés ne sont pas disponibles.');
        }

        // Une réservation décale le prochain devis sans modifier le cours public ni créer de ressources.
        $projected = [];
        foreach (MarketPricing::WEIGHTS as $resource => $weight) {
            $projected[$resource] = ($market->pressure[$resource] ?? 0)
                + ($reserved['outgoing'][$resource] ?? 0) - ($reserved['incoming'][$resource] ?? 0);
        }
        $fill = MarketPricing::exchange($pay, $receive, $quantity, $projected, $market->targets,
            ($market->stock[$receive] ?? 0) - ($reserved['outgoing'][$receive] ?? 0));
        if (($market->stock[$pay] ?? 0) + ($reserved['incoming'][$pay] ?? 0) + $quantity > MarketPricing::MAX_STOCK) {
            throw new \DomainException('Les entrepôts de la station ne peuvent plus recevoir ce lot.');
        }

        $definition = $this->catalog->getTierForUniverse($member->universe, 'ships', 'transport', $rank);
        $definition['quantity'] = $cargos;
        $levels = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $member]);
        $ships = $this->effects->applyToShips([$definition], $levels, $member->universe);
        // Un même couloir de référence sert au tarif H dans tout l’univers. La durée est
        // indépendante du calcul de vitesse, de recherche ou de multiplicateur du jeu.
        $route = $this->movement->quoteDistances(100, 0, true, $ships, 1.0, 1.0, $this->catalog->rules()['fuel_ratio_policy']);
        $returnCapacity = max(0.0, $route['cargo_capacity'] - max(0.0, $route['one_way_fuel'] - $route['fuel_capacity']));
        if ($quantity > floor($route['freight_capacity']) || $fill['received'] > floor($returnCapacity)) {
            throw new \DomainException('Les cargos ne peuvent pas transporter le lot et le carburant sur les deux trajets.');
        }
        $fuel = $this->hold->departure($this->idle($member, $planet), ['transport' => [$rank => $cargos]], $route['total_fuel']);
        $snapshot = $this->resources->snapshot($planet);
        $needed = [$pay => $quantity];
        $needed['hydrogen'] = ($needed['hydrogen'] ?? 0) + $fuel['planet_fuel'];
        foreach ($needed as $resource => $amount) {
            if (($snapshot[$resource]['stock'] ?? 0) + 1e-6 < $amount) {
                throw new \DomainException('Les ressources ou le carburant sont insuffisants sur cette colonie.');
            }
        }

        return [
            'planet' => $planet->id, 'pay' => $pay, 'receive' => $receive, 'quantity' => $quantity, 'rank' => $rank,
            'cargos' => $cargos, 'oneWayFuel' => $route['one_way_fuel'], 'planetFuel' => $fuel['planet_fuel'],
            'storedFuel' => $fuel['stored_fuel_used'], 'outCapacity' => (int) floor($route['freight_capacity']),
            'returnCapacity' => (int) floor($returnCapacity),
        ] + $fill;
    }
}
