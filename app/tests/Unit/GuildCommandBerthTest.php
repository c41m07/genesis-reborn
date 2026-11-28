<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Fleet, Guild, GuildHall, GuildHallConvoy, Planet, SolarSystem};
use App\Service\Guild\GuildCommandBerth;
use PHPUnit\Framework\TestCase;

final class GuildCommandBerthTest extends TestCase
{
    public function testOnlyCurrentStationingArrivesAndKeepsItsPrivateReturnReserve(): void
    {
        $now = new \DateTimeImmutable('@1900000000');
        $guild = new Guild();
        $guild->id = 3;
        $system = new SolarSystem();
        $system->id = 9;
        $hall = new GuildHall();
        $hall->id = 7;
        $hall->placedAt = $now;
        $hall->guild = $guild;
        $hall->solarSystem = $system;
        $fleet = new Fleet();
        $fleet->homePlanet = $home = new Planet();
        $fleet->destinationSystem = $system;
        $fleet->status = 'traveling';
        $fleet->arrivalAt = $now;
        $fleet->dispatchToken = 'active';
        $fleet->reservedFuel = 123.5;
        $fleet->cargo = ['hydrogen' => 123.5];
        $receipt = new GuildHallConvoy();
        $receipt->fleet = $fleet;
        $receipt->guildId = 3;
        $receipt->hallId = 7;
        $receipt->outboundToken = 'active';
        self::assertFalse(GuildCommandBerth::arrive($receipt, $hall, 'active', $now));
        $receipt->commandStation = true;
        $other = clone $hall;
        $other->id = 8;
        self::assertFalse(GuildCommandBerth::arrive($receipt, $other, 'active', $now));
        self::assertFalse(GuildCommandBerth::arrive($receipt, $hall, 'old', $now));
        self::assertFalse(GuildCommandBerth::arrive($receipt, $hall, 'active', $now->modify('-1 second')));
        self::assertTrue(GuildCommandBerth::arrive($receipt, $hall, 'active', $now));
        self::assertFalse(GuildCommandBerth::arrive($receipt, $hall, 'active', $now));
        self::assertSame(GuildCommandBerth::DOCKED, $fleet->status);
        self::assertSame($home, $fleet->homePlanet);
        self::assertSame(123.5, $fleet->reservedFuel);
        self::assertNull($fleet->arrivalAt);
        self::assertSame([], $receipt->delivered);
        self::assertNull($receipt->deliveredShips);
    }
}
