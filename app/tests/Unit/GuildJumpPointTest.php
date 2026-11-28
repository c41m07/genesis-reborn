<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Guild, GuildHall, GuildJumpPoint, GuildMember, Planet, PlayerUniverse, SolarSystem, Universe};
use PHPUnit\Framework\TestCase;

final class GuildJumpPointTest extends TestCase
{
    public function testADuePointRequiresTheSameGuildAndColonyAcquisition(): void
    {
        $now = new \DateTimeImmutable('2026-11-28T03:00:00Z');
        $universe = new Universe();
        $universe->id = 1;
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $owner = new PlayerUniverse();
        $owner->id = 8;
        $guild = new Guild();
        $guild->id = 4;
        $guild->universe = $universe;
        $hall = new GuildHall();
        $hall->guild = $guild;
        $hall->solarSystem = $system;
        $hall->placedAt = $now;
        $member = new GuildMember();
        $member->guild = $guild;
        $member->playerUniverse = $owner;
        $colony = new Planet();
        $colony->solarSystem = $system;
        $colony->acquireBy($owner, $now);
        $point = new GuildJumpPoint();
        $point->hall = $hall;
        $point->member = $member;
        $point->planet = $colony;
        $point->acquisitionAt = $colony->acquiredAt;
        $point->readyAt = $now->modify('+60 seconds');

        self::assertFalse($point->isUsableAt($now->modify('+59 seconds')));
        self::assertTrue($point->isUsableAt($point->readyAt));
        $rival = new PlayerUniverse();
        $rival->id = 9;
        $colony->acquireBy($rival, $point->readyAt);
        self::assertFalse($point->isUsableAt($point->readyAt));
        $colony->acquireBy($owner, $now->modify('+61 seconds'));
        self::assertFalse($point->isUsableAt($now->modify('+62 seconds')));
        $point->acquisitionAt = $colony->acquiredAt;
        $guild->archivedAt = $now->modify('+62 seconds');
        self::assertFalse($point->isUsableAt($now->modify('+62 seconds')));
    }
}
