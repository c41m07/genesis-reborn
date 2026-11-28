<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{GuildHallBuilding, GuildJumpPoint};
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

    public function testAJumpPointHasOneInstallationPriceWithoutASecondRank(): void
    {
        $warehouse = [1 => ['cost' => ['metal' => 25], 'duration' => 300]];
        $jump = ['cost' => ['metal' => 45, 'hydrogen' => 15], 'duration' => 600];
        $capacity = [1 => ['metal' => 90, 'hydrogen' => 50]];
        $catalogue = new GuildHallBuildingSchedule([
            GuildHallBuilding::WAREHOUSE => $warehouse,
            GuildJumpPoint::SCHEDULE_SLUG => [1 => $jump],
        ], $capacity, 900);
        self::assertSame($jump + ['maxLevel' => 1], $catalogue->quote(GuildJumpPoint::SCHEDULE_SLUG, 0));

        $this->expectException(\DomainException::class);
        new GuildHallBuildingSchedule([
            GuildHallBuilding::WAREHOUSE => $warehouse,
            GuildJumpPoint::SCHEDULE_SLUG => [1 => $jump, 2 => $jump],
        ], $capacity, 900);
    }

    public function testRelayUsesConfiguredMaterialsAndCannotExceedHallCapacity(): void
    {
        $level = ['cost' => ['metal' => 40, 'nano' => 10], 'duration' => 420];
        $levels = [
            GuildHallBuilding::WAREHOUSE => [1 => ['cost' => ['metal' => 20], 'duration' => 60]],
            GuildHallBuilding::RELAY => [1 => $level],
        ];
        $schedule = new GuildHallBuildingSchedule($levels, [1 => ['metal' => 60, 'nano' => 10]]);
        self::assertSame($level + ['maxLevel' => 1], $schedule->quote(GuildHallBuilding::RELAY, 0));

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Le chantier ne peut pas tenir dans le stock du Hall.');
        new GuildHallBuildingSchedule($levels, [1 => ['metal' => 60, 'nano' => 9]]);
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
