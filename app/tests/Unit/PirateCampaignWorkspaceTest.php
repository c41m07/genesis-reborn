<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignPresenter, PirateCampaignProgress, PirateCampaignSequence, PirateCampaignWorkspace};
use PHPUnit\Framework\TestCase;

final class PirateCampaignWorkspaceTest extends TestCase
{
    public function testFirstSignalShowsOnlyCivilCopyAndHidesLockedDossiers(): void
    {
        [$viewer, , , $workspace] = $this->fixture();
        $dossier = $workspace->forViewer($viewer, [], $this->at(0));
        self::assertSame(20, $dossier['mission_count']);
        self::assertSame(1, $dossier['current']);
        self::assertSame(1, $dossier['selected']);
        self::assertSame([], $dossier['history']);
        self::assertTrue($dossier['signal_pending']);
        self::assertNull($dossier['briefing']);
        self::assertSame('Le silence de Balise 17', $dossier['signal_copy']['title']);
        self::assertSame([
            'number' => 1,
            'title' => 'Les appels interrompus',
            'missions' => [
                ['number' => 1, 'title' => 'Le silence de Balise 17', 'state' => 'current'],
                ['number' => 2, 'title' => null, 'state' => 'locked'],
                ['number' => 3, 'title' => null, 'state' => 'locked'],
            ],
        ], $dossier['chapter']);
        foreach ([2, 20, 21, 0, PHP_INT_MIN] as $invalid) {
            self::assertNull($workspace->forViewer($viewer, [], $this->at(0), $invalid));
        }
    }

