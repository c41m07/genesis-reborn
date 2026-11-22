<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateReconnaissance, Planet, Player, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PiratePilotSignal};
use PHPUnit\Framework\TestCase;

final class PiratePilotSignalTest extends TestCase
{
    public function testFirstSignalUsesOnlyTheActuallyDiscoveredActiveSystem(): void
    {
        [$owner, $active, $new, $proof, $old, $history] = $this->fixture();
        $at = new \DateTimeImmutable('2026-11-22T12:00:00Z');
        $signal = PiratePilotSignal::fromEvidence($owner, $active, $new, $proof, null, null, $at);
        self::assertNotNull($signal);
        self::assertTrue($signal->allowsFirstScout);
        self::assertTrue($signal->site->matchesSystem($new));
        self::assertFalse($signal->site->matchesSystem($old->solarSystem));
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $new, null, null, null, $at));
        $proof->solarSystem = $old->solarSystem;
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $old->solarSystem, $proof, null, null, $at));
        $proof->solarSystem = $new;
        $proof->id = null;
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $new, $proof, null, null, $at));
    }

    public function testHistoryKeepsItsOwnedDiscoveredAnchorAndNeverAllowsAnotherScout(): void
    {
        [$owner, $active, $new, $proof, $old, $history] = $this->fixture();
        $at = new \DateTimeImmutable('2026-11-22T12:00:00Z');
        $proof->solarSystem = $old->solarSystem;
        $known = PiratePilotSignal::fromEvidence($owner, $active, $old->solarSystem, $proof, $history, $old, $at);
        self::assertNotNull($known);
        self::assertFalse($known->allowsFirstScout);
        self::assertTrue($known->site->matchesSystem($old->solarSystem));
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $old->solarSystem, $proof, $history, null, $at));

        $activeProof = clone $proof;
        $activeProof->solarSystem = $new;
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $new, $activeProof, $history, $active, $at));
        $old->owner = null;
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $old->solarSystem, $proof, $history, $old, $at));
    }

    public function testExistingLegacyReceiptCannotBecomeAFreshSignal(): void
    {
        [$owner, $active, , $proof, $old, , $legacy] = $this->fixture();
        $at = new \DateTimeImmutable('2026-11-22T12:00:00Z');
        $proof->solarSystem = $old->solarSystem;
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $old->solarSystem, $proof, $legacy, $old, $at));

        $differentSeat = clone $owner;
        $differentSeat->id = 75;
        $proof->playerUniverse = $differentSeat;
        self::assertNull(PiratePilotSignal::fromEvidence($owner, $active, $old->solarSystem, $proof, $legacy, $old, $at));
        $hidden = clone $legacy;
        self::assertNull($hidden->existingSystemFor($differentSeat, $at));
    }

    private function fixture(): array
    {
        $u = new Universe();
        $u->id = 1;
        $u->slug = 'omega-1';
        $g = new Galaxy();
        $g->id = 2;
        $g->universe = $u;
        $owner = new PlayerUniverse();
        $owner->id = 3;
        $owner->universe = $u;
        $owner->player = new Player();
        $owner->player->id = 4;
        $s1 = new SolarSystem();
        $s1->id = 11;
        $s1->galaxy = $g;
        $s2 = new SolarSystem();
        $s2->id = 12;
        $s2->galaxy = $g;
        $active = new Planet();
        $active->id = 21;
        $active->solarSystem = $s1;
        $active->owner = $owner;
        $old = new Planet();
        $old->id = 22;
        $old->solarSystem = $s2;
        $old->owner = $owner;
        $discovery = new PlayerDiscovery();
        $discovery->id = 31;
        $discovery->playerUniverse = $owner;
        $discovery->solarSystem = $s1;
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $campaign = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $plan = $campaign->prepareForServer('balise_17', $u);
        $depart = new \DateTimeImmutable('2026-11-22T11:00:00Z');
        $due = $depart->modify('+30 seconds');
        $current = new PirateReconnaissance($owner, $owner->player, $s2, 'balise_17', $depart, $due,
            bin2hex(random_bytes(14)), false, $plan);
        $current->id = 40;
        $legacy = new PirateReconnaissance($owner, $owner->player, $s2, 'balise_17', $depart, $due,
            bin2hex(random_bytes(14)), false, $plan->encounterForServer());
        $legacy->id = 41;

        return [$owner, $active, $s1, $discovery, $old, $current, $legacy];
    }
}
