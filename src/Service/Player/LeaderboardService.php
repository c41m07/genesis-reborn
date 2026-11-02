<?php
namespace App\Service\Player;

use App\Entity\{Fleet,FleetShip,Planet,PlanetBuilding,PlanetDefense,PlayerTechnology,PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final class LeaderboardService
{
    public function __construct(private GameRepository $repo, private GameCatalog $catalog) {}

    public function forUniverse(PlayerUniverse $context): array
    {
        $universe = $context->universe;
        $memberships = $this->repo->all(PlayerUniverse::class, ['universe' => $universe]);
        $planets = $this->repo->planets($universe);
        $fleets = $this->repo->all(Fleet::class, ['playerUniverse' => $memberships]);
        $rows = [];
        foreach ($memberships as $membership) {
            $rows[$membership->id] = [
                'membership' => $membership,
                'building_resources' => 0,
                'research_resources' => 0,
                'fleet_resources' => 0,
                'fleet_units' => 0,
                'ship_units' => 0,
                'defense_units' => 0,
                'planets' => 0,
            ];
        }

        foreach ($planets as $planet) {
            if ($planet->owner) {
                ++$rows[$planet->owner->id]['planets'];
            }
        }
        foreach ($this->repo->all(PlanetBuilding::class, ['planet' => $planets]) as $building) {
            if ($building->level > 0 && isset($building->planet->owner)) {
                $rows[$building->planet->owner->id]['building_resources'] += $this->cumulativeCost($universe, 'buildings', $building->slug, $building->level, $building->tier);
            }
        }
        foreach ($this->repo->all(PlayerTechnology::class, ['playerUniverse' => $memberships]) as $technology) {
            if ($technology->level > 0) {
                $rows[$technology->playerUniverse->id]['research_resources'] += $this->cumulativeCost($universe, 'technologies', $technology->slug, $technology->level, $technology->tier);
            }
        }
        foreach ($this->repo->all(FleetShip::class, ['fleet' => $fleets]) as $ship) {
            if ($ship->quantity < 1) {
                continue;
            }
            $row = &$rows[$ship->fleet->playerUniverse->id];
            $row['fleet_resources'] += $this->unitCost($universe, 'ships', $ship->slug, $ship->quantity, $ship->tier);
            $row['ship_units'] += $ship->quantity;
            unset($row);
        }
        foreach ($this->repo->all(PlanetDefense::class, ['planet' => $planets]) as $defense) {
            if ($defense->quantity < 1 || !$defense->planet->owner) {
                continue;
            }
            $row = &$rows[$defense->planet->owner->id];
            $row['fleet_resources'] += $this->unitCost($universe, 'defenses', $defense->slug, $defense->quantity);
            $row['defense_units'] += $defense->quantity;
            unset($row);
        }

        $result = [];
        foreach ($rows as $row) {
            $row['fleet_units'] = $row['ship_units'] + $row['defense_units'];
            $row['building_points'] = intdiv($row['building_resources'], 100);
            $row['research_points'] = intdiv($row['research_resources'], 100);
            $row['fleet_points'] = intdiv($row['fleet_resources'], 100);
            $row['total_points'] = $row['building_points'] + $row['research_points'] + $row['fleet_points'];
            $result[] = $row;
        }

        foreach ([
            'points' => 'total_points',
            'fleet' => 'fleet_points',
            'planets' => 'planets',
        ] as $sort => $field) {
            $sorted = $result;
            usort($sorted, static fn (array $a, array $b): int =>
                ($b[$field] <=> $a[$field])
                ?: strcasecmp($a['membership']->player->username, $b['membership']->player->username)
            );
            foreach ($sorted as $index => $row) {
                $resultIndex = null;
                foreach ($result as $i => $item) {
                    if ($item['membership']->id === $row['membership']->id) {
                        $resultIndex = $i;
                        break;
                    }
                }
                if ($resultIndex === null) {
                    throw new \LogicException('Inscription absente du classement calculé.');
                }
                $result[$resultIndex]['ranks'][$sort] = $index + 1;
            }
        }

        usort($result, static fn (array $a, array $b): int => $a['ranks']['points'] <=> $b['ranks']['points']);

        return $result;
    }

    private function cumulativeCost(\App\Entity\Universe $universe,string $group,string $slug,int $level,string $tier='basic'):int
    {
        $definition=$this->catalog->getTierForUniverse($universe,$group,$slug,$tier);
        $total = 0;
        for ($currentLevel = 1; $currentLevel <= $level; ++$currentLevel) {
            $total += array_sum($this->catalog->costs($definition, $currentLevel));
        }

        return $total;
    }

    private function unitCost(\App\Entity\Universe $universe,string $group,string $slug,int $quantity,string $tier='basic'):int
    {
        return array_sum($this->catalog->costs($this->catalog->getTierForUniverse($universe,$group,$slug,$tier),1,$quantity));
    }
}
