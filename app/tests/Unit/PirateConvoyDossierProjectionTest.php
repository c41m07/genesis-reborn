<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateConvoyDelivery, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateConvoyDispatchToken, PirateConvoyDossierProjection, PirateEncounterSnapshot, PiratePreparedEncounter};
use PHPUnit\Framework\TestCase;

final class PirateConvoyDossierProjectionTest extends TestCase
{
    private const KEYS = ['balise_17', 'derniere_liaison', 'mission_futur'];

    public function testSeparateCurrentProgressSelectionAndOldestPendingConvoy(): void
    {
        $viewer = $this->viewer();
        $earlier = $this->right($viewer, self::KEYS[0], 70);
        $later = $this->right($viewer, self::KEYS[1], 85);
        $projection = new PirateConvoyDossierProjection();
        $dossier = $this->dossier(2, secondWon: false);
        self::assertSame([self::KEYS[0]], $projection->visibleKeys($dossier, self::KEYS));
        self::assertSame([
            'selected_reward' => null,
            'oldest_pending' => ['mission' => 1, 'state' => 'unassigned'],
        ], $projection->forDossier($viewer, $dossier, self::KEYS, [$later, $earlier], $this->at(90)));
        self::assertSame(2, $dossier['current']);

        $dossier = $this->dossier(2);
        $later['right'] = $this->advance($later['right'], 'traveling');
        $result = $projection->forDossier($viewer, $dossier, self::KEYS, [$later, $earlier], $this->at(100));
        self::assertSame(['mission' => 1, 'state' => 'unassigned'], $result['oldest_pending']);
        self::assertSame('protected_travel', $result['selected_reward']['state']);
        self::assertSame(['metal' => '41,5', 'nano' => '0,5', 'antimatter' => '4', 'hydrogen' => '0'], $result['selected_reward']['amounts']);
        self::assertSame($this->at(130)->format(DATE_ATOM), $result['selected_reward']['expected_arrival_at']);
        self::assertFalse($result['selected_reward']['awaiting_arrival_confirmation']);
        self::assertSame(3, $dossier['current']);
    }

    public function testArrivalEstimateCannotConfirmCreditAndEveryDurableStateIsDistinct(): void
    {
        foreach ([
            'traveling' => 'protected_travel', 'arrived' => 'arrival_confirmed', 'waiting_capacity' => 'full_pending',
            'destination_lost' => 'destination_lost', 'settled' => 'credited',
        ] as $timeline => $expected) {
            $viewer = $this->viewer();
            $row = $this->right($viewer, self::KEYS[0], 70);
            $this->advance($row['right'], $timeline);
            $value = (new PirateConvoyDossierProjection())->forDossier($viewer, $this->dossier(1), self::KEYS, [$row], $this->at(150));
            $selected = $value['selected_reward'];
            self::assertNotNull($selected, $timeline);
            self::assertSame(['state', 'amounts', 'expected_arrival_at', 'awaiting_arrival_confirmation', 'credited_at'], array_keys($selected));
            self::assertSame($expected, $selected['state']);
            self::assertSame($timeline === 'traveling', $selected['awaiting_arrival_confirmation']);
            self::assertSame($timeline === 'settled' ? $this->at(132)->format(DATE_ATOM) : null, $selected['credited_at']);
            self::assertSame($timeline === 'settled' ? null : ['mission' => 1, 'state' => $expected], $value['oldest_pending']);
        }
    }

    public function testNoFutureForeignOrInconsistentReceiptCanPopulateTheDossier(): void
    {
        foreach (['compte', 'participation', 'univers', 'sélection', 'mission future', 'victoire future',
            'transition future', 'reçu absent', 'doublon'] as $kind) {
            $viewer = $this->viewer();
            $row = $this->right($viewer, self::KEYS[0], 70);
            $now = $this->at(150);
            switch ($kind) {
                case 'compte': $viewer->player->id++; break;
                case 'participation': $viewer->id++; break;
                case 'univers': $viewer->universe->id++; break;
                case 'sélection': $viewer->player->currentPlayerUniverse = null; break;
                case 'mission future': $row['missionKey'] = self::KEYS[2]; break;
                case 'victoire future': $now = $this->at(69); break;
                case 'transition future':
                    $this->advance($row['right'], 'settled');
                    $now = $this->at(131);
                    break;
                case 'reçu absent':
                    $this->advance($row['right'], 'settled');
                    (new \ReflectionProperty(PirateConvoyDelivery::class, 'settledAt'))->setValue($row['right'], null);
                    break;
            }
            $rows = $kind === 'doublon' ? [$row, $row] : [$row];
            self::assertSame(['selected_reward' => null, 'oldest_pending' => null],
                (new PirateConvoyDossierProjection())->forDossier($viewer, $this->dossier(1), self::KEYS, $rows, $now), $kind);
        }
    }

