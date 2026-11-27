<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildHall, GuildHallBuilding, GuildHallStock};
use App\Repository\GameRepository;

final readonly class GuildHallWarehouseView
{
    private const MATERIALS = ['metal' => 'Métal', 'nano' => 'Nano', 'antimatter' => 'Antimatière', 'hydrogen' => 'Hydrogène'];

    public function __construct(private GameRepository $repository, private GuildHallScheduleProvider $schedules)
    {
    }

    /** Le contrôleur a déjà confirmé que ce Hall appartient à la guilde actuelle. */
    public function describe(GuildHall $hall): array
    {
        $stock = $this->repository->one(GuildHallStock::class, ['hall' => $hall]);
        $warehouse = $this->repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => GuildHallBuilding::WAREHOUSE]);
        $atelier = $this->repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => GuildHallBuilding::ATELIER]);
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

        return [
            'enabled' => $schedule !== null,
            'atelier_built' => ($atelier?->level ?? 0) > 0,
            'level' => $level,
            'finishes_at' => $warehouse?->finishesAt,
            'quote' => $quote,
            'materials' => $fields,
            'can_pay' => $canPay,
        ];
    }
}
