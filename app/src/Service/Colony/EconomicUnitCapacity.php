<?php
namespace App\Service\Colony;

use App\Entity\{Planet, QueueItem};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

/** Lecture des unités et places civiles ; les écritures restent sous les verrous de jeu. */
final class EconomicUnitCapacity
{
    public function __construct(private GameRepository $repo, private GameCatalog $catalog) {}

    /**
     * @return array{present: array<string,array<string,int>>, incoming: array<string,array<string,int>>,
     *     construction: array<string,array<string,int>>}
     */
    public function inventory(Planet $planet, ?int $excludeFleetId = null): array
    {
        $inventory = ['present'=>[], 'incoming'=>[], 'construction'=>[]];
        // À l'inscription, la planète existe avant l'enregistrement de sa nouvelle participation.
        if ($planet->id === null || $planet->owner === null || $planet->owner->id === null) return $inventory;
        $definitions = array_filter($this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['catalog']['ships'],
            static fn(array $definition): bool => isset($definition['economic_unit']));
        foreach ($this->repo->economicUnitStocks($planet, array_keys($definitions)) as $ship) {
            $fleet = $ship->fleet;
            if ($fleet->homePlanet->id === $planet->id && ($fleet->status === 'idle'
                || ($fleet->status === 'defending' && $fleet->departureAt === null))) {
                // Une garnison engagée continue à produire jusqu'à l'application des pertes à la clôture.
                $inventory['present'][$ship->slug][$ship->tier] = ($inventory['present'][$ship->slug][$ship->tier] ?? 0) + $ship->quantity;
                continue;
            }
            if ($fleet->id === $excludeFleetId) continue;
            $reservation = $fleet->navigation['economic_slots'] ?? null;
            if (!is_array($reservation) || ($reservation['planet_id'] ?? null) !== $planet->id
                || ($reservation['owner_id'] ?? null) !== $planet->owner->id) continue;
            $destination = $fleet->status === 'returning' ? ($fleet->returnPlanet ?? $fleet->homePlanet) : $fleet->destination;
            if ($destination?->id !== $planet->id) continue;
            $quantity = $reservation['quantities'][$ship->slug][$ship->tier] ?? 0;
            if (!is_int($quantity) || $quantity < 0 || $quantity > $ship->quantity) {
                throw new \DomainException('Réservation d’unités économiques incohérente.');
            }
            $inventory['incoming'][$ship->slug][$ship->tier] = ($inventory['incoming'][$ship->slug][$ship->tier] ?? 0) + $quantity;
        }
        foreach ($this->repo->all(QueueItem::class, ['planet'=>$planet, 'playerUniverse'=>$planet->owner, 'queueType'=>'shipyard']) as $item) {
            $definition = $definitions[$item->itemSlug] ?? null;
            if ($definition === null || ($definition['mobile'] ?? true)) continue;
            $inventory['construction'][$item->itemSlug][$item->tier] = ($inventory['construction'][$item->itemSlug][$item->tier] ?? 0) + $item->quantity;
        }
        return $inventory;
    }

    /**
     * Les données physiques peuvent être préparées une fois pour les rangs et les segments temporels.
     * @return array<string,array<string,array{capacity:int,present:int,active:int,inactive:int,reserved:int,
     *     incoming:int,construction:int,waiting_reservations:int,available:int,resource:string,production_per_hour:float}>>
     */
    public function state(Planet $planet, array $buildings, ?array $inventory = null): array
    {
        $inventory ??= $this->inventory($planet);
        $result = [];
        foreach ($this->catalog->forUniverse($planet->solarSystem->galaxy->universe)['catalog']['ships'] as $slug=>$definition) {
            $economic = $definition['economic_unit'] ?? null;
            if (!is_array($economic)) continue;
            if (!in_array($economic['resource'] ?? null, ['hydrogen','electricity'], true)
                || !is_int($economic['slots_per_level'] ?? null) || $economic['slots_per_level'] < 1
                || !isset($this->catalog->all('buildings')[$economic['capacity_building'] ?? ''])) {
                throw new \DomainException('Profil de production civile invalide.');
            }
            foreach (GameCatalog::TIERS as $tier) {
                $levels = $buildings[$economic['capacity_building']] ?? [];
                $level = is_int($levels) ? ($tier === 'basic' ? $levels : 0) : ($levels[$tier] ?? 0);
                $capacity = max(0, (int)$level) * (int)$economic['slots_per_level'];
                $present = $inventory['present'][$slug][$tier] ?? 0;
                $incoming = $inventory['incoming'][$slug][$tier] ?? 0;
                $construction = $inventory['construction'][$slug][$tier] ?? 0;
                $demand = $incoming + $construction;
                // Une place déjà réservée ne peut pas être prise par une nouvelle construction de récolteur.
                // Si la capacité baisse, les excédents sont conservés et ne produisent pas.
                $reserved = min($capacity, $demand);
                $active = min($present, max(0, $capacity - $reserved));
                $production = $economic['production_per_hour'][$tier] ?? null;
                if (!is_numeric($production) || !is_finite((float)$production) || $production < 0 || $capacity < 0) {
                    throw new \DomainException('Profil de production civile invalide.');
                }
                $result[$slug][$tier] = ['capacity'=>$capacity, 'present'=>$present, 'active'=>$active,
                    'inactive'=>$present-$active, 'reserved'=>$reserved, 'incoming'=>$incoming,
                    'construction'=>$construction, 'waiting_reservations'=>max(0, $demand-$reserved),
                    'available'=>max(0, $capacity-$active-$reserved),
                    'resource'=>$economic['resource'], 'production_per_hour'=>(float)$production*$active];
            }
        }
        return $result;
    }

    public function constructionLimit(Planet $planet, array $definition, array $buildings): int
    {
        if (!isset($definition['economic_unit']) || ($definition['mobile'] ?? true)) return PHP_INT_MAX;
        $state = $this->state($planet, $buildings)[$definition['slug']][$definition['_tier']];
        return max(0, $state['capacity']-$state['present']-$state['incoming']-$state['construction']);
    }

    /**
     * Prépare les places du transfert ; les rappels et replis peuvent conserver des unités inactives.
     * @param array<string,array<string,int>> $composition
     * @return array<string,array<string,int>>
     */
    public function allocate(Planet $planet, array $composition, ?int $excludeFleetId = null, bool $requireAll = true): array
    {
        $requested = [];
        foreach ($composition as $slug=>$tiers) {
            $definition = $this->catalog->getForUniverse($planet->solarSystem->galaxy->universe, 'ships', $slug);
            if (!isset($definition['economic_unit']) || !($definition['mobile'] ?? true)) continue;
            foreach ($tiers as $tier=>$quantity) if ($quantity > 0) $requested[$slug][$tier] = $quantity;
        }
        if ($requested === []) return [];
        $buildings = $this->repo->levelsByTier(\App\Entity\PlanetBuilding::class, ['planet'=>$planet]);
        $states = $this->state($planet, $buildings, $this->inventory($planet, $excludeFleetId));
        $allocated = [];
        foreach ($requested as $slug=>$tiers) foreach ($tiers as $tier=>$quantity) {
            $available = $states[$slug][$tier]['available'] ?? 0;
            if ($requireAll && $quantity > $available) {
                throw new \DomainException('Places de récolteurs insuffisantes au rang '.$this->catalog->tiers()[$tier]['label'].' sur cette colonie.');
            }
            if ($available > 0) $allocated[$slug][$tier] = min($quantity, $available);
        }
        return $allocated;
    }
}
