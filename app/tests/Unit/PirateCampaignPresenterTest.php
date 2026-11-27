<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignPresenter, PirateCampaignProgress, PirateDistressSite};
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class PirateCampaignPresenterTest extends TestCase
{
    public function testAllTwentyPrivateBriefingsAndRenderedSignalHaveNoRouteOrIntelBeforeReport(): void
    {
        [$player, $site, $catalogue, , , $presenter] = $this->fixture();
        foreach ($catalogue->keys() as $index => $key) {
            $signal = $presenter->forSignal($player, $site, $key, $this->at(1));
            self::assertSame($index + 1, $signal['mission_number']);
            self::assertSame([3, 5, 5, 3, 4], array_column($signal['chapters'], 'count'));
            self::assertCount(1, array_filter(array_column($signal['chapters'], 'current')));
            self::assertNull($signal['formation']);
            self::assertNull($signal['quote']);
            self::assertNull($signal['tactical_guidance']);
            self::assertStringNotContainsString($key, json_encode($signal, JSON_THROW_ON_ERROR));
        }

        $finale = $this->render($signal);
        self::assertStringContainsString($signal['narrative']['title'], $finale);
        self::assertStringContainsString('une seule sonde basique', $finale);
        self::assertStringContainsString('Elle sera perdue', $finale);
        self::assertStringNotContainsString('Flotte pirate révélée', $finale);
        self::assertStringNotContainsString('Estimation', $finale);
        self::assertStringNotContainsString('PIRATE_PRIVATE_777', $finale);
        self::assertDoesNotMatchRegularExpression('/<(?:button|a|form|input)\b/', $finale);
    }

    public function testActualReportNotEstimatedArrivalRevealsFrozenPriceAndQualitativeAdvice(): void
    {
        [$player, , , $recon, , $presenter] = $this->fixture(true);
        $early = $presenter->forProgress($player, $recon, null, $this->at(20));
        self::assertNull($early['formation']);
        self::assertNull($early['quote']);
        self::assertStringContainsString('Arrivée prévue de la sonde', $this->render($early));
        self::assertStringContainsString('datetime="2026-11-21T09:00:31+00:00"', $this->render($early));

        $late = $presenter->forProgress($player, $recon, null, $this->at(35));
        self::assertStringContainsString('le rapport n’a pas encore été remis', $this->render($late));
        self::assertNull($late['quote']);
        $recon->reportWhenDue($player, 'fixture-pirate-job', $this->at(36));
        self::assertNull($presenter->forProgress($player, $recon, null, $this->at(35))['quote']);

        $reported = $presenter->forProgress($player, $recon, null, $this->at(36));
        self::assertSame([1, 1], array_column($reported['formation'], 'quantity'));
        self::assertSame(['17 071,5', '6 539,5', '1 482,5', '11 484'], array_column($reported['quote'], 'amount'));
        $html = $this->render($reported);
        self::assertStringContainsString('Flotte pirate révélée', $html);
        self::assertStringContainsString('Un destroyer menace particulièrement un croiseur', $html);
        self::assertStringContainsString('50 % du coût catalogue', $html);
        self::assertStringContainsString('Ce montant n’a pas été versé', $html);
        self::assertStringContainsString('+17 071,5', $html);
        self::assertStringContainsString('des vaisseaux en réserve', $html);
        self::assertStringContainsString('de l’électricité', $html);
        self::assertStringContainsString('vous recevrez une boîte de ressources dans votre inventaire', $html);
        self::assertStringNotContainsString('fixture-pirate-job', $html);
    }

    public function testEscorteLossesAndReturnFollowPersistedEvidenceForEachOutcome(): void
    {
        [$player, , , $recon, $attack, $presenter] = $this->fixture();
        $recon->reportWhenDue($player, 'fixture-pirate-job', $this->at(32));
        $attack->departedAt = $this->at(40);
        $attack->arrivalAt = $this->at(60);
        $attack->timeline = [
            'state' => 'returning',
            'combatDue' => $this->at(80)->format(DATE_ATOM),
            'resolved' => $this->at(82)->format(DATE_ATOM),
            'returnDue' => $this->at(102)->format(DATE_ATOM),
        ];
        $attack->report = ['outcome' => 'defender_wins', 'losses' => ['escort:fighter' => 2, 'pirates:frigate' => 3]];
        $attack->reservedShips = ['fighter' => ['basic' => 1]];

        self::assertSame([], $this->labelsAfterFlag($presenter->forProgress($player, $recon, $attack, $this->at(39))));
        $outbound = $presenter->forProgress($player, $recon, $attack, $this->at(45));
        self::assertStringContainsString('Votre escorte est en route', $this->render($outbound));
        self::assertSame([
            'Départ de l’escorte', 'Arrivée prévue de l’escorte', 'Échéance prévue du combat',
        ], $this->labelsAfterFlag($outbound));
        self::assertNull($presenter->forProgress($player, $recon, $attack, $this->at(81))['losses']);

        foreach (['defender_wins' => 'Défaite', 'draw' => 'Combat sans vainqueur', 'attacker_wins' => 'Victoire'] as $outcome => $status) {
            $attack->report['outcome'] = $outcome;
            $view = $presenter->forProgress($player, $recon, $attack, $this->at(82));
            self::assertSame($status, $view['status']);
            self::assertSame([2], array_column($view['losses'], 'quantity'));
            self::assertSame([1, 1], array_column($view['formation'], 'quantity'));
            self::assertStringContainsString('Ces pertes sont définitives', $this->render($view));
            self::assertContains('Retour prévu des survivants', array_column($view['dates'], 'label'));
            if ($outcome !== 'attacker_wins') {
                self::assertMatchesRegularExpression('~(?:pas besoin de|sans) renvoyer (?:une|de) sonde~', $view['narrative']['guidance']);
                self::assertStringContainsString('relevé reste valable', $view['narrative']['message']);
            } else {
                self::assertStringContainsString('aucune ressource n’est encore livrée', $view['narrative']['guidance']);
            }
        }

        self::assertSame('L’heure de retour prévue est passée. Le retour n’a pas encore été confirmé.',
            $presenter->forProgress($player, $recon, $attack, $this->at(104))['return']);
        $attack->returnBlocked = true;
        $blocked = $presenter->forProgress($player, $recon, $attack, $this->at(105));
        self::assertSame('Le retour des survivants n’a pas été confirmé.', $blocked['return']);
        self::assertNotContains('Retour prévu des survivants', array_column($blocked['dates'], 'label'));
        $attack->returnBlocked = false;
        $attack->reservedShips = [];
        $attack->timeline['state'] = 'won';
        $attack->timeline['settled'] = $this->at(106)->format(DATE_ATOM);
        self::assertNull($presenter->forProgress($player, $recon, $attack, $this->at(105))['return']);
        self::assertSame('Le retour des survivants a été confirmé.', $presenter->forProgress($player, $recon, $attack, $this->at(106))['return']);

        unset($attack->timeline['returnDue']);
        $attack->report['losses'] = [];
        $noReturn = $presenter->forProgress($player, $recon, $attack, $this->at(106));
        self::assertNull($noReturn['return']);
        self::assertStringContainsString('Aucun vaisseau perdu', $this->render($noReturn));
    }

    public function testOpaqueArrivalShowsOnlyTheConfirmedArrivalAndNotThePrivateBattleDeadline(): void
    {
        [$player, , , $recon, $attack, $presenter] = $this->fixture();
        $recon->reportWhenDue($player, 'fixture-pirate-job', $this->at(32));
        $attack->departedAt = $this->at(40);
        $attack->arrivalAt = $this->at(60);
        $attack->nextAt = $this->at(180);
        $attack->timeline = [
            'state' => 'scheduled',
            'arrival_flow' => 2,
            'arrival_observed' => $this->at(63)->format(DATE_ATOM),
            'combatDue' => $this->at(660)->format(DATE_ATOM),
            'opaque_battle' => ['payload' => ['outcome' => 'attacker_wins', 'rounds_played' => 2]],
        ];
        $playerView = $presenter->forProgress($player, $recon, $attack, $this->at(181));
        self::assertSame('Combat en cours', $playerView['status']);
        self::assertNull($playerView['losses']);
        self::assertNull($playerView['return']);
        $html = $this->render($playerView);
        self::assertStringContainsString('Arrivée de l’escorte confirmée', $html);
        self::assertStringContainsString($this->at(63)->format(DATE_ATOM), $html);
        self::assertStringNotContainsString($this->at(180)->format(DATE_ATOM), $html);
        self::assertStringNotContainsString($this->at(660)->format(DATE_ATOM), $html);
        self::assertStringNotContainsString('attacker_wins', $html);
        self::assertStringNotContainsString('Retour prévu', $html);
    }


    public function testPrivateRecoveryDoesNotPresentDeliveryBeforeARealArrival(): void
    {
        [$player, , , $recon, $attack, $presenter] = $this->fixture();
        $recon->reportWhenDue($player, 'fixture-pirate-job', $this->at(32));
        $attack->departedAt = $this->at(40);
        $attack->report = ['outcome' => 'attacker_wins'];
        $attack->returnBlocked = true;
        $attack->reservedShips = ['fighter' => ['basic' => 2]];
        $stamp = fn(int $offset): string => $this->at($offset)->format(DATE_ATOM);
        $attack->timeline = ['attempt' => 1, 'state' => 'won', 'resolved' => $stamp(60),
            'pilot_return' => [], 'pilot_blocked_return' => []];
        self::assertTrue($presenter->forProgress($player, $recon, $attack, $this->at(70))['may_offer_recovery']);
        $leg = ['version' => 1, 'generation' => 2, 'attempt' => 1, 'state' => 'traveling',
            'departure' => $stamp(70), 'due' => $stamp(130), 'observed' => null];
        $attack->timeline['pilot_recovery'] = $leg;
        $travel = $presenter->forProgress($player, $recon, $attack, $this->at(129));
        self::assertFalse($travel['may_offer_recovery']);
        self::assertStringContainsString('sont en repli', $travel['return']);
        self::assertSame('L’arrivée du repli reste à confirmer.',
            $presenter->forProgress($player, $recon, $attack, $this->at(130))['return']);

        $attack->timeline['pilot_recovery'] = array_replace($leg, ['state' => 'capacity_blocked', 'observed' => $stamp(131)]);
        self::assertStringContainsString('restent en attente', $presenter->forProgress($player, $recon, $attack, $this->at(131))['return']);
        $attack->timeline['pilot_recovery'] = array_replace($leg, ['state' => 'destination_lost', 'observed' => $stamp(131)]);
        self::assertStringContainsString('n’ont été transférés', $presenter->forProgress($player, $recon, $attack, $this->at(131))['return']);

        $attack->returnBlocked = false;
        $attack->reservedShips = [];
        $attack->timeline['settled'] = $stamp(131);
        $attack->timeline['pilot_recovery']['state'] = 'delivered';
        self::assertNull($presenter->forProgress($player, $recon, $attack, $this->at(130))['return']);
        $delivered = $presenter->forProgress($player, $recon, $attack, $this->at(131));
        self::assertStringContainsString('ont rejoint votre hangar', $this->render($delivered));
        self::assertFalse($delivered['may_offer_recovery']);
    }

    public function testForeignAccountParticipationUniverseAndForeignAttemptRenderNothingPrivate(): void
    {
        [$player, $site, , $recon, $attack, $presenter] = $this->fixture();
        $recon->reportWhenDue($player, 'fixture-pirate-job', $this->at(32));
        $wrongPlayer = clone $player;
        $wrongPlayer->player = clone $player->player;
        ++$wrongPlayer->player->id;
        $wrongUniverse = clone $player;
        $wrongUniverse->universe = clone $player->universe;
        ++$wrongUniverse->universe->id;
        $wrongMembership = clone $player;
        ++$wrongMembership->id;
        foreach ([$wrongPlayer, $wrongUniverse, $wrongMembership] as $outsider) {
            self::assertNull($presenter->forSignal($outsider, $site, 'couper_le_commandement', $this->at(33)));
            self::assertNull($presenter->forProgress($outsider, $recon, $attack, $this->at(33)));
            self::assertNull($recon->convoyQuoteFor($outsider, $this->at(33)));
        }

        [, , , $otherRecon] = $this->fixture();
        $otherRecon->id = 301;
        $attack->reconnaissance = $otherRecon;
        $attack->departedAt = $this->at(33);
        $attack->timeline = ['state' => 'won', 'resolved' => $this->at(34)->format(DATE_ATOM)];
        $attack->report = ['outcome' => 'attacker_wins', 'losses' => ['escort:destroyer' => 40]];
        $mismatch = $presenter->forProgress($player, $recon, $attack, $this->at(35));
        self::assertNull($mismatch['losses']);
        self::assertSame('Renseignement reçu', $mismatch['status']);
        $player->universe->endsAt = $this->at(35);
        self::assertNull($presenter->forSignal($player, $site, 'couper_le_commandement', $this->at(35)));
        self::assertNull($presenter->forProgress($player, $recon, null, $this->at(35)));
    }

    private function labelsAfterFlag(array $view): array
    {
        return array_slice(array_column($view['dates'], 'label'), 3);
    }

    private function render(array $view): string
    {
        $twig = new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]);

        $twig->addExtension(new \App\Twig\QuantityExtension());
        $twig->addFunction(new \Twig\TwigFunction('path', static fn (string $route, array $parameters): string => '/rapports/espionnage-pirate/'.$parameters['id']));
        $twig->addFunction(new \Twig\TwigFunction('asset', static fn (string $path): string => '/'.$path));
        $twig->addFunction(new \Twig\TwigFunction('duration_label', static fn (int $seconds): string => $seconds.' s'));
        $partials = 'player/pirate_campaign/';
        $html = $twig->render($partials.'_mission_heading.html.twig', ['narrative' => $view['narrative'], 'status' => $view['status']])
            .$twig->render($partials.'_briefing.html.twig', ['briefing' => $view]);
        if ($view['formation'] !== null || $view['quote'] !== null) {
            $html .= $twig->render($partials.'_intelligence.html.twig', ['briefing' => $view, 'has_durable_reward' => false]);
        }

        return $html;
    }

    /** @return array{PlayerUniverse, PirateDistressSite, PirateCampaignCatalogue, PirateReconnaissance, PirateAttackCampaign, PirateCampaignPresenter} */
    private function fixture(bool $detected = false): array
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $catalogue = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $universe = new Universe();
        $universe->id = 10;
        $universe->slug = 'omega-1';
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->id = 777;
        $system->name = 'PIRATE_PRIVATE_777';
        $system->galaxy = $galaxy;
        $viewer = new PlayerUniverse();
        $viewer->id = 21;
        $viewer->player = new Player();
        $viewer->player->id = 22;
        $viewer->universe = $universe;
        $site = PirateDistressSite::inExistingSystem($viewer, $system);
        $recon = new PirateReconnaissance($viewer, $viewer->player, $system, 'fixture-key', $this->at(1), $this->at(31),
            'fixture-pirate-job', $detected, $catalogue->prepareForServer('couper_le_commandement', $universe));
        $recon->id = 30;
        $attack = new PirateAttackCampaign();
        $attack->reconnaissance = $recon;

        return [$viewer, $site, $catalogue, $recon, $attack, new PirateCampaignPresenter($catalogue, new PirateCampaignProgress($catalogue), $catalog)];
    }

    private function at(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T09:00:00Z'))->modify('+'.$offset.' seconds');
    }
}
