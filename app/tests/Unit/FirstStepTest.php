<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\QueueItem;
use App\Service\Player\FirstStep;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class FirstStepTest extends TestCase
{
    #[DataProvider('completedOrders')]
    public function testMapsOnlyTheInitialMilestones(
        string $type,
        string $slug,
        string $tier,
        ?int $level,
        ?FirstStep $expected,
        bool $shipConstructed = false,
        int $quantity = 1,
    ): void
    {
        $item = new QueueItem();
        $item->queueType = $type;
        $item->itemSlug = $slug;
        $item->tier = $tier;
        $item->itemLevel = $level;
        $item->quantity = $quantity;

        self::assertSame($expected, FirstStep::forCompletedQueue($item, $shipConstructed));
    }

    /** @return iterable<string, array{string, string, string, ?int, ?FirstStep, 5?: bool, 6?: int}> */
    public static function completedOrders(): iterable
    {
        yield 'mine terminée' => ['building', 'mine_metal', 'basic', 1, FirstStep::MetalMine];
        yield 'amélioration mine' => ['building', 'mine_metal', 'basic', 3, FirstStep::MetalMine];
        yield 'centrale' => ['building', 'power_plant', 'basic', 1, FirstStep::PowerPlant];
        yield 'atelier nano' => ['building', 'mine_nano', 'basic', 1, FirstStep::NanoWorkshop];
        yield 'autre installation' => ['building', 'lab', 'basic', 1, null];
        yield 'autre rang' => ['building', 'mine_nano', 'intermediate', 1, null];
        yield 'recherche au choix' => ['research', 'vector_propulsion', 'basic', 1, FirstStep::Research];
        yield 'aucun niveau terminé' => ['research', 'vector_propulsion', 'basic', 0, null];
        yield 'sans niveau' => ['building', 'mine_metal', 'basic', null, null];
        yield 'sans preuve de crédit au hangar' => ['shipyard', 'probe', 'basic', null, null];
        yield 'vaisseau crédité' => ['shipyard', 'probe', 'basic', null, FirstStep::Ship, true];
        yield 'défense au chantier' => ['shipyard', 'ground_turret_anti_light', 'basic', null, null, false];
        yield 'lot vide non construit' => ['shipyard', 'probe', 'basic', null, null, true, 0];
    }
}
