<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignProgress, PirateCampaignSequence, PiratePilotCampaignOrder};
use PHPUnit\Framework\TestCase;

final class PirateCampaignSequenceTest extends TestCase
{
    public function testOnlyAnActualVictoryOpensTheNextSignalWhileARealReturnIsPending(): void
    {
        [$viewer, $site, $catalogue, $sequence] = $this->fixture();
        $keys = $catalogue->keys();
        self::assertSame(['current_mission' => $keys[0], 'next_signal' => $keys[0], 'consultable' => []],
            $sequence->forViewer($viewer, [], $this->at(0)));
        $row = $this->mission($viewer, $site, $catalogue, 0);
        $current = ['current_mission' => $keys[0], 'next_signal' => null, 'consultable' => [$keys[0]]];
        self::assertSame($current, $sequence->forViewer($viewer, [$row], $this->at(4)));
        self::assertSame($current, $sequence->forViewer($viewer, [$row], $this->at(19)));
        $attack = $row['attack'];
        $attack->report = null;
        self::assertSame($current, $sequence->forViewer($viewer, [$row], $this->at(20)));
        foreach (['draw', 'defender_wins'] as $nonVictory) {
            $attack->report = ['outcome' => $nonVictory];
            self::assertSame($current, $sequence->forViewer($viewer, [$row], $this->at(20)));
        }

        $attack->report = ['outcome' => 'attacker_wins'];
        $attack->id = null;
        self::assertSame($current, $sequence->forViewer($viewer, [$row], $this->at(20)));
        $attack->id = 100;
        self::assertSame([
            'current_mission' => $keys[1], 'next_signal' => $keys[1], 'consultable' => [$keys[0]],
        ], $sequence->forViewer($viewer, [$row], $this->at(20)));
    }

    public function testTwentyCanonicalKeysDoNotAllowSkippingAndRemainConsultableAfterFinale(): void
    {
        [$viewer, $site, $catalogue, $sequence] = $this->fixture();
        $keys = $catalogue->keys();
        $rows = array_map(fn (int $i): array => $this->mission($viewer, $site, $catalogue, $i), array_keys($keys));
        $nonContiguous = $rows;
        unset($nonContiguous[1]);
        self::assertSame([
            'current_mission' => $keys[1], 'next_signal' => $keys[1], 'consultable' => [$keys[0]],
        ], $sequence->forViewer($viewer, array_reverse($nonContiguous), $this->at(20)));

        self::assertSame([
            'current_mission' => null, 'next_signal' => null, 'consultable' => $keys,
        ], $sequence->forViewer($viewer, array_reverse($rows), $this->at(20)));
        self::assertNull($sequence->forViewer($viewer, [$rows[0], $rows[0]], $this->at(20)));
    }

    public function testForeignEvidenceAndInactiveViewerDoNotLeakHistoryOrUnlockAMission(): void
    {
        [$owner, $site, $catalogue, $sequence] = $this->fixture();
        $row = $this->mission($owner, $site, $catalogue, 0);
        $initial = ['current_mission' => 'balise_17', 'next_signal' => 'balise_17', 'consultable' => []];
        $otherMembership = clone $owner;
        ++$otherMembership->id;
        $otherPlayer = clone $owner;
        $otherPlayer->player = clone $owner->player;
        ++$otherPlayer->player->id;
        $otherUniverse = clone $owner;
        $otherUniverse->universe = clone $owner->universe;
        ++$otherUniverse->universe->id;
        foreach ([$otherMembership, $otherPlayer, $otherUniverse] as $outsider) {
            self::assertSame($initial, $sequence->forViewer($outsider, [$row], $this->at(20)));
        }

        $foreignAttack = $this->mission($owner, $site, $catalogue, 1)['attack'];
        self::assertSame(['current_mission' => 'balise_17', 'next_signal' => null, 'consultable' => ['balise_17']],
            $sequence->forViewer($owner, [['reconnaissance' => $row['reconnaissance'], 'attack' => $foreignAttack]], $this->at(20)));
        $owner->universe->endsAt = $this->at(20);
        self::assertNull($sequence->forViewer($owner, [$row], $this->at(20)));
        $owner->forgottenAt = $this->at(19);
        self::assertNull($sequence->forViewer($owner, [$row], $this->at(19)));
    }

