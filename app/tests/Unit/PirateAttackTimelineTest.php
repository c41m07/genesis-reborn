<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Combat\CombatSimulator;
use App\Service\PirateCampaign\{PirateAttackTimeline, PirateBattleScenario, PirateEncounterSnapshot};
use PHPUnit\Framework\TestCase;

final class PirateAttackTimelineTest extends TestCase
{
    public function testNoAttemptWithoutReport(): void
    {
        $this->expectException(\DomainException::class);
        PirateAttackTimeline::afterReport($this->scenario(0), null);
    }

    public function testOpponentMustMatchRevealedComposition(): void
    {
        $this->expectException(\DomainException::class);
        PirateBattleScenario::freeze($this->snapshot(), [$this->unit('fighter', 0)], [], 5, 5);
    }

    public function testPirateStatisticsAndModifiersAreDetachedAndDeterministic(): void
    {
        $attack = 0;
        $factor = 1.0;
        $pirate = $this->unit('raider', 0);
        $pirate['base_attack'] = &$attack;
        $scenario = PirateBattleScenario::freeze($this->snapshot(), [$pirate], ['fighter' => ['raider' => &$factor]], 5, 5);
        $simulator = new CombatSimulator();
        $player = [$this->unit('fighter', 400)];
        $expected = $scenario->resolve($simulator, $player, 31);
        $attack = 1000;
        $factor = 0.1;

        self::assertSame('attacker_wins', $expected['outcome']);
        self::assertSame($expected, $scenario->resolve($simulator, $player, 31));
    }

    public function testFailedAttemptReturnsBeforeARebuiltFleetWinsAndRetriesCannotOutliveVictory(): void
    {
        $simulator = new CombatSimulator();
        $timeline = PirateAttackTimeline::afterReport($this->scenario(0), $this->at(0));
        $units = [$this->unit('fighter', 0)];
        $id = $timeline->start($units, 31, $this->at(1), $this->at(4), $this->at(10));
        self::assertSame(1, $id);
        $units[0]['base_attack'] = 400;

        $return = static fn(array $units, \DateTimeImmutable $at): \DateTimeImmutable => $at->modify('+4 seconds');
        self::assertNull($timeline->resolveWhenDue($id, $this->at(9), $simulator, $return));
        self::assertNull($timeline->resolveWhenDue(0, $this->at(10), $simulator, $return));
        self::assertSame('scheduled', $timeline->state());
        $defeat = $timeline->resolveWhenDue($id, $this->at(10), $simulator, $return);
        self::assertSame('defender_wins', $defeat['outcome']);
        self::assertSame('returning', $timeline->state());
        self::assertFalse($timeline->isVictorious());
        self::assertFalse($timeline->settleReturnWhenDue($id, $this->at(13)));
        $this->assertStartRefused($timeline, $units, 13);
        self::assertTrue($timeline->settleReturnWhenDue($id, $this->at(14)));
        self::assertFalse($timeline->settleReturnWhenDue($id, $this->at(14)));
        self::assertSame('retryable', $timeline->state());
        $this->assertStartRefused($timeline, $units, 13);

        // La flotte peut être reconstruite, le scénario adversaire et le rapport restent inchangés.
        $second = $timeline->start($units, 32, $this->at(15), $this->at(17), $this->at(25));
        self::assertSame(2, $second);
        self::assertNull($timeline->resolveWhenDue($id, $this->at(25), $simulator, $return));
        $victory = $timeline->resolveWhenDue($second, $this->at(25), $simulator, $return);
        self::assertSame('attacker_wins', $victory['outcome']);
        self::assertSame(['pirates:raider' => 1], $victory['losses']);
        self::assertTrue($timeline->isVictorious());
        self::assertSame('returning', $timeline->state());
        $this->assertStartRefused($timeline, $units, 26);
        self::assertFalse($timeline->settleReturnWhenDue($id, $this->at(29)));
        self::assertTrue($timeline->settleReturnWhenDue($second, $this->at(29)));
        self::assertSame('won', $timeline->state());
        self::assertNull($timeline->resolveWhenDue($second, $this->at(40), $simulator, $return));
        $this->assertStartRefused($timeline, $units, 41);
    }

    public function testNoReturnIsPlannedIfTheActualCombatLeavesNoEscort(): void
    {
        $timeline = PirateAttackTimeline::afterReport($this->scenario(400), $this->at(0));
        $id = $timeline->start([$this->unit('fighter', 0)], 31, $this->at(1), $this->at(3), $this->at(7));
        $result = $timeline->resolveWhenDue($id, $this->at(7), new CombatSimulator(), static function (): never {
            throw new \LogicException('Il ne reste aucune escorte à ramener.');
        });

        self::assertSame(['escort:fighter' => 1], $result['losses']);
        self::assertSame('retryable', $timeline->state());
        self::assertFalse($timeline->settleReturnWhenDue($id, $this->at(8)));
    }

    public function testLateBattleDoesNotSettleReturnBeforeTheReportActuallyExists(): void
    {
        $timeline = PirateAttackTimeline::afterReport($this->scenario(0), $this->at(0));
        $id = $timeline->start([$this->unit('fighter', 400)], 31, $this->at(1), $this->at(4), $this->at(8));
        $timeline->resolveWhenDue($id, $this->at(14), new CombatSimulator(), fn (): \DateTimeImmutable => $this->at(12));
        self::assertEquals($this->at(12), $timeline->returnDueAt());
        self::assertFalse($timeline->settleReturnWhenDue($id, $this->at(13)));
        self::assertTrue($timeline->settleReturnWhenDue($id, $this->at(14)));
        self::assertNull($timeline->returnDueAt());
    }

    public function testInvalidReturnDoesNotAcceptOrCloseBattle(): void
    {
        $timeline = PirateAttackTimeline::afterReport($this->scenario(0), $this->at(0));
        $id = $timeline->start([$this->unit('fighter', 400)], 31, $this->at(1), $this->at(4), $this->at(8));
        try {
            $timeline->resolveWhenDue($id, $this->at(8), new CombatSimulator(), fn (): \DateTimeImmutable => $this->at(7));
            self::fail('Un retour antérieur au résultat ne doit pas être accepté.');
        } catch (\DomainException) {
            self::assertSame('scheduled', $timeline->state());
            self::assertFalse($timeline->isVictorious());
        }
    }

    private function assertStartRefused(PirateAttackTimeline $timeline, array $units, int $at): void
    {
        try {
            $timeline->start($units, 31, $this->at($at), $this->at($at + 3), $this->at($at + 6));
            self::fail('Un nouveau départ ne doit pas être accepté.');
        } catch (\DomainException $error) {
            self::assertNotSame('', $error->getMessage());
        }
    }

    private function unit(string $slug, int $attack): array
    {
        return [
            'slug' => $slug, 'tier' => 'basic', 'quantity' => 1, 'base_attack' => $attack,
            'hp' => 30, 'shield' => 0, 'precision' => 100, 'tracking' => 100,
            'evasion' => 0, 'signature' => 20, 'shots_min' => 1, 'shots_max' => 1,
        ];
    }

    private function snapshot(): PirateEncounterSnapshot
    {
        return PirateEncounterSnapshot::freeze(['raider' => ['basic' => 1]], ['metal' => 100]);
    }

    private function scenario(int $attack): PirateBattleScenario
    {
        return PirateBattleScenario::freeze($this->snapshot(), [$this->unit('raider', $attack)], [], 5, 5);
    }

    private function at(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T08:00:00Z'))->modify('+'.$offset.' seconds');
    }
}
