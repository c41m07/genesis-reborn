<?php

namespace App\Tests\Service;

use App\Service\Combat\CombatCapacityExceeded;
use App\Service\Combat\CombatSimulator;
use App\Service\Combat\CompactCombatEngine;
use PHPUnit\Framework\TestCase;

final class CompactCombatEngineTest extends TestCase
{
    public function testCompactAndLegacyResolveTheSameRoundsWithDifferentCamps(): void
    {
        $legacy = new CombatSimulator();
        $engine = $this->engine();
        [$participants, $defenses, $modifiers] = $this->forces(false);
        for ($seed = 0; $seed < 8; ++$seed) {
            foreach ([false, true] as $independent) {
                $reference = $legacy->simulate($participants, $defenses, $modifiers, 6, $seed, null, 13.5, false, $independent);
                $compact = $engine->simulate($participants, $defenses, $modifiers, 6, $seed, null, 13.5, false, $independent);
                foreach (['outcome', 'rounds_played', 'losses', 'winner_camp'] as $key) {
                    self::assertSame($reference[$key] ?? null, $compact[$key] ?? null);
                }
                self::assertEquals($this->aggregateSurvivors($reference['survivors']), $this->keyGroups($compact['survivors']));
                $byFleet = array_column($participants, 'score_owner', 'id');
                foreach ($reference['rounds'] as $index => $round) {
                    $actual = $compact['rounds'][$index];
                    $stats = [0, 0, 0];
                    $damage = [];
                    $targeted = [];
                    foreach ($round['shots'] as $shot) {
                        ++$stats[0];
                        $stats[1] += (int) $shot['hit'];
                        $stats[2] += (int) $shot['destroyed'];
                        $fleet = explode(':', $shot['attacker'], 2)[0];
                        if (isset($byFleet[$fleet])) {
                            $scoreOwner = $byFleet[$fleet];
                            $damage[$scoreOwner] = ($damage[$scoreOwner] ?? 0.0) + $shot['damage'];
                        }
                        $target = $this->unitKeyToGroupKey($shot['target']);
                        $targeted[$target] = ($targeted[$target] ?? 0) + 1;
                    }
                    self::assertSame($stats, [$actual['total_shots'], $actual['hits'], $actual['destroyed']]);
                    foreach ($damage as $scoreOwner => $sum) {
                        self::assertSame($sum, $actual['damage_by_score_owner'][$scoreOwner] ?? 0.0);
                    }
                    foreach ($targeted as $key => $count) {
                        self::assertSame($count, $actual['groups'][$key]['targeted']);
                    }
                }
            }
        }
    }

