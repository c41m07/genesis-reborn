<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignProgress, PiratePreparedEncounter};
use PHPUnit\Framework\TestCase;

final class PirateCampaignProgressTest extends TestCase
{
    public function testOnlyFactsWithEffectiveTimesYieldReconnaissanceAndVictory(): void
    {
        [$owner, $recon, $attack, $progress] = $this->fixture();
        $attack->departedAt = $this->at(34);
        $attack->timeline = ['state' => 'won', 'resolved' => $this->at(75)->format(DATE_ATOM)];
        $attack->report = ['outcome' => 'attacker_wins'];
        $attack->reservedShips = [];

        $early = $progress->forViewer($owner, $recon, $attack, $this->at(20));
        self::assertSame('ReconnaissanceEnRoute', $early['phase']);
        self::assertNull($early['composition']);
        $recon->reportWhenDue($owner, 'test-only-temporary-token', $this->at(32));
        self::assertNull($progress->forViewer($owner, $recon, $attack, $this->at(31))['composition']);

        $scout = $progress->forViewer($owner, $recon, $attack, $this->at(32));
        self::assertSame('RenseignementRecu', $scout['phase']);
        self::assertSame(['fighter' => ['basic' => 12], 'interceptor' => ['basic' => 10], 'stealth_fighter' => ['basic' => 8]], $scout['composition']);
        self::assertSame('AttaqueEngagee', $progress->forViewer($owner, $recon, $attack, $this->at(74))['phase']);
        $victory = $progress->forViewer($owner, $recon, $attack, $this->at(75));
        self::assertSame('Victoire', $victory['phase']);
        self::assertStringContainsString('aucune ressource n’est encore livrée', $victory['narrative']['guidance']);
        self::assertNull($victory['return_narrative']);
    }

    public function testMismatchedAttackAndOtherViewerCannotSupplyProgressEvidence(): void
    {
        [$owner, $recon, $attack, $progress] = $this->fixture();
        [, $otherMission] = $this->fixture();
        $otherMission->id = 99;
        $attack->reconnaissance = $otherMission;
        $attack->timeline = ['state' => 'won', 'resolved' => $this->at(70)->format(DATE_ATOM)];
        $attack->report = ['outcome' => 'attacker_wins'];
        $attack->departedAt = $this->at(32);
        $recon->reportWhenDue($owner, 'test-only-temporary-token', $this->at(32));

        $outsider = clone $owner;
        $outsider->player = new Player();
        $outsider->player->id = 101;
        self::assertNull($progress->forViewer($outsider, $recon, $attack, $this->at(71)));
        self::assertSame('RenseignementRecu', $progress->forViewer($owner, $recon, $attack, $this->at(71))['phase']);
    }

    public function testNoSurvivorsLoreForEmptyOrBlockedReturn(): void
    {
        [$owner, $recon, $attack, $progress] = $this->fixture();
        $recon->reportWhenDue($owner, 'test-only-temporary-token', $this->at(32));
        $attack->timeline = ['state' => 'returning', 'resolved' => $this->at(72)->format(DATE_ATOM)];
        $attack->report = ['outcome' => 'defender_wins'];
        $attack->departedAt = $this->at(34);
        $attack->reservedShips = [];
        $failed = $progress->forViewer($owner, $recon, $attack, $this->at(74));
        self::assertSame('Defaite', $failed['phase']);
        self::assertNull($failed['return_narrative']);
        $attack->reservedShips = ['fighter' => ['basic' => 1]];
        $return = $progress->forViewer($owner, $recon, $attack, $this->at(74));
        self::assertStringContainsString('survivants', $return['return_narrative']['message']);
        $attack->returnBlocked = true;
        self::assertNull($progress->forViewer($owner, $recon, $attack, $this->at(74))['return_narrative']);
    }

    public function testScenarioCannotBePairedWithADifferentRevealedFormation(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $campaign = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $u = new Universe();
        $u->slug = 'omega-1';
        $prepared = $campaign->prepareForServer('balise_17', $u);
        $other = $campaign->prepareForServer('derniere_liaison', $u);
        $this->expectException(\DomainException::class);
        new PiratePreparedEncounter('balise_17', $prepared->encounterForServer(), $other->battleForServer());
    }

    /** @return array{PlayerUniverse, PirateReconnaissance, PirateAttackCampaign, PirateCampaignProgress} */
    private function fixture(): array
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $catalogue = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $u = new Universe();
        $u->slug = 'omega-1';
        $u->id = 10;
        $g = new Galaxy();
        $g->universe = $u;
        $s = new SolarSystem();
        $s->id = 20;
        $s->galaxy = $g;
        $owner = new PlayerUniverse();
        $owner->id = 21;
        $owner->universe = $u;
        $owner->player = new Player();
        $owner->player->id = 22;
        $recon = new PirateReconnaissance($owner, $owner->player, $s, 'fixture',
            $this->at(1), $this->at(31), 'test-only-temporary-token', false, $catalogue->prepareForServer('balise_17', $u));
        $recon->id = 30;
        $attack = new PirateAttackCampaign();
        $attack->reconnaissance = $recon;

        return [$owner, $recon, $attack, new PirateCampaignProgress($catalogue)];
    }

    private function at(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T09:00:00Z'))->modify('+'.$offset.' seconds');
    }
}
