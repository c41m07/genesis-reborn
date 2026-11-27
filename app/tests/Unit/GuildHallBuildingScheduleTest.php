<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\GuildHallBuilding;
use App\Service\Guild\GuildHallBuildingSchedule;
use PHPUnit\Framework\TestCase;

final class GuildHallBuildingScheduleTest extends TestCase
{
    public function testFirstWarehouseCanStartFromTheSmallReceivingBay(): void
    {
        $catalogue = new GuildHallBuildingSchedule([
            GuildHallBuilding::WAREHOUSE => [
                1 => ['cost' => ['metal' => 60], 'duration' => 300],
                2 => ['cost' => ['metal' => 100], 'duration' => 600],
            ],
            GuildHallBuilding::DOCK => [
                1 => ['cost' => ['metal' => 140], 'duration' => 900],
            ],
        ], [1 => ['metal' => 120], 2 => ['metal' => 200]]);

        self::assertSame(60, $catalogue->capacity(0)['metal']);
        self::assertSame(120, $catalogue->capacity(1)['metal']);
        self::assertSame(['cost' => ['metal' => 60], 'duration' => 300, 'maxLevel' => 2], $catalogue->quote(GuildHallBuilding::WAREHOUSE, 0));
        self::assertSame(['metal' => 100], $catalogue->quote(GuildHallBuilding::WAREHOUSE, 1)['cost']);
    }

    public function testCatalogueRejectsAnUnreachableWarehouseUpgrade(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le chantier ne peut pas tenir dans le stock du Hall.');

        new GuildHallBuildingSchedule([GuildHallBuilding::WAREHOUSE => [
            1 => ['cost' => ['metal' => 60], 'duration' => 300],
            2 => ['cost' => ['metal' => 121], 'duration' => 600],
        ]], [1 => ['metal' => 120], 2 => ['metal' => 200]]);
    }
}