    public function testImportsLegacyCheckpointWhenAReinforcementSortsBeforeExistingFleets(): void
    {
        $engine = $this->engine();
        $legacy = new CombatSimulator();
        [$participants, $defenses, $modifiers] = $this->forces(false);
        $roundOneForces = array_values(array_filter($participants, static fn (array $fleet): bool => $fleet['arrival_round'] === 1));
        // L'identifiant est volontairement antérieur aux flottes déjà dans le combat.
        $participants[4]['id'] = '01';
        $reference = $engine->simulate($participants, $defenses, $modifiers, 6, 534, null, 13.5);
        $roundOne = $legacy->advance($roundOneForces, $defenses, $modifiers, 6, 534, 1, 13.5, true, false);
        self::assertSame(1, $roundOne['checkpoint']['version']);
        $roundTwo = $engine->advance($participants, $defenses, $modifiers, 6, 534, 2, 13.5, true, false, $roundOne['checkpoint']);
        self::assertSame(CompactCombatEngine::CHECKPOINT_VERSION, $roundTwo['checkpoint']['version']);
        $checkpoint = json_decode(json_encode($roundTwo['checkpoint'], JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);
        $rest = $engine->advance($participants, $defenses, $modifiers, 6, 534, 6, 13.5, true, false, $checkpoint);
        self::assertSame(array_slice($reference['rounds'], 1), [...$roundTwo['rounds'], ...$rest['rounds']]);
        self::assertSame($reference['survivors'], $rest['survivors']);
        self::assertSame($reference['losses'], $rest['losses']);
    }

    public function testRoutesLargeReinforcementFromTheOriginalEntryPoint(): void
    {
        $compact = $this->engine();
        $simulator = new CombatSimulator($compact);
        [$participants, $defenses, $modifiers] = $this->forces(false);
        $first = $simulator->advance(array_values(array_filter($participants, static fn (array $fleet): bool => $fleet['arrival_round'] === 1)),
            $defenses, $modifiers, 6, 534, 1, 13.5, true, false);
        self::assertSame(1, $first['checkpoint']['version']);
        self::assertArrayHasKey('shots', $first['rounds'][0]);

        $participants[4]['units'][0]['quantity'] = 5200;
        $reference = $compact->simulate($participants, $defenses, $modifiers, 6, 534, null, 13.5);
        $resolved = $simulator->advance($participants, $defenses, $modifiers, 6, 534, 6, 13.5, true, false, $first['checkpoint']);
        self::assertSame(array_slice($reference['rounds'], 1), $resolved['rounds']);
        self::assertSame($reference['losses'], $resolved['losses']);
    }

    public function testRefusesAnAllocationAboveTheChildMemoryLimit(): void
    {
        $engine = $this->engine();
        [$participants, $defenses, $modifiers] = $this->forces(false);
        $defenses[0]['quantity'] = 100_000_000;
        $this->expectException(CombatCapacityExceeded::class);
        $engine->simulate($participants, $defenses, $modifiers, 4, 98761);
    }

    public function testWeightedTargetSelectionAlsoMatchesWhenTheDrawRequires64Bits(): void
    {
        [$participants, $defenses, $modifiers] = $this->forces(false);
        foreach ($participants as &$fleet) {
            foreach ($fleet['units'] as &$unit) {
                $unit['signature'] *= 10_000_000;
            }
            unset($unit);
        }
        unset($fleet);
        $legacy = new CombatSimulator();
        $engine = $this->engine();
        $reference = $legacy->simulate($participants, $defenses, $modifiers, 6, 23417);
        $actual = $engine->simulate($participants, $defenses, $modifiers, 6, 23417);
        self::assertSame($reference['losses'], $actual['losses']);
        self::assertSame(array_map(static fn (array $round): int => count($round['shots']), $reference['rounds']),
            array_column($actual['rounds'], 'total_shots'));
    }

    public function testResolvesTenMillionShipsWithDefensesAndTwoReinforcementRounds(): void
    {
        $engine = new CombatSimulator($this->engine());
        [$all, $defenses, $modifiers] = $this->forces(true);
        $perRound = static fn (int $round): array => array_values(array_filter($all, static fn (array $fleet): bool => $fleet['arrival_round'] <= $round));
        $first = $engine->advance($perRound(1), $defenses, $modifiers, 10, 98761, 1, 13.5, false, false);
        $second = $engine->advance($perRound(2), $defenses, $modifiers, 10, 98761, 2, 13.5, false, false, $first['checkpoint']);
        $resolved = $engine->advance($all, $defenses, $modifiers, 10, 98761, 10, 13.5, false, false, $second['checkpoint']);
        $everyRound = [...$first['rounds'], ...$second['rounds'], ...$resolved['rounds']];
        self::assertGreaterThanOrEqual(3, $resolved['rounds_played']);
        self::assertSame(range(1, $resolved['rounds_played']), array_column($everyRound, 'number'));
        self::assertNotSame('ongoing', $resolved['outcome']);
        self::assertGreaterThan(10_000_000, array_sum(array_column($everyRound, 'total_shots')));
        self::assertSame(10_500_000, array_sum($resolved['losses']) + array_sum(array_column($resolved['survivors'], 'quantity')));
        self::assertGreaterThan(0, $resolved['losses']['16:frigate']);
        self::assertGreaterThan(0, $resolved['losses']['21:frigate']);
        self::assertGreaterThan(0, $resolved['losses']['planet:3:cannon']);
        self::assertArrayNotHasKey('16:frigate', $first['rounds'][0]['groups']);
        self::assertArrayNotHasKey('21:frigate', $second['rounds'][0]['groups']);
        self::assertArrayNotHasKey('checkpoint', $resolved);
    }

    private function engine(): CompactCombatEngine
    {
        $path = getenv('TEST_COMBAT_ENGINE_BINARY');

        return $path === false ? new CompactCombatEngine() : new CompactCombatEngine($path);
    }

    private function forces(bool $large): array
    {
        $participants = [];
        for ($i = 0; $i < 10; ++$i) {
            $participants[] = [
                'id' => (string) ($i + 12), 'role' => $i < 5 ? 'attacker' : 'defender',
                'camp' => $i < 5 ? (!$large && $i % 5 > 2 ? 'alliance-B' : 'alliance-A') : 'defender',
                'score_owner' => (string) ($i % 3 + 10), 'arrival_round' => [4 => 2, 9 => 3][$i] ?? 1,
                'units' => [
                    $this->unit('frigate', $large ? 700_000 : 3, [
                        'base_attack' => 13, 'signature' => 45, 'hp' => 23, 'shield' => 11,
                        'secondary_attack' => 6.5, 'secondary_shots_min' => 0, 'secondary_shots_max' => 1,
                        'secondary_precision_modifier' => -7, 'secondary_target_category' => 'light',
                    ]),
                    $this->unit('fighter', $large ? 300_000 : 5, ['tier' => $i % 2 ? 'superior' : 'intermediate', 'hp' => 41.5]),
                ],
            ];
        }
        $defenses = [
            $this->unit('cannon', $large ? 500_000 : 3, [
                'id' => '3', 'camp' => 'defender', 'category' => 'ground', 'base_attack' => 16,
                'evasion' => 0, 'signature' => 115, 'hp' => 35,
            ]),
        ];
        return [
            $participants,
            $defenses,
            ['frigate' => ['fighter' => .95, 'ground_defense' => 1.4], 'cannon' => ['fighter' => 1.17], 'fighter' => ['frigate' => 1.28]],
        ];
    }

    private function unit(string $slug, int $quantity, array $definition): array
    {
        return $definition + [
            'slug' => $slug, 'tier' => 'basic', 'category' => 'light', 'quantity' => $quantity, 'hp' => 25.5, 'shield' => 8.4,
            'base_attack' => 11.5, 'precision' => 76, 'tracking' => 11, 'evasion' => 9,
            'signature' => 30.25, 'shots_min' => 1, 'shots_max' => 2, 'extra_shot_chances' => [.17, .36],
        ];
    }

    private function unitKeyToGroupKey(string $full): string
    {
        $parts = explode(':', $full);
        array_pop($parts);
        foreach (['attacker', 'defender', 'static_defense'] as $role) {
            $rolePosition = array_search($role, $parts, true);
            if ($rolePosition !== false) {
                unset($parts[$rolePosition]);
                break;
            }
        }

        return implode(':', $parts);
    }

    private function keyGroups(array $groups): array
    {
        $keyed = [];
        foreach ($groups as $group) {
            $key = $group['owner'] . ':' . $group['slug'] . ($group['tier'] === 'basic' ? '' : ':' . $group['tier']);
            $keyed[$key] = $group;
        }
        return $keyed;
    }

    private function aggregateSurvivors(array $units): array
    {
        $groups = [];
        foreach ($units as $unit) {
            $key = $unit['owner'] . ':' . $unit['slug'] . ($unit['tier'] === 'basic' ? '' : ':' . $unit['tier']);
            if (!isset($groups[$key])) {
                $groups[$key] = array_diff_key($unit, ['hp' => true, 'shield' => true]) + [
                    'quantity' => 0, 'hp_min' => $unit['hp'], 'hp_max' => $unit['hp'],
                    'shield_min' => $unit['shield'], 'shield_max' => $unit['shield'],
                ];
            }
            $group = &$groups[$key];
            ++$group['quantity'];
            $group['hp_min'] = min($group['hp_min'], $unit['hp']);
            $group['hp_max'] = max($group['hp_max'], $unit['hp']);
            $group['shield_min'] = min($group['shield_min'], $unit['shield']);
            $group['shield_max'] = max($group['shield_max'], $unit['shield']);
        }
        return $groups;
    }
}
