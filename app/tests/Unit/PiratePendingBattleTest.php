<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Combat\CombatSimulator;
use App\Service\PirateCampaign\{PirateBattleScenario, PirateEncounterSnapshot, PiratePendingBattle};
use PHPUnit\Framework\TestCase;

final class PiratePendingBattleTest extends TestCase
{
    public function testNoPreparationWithoutArrivalAndNoFateBeforeTheLastPlayedRound(): void
    {
        $simulator = new CombatSimulator();
        $scenario = $this->scenario();
        $units = [$this->unit('fighter', 400)];
        $departure = $this->at(1);
        $arrival = $this->at(4);
        self::assertNull(PiratePendingBattle::prepareAfterArrival(1, null, $this->at(1000), $scenario, $units, 31, $simulator));
        self::assertNull(PiratePendingBattle::prepareAfterArrival(1, $arrival, $departure, $scenario, $units, 31, $simulator));

        $battle = PiratePendingBattle::prepareAfterArrival(1, $arrival, $arrival, $scenario, $units, 31, $simulator);
        $native = $scenario->resolve($simulator, $units, 31);
        self::assertSame(2, $native['rounds_played']);
        self::assertEquals($this->at(124), $battle->workerDueAt());
        self::assertLessThan($this->at(604), $battle->workerDueAt());
        self::assertNull($battle->takeWhenDue(1, $this->at(123)));
        self::assertNull($battle->takeWhenDue(2, $this->at(124)));

        $payload = $battle->takeWhenDue(1, $this->at(124));
        self::assertSame(['outcome' => 'attacker_wins', 'rounds_played' => 2, 'losses' => ['pirates:raider' => 1],
            'survivors' => ['fighter' => ['basic' => 1]]], $payload);
        self::assertNull($battle->takeWhenDue(1, $this->at(124)));
    }

    public function testLateWorkerAndRolledBackCommitReuseTheSameSnapshotExactlyOnce(): void
    {
        $prepared = PiratePendingBattle::prepareAfterArrival(2, $this->at(10), $this->at(200),
            $this->scenario(), [$this->unit('fighter', 400)], 31, new CombatSimulator());
        self::assertEquals($this->at(130), $prepared->workerDueAt(), 'Le retard ne décale pas l’arrivée fournie par le worker.');
        $before = json_decode(json_encode($prepared->checkpointForPersistence(), JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame(['version', 'attempt', 'arrived_at', 'consumed', 'payload'], array_keys($before));
        self::assertSame(['outcome', 'rounds_played', 'losses', 'survivors'], array_keys($before['payload']));
        self::assertFalse($before['consumed']);

        $discarded = $prepared->takeWhenDue(2, $this->at(220));
        self::assertSame($discarded, $before['payload']);
        $rehydratedAfterRollback = PiratePendingBattle::fromPrivateCheckpoint($before);
        self::assertNull($rehydratedAfterRollback->takeWhenDue(1, $this->at(230)));
        self::assertSame($discarded, $rehydratedAfterRollback->takeWhenDue(2, $this->at(230)));
        self::assertNull(PiratePendingBattle::fromPrivateCheckpoint($rehydratedAfterRollback->checkpointForPersistence())
            ->takeWhenDue(2, $this->at(240)));
    }

    public function testTenRoundsAndNoSurvivingEscortAreHandledWithoutInventingReturn(): void
    {
        $drawnOut = PiratePendingBattle::prepareAfterArrival(1, $this->at(10), $this->at(10),
            $this->scenario(0), [$this->unit('fighter', 0)], 31, new CombatSimulator());
        self::assertEquals($this->at(610), $drawnOut->workerDueAt());
        self::assertNull($drawnOut->takeWhenDue(1, $this->at(609)));
        self::assertSame(10, $drawnOut->takeWhenDue(1, $this->at(610))['rounds_played']);

        $lost = PiratePendingBattle::prepareAfterArrival(1, $this->at(10), $this->at(10),
            $this->scenario(400), [$this->unit('fighter', 0)], 31, new CombatSimulator());
        $terminal = $lost->takeWhenDue(1, $lost->workerDueAt());
        self::assertSame('defender_wins', $terminal['outcome']);
        self::assertSame(['escort:fighter' => 1], $terminal['losses']);
        self::assertSame([], $terminal['survivors']);
    }

    public function testInvalidStoredQuantityCannotApplyAResult(): void
    {
        $prepared = PiratePendingBattle::prepareAfterArrival(1, $this->at(10), $this->at(10),
            $this->scenario(), [$this->unit('fighter', 400)], 31, new CombatSimulator());
        $data = $prepared->checkpointForPersistence();
        $data['payload']['survivors']['fighter']['basic'] = -1;
        $this->expectException(\DomainException::class);
        PiratePendingBattle::fromPrivateCheckpoint($data);
    }

    private function scenario(int $attack = 0): PirateBattleScenario
    {
        $snapshot = PirateEncounterSnapshot::freeze(['raider' => ['basic' => 1]], ['metal' => 100]);

        return PirateBattleScenario::freeze($snapshot, [$this->unit('raider', $attack)], [], 10, 5);
    }

    private function unit(string $slug, int $attack): array
    {
        return [
            'slug' => $slug, 'tier' => 'basic', 'quantity' => 1, 'base_attack' => $attack,
            'hp' => 30, 'shield' => 0, 'precision' => 100, 'tracking' => 100,
            'evasion' => 0, 'signature' => 20, 'shots_min' => 1, 'shots_max' => 1,
        ];
    }

    private function at(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T08:00:00Z'))->modify('+'.$offset.' seconds');
    }
}
