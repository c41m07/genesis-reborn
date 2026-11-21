<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator, CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignMoment, PirateDistressSite};
use PHPUnit\Framework\TestCase;

final class PirateCampaignCatalogueTest extends TestCase
{
    private GameCatalog $catalogue;
    private PirateCampaignCatalogue $campaign;
    private CombatUnitFactory $units;

    protected function setUp(): void
    {
        $this->catalogue = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $this->units = new CombatUnitFactory($this->catalogue, new TechnologyEffectCalculator($this->catalogue));
        $this->campaign = new PirateCampaignCatalogue($this->catalogue, $this->units);
    }

    public function testTheThreePreparedEncountersUseTheCalibratedCatalogueAndDoNotSerialize(): void
    {
        $universe = $this->universe();
        $keys = $this->campaign->keys();
        self::assertSame(['balise_17', 'escorte_interceptee', 'derniere_liaison'], array_slice($keys, 0, 3));
        self::assertSame($keys, array_values(array_unique($keys)));
        $expected = [
            'balise_17' => [['fighter' => ['basic' => 1]], 1440, 780],
            'escorte_interceptee' => [['fighter' => ['basic' => 2], 'interceptor' => ['basic' => 1]], 4644, 2516],
            'derniere_liaison' => [['bomber' => ['basic' => 1], 'heavy_fighter' => ['basic' => 1]], 8713, 4719],
        ];
        foreach ($expected as $key => [$ships, $metal, $nano]) {
            $prepared = $this->campaign->prepareForServer($key, $universe);
            self::assertSame($key, $prepared->definitionKey());
            self::assertSame($ships, $prepared->encounterForServer()->composition());
            self::assertEquals(['metal' => $metal, 'nano' => $nano, 'antimatter' => 0, 'hydrogen' => 0], $prepared->encounterForServer()->buildCosts());
            $battle = $prepared->battleForServer()->snapshot();
            self::assertSame($this->catalogue->rules()['max_combat_rounds'], $battle['rounds']);
            self::assertSame((float) $this->catalogue->rules()['shield_recharge_percent_per_round'], $battle['recharge']);
            self::assertSame($this->catalogue->all('combat_modifiers')['modifiers'], $battle['modifiers']);
            self::assertSame('{}', json_encode($prepared, JSON_THROW_ON_ERROR));
        }
    }

    public function testFrozenEncounterDoesNotFollowLaterCatalogueChanges(): void
    {
        $universe = $this->universe();
        $prepared = $this->campaign->prepareForServer('balise_17', $universe);
        $first = $prepared->battleForServer()->snapshot();
        $universe->gameSettings['catalog_overrides']['ships']['fighter'] = [
            'cost_profiles' => ['basic' => ['metal' => 2222]],
            'base_attack' => 700,
        ];
        $fresh = $this->campaign->prepareForServer('balise_17', $universe);

        self::assertSame(1440, $prepared->encounterForServer()->buildCosts()['metal']);
        self::assertSame(2222, $fresh->encounterForServer()->buildCosts()['metal']);
        self::assertSame($first, $prepared->battleForServer()->snapshot());
        self::assertNotSame($first['units'][0]['base_attack'], $fresh->battleForServer()->snapshot()['units'][0]['base_attack']);
    }

    public function testBaliseUsesTwoPatrolFightersAsTheCalibratedStartingPoint(): void
    {
        $u = $this->universe();
        $prepared = $this->campaign->prepareForServer('balise_17', $u);
        $engine = new CombatSimulator();
        $escort = $this->units->ships($u, ['fighter' => ['basic' => 2]], []);
        for ($seed = 1; $seed <= 32; ++$seed) {
            $result = $prepared->battleForServer()->resolve($engine, $escort, $seed);
            self::assertSame('attacker_wins', $result['outcome']);
            self::assertSame(['pirates:fighter' => 1], $result['losses']);
        }
    }

    public function testLaterEncountersIntroduceLossesAndRewardARealCounter(): void
    {
        $u = $this->universe();
        $engine = new CombatSimulator();
        $middle = $this->campaign->prepareForServer('escorte_interceptee', $u)->battleForServer();
        $last = $this->campaign->prepareForServer('derniere_liaison', $u)->battleForServer();
        $patrols = $this->units->ships($u, ['fighter' => ['basic' => 4]], []);
        $mixed = $this->units->ships($u, ['fighter' => ['basic' => 3], 'interceptor' => ['basic' => 2]], [
            'vector_propulsion' => ['basic' => 1],
            'weapon_calibration' => ['basic' => 1],
        ]);
        $middleWithLoss = $mixedVictories = 0;
        for ($seed = 1; $seed <= 32; ++$seed) {
            $middleResult = $middle->resolve($engine, $patrols, $seed);
            self::assertSame('attacker_wins', $middleResult['outcome']);
            $middleWithLoss += (int) isset($middleResult['losses']['escort:fighter']);

            $badMatch = $last->resolve($engine, $patrols, $seed);
            self::assertSame(4, $badMatch['losses']['escort:fighter']);
            $counter = $last->resolve($engine, $mixed, $seed);
            $mixedVictories += (int) ($counter['outcome'] === 'attacker_wins');
        }
        self::assertGreaterThan(0, $middleWithLoss);
        self::assertGreaterThanOrEqual(28, $mixedVictories);
    }

