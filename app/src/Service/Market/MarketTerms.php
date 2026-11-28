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
        private PlayerMarket $players,
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
        string $pay, string $receive, int $quantity, array $composition, string $operation, bool $compositionOnly = false,
    ): array {
        if (!$market->enabled || $market->universe->id !== $member->universe->id || $planet->owner?->id !== $member->id) {
            throw new \DomainException('La Bourse ou la colonie est inaccessible.');
        }
        $transport = $this->transportTerms($member, $planet, $composition);
        if ($compositionOnly) {
            return $transport;
        }

        // Une réservation décale le prochain devis sans modifier le cours public ni créer de ressources.
        if (!isset(MarketPricing::WEIGHTS[$pay], MarketPricing::WEIGHTS[$receive])
            || $quantity < 1 || $quantity > MarketPricing::MAX_LOT
            || !in_array($operation, ['barter', 'buy', 'sell', 'deposit', 'collect'], true)) {
            throw new \DomainException('Opération ou lot invalide.');
        }
        $current = MarketPricing::scarcity($market->targets, $market->stock);
        // Un convoi peut encore être rappelé. Les lots réservés du même côté comptent pour le prix,
        // mais une promesse inverse ne doit pas améliorer un devis avant la livraison physique.
        $projected = $current;
        $projected[$pay] -= $reserved['incoming'][$pay] ?? 0;
        if ($operation === 'barter') {
            $projected[$receive] += $reserved['outgoing'][$receive] ?? 0;
        } elseif ($operation === 'buy') {
            $projected[$pay] = $current[$pay] + ($reserved['outgoing'][$pay] ?? 0);
        }
        $resource = $pay;
        $creditCents = 0;
        $out = $quantity;
        $back = $quantity;
        if ($operation === 'barter') {
            $fill = MarketPricing::exchange($pay, $receive, $quantity, $projected, $market->targets,
                ($market->stock[$receive] ?? 0) - ($reserved['outgoing'][$receive] ?? 0));
            $back = $fill['received'];
        } else {
            $receive = $resource;
            $takesFromStation = in_array($operation, ['buy', 'collect'], true);
            $out = $takesFromStation ? 0 : $quantity;
            $back = $takesFromStation ? $quantity : 0;
            $fill = ['received' => $back, 'before_fee' => $back];
            if ($operation === 'sell' || $operation === 'buy') {
                $creditCents = MarketPricing::creditPrice($resource, $quantity, $projected[$resource],
                    $market->targets[$resource] ?? 0, $operation === 'buy');
            }
            if ($operation === 'buy' && ($quantity > ($market->stock[$resource] ?? 0) - ($reserved['outgoing'][$resource] ?? 0)
                || $creditCents > ($this->players->account($member)?->cents ?? 0))) {
                throw new \DomainException('Réserve de la station ou solde de crédits insuffisant.');
            }
            if ($operation === 'collect' && $quantity > ($this->players->account($member)?->goods[$resource] ?? 0)) {
                throw new \DomainException('Quantité indisponible dans votre dépôt à la station.');
            }
        }

        if ($out > $transport['outCapacity'] || $back > $transport['returnCapacity']) {
            throw new \DomainException('Les cargos ne peuvent pas transporter le lot et le carburant sur les deux trajets.');
        }
        $snapshot = $this->resources->snapshot($planet);
        $needed = [$pay => $out];
        $needed['hydrogen'] = ($needed['hydrogen'] ?? 0) + $transport['planetFuel'];
        foreach ($needed as $resource => $amount) {
            if (($snapshot[$resource]['stock'] ?? 0) + 1e-6 < $amount) {
                throw new \DomainException('Les ressources ou le carburant sont insuffisants sur cette colonie.');
            }
        }

        return [
            'operation' => $operation, 'out' => $out, 'creditCents' => $creditCents,
            'planet' => $planet->id, 'pay' => $pay, 'receive' => $receive, 'quantity' => $quantity,
        ] + $transport + $fill;
    }

    private function transportTerms(PlayerUniverse $member, Planet $planet, array $composition): array
    {
        $limit = $this->catalog->forUniverse($member->universe)['limits']['max_fleet_size'];
        $stock = $this->cargos($member, $planet);
        $selected = [];
        $definitions = [];
        foreach ($composition as $tier => $raw) {
            $count = filter_var($raw === '' ? 0 : $raw, FILTER_VALIDATE_INT);
            if (!in_array($tier, GameCatalog::TIERS, true) || $count === false || $count < 0 || $count > ($stock[$tier] ?? 0)) {
                throw new \DomainException('Les cargos sélectionnés ne sont pas disponibles.');
            }
            if ($count > 0) {
                $selected[$tier] = $count;
                $definition = $this->catalog->getTierForUniverse($member->universe, 'ships', 'transport', $tier);
                $definition['quantity'] = $count;
                $definitions[] = $definition;
            }
        }
        if (!$selected || array_sum($selected) > $limit) {
            throw new \DomainException('Sélectionnez au moins un cargo dans la limite de flotte.');
        }
        $levels = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $member]);
        $ships = $this->effects->applyToShips($definitions, $levels, $member->universe);
        // Un même couloir de référence sert au tarif H dans tout l’univers. La durée est
        // indépendante du calcul de vitesse, de recherche ou de multiplicateur du jeu.
        $route = $this->movement->quoteDistances(100, 0, true, $ships, 1.0, 1.0, $this->catalog->rules()['fuel_ratio_policy']);
        $returnCapacity = max(0.0, $route['cargo_capacity'] - max(0.0, $route['one_way_fuel'] - $route['fuel_capacity']));
        $fuel = $this->hold->departure($this->idle($member, $planet), ['transport' => $selected], $route['total_fuel']);
        return [
            'composition' => $selected, 'cargos' => array_sum($selected),
            'oneWayFuel' => $route['one_way_fuel'], 'planetFuel' => $fuel['planet_fuel'], 'storedFuel' => $fuel['stored_fuel_used'],
            'outCapacity' => (int) floor($route['freight_capacity']), 'returnCapacity' => (int) floor($returnCapacity),
        ];
    }
}
