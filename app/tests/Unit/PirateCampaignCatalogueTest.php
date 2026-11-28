<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator, CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignMoment, PirateConvoyQuote, PirateDistressSite, PiratePreparedEncounter};
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
            'balise_17' => [['fighter' => ['basic' => 1]], 1440, 780, 0, 0],
            'escorte_interceptee' => [['fighter' => ['basic' => 2], 'interceptor' => ['basic' => 1]], 4424, 2075, 0, 2204],
            'derniere_liaison' => [['bomber' => ['basic' => 1], 'heavy_fighter' => ['basic' => 1]], 7086, 2518, 484, 8186],
        ];
        foreach ($expected as $key => [$ships, $metal, $nano, $antimatter, $hydrogen]) {
            $prepared = $this->campaign->prepareForServer($key, $universe);
            self::assertSame($key, $prepared->definitionKey());
            self::assertSame($ships, $prepared->encounterForServer()->composition());
            self::assertEquals(['metal' => $metal, 'nano' => $nano, 'antimatter' => $antimatter, 'hydrogen' => $hydrogen], $prepared->encounterForServer()->buildCosts());
            $battle = $prepared->battleForServer()->snapshot();
            self::assertSame($this->catalogue->rules()['max_combat_rounds'], $battle['rounds']);
            self::assertSame((float) $this->catalogue->rules()['shield_recharge_percent_per_round'], $battle['recharge']);
            self::assertSame($this->catalogue->all('combat_modifiers')['modifiers'], $battle['modifiers']);
            self::assertSame('{}', json_encode($prepared, JSON_THROW_ON_ERROR));
        }
    }

    public function testAllTwentyDefinitionsRoundTripWithExactBasicFleetsAndNoRewardCap(): void
    {
        $expected = [
            'balise_17' => ['fighter' => 1],
            'escorte_interceptee' => ['fighter' => 2, 'interceptor' => 1],
            'derniere_liaison' => ['heavy_fighter' => 1, 'bomber' => 1],
            'balises_volees' => ['corvette' => 1, 'fighter' => 2],
            'porteurs_de_brume' => ['fighter' => 2, 'interceptor' => 2, 'stealth_fighter' => 1],
            'refuge_sous_escorte' => ['frigate' => 1, 'interceptor' => 2],
            'noeud_fausses_routes' => ['corvette' => 2, 'stealth_fighter' => 2],
            'corridor_des_ombres' => ['frigate' => 2, 'stealth_fighter' => 1, 'fighter' => 2],
            'briseur_de_relais' => ['destroyer' => 1, 'frigate' => 1, 'fighter' => 2],
            'couper_le_commandement' => ['cruiser' => 1, 'frigate' => 1],
            'secours_en_tenaille' => ['destroyer' => 2, 'frigate' => 1, 'interceptor' => 2],
            'cuirasse_de_la_passe' => ['battleship' => 1, 'frigate' => 1, 'fighter' => 2],
            'deux_croiseurs_du_verrou' => ['cruiser' => 2, 'stealth_fighter' => 1, 'corvette' => 2],
            'arsenal_des_exiles' => ['battleship' => 2, 'destroyer' => 1, 'interceptor' => 2],
            'signal_noye' => ['battleship' => 2, 'cruiser' => 1, 'frigate' => 2, 'stealth_fighter' => 2],
            'bastion_des_faussaires' => ['battleship' => 3, 'destroyer' => 2, 'frigate' => 2],
            'premier_capital_noir' => ['capital' => 1, 'battleship' => 1, 'cruiser' => 1, 'frigate' => 2, 'destroyer' => 1, 'corvette' => 1],
            'cuirasse_et_son_ecran' => ['capital' => 1, 'battleship' => 1, 'frigate' => 4, 'stealth_fighter' => 2, 'destroyer' => 2],
            'deux_ombres_sur_orbite' => ['capital' => 2, 'frigate' => 2, 'destroyer' => 2],
            'la_nuit_se_brise' => ['capital' => 2, 'battleship' => 1, 'cruiser' => 1, 'frigate' => 2, 'stealth_fighter' => 2],
        ];
        self::assertCount(20, $this->campaign->keys());
        self::assertSame(array_keys($expected), $this->campaign->keys());
        $chapters = $this->campaign->chapters();
        self::assertSame([3, 5, 5, 3, 4], array_map(static fn (array $chapter): int => count($chapter['keys']), $chapters));
        self::assertSame(array_keys($expected), array_merge(...array_column($chapters, 'keys')));
        self::assertCount(5, array_unique(array_column($chapters, 'title')));

        $u = $this->universe();
        $engine = new CombatSimulator();
        $testEscort = $this->units->ships($u, ['fighter' => ['basic' => 2]], []);
        $families = [];
        $last = 0;
        foreach ($expected as $key => $flat) {
            $prepared = $this->campaign->prepareForServer($key, $u);
            $scout = $prepared->encounterForServer();
            self::assertEquals(array_map(static fn (int $n): array => ['basic' => $n], $flat), $scout->composition(), $key);
            $families = array_merge($families, array_keys($scout->composition()));
            $restored = PiratePreparedEncounter::restore($scout, $prepared->checkpoint());
            self::assertSame($key, $restored->definitionKey());
            self::assertSame($prepared->battleForServer()->snapshot(), $restored->battleForServer()->snapshot());
            $native = $restored->battleForServer()->resolve($engine, $testEscort, 31);
            self::assertContains($native['outcome'], ['attacker_wins', 'defender_wins', 'draw']);

            $costs = $scout->buildCosts();
            // Les recettes RP déplacent les matériaux sans changer le budget pondéré du dossier.
            $hullValue = \App\Service\Balance\CostDistributionCandidate::value($costs);
            self::assertGreaterThan($last, $hullValue, 'La valeur de coque progresse quel que soit son mélange de matériaux.');
            $last = $hullValue;
            $half = PirateConvoyQuote::halfOfFrozenCatalogue($scout)->exactParts();
            foreach ($half as $resource => $part) {
                self::assertSame($costs[$resource], 2 * $part['whole'] + (int) $part['extra_half']);
            }
        }
        self::assertEqualsCanonicalizing(
            ['fighter', 'interceptor', 'heavy_fighter', 'bomber', 'corvette', 'stealth_fighter', 'frigate', 'destroyer', 'cruiser', 'battleship', 'capital'],
            array_values(array_unique($families)),
        );
        $finale = $this->campaign->prepareForServer('la_nuit_se_brise', $u)->encounterForServer();
        self::assertSame(2, $finale->composition()['capital']['basic']);
        self::assertSame(
            [
                'metal' => ['whole' => 120026, 'extra_half' => false], 'nano' => ['whole' => 42941, 'extra_half' => false],
                'antimatter' => ['whole' => 16423, 'extra_half' => true], 'hydrogen' => ['whole' => 75424, 'extra_half' => false],
            ],
            PirateConvoyQuote::halfOfFrozenCatalogue($finale)->exactParts(),
        );
    }

    public function testEveryEarlySignalAndTitleKeepTheThreatAndAdvicePrivate(): void
    {
        [$owner, $site] = $this->ownerAndSite();
        foreach ($this->campaign->keys() as $key) {
            foreach ([PirateCampaignMoment::Signal, PirateCampaignMoment::ReconnaissanceEnRoute] as $moment) {
                $preview = $this->campaign->narrativeFor($key, $site, $owner, $moment);
                self::assertSame(['title', 'message', 'guidance'], array_keys($preview));
                self::assertDoesNotMatchRegularExpression(
                    '/chasseur|intercepteur|bombardier|corvette|frégate|destroyer|croiseur|cuirassé|capital|furtif/ui',
                    implode(' ', $preview),
                    $key,
                );
            }
            $intel = $this->campaign->narrativeFor($key, $site, $owner, PirateCampaignMoment::RenseignementRecu);
            self::assertNotEmpty($intel['guidance']);
            self::assertDoesNotMatchRegularExpression('/[0-9]+\\s*(%|destroyers?|chasseurs?|bombardiers?|intercepteurs?)/ui', $intel['guidance']);
        }
        $firstCapital = $this->campaign->narrativeFor('premier_capital_noir', $site, $owner, PirateCampaignMoment::RenseignementRecu);
        self::assertSame('Le premier capital noir', $firstCapital['title']);
        self::assertStringContainsString('croiseurs et cuirassés', $firstCapital['guidance']);
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

    public function testCustomMissionKeepsItsUniverseAndFrozenReward(): void
    {
        $world = $this->universe();
        $other = clone $world;
        $entry = $this->campaign->definition('balise_17');
        $entry['reward'] = ['metal'=>1000,'nano'=>50,'hydrogen'=>0,'antimatter'=>0];
        $world->gameSettings['pirate_missions']['custom_dock'] = $entry;
        self::assertNotContains('custom_dock',$this->campaign->keys($other));
        self::assertContains('custom_dock',$this->campaign->keys($world));
        $prepared = $this->campaign->prepareForServer('custom_dock',$world);
        $world->gameSettings['pirate_missions']['custom_dock']['reward']['metal'] = 999;
        $restored = PiratePreparedEncounter::restore($prepared->encounterForServer(),$prepared->checkpoint());
        self::assertSame(1000,$restored->checkpoint()['reward']['metal']['whole']);
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