    public function testOneInvalidDurableDateDoesNotHideTheOtherOwnedDossier(): void
    {
        $viewer = $this->viewer();
        $broken = $this->right($viewer, self::KEYS[0], 70);
        $valid = $this->right($viewer, self::KEYS[1], 85);
        $property = new \ReflectionProperty(PirateConvoyDelivery::class, 'checkpoint');
        $invalid = $broken['right']->timeline()->checkpoint();
        $invalid['transition_at'] .= chr(0);
        $property->setValue($broken['right'], $invalid);

        $result = (new PirateConvoyDossierProjection())->forDossier(
            $viewer, $this->dossier(1), self::KEYS, [$broken, $valid], $this->at(100),
        );
        self::assertNull($result['selected_reward']);
        self::assertSame(['mission' => 2, 'state' => 'unassigned'], $result['oldest_pending']);
    }

    private function advance(PirateConvoyDelivery $right, string $target): PirateConvoyDelivery
    {
        $timeline = $right->timeline();
        $token = PirateConvoyDispatchToken::generate(1);
        self::assertTrue($timeline->dispatch(27, 33, 92, $timeline->attestedPositionForDeparture(),
            [41, 39, 1, 1, 8], $token, $this->at(95), $this->at(130)));
        $right->replaceState($timeline);
        if ($target === 'traveling') {
            return $right;
        }

        self::assertTrue($timeline->arriveWhenDue($token, $this->at(131)));
        match ($target) {
            'settled' => self::assertTrue($timeline->recordSettlementAfterCredit(27, 33, 92, $this->at(132))),
            'waiting_capacity' => self::assertTrue($timeline->recordCapacityShortage(27, 33, 92, $this->at(132))),
            'destination_lost' => self::assertTrue($timeline->recordDestinationLoss(27, 33, 92, $this->at(132))),
            'arrived' => null,
        };
        $right->replaceState($timeline);

        return $right;
    }

    private function right(PlayerUniverse $member, string $key, int $victory): array
    {
        $world = $member->universe;
        $galaxy = new Galaxy();
        $galaxy->id = 39;
        $galaxy->universe = $world;
        $site = new SolarSystem();
        $site->id = 41;
        $site->galaxy = $galaxy;
        $site->coordX = $site->coordY = 1;
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $campaign = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $reference = $campaign->prepareForServer($key, $world);
        $encounter = PirateEncounterSnapshot::freeze($reference->encounterForServer()->composition(), [
            'metal' => 83, 'nano' => 1, 'antimatter' => 8, 'hydrogen' => 0,
        ]);
        $recon = new PirateReconnaissance($member, $member->player, $site, $key, $this->at(1), $this->at(5), 'fixture-convoy',
            null, new PiratePreparedEncounter($key, $encounter, $reference->battleForServer()));
        $recon->reportWhenDue($member, 'fixture-convoy', $this->at(5));
        $attack = new PirateAttackCampaign();
        $attack->reconnaissance = $recon;
        $attack->id = $victory + 300;
        $attack->timeline = ['arrival_flow' => 2, 'attempt' => 2, 'resolved' => $this->at($victory)->format(DATE_ATOM),
            'arrival_observed' => $this->at(10)->format(DATE_ATOM), 'won' => true, 'state' => 'returning'];
        $attack->report = ['outcome' => 'attacker_wins'];
        $right = PirateConvoyDelivery::forVictory($attack, $member, $this->at($victory));
        $right->id = $victory + 100;

        return ['right' => $right, 'missionKey' => $key, 'victoryAt' => $this->at($victory)];
    }

    private function viewer(): PlayerUniverse
    {
        $member = new PlayerUniverse();
        $member->id = 27;
        $member->player = new Player();
        $member->player->id = 9;
        $member->player->currentPlayerUniverse = $member;
        $member->player->emailVerifiedAt = $this->at(0);
        $member->universe = new Universe();
        $member->universe->id = 33;
        $member->universe->slug = 'omega-1';

        return $member;
    }

    private function dossier(int $selected, bool $secondWon = true): array
    {
        return ['current' => $secondWon ? 3 : 2, 'selected' => $selected, 'history' => [
            ['number' => 1, 'won' => true], ['number' => 2, 'won' => $secondWon],
        ]];
    }

    private function at(int $seconds): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T12:00:00Z'))->modify('+'.$seconds.' seconds');
    }
}
