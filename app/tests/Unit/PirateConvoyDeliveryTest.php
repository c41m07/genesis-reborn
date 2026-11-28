<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateConvoyDelivery, PirateReconnaissance, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateConvoyDispatchToken, PirateConvoyTimeline};
use PHPUnit\Framework\TestCase;

final class PirateConvoyDeliveryTest extends TestCase
{
    public function testVictoryCreatesAnExactPrivateRightAndNoDepositBeforeArrived(): void
    {
        [$victory, $member] = $this->victory();
        $right = PirateConvoyDelivery::forVictory($victory, $member, $this->at(70));
        self::assertSame(2, $right->winningAttempt);
        self::assertSame(9, $right->ownerId);
        self::assertSame(27, $right->membershipId);
        self::assertSame(33, $right->universeId);
        $first = $right->timeline();
        self::assertSame('eligible', $first->state());
        self::assertSame(33440.0, $first->quote->depositAmounts()['metal']);

        $origin = $first->attestedPositionForDeparture();
        self::assertSame([41, 39, 1, 1, null], $origin);
        $token = PirateConvoyDispatchToken::generate(1);
        self::assertSame(36, strlen($token));
        self::assertTrue(PirateConvoyDispatchToken::matches($token, 1));
        self::assertFalse(PirateConvoyDispatchToken::matches($token, 2));
        self::assertTrue($first->dispatch(27, 33, 92, $origin, [41, 39, 1, 1, 8], $token, $this->at(72), $this->at(130)));
        $right->replaceState($first);
        $expected = $right->timeline();
        self::assertSame(1, $expected->checkpoint()['generation']);
        self::assertFalse($expected->mayTryDeposit(27, 33, 92, $this->at(100)));
        self::assertTrue($expected->arriveWhenDue($token, $this->at(130)));
        self::assertTrue($expected->recordSettlementAfterCredit(27, 33, 92, $this->at(130)));
        $right->replaceState($expected);
        self::assertSame('settled', $right->timeline()->state());
        self::assertFalse($right->timeline()->mayTryDeposit(27, 33, 92, $this->at(140)));

        $this->expectException(\DomainException::class);
        $right->replaceState($first);
    }

    public function testCheckpointValidationRejectsOldOrFabricatedPositionAndWrongTokenGeneration(): void
    {
        [$victory, $member] = $this->victory();
        $right = PirateConvoyDelivery::forVictory($victory, $member, $this->at(70));
        $first = $right->timeline();
        $tokenSecondLeg = PirateConvoyDispatchToken::generate(2);
        $first->dispatch(27, 33, 92, $first->attestedPositionForDeparture(), [41, 39, 1, 1, 8],
            $tokenSecondLeg, $this->at(72), $this->at(130));
        try {
            $right->replaceState($first);
            self::fail('Le jeton de la seconde jambe ne peut pas servir à la première.');
        } catch (\DomainException) {
            self::assertSame('eligible', $right->timeline()->state());
        }
        $checkpoint = $first->checkpoint();
        $checkpoint['state'] = 'destination_lost'; // Aucun point d’arrivée n’est encore attesté.
        $this->expectException(\DomainException::class);
        PirateConvoyTimeline::fromCheckpoint(2, $this->at(70), $first->quote, 27, 33, $checkpoint);
    }

    public function testNoRightForLegacyDefeatUnresolvedOrWrongOwner(): void
    {
        foreach (['legacy', 'defeat', 'future', 'owner', 'old', 'no_arrival'] as $kind) {
            [$attack, $member] = $this->victory();
            match ($kind) {
                'legacy' => $attack->timeline['arrival_flow'] = 1,
                'defeat' => $attack->report['outcome'] = 'defender_wins',
                'future' => $attack->timeline['resolved'] = $this->at(80)->format(DATE_ATOM),
                'owner' => $member = $this->foreign($member),
                'old' => $attack->timeline['resolved'] = $this->at(69)->format(DATE_ATOM),
                'no_arrival' => $attack->timeline['arrival_observed'] = null,
            };
            try {
                PirateConvoyDelivery::forVictory($attack, $member, $this->at(70));
                self::fail('Droit de convoi interdit : '.$kind);
            } catch (\DomainException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }

    private function foreign(PlayerUniverse $original): PlayerUniverse
    {
        $other = clone $original;
        $other->player = clone $original->player;
        $other->player->id = 67;

        return $other;
    }

    /** @return array{PirateAttackCampaign,PlayerUniverse} */
    private function victory(): array
    {
        $world = new Universe();
        $world->id = 33;
        $world->slug = 'omega-1';
        $galaxy = new Galaxy();
        $galaxy->id = 39;
        $galaxy->universe = $world;
        $site = new SolarSystem();
        $site->id = 41;
        $site->galaxy = $galaxy;
        $site->coordX = $site->coordY = 1;
        $member = new PlayerUniverse();
        $member->id = 27;
        $member->player = new Player();
        $member->player->id = 9;
        $member->universe = $world;
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $campaign = new PirateCampaignCatalogue($catalog, new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)));
        $recon = new PirateReconnaissance($member, $member->player, $site, 'balise_17', $this->at(1), $this->at(5), 'fixture-convoy',
            null, $campaign->prepareForServer('balise_17', $world));
        $recon->reportWhenDue($member, 'fixture-convoy', $this->at(5));
        $attack = new PirateAttackCampaign();
        $attack->reconnaissance = $recon;
        $attack->id = 103;
        $attack->timeline = ['arrival_flow' => 2, 'attempt' => 2, 'resolved' => $this->at(70)->format(DATE_ATOM),
            'arrival_observed' => $this->at(10)->format(DATE_ATOM), 'won' => true, 'state' => 'returning'];
        $attack->report = ['outcome' => 'attacker_wins'];

        return [$attack, $member];
    }

    private function at(int $seconds): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T12:00:00Z'))->modify('+'.$seconds.' seconds');
    }
}
