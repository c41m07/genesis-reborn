<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Fleet, GuildCommandConsent, GuildCommandPlan, GuildHallConvoy, Planet, PlayerUniverse};
use App\Service\Guild\GuildCommandBerth;
use PHPUnit\Framework\TestCase;

final class GuildCommandConsentTest extends TestCase
{
    public function testOnlyTheExistingPrivateBerthMatchesAndOnlyItsOwnerCanWithdraw(): void
    {
        $now = new \DateTimeImmutable('@1900000000');
        $plan = new GuildCommandPlan(1, 2, 3, 4, 5, 6, 7, $now);
        $consent = new GuildCommandConsent($plan, 8, 9, 10, 'active', $now);
        $fleet = new Fleet();
        $fleet->id = 8;
        $fleet->playerUniverse = new PlayerUniverse();
        $fleet->playerUniverse->id = 9;
        $fleet->homePlanet = new Planet();
        $fleet->homePlanet->id = 10;
        $fleet->status = GuildCommandBerth::DOCKED;
        $fleet->mission = GuildHallConvoy::MISSION;
        $fleet->dispatchToken = 'old';
        self::assertFalse($consent->matchesBerth($fleet));
        $fleet->dispatchToken = 'active';
        self::assertTrue($consent->matchesBerth($fleet));
        self::assertFalse($consent->withdraw(7, $now));
        self::assertTrue($consent->withdraw(9, $now));
        self::assertFalse($consent->withdraw(9, $now));
        self::assertFalse($consent->matchesBerth($fleet));
    }

    public function testAnOpenedPlanDoesNotAcceptChangesToConsent(): void
    {
        $now = new \DateTimeImmutable('@1900000000');
        $plan = new GuildCommandPlan(1, 2, 3, 4, 5, 6, 7, $now);
        $consent = new GuildCommandConsent($plan, 8, 9, 10, 'active', $now);
        $plan->launchedAt = $now;

        self::assertFalse($consent->withdraw(9, $now));
        $this->expectException(\InvalidArgumentException::class);
        new GuildCommandConsent($plan, 11, 12, 13, 'another', $now);
    }
}