    public function testCommandsOnlyFollowCanonicalVictoryEvenWhenReturnIsStillTraveling(): void
    {
        [$viewer, $site, $catalogue, $sequence] = $this->fixture();
        $order = new PiratePilotCampaignOrder($catalogue, $sequence);
        $first = $this->mission($viewer, $site, $catalogue, 0);
        $later = $this->mission($viewer, $site, $catalogue, 2);
        self::assertNull($order->choose($viewer, [$later], 3, $this->at(22)), 'Une victoire isolée ne permet pas de sauter une mission.');
        self::assertNull($order->choose($viewer, [$first], 2, $this->at(19)), 'Ni l’heure, ni un retour apparent n’ouvrent la mission avant victoire.');
        $next = $order->choose($viewer, [$first], 2, $this->at(21));
        self::assertSame(2, $next['ordinal']);
        self::assertSame($catalogue->keys()[1], $next['key']);
        self::assertTrue($next['isNew'], 'Le renseignement de A ne constitue pas le rapport de B.');
        self::assertSame($first['reconnaissance'], $next['preceding']);
        self::assertNull($order->choose($viewer, [$first], 1, $this->at(21)), 'L’historique ne remet pas le scout ou l’attaque en action.');
        self::assertNull($order->choose($viewer, [$first], 3, $this->at(21)));
    }

    public function testTwentiethVictoryOpensOnlyTheNextCampaign(): void
    {
        [$viewer, $site, $catalogue, $sequence] = $this->fixture();
        $order = new PiratePilotCampaignOrder($catalogue, $sequence);
        $all = array_map(fn ($index) => $this->mission($viewer, $site, $catalogue, $index), range(0, 19));
        $notYet = array_slice($all, 0, 19);
        $last = $order->choose($viewer, $notYet, 20, $this->at(23));
        self::assertSame($catalogue->keys()[19], $last['key']);
        self::assertTrue($last['isNew']);
        self::assertNull($order->choose($viewer, $all, 20, $this->at(23)));
        self::assertSame('t2_balise_17', $order->choose($viewer, $all, 200000001, $this->at(23))['key']);
        self::assertNull($order->choose($viewer, $notYet, 200000001, $this->at(23)));
        self::assertNull($order->choose($viewer, $all, 300000001, $this->at(23)));
        self::assertCount(20, $sequence->forViewer($viewer, $all, $this->at(23))['consultable']);
    }

    /** @return array{PlayerUniverse, SolarSystem, PirateCampaignCatalogue, PirateCampaignSequence} */
    private function fixture(): array
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $catalogue = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $universe = new Universe();
        $universe->id = 2;
        $universe->slug = 'omega-1';
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $site = new SolarSystem();
        $site->id = 3;
        $site->galaxy = $galaxy;
        $viewer = new PlayerUniverse();
        $viewer->id = 4;
        $viewer->player = new Player();
        $viewer->player->id = 5;
        $viewer->universe = $universe;

        return [$viewer, $site, $catalogue, new PirateCampaignSequence($catalogue, new PirateCampaignProgress($catalogue))];
    }

    /** @return array{reconnaissance:PirateReconnaissance, attack:PirateAttackCampaign} */
    private function mission(PlayerUniverse $viewer, SolarSystem $site, PirateCampaignCatalogue $catalogue, int $index): array
    {
        $key = $catalogue->keys()[$index];
        $recon = new PirateReconnaissance($viewer, $viewer->player, $site, $key, $this->at(1), $this->at(5), 'fixture-worker',
            null, $catalogue->prepareForServer($key, $viewer->universe));
        $recon->id = 10 + $index;
        $recon->reportWhenDue($viewer, 'fixture-worker', $this->at(5));
        $attack = new PirateAttackCampaign();
        $attack->id = 100 + $index;
        $attack->reconnaissance = $recon;
        $attack->departedAt = $this->at(6);
        $attack->timeline = [
            'state' => 'returning',
            'resolved' => $this->at(20)->format(DATE_ATOM),
            'returnDue' => $this->at(30)->format(DATE_ATOM),
        ];
        $attack->report = ['outcome' => 'attacker_wins'];

        return ['reconnaissance' => $recon, 'attack' => $attack];
    }

    private function at(int $seconds): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T18:00:00Z'))->modify('+'.$seconds.' seconds');
    }
}
