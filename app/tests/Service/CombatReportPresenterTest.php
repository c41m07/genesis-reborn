<?php
namespace App\Tests\Service;

use App\Service\Combat\CombatReportPresenter;
use PHPUnit\Framework\TestCase;

final class CombatReportPresenterTest extends TestCase
{
    public function testRoundsKeepTiersReinforcementsAndDestructionsSeparate(): void
    {
        $snapshot = ['participants' => [
            ['fleet_id' => 10, 'player_universe_id' => 7, 'role' => 'attacker', 'camp' => 'a', 'units' => [['slug' => 'fighter', 'quantity' => 2, 'tier' => 'intermediate']]],
            ['fleet_id' => 20, 'player_universe_id' => 8, 'role' => 'attacker', 'camp' => 'a', 'arrival_round' => 2, 'units' => [['slug' => 'frigate', 'quantity' => 1]]],
            ['fleet_id' => 30, 'player_universe_id' => 9, 'role' => 'defender', 'camp' => 'defenders', 'units' => [['slug' => 'fighter', 'quantity' => 2]]],
        ], 'losses' => ['30:fighter' => 2, '10:fighter:intermediate' => 1]];
        $a = '10:attacker:fighter:intermediate:1';
        $b = '30:defender:fighter:1';
        $rounds = [
            ['number' => 1, 'shots' => [
                ['attacker' => $a, 'target' => $b, 'hit' => false, 'destroyed' => false],
                ['attacker' => $a, 'target' => $b, 'weapon' => 'anti_light', 'hit' => true, 'destroyed' => true],
                ['attacker' => $b, 'target' => $a, 'hit' => true, 'destroyed' => true],
            ]],
            ['number' => 2, 'shots' => [
                ['attacker' => '20:attacker:frigate:1', 'target' => '30:defender:fighter:2', 'hit' => true, 'destroyed' => true],
            ]],
        ];
        $view = (new CombatReportPresenter())->present($snapshot, $rounds, 7);
        $first = $view['round_details'][0];
        self::assertCount(1, $first['own']);
        self::assertSame([2, 1, 1, 2, 1, 1, 50.0], array_map(static fn (string $key) => $first['own'][0][$key], ['start', 'round_lost', 'end', 'shots', 'hits', 'misses', 'accuracy']));
        self::assertCount(2, $view['round_details'][1]['own']);
        self::assertSame('Flotte alliée', $view['round_details'][1]['own'][1]['label']);
        self::assertSame(1, $view['round_details'][1]['enemy'][0]['start']);
        self::assertSame(0, $view['round_details'][1]['enemy'][0]['end']);
        self::assertNull($view['round_details'][1]['own'][0]['accuracy']);
        self::assertSame(1, $view['rows'][0]['lost']);
    }

    public function testDefenderSeesOwnDefensesOnLeftAndReconstructionAfterLosses(): void
    {
        $snapshot = ['participants' => [
            ['fleet_id' => 10, 'player_universe_id' => 7, 'role' => 'attacker', 'camp' => 'a', 'units' => [['slug' => 'fighter', 'quantity' => 1]]],
            ['fleet_id' => 20, 'player_universe_id' => 8, 'role' => 'attacker', 'camp' => 'b', 'units' => [['slug' => 'fighter', 'quantity' => 1]]],
        ], 'defenses' => [['id' => 3, 'slug' => 'laser', 'tier' => 'superior', 'quantity' => 10]],
            'losses' => ['planet:3:laser:superior' => 10], 'defense_rebuilt' => ['laser:superior' => 3]];
        $round = ['number' => 1, 'shots' => [
            ['attacker' => 'planet:3:static_defense:laser:superior:1', 'target' => '10:attacker:fighter:1', 'hit' => true, 'destroyed' => false],
        ]];
        $view = (new CombatReportPresenter())->present($snapshot, [$round], 99);
        self::assertCount(1, $view['round_details'][0]['own']);
        self::assertCount(2, $view['round_details'][0]['enemy']);
        self::assertSame(1, $view['round_details'][0]['own'][0]['hits']);
        self::assertSame(3, $view['reconstruction'][0]['rebuilt']);
        self::assertSame(7, $view['reconstruction'][0]['permanent']);
        $attackerView = (new CombatReportPresenter())->present($snapshot, [$round], 7);
        self::assertCount(1, $attackerView['round_details'][0]['own']);
        self::assertCount(2, $attackerView['round_details'][0]['enemy']);
    }

    public function testHistoricalReportDoesNotInventRoundStatistics(): void
    {
        $snapshot = ['participants' => [['pve_npc' => true, 'planet_id' => 3, 'role' => 'defender', 'units' => [['slug' => 'fighter', 'quantity' => 2]]]], 'losses' => ['npc:3:fighter' => 2]];
        $view = (new CombatReportPresenter())->present($snapshot, [], 7);
        self::assertSame([], $view['round_details']);
        self::assertSame(2, $view['rows'][0]['lost']);
    }
}