    public function testNarrationIsPrivateAndDoesNotRevealFormationBeforeReport(): void
    {
        [$owner, $site] = $this->ownerAndSite();
        $opening = $this->campaign->narrativeFor('balise_17', $site, $owner, PirateCampaignMoment::Signal);
        self::assertSame('Le silence de Balise 17', $opening['title']);
        self::assertStringContainsString('coupées', $opening['message']);
        self::assertStringContainsString('sera perdue', $opening['guidance']);

        $outsider = clone $owner;
        $outsider->id = 42;
        self::assertNull($this->campaign->narrativeFor('balise_17', $site, $outsider, PirateCampaignMoment::Signal));
        // Même compte, autre univers : aucune projection de cette participation.
        $elsewhere = clone $owner;
        $elsewhere->universe = $this->universe();
        $elsewhere->universe->id = 90;
        self::assertNull($this->campaign->narrativeFor('balise_17', $site, $elsewhere, PirateCampaignMoment::Victoire));
        $owner->forgottenAt = new \DateTimeImmutable();
        self::assertNull($this->campaign->narrativeFor('balise_17', $site, $owner, PirateCampaignMoment::Signal));

        [$owner, $site] = $this->ownerAndSite();
        foreach ([PirateCampaignMoment::Signal, PirateCampaignMoment::ReconnaissanceEnRoute] as $moment) {
            $view = $this->campaign->narrativeFor('derniere_liaison', $site, $owner, $moment);
            self::assertSame(['title', 'message', 'guidance'], array_keys($view));
            self::assertStringNotContainsString('intercepteur', json_encode($view, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('bomber', json_encode($view, JSON_THROW_ON_ERROR));
            self::assertStringNotContainsString('Nom du système', $view['message']);
        }
        $intel = $this->campaign->narrativeFor('derniere_liaison', $site, $owner, PirateCampaignMoment::RenseignementRecu);
        self::assertStringContainsString('relevé est complet', $intel['message']);
        self::assertStringContainsString('intercepteurs', $intel['guidance']);
        self::assertArrayNotHasKey('composition', $intel);
    }

    public function testOnlyTheProvenReturnStateMentionsSurvivorsAndVictoryDoesNotConfirmPayment(): void
    {
        [$owner, $site] = $this->ownerAndSite();
        foreach ([PirateCampaignMoment::Defaite, PirateCampaignMoment::IssueIndecise] as $moment) {
            $defeat = $this->campaign->narrativeFor('escorte_interceptee', $site, $owner, $moment);
            self::assertStringContainsString('relevé reste valable', $defeat['message']);
            self::assertStringContainsString('renvoyer', $defeat['guidance']);
            self::assertStringNotContainsString('survivant', $defeat['message']);
        }
        $return = $this->campaign->narrativeFor('escorte_interceptee', $site, $owner, PirateCampaignMoment::RetourDesSurvivants);
        self::assertStringContainsString('survivants', $return['message']);
        $victory = $this->campaign->narrativeFor('escorte_interceptee', $site, $owner, PirateCampaignMoment::Victoire);
        self::assertStringContainsString('aucune ressource n’est encore livrée', $victory['guidance']);
        self::assertArrayNotHasKey('amount', $victory);
        self::assertArrayNotHasKey('arrival_at', $victory);
    }

    public function testUnknownDefinitionDoesNotFallbackToASurpriseEncounter(): void
    {
        $this->expectException(\DomainException::class);
        $this->campaign->prepareForServer('quatrieme', $this->universe());
    }

    private function universe(): Universe
    {
        $u = new Universe();
        $u->slug = 'omega-1';
        $u->id = 17;

        return $u;
    }

    /** @return array{PlayerUniverse, PirateDistressSite} */
    private function ownerAndSite(): array
    {
        $u = $this->universe();
        $g = new Galaxy();
        $g->universe = $u;
        $s = new SolarSystem();
        $s->id = 20;
        $s->name = 'Nom du système';
        $s->galaxy = $g;
        $owner = new PlayerUniverse();
        $owner->id = 21;
        $owner->universe = $u;
        $owner->player = new Player();
        $owner->player->id = 22;

        return [$owner, PirateDistressSite::inExistingSystem($owner, $s)];
    }
}