    public function testHistoricalMissionCanBeReadWithoutReplacingActiveSignalOrLeakingFutureData(): void
    {
        [$viewer, $site, $catalogue, $workspace] = $this->fixture();
        $first = $this->row($viewer, $site, $catalogue, 0);
        $future = $this->row($viewer, $site, $catalogue, 2);
        $conditions = [$future, $first];
        $during = $workspace->forViewer($viewer, $conditions, $this->at(4));
        self::assertSame(1, $during['current']);
        self::assertFalse($during['signal_pending']);
        self::assertNull($during['briefing']['formation']);

        $active = $workspace->forViewer($viewer, $conditions, $this->at(10));
        self::assertSame(20, $active['mission_count']);
        self::assertSame(2, $active['current']);
        self::assertSame(2, $active['selected']);
        self::assertSame([['number' => 1, 'won' => true]], $active['history']);
        self::assertTrue($active['signal_pending']);
        self::assertNull($active['briefing']);
        self::assertSame([
            ['number' => 1, 'title' => 'Le silence de Balise 17', 'state' => 'won'],
            ['number' => 2, 'title' => 'L’escorte interceptée', 'state' => 'current'],
            ['number' => 3, 'title' => null, 'state' => 'locked'],
        ], $active['chapter']['missions']);
        $old = $workspace->forViewer($viewer, $conditions, $this->at(10), 1);
        self::assertSame(1, $old['selected']);
        self::assertSame(2, $old['current']);
        self::assertSame('Victoire', $old['briefing']['status']);
        self::assertNotEmpty($old['briefing']['formation']);
        self::assertNull($workspace->forViewer($viewer, $conditions, $this->at(10), 3));
        self::assertStringNotContainsString('system-fixture-private-27', json_encode($old, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('fixture-message', json_encode($old, JSON_THROW_ON_ERROR));
    }

    public function testRowsCannotServeAnotherEmpireOrBypassAmbiguousHistory(): void
    {
        [$viewer, $site, $catalogue, $workspace] = $this->fixture();
        $row = $this->row($viewer, $site, $catalogue, 0);
        $outsider = clone $viewer;
        $outsider->id = 99;
        $foreignWorld = clone $viewer;
        $foreignWorld->universe = clone $viewer->universe;
        $foreignWorld->universe->id = 10;
        foreach ([$outsider, $foreignWorld] as $other) {
            self::assertSame($workspace->forViewer($other, [], $this->at(10)),
                $workspace->forViewer($other, [$row], $this->at(10)));
            self::assertNull($workspace->forViewer($other, [$row], $this->at(10), 2));
        }
        self::assertNull($workspace->forViewer($viewer, [$row, $row], $this->at(10)));
        $viewer->universe->endsAt = $this->at(10);
        self::assertNull($workspace->forViewer($viewer, [$row], $this->at(10)));
    }

    public function testAfterFinaleEveryHistoricalResultRemainsSelectable(): void
    {
        [$viewer, $site, $catalogue, $workspace] = $this->fixture();
        $rows = array_map(fn (int $i): array => $this->row($viewer, $site, $catalogue, $i), range(0, 19));
        $second = $workspace->forViewer($viewer, $rows, $this->at(10));
        self::assertSame(2, $second['campaign_tier']);
        self::assertSame(200000001, $second['current']);
        self::assertSame(200000000, $second['mission_offset']);
        self::assertNull($workspace->forViewer($viewer, $rows, $this->at(10), 300000001));
        $custom = 'mission_legacy';
        $viewer->universe->gameSettings['pirate_missions'][$custom] =
            ['starts' => null, 'ends' => null] + $catalogue->definition('balise_17');
        $customDossier = $workspace->forViewer($viewer, $rows, $this->at(10), 21);
        self::assertSame(0, $customDossier['campaign_tier']);
        self::assertSame(21, $customDossier['current']);
        $main = $workspace->forViewer($viewer, $rows, $this->at(10));
        self::assertSame(21, $main['editorial_mission']);
        self::assertSame($second['current'], $main['current']);
        for ($index = 20; $index < 60; ++$index) {
            $rows[] = $this->row($viewer, $site, $catalogue, $index);
        }
        $ending = $workspace->forViewer($viewer, $rows, $this->at(10));
        self::assertNull($ending['current']);
        self::assertSame(300000020, $ending['selected']);
        self::assertCount(60, $ending['history']);
        self::assertSame(5, $ending['chapter']['number']);
        self::assertSame(['won'], array_values(array_unique(array_column($ending['chapter']['missions'], 'state'))));
        self::assertSame(1, $workspace->forViewer($viewer, $rows, $this->at(10), 1)['selected']);
    }

    /** @return array{PlayerUniverse,SolarSystem,PirateCampaignCatalogue,PirateCampaignWorkspace} */
    private function fixture(): array
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $catalogue = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $universe = new Universe();
        $universe->slug = 'omega-1';
        $universe->id = 22;
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $site = new SolarSystem();
        $site->id = 27;
        $site->galaxy = $galaxy;
        $site->name = 'system-fixture-private-27';
        $viewer = new PlayerUniverse();
        $viewer->id = 31;
        $viewer->universe = $universe;
        $viewer->player = new Player();
        $viewer->player->id = 33;
        $progress = new PirateCampaignProgress($catalogue);

        return [
            $viewer, $site, $catalogue, new PirateCampaignWorkspace(
                $catalogue, new PirateCampaignSequence($catalogue, $progress), new PirateCampaignPresenter($catalogue, $progress, $catalog)),
        ];
    }

    /** @return array{reconnaissance:PirateReconnaissance,attack:PirateAttackCampaign} */
    private function row(PlayerUniverse $viewer, SolarSystem $site, PirateCampaignCatalogue $catalogue, int $index): array
    {
        $key = $catalogue->keys()[$index];
        $scout = new PirateReconnaissance($viewer, $viewer->player, $site, $key, $this->at(1), $this->at(5),
            'fixture-message', null, $catalogue->prepareForServer($key, $viewer->universe));
        $scout->id = 100 + $index;
        $scout->reportWhenDue($viewer, 'fixture-message', $this->at(5));
        $attack = new PirateAttackCampaign();
        $attack->id = 200 + $index;
        $attack->reconnaissance = $scout;
        $attack->departedAt = $this->at(6);
        $attack->arrivalAt = $this->at(7);
        $attack->timeline = ['state' => 'won', 'resolved' => $this->at(10)->format(DATE_ATOM)];
        $attack->report = ['outcome' => 'attacker_wins'];

        return ['reconnaissance' => $scout, 'attack' => $attack];
    }

    private function at(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T08:00:00Z'))->modify('+'.$offset.' seconds');
    }
}
