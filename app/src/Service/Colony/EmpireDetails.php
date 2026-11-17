<?php

namespace App\Service\Colony;

use App\Entity\{Fleet, FleetShip, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class EmpireDetails
{
    public function __construct(private GameRepository $repo, private ResourceService $resources, private GameCatalog $catalog)
    {
    }

    public function planets(PlayerUniverse $membership): array
    {
        $details = [];
        $shipsByPlanet = [];
        $planets = $this->repo->planets($membership->universe, $membership);
        $fleets = $planets === [] ? [] : $this->repo->all(Fleet::class, ['playerUniverse' => $membership, 'homePlanet' => $planets, 'status' => 'idle']);
        foreach ($fleets === [] ? [] : $this->repo->all(FleetShip::class, ['fleet' => $fleets]) as $ship) {
            $shipsByPlanet[$ship->fleet->homePlanet->id][] = $ship;
        }
        foreach ($planets as $planet) {
            $details[$planet->id] = ['ships' => $this->ships($membership, $shipsByPlanet[$planet->id] ?? []), 'resources' => $this->resources->snapshot($planet)];
        }
        return $details;
    }

    public function fleets(PlayerUniverse $membership, array $fleets): array
    {
        $details = [];
        $shipsByFleet = [];
        // Filtrer les flottes privées avant la lecture groupée de leurs compositions.
        $fleets = array_values(array_filter($fleets, static fn(Fleet $fleet): bool => $fleet->playerUniverse->id === $membership->id && $fleet->playerUniverse->universe->id === $membership->universe->id));
        foreach ($fleets === [] ? [] : $this->repo->all(FleetShip::class, ['fleet' => $fleets]) as $ship) {
            $shipsByFleet[$ship->fleet->id][] = $ship;
        }
        foreach ($fleets as $fleet) {
            $cargo = [];
            foreach ($this->catalog->rules()['resources'] as $slug => $label) {
                $stock = max(0, (float) ($fleet->cargo[$slug] ?? 0));
                if ($stock > 0) {
                    $cargo[$slug] = ['label' => $label, 'stock' => $stock];
                }
            }
            $details[$fleet->id] = ['ships' => $this->ships($membership, $shipsByFleet[$fleet->id] ?? []), 'resources' => $cargo];
        }
        return $details;
    }

    private function ships(PlayerUniverse $membership, array $shipRows): array
    {
        $ships = [];
        $definitions = $this->catalog->forUniverse($membership->universe)['catalog']['ships'];
        foreach ($shipRows as $ship) {
            if ($ship->quantity <= 0) {
                continue;
            }
            $key = $ship->slug . ':' . $ship->tier;
            $ships[$key] ??= ['label' => $definitions[$ship->slug]['label'] ?? str_replace('_', ' ', $ship->slug), 'tier' => $this->catalog->tiers()[$ship->tier]['label'] ?? $ship->tier, 'quantity' => 0];
            $ships[$key]['quantity'] += $ship->quantity;
        }
        return array_values($ships);
    }
}
