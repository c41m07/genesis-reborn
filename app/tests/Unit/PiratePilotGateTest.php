<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Player, PlayerUniverse, Universe};
use App\Service\PirateCampaign\PiratePilotGate;
use PHPUnit\Framework\TestCase;

final class PiratePilotGateTest extends TestCase
{
    public function testCampaignIsOnByDefaultForPersistedWorldsWithNoSlugRestriction(): void
    {
        [$player, $membership, $at] = $this->fixture();
        unset($membership->universe->gameSettings['features']);
        $membership->universe->slug = 'frontiere-1';
        $membership->universe->name = 'Univers des joueurs';
        foreach (['dev', 'prod'] as $environment) {
            $gate = new PiratePilotGate($environment);
            self::assertTrue($gate->isAvailable());
            self::assertTrue($gate->matchesWorld($membership->universe, $at));
            self::assertTrue($gate->admits($player, $membership, $at));
        }
        foreach (['alpha', 'test'] as $environment) {
            $gate = new PiratePilotGate($environment);
            self::assertFalse($gate->isAvailable());
            self::assertFalse($gate->admits($player, $membership, $at));
        }
    }

    public function testExplicitOptOutAndUnpersistedWorldCloseAdmission(): void
    {
        [$player, $membership, $at] = $this->fixture();
        $gate = new PiratePilotGate('prod');
        foreach ([false, '0', '1', null] as $setting) {
            $membership->universe->gameSettings['features'][Universe::PIRATE_CAMPAIGN_FEATURE] = $setting;
            self::assertFalse($gate->admits($player, $membership, $at));
        }
        $membership->universe->gameSettings['features'][Universe::PIRATE_CAMPAIGN_FEATURE] = true;
        self::assertTrue($gate->admits($player, $membership, $at));
        $membership->universe->id = null;
        self::assertFalse($gate->matchesWorld($membership->universe, $at));
    }

    public function testUntrustedOwnerOrStaleDatabaseMembershipCannotOpenThePilot(): void
    {
        $gate = new PiratePilotGate('dev');
        foreach (['anonyme', 'sans participation', 'identité divergente', 'ancienne sélection authentifiée',
            'sélection DB remplacée', 'empire oublié', 'non vérifié', 'vérification future', 'verrouillé', 'verrouillé en base',
            'avant ouverture', 'pile à la fermeture', 'univers suspendu'] as $case) {
            [$player, $membership, $at] = $this->fixture();
            switch ($case) {
                case 'anonyme': $player = null; break;
                case 'sans participation': $membership = null; break;
                case 'identité divergente':
                    $membership->player = clone $player;
                    $membership->player->id = 34;
                    break;
                case 'ancienne sélection authentifiée': $player->currentPlayerUniverse = null; break;
                case 'sélection DB remplacée':
                    $membership->player = clone $player;
                    $membership->player->currentPlayerUniverse = null;
                    break;
                case 'empire oublié': $membership->forgottenAt = $at; break;
                case 'non vérifié': $player->emailVerifiedAt = null; break;
                case 'vérification future': $player->emailVerifiedAt = $at->modify('+1 second'); break;
                case 'verrouillé': $player->lockedAt = $at; break;
                case 'verrouillé en base':
                    $membership->player = clone $player;
                    $membership->player->lockedAt = $at;
                    break;
                case 'avant ouverture': $membership->universe->startsAt = $at->modify('+1 second'); break;
                case 'pile à la fermeture': $membership->universe->endsAt = $at; break;
                case 'univers suspendu': $membership->universe->status = 'archived'; break;
            }

            self::assertFalse($gate->admits($player, $membership, $at), $case);
        }
    }

    /** @return array{Player,PlayerUniverse,\DateTimeImmutable} */
    private function fixture(): array
    {
        $at = new \DateTimeImmutable('2026-11-21T22:00:00Z');
        $player = new Player();
        $player->id = 17;
        $player->emailVerifiedAt = $at;
        $membership = new PlayerUniverse();
        $membership->id = 18;
        $membership->player = $player;
        $membership->universe = new Universe();
        $membership->universe->id = 21;
        $membership->universe->slug = 'omega-1';
        $player->currentPlayerUniverse = $membership;

        return [$player, $membership, $at];
    }
}
