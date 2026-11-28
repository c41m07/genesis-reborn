<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildHallBuilding, GuildHallStock};
use App\Repository\GameRepository;

final readonly class GuildHallWarehouseView
{
    public const STRUCTURES = [
        GuildHallBuilding::ATELIER => 'Atelier d’assemblage',
        GuildHallBuilding::DOCK => 'Dock de modernisation',
        GuildHallBuilding::RELAY => 'Relais de surveillance',
        GuildHallBuilding::COMMAND => 'Centre de commandement',
    ];

    private const MATERIALS = ['metal' => 'Métal', 'nano' => 'Nano', 'antimatter' => 'Antimatière', 'hydrogen' => 'Hydrogène'];

    public function __construct(private GameRepository $repository, private GuildHallScheduleProvider $schedules)
    {
    }

    /** Le contrôleur a déjà confirmé que ce Hall appartient à la guilde actuelle. */
    public function describe(GuildHall $hall): array
    {
        $stock = $this->repository->one(GuildHallStock::class, ['hall' => $hall]);
        $buildings = [];
        foreach ($this->repository->all(GuildHallBuilding::class, ['hall' => $hall]) as $building) {
            $buildings[$building->slug] = $building;
        }
        $warehouse = $buildings[GuildHallBuilding::WAREHOUSE] ?? null;
        $level = $warehouse?->level ?? 0;
        $schedule = $this->schedules->schedule();
        $capacity = $schedule?->capacity($level) ?? [];
        $quote = $schedule !== null && $warehouse?->finishesAt === null
            && $level < $schedule->maximumLevel(GuildHallBuilding::WAREHOUSE)
            ? $schedule->quote(GuildHallBuilding::WAREHOUSE, $level) : null;
        $fields = [];
        $canPay = $quote !== null;
        foreach (self::MATERIALS as $slug => $label) {
            $held = $stock?->materials[$slug] ?? 0;
            $cost = $quote['cost'][$slug] ?? 0;
            $fields[] = ['label' => $label, 'held' => $held, 'capacity' => $capacity[$slug] ?? 0, 'cost' => $cost];
            $canPay = $canPay && $held >= $cost;
        }

        $structures = [];
        foreach (self::STRUCTURES as $slug => $name) {
            $building = $buildings[$slug] ?? null;
            $current = $building?->level ?? 0;
            if ($schedule === null || $schedule->maximumLevel($slug) === 0) {
                continue;
            }
            $project = $building?->finishesAt === null && $current < $schedule->maximumLevel($slug) ? $schedule->quote($slug, $current) : null;
            $costs = [];
            $canStart = $project !== null;
            foreach (self::MATERIALS as $material => $label) {
                $cost = $project['cost'][$material] ?? 0;
                if ($cost > 0) {
                    $costs[] = ['label' => $label, 'cost' => $cost];
                    $canStart = $canStart && ($stock?->materials[$material] ?? 0) >= $cost;
                }
            }
            $structures[] = [
                'slug' => $slug,
                'name' => $name,
                'level' => $current,
                'finishes_at' => $building?->finishesAt,
                'duration' => $project['duration'] ?? null,
                'costs' => $costs,
                'can_start' => $canStart,
            ];
        }

        return [
            'structures' => $structures,
            'enabled' => $schedule !== null,
            'dock_built' => ($buildings[GuildHallBuilding::DOCK]->level ?? 0) > 0,
            'atelier_built' => ($buildings[GuildHallBuilding::ATELIER]->level ?? 0) > 0,
            'level' => $level,
            'finishes_at' => $warehouse?->finishesAt,
            'quote' => $quote,
            'materials' => $fields,
            'can_pay' => $canPay,
        ];
    }
}
