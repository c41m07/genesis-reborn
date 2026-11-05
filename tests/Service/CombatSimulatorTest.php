<?php
namespace App\Tests\Service;

use App\Service\Combat\CombatSimulator;
use PHPUnit\Framework\TestCase;

final class CombatSimulatorTest extends TestCase
{
    private function unit(string $slug, int $hp, int $attack, int $shots = 1): array
    {
        return [
            'slug' => $slug,
            'quantity' => 1,
            'hp' => $hp,
            'precision' => 100,
            'tracking' => 100,
            'evasion' => 0,
            'signature' => 100,
            'base_attack' => $attack,
            'shield' => 0,
            'shots_min' => $shots,
            'shots_max' => $shots,
        ];
    }

    private function participants(int $attackerArrival = 1): array
    {
        return [
            ['id' => 'attacker-a', 'role' => 'attacker', 'camp' => 'attackers', 'arrival_round' => $attackerArrival, 'units' => [$this->unit('fighter', 100, 20)]],
            ['id' => 'defender-b', 'role' => 'defender', 'camp' => 'defenders', 'arrival_round' => 1, 'units' => [$this->unit('cruiser', 100, 20)]],
        ];
    }

    public function testSameSnapshotAndSeedProduceIdenticalCombatLog(): void
    {
        $simulator = new CombatSimulator();
        $first = $simulator->simulate($this->participants(), [], ['fighter' => ['cruiser' => 1.3]], 5, 12345);
        $second = $simulator->simulate($this->participants(), [], ['fighter' => ['cruiser' => 1.3]], 5, 12345);

        self::assertSame($first, $second);
        self::assertSame(12345, $first['seed']);
    }

    public function testArrivingUnitsDoNotActOrTakeDamageBeforeTheirEntryRound(): void
    {
        $simulator = new CombatSimulator();
        $result = $simulator->simulate($this->participants(3), [], [], 4, 981);

        self::assertSame([], $result['rounds'][0]['shots']);
        self::assertSame([], $result['rounds'][1]['shots']);
        self::assertNotEmpty($result['rounds'][2]['shots']);
    }

    public function testDestroyedUnitCannotFireLaterInTheSameRound(): void
    {
        $simulator = new CombatSimulator();
        $participants = [
            ['id' => 'attacker-a', 'role' => 'attacker', 'camp' => 'attackers', 'arrival_round' => 1, 'units' => [$this->unit('fighter', 1000, 10000)]],
            ['id' => 'defender-b', 'role' => 'defender', 'camp' => 'defenders', 'arrival_round' => 1, 'units' => [$this->unit('cruiser', 1, 0)]],
        ];
        $destroyedBeforeTurn = false;
        for ($seed = 0; $seed < 100; ++$seed) {
            $result = $simulator->simulate($participants, [], [], 1, $seed);
            if (count($result['rounds'][0]['shots']) === 1 && ($result['losses']['defender-b:cruiser'] ?? 0) === 1) {
                $destroyedBeforeTurn = true;
                break;
            }
        }

        self::assertTrue($destroyedBeforeTurn, 'Le test doit trouver un ordre où le défenseur est détruit avant son tour.');
    }

    public function testHitChanceUsesDocumentedBounds(): void
    {
        $simulator = new CombatSimulator();
        self::assertSame(95.0, $simulator->hitChance(['precision' => 100, 'tracking' => 100], ['evasion' => 0, 'signature' => 100]));
        self::assertSame(15.0, $simulator->hitChance(['precision' => 0, 'tracking' => 0], ['evasion' => 100, 'signature' => 0]));
    }


    public function testPlanetaryDefensesOnlyTargetAttackingFleets(): void
    {
        $attacker = $this->unit('fighter', 1000, 0);
        $externalDefender = $this->unit('frigate', 1000, 0);
        $turret = $this->unit('ground_turret', 1000, 1000);
        $participants = [
            ['id' => 'attacker-a', 'role' => 'attacker', 'camp' => 'attackers', 'arrival_round' => 1, 'units' => [$attacker]],
            ['id' => 'defender-c', 'role' => 'defender', 'camp' => 'defenders', 'arrival_round' => 1, 'units' => [$externalDefender]],
        ];
        $defenses = [['id' => 'turret-1', 'camp' => 'defenders', ...$turret]];
        $result = (new CombatSimulator())->simulate($participants, $defenses, [], 1, 42);
        $defenseKey = 'planet:turret-1:static_defense:ground_turret:1';
        $defenseShots = array_filter($result['rounds'][0]['shots'], static fn (array $shot): bool => $shot['attacker'] === $defenseKey);

        self::assertNotEmpty($defenseShots);
        foreach ($defenseShots as $shot) {
            self::assertStringStartsWith('attacker-a:attacker:', $shot['target']);
        }
    }

    public function testDepletedShieldsStayDownDuringCombatAndRestoreAfterwards(): void
    {
        $attacker = $this->unit('fighter', 100, 30);
        $target = $this->unit('cruiser', 100, 0);
        $target['shield'] = 20;
        $participants = [
            ['id' => 'a', 'role' => 'attacker', 'camp' => 'attackers', 'arrival_round' => 1, 'units' => [$attacker]],
            ['id' => 'b', 'role' => 'defender', 'camp' => 'defenders', 'arrival_round' => 1, 'units' => [$target]],
        ];
        $simulator = new CombatSimulator();
        $result = null;
        for ($seed = 0; $seed < 200; ++$seed) {
            $candidate = $simulator->simulate($participants, [], [], 5, $seed, 2);
            $shots = array_values(array_filter(
                [...$candidate['rounds'][0]['shots'], ...$candidate['rounds'][1]['shots']],
                static fn (array $shot): bool => str_starts_with($shot['attacker'], 'a:attacker:'),
            ));
            if (count($shots) === 2 && $shots[0]['hit'] && $shots[1]['hit']) {
                $result = $candidate;
                break;
            }
        }
        self::assertNotNull($result);
        $shots = array_values(array_filter(
            [...$result['rounds'][0]['shots'], ...$result['rounds'][1]['shots']],
            static fn (array $shot): bool => str_starts_with($shot['attacker'], 'a:attacker:'),
        ));
        self::assertSame(20.0, $shots[0]['shield_damage']);
        self::assertSame(10.0, $shots[0]['structure_damage']);
        self::assertSame(0.0, $shots[1]['shield_damage']);
        $cruiser = array_values(array_filter($result['survivors'], static fn (array $unit): bool => $unit['slug'] === 'cruiser'))[0];
        self::assertSame(20.0, $cruiser['shield']);
    }

    public function testExtraShotChanceAddsAnIndependentShotPerBaseShot(): void
    {
        $attacker=$this->unit('fighter',10000,1);$attacker['extra_shot_chances']=[1.0];
        $participants=[['id'=>'attacker-a','role'=>'attacker','camp'=>'attackers','arrival_round'=>1,'units'=>[$attacker]],['id'=>'defender-b','role'=>'defender','camp'=>'defenders','arrival_round'=>1,'units'=>[$this->unit('cruiser',10000,0)]]];
        $result=(new CombatSimulator())->simulate($participants,[],[],1,11);$shots=array_values(array_filter($result['rounds'][0]['shots'],static fn(array $shot):bool=>str_starts_with($shot['attacker'],'attacker-a:attacker:')));
        self::assertCount(2,$shots);self::assertFalse($shots[0]['extra_shot']);self::assertTrue($shots[1]['extra_shot']);
    }

    public function testPartiallyDamagedShieldsStillRechargeFivePercentOfTheirMaximum(): void
    {
        $participants = $this->participants();
        $participants[0]['units'] = [$this->unit('fighter', 100, 5)];
        $target = $this->unit('cruiser', 100, 0);
        $target['shield'] = 20;
        $participants[1]['units'] = [$target];
        $checked = false;
        for ($seed = 0; $seed < 20; ++$seed) {
            $result = (new CombatSimulator())->simulate($participants, [], [], 2, $seed, null, 5, false);
            $first = $result['rounds'][0]['shots'][0];
            $second = $result['rounds'][1]['shots'][0];
            if (!$first['hit'] || !$second['hit']) continue;
            self::assertSame(15.0, $first['target_shield']);
            self::assertSame(11.0, $second['target_shield']);
            self::assertSame(12.0, array_values(array_filter($result['survivors'], static fn(array $unit): bool => $unit['slug'] === 'cruiser'))[0]['shield']);
            $checked = true;
            break;
        }
        self::assertTrue($checked);
    }

    public function testCriticalExplosionOnlyStartsOnTheNextSuccessfulHitAndIsProgressive(): void
    {
        foreach ([75=>0.0, 80=>0.2, 90=>0.6, 99=>0.96] as $damage => $expectedRate) {
            $participants = $this->participants();
            $participants[0]['units'] = [$this->unit('fighter', 1000, $damage, 2)];
            $participants[1]['units'] = [$this->unit('cruiser', 100, 0)];
            $critical = 0;
            $eligible = 0;
            for ($seed = 0; $seed < 512; ++$seed) {
                $result = (new CombatSimulator())->simulate($participants, [], [], 1, $seed);
                [$first, $second] = $result['rounds'][0]['shots'];
                if ($first['hit']) {
                    self::assertFalse($first['destroyed']);
                    self::assertSame((float)(100-$damage), $first['target_hp']);
                    if (!$second['hit']) {
                        self::assertFalse($second['destroyed']);
                        self::assertSame($first['target_hp'], $second['target_hp']);
                    }
                }
                if (!$first['hit'] || !$second['hit']) continue;
                ++$eligible;
                if ($second['destroyed'] && $second['damage'] === 0.0) ++$critical;
            }
            self::assertGreaterThan(400, $eligible);
            self::assertEqualsWithDelta($expectedRate, $critical/$eligible, 0.07, "Structure restante : ".(100-$damage).'%');
        }
    }

    public function testDamagedTargetsArePrioritizedAcrossDifferentShooters(): void
    {
        $participants = $this->participants();
        $attacker = $this->unit('fighter', 1000, 30);
        $attacker['quantity'] = 3;
        $target = $this->unit('cruiser', 100, 0);
        $target['quantity'] = 2;
        $participants[0]['units'] = [$attacker];
        $participants[1]['units'] = [$target];
        for ($seed = 0; $seed < 32; ++$seed) {
            $result = (new CombatSimulator())->simulate($participants, [], [], 1, $seed);
            $hits = array_values(array_filter($result['rounds'][0]['shots'], static fn(array $shot): bool => $shot['hit']));
            self::assertGreaterThanOrEqual(2, count($hits));
            self::assertCount(1, array_unique(array_column($hits, 'target')));
        }
    }

    public function testLosingHalfOfTotalDurabilityReducesVolleyPowerToOneQuarter(): void
    {
        $participants = $this->participants();
        $participants[0]['units'] = [$this->unit('fighter', 1000, 100)];
        $target = $this->unit('cruiser', 100, 40, 2);
        $target['shield'] = 100;
        $participants[1]['units'] = [$target];
        $checked = false;
        for ($seed = 0; $seed < 100; ++$seed) {
            $result = (new CombatSimulator())->simulate($participants, [], [], 1, $seed);
            $shots = $result['rounds'][0]['shots'];
            if ($shots[0]['hit'] && $shots[0]['shield_damage'] === 100.0 && $shots[1]['hit'] && $shots[2]['hit']) {
                self::assertSame(10.0, $shots[1]['damage']);
                self::assertSame(10.0, $shots[2]['damage']);
                $checked = true;
                break;
            }
        }
        self::assertTrue($checked);
    }

    public function testInvalidUnitStatisticsAreRejected(): void
    {
        $participants = $this->participants();
        $participants[0]['units'][0]['hp'] = -1;
        $this->expectException(\DomainException::class);
        (new CombatSimulator())->simulate($participants, [], [], 5, 42);
    }
    public function testTierAdvantageChangesHitChanceWithoutMultiplyingBaseStats():void
    {
        $simulator=new CombatSimulator();$base=['precision'=>70,'tracking'=>76,'evasion'=>60,'signature'=>20];$expected=[[71,41,15],[95,71,41],[95,95,71]];$tiers=['basic','intermediate','superior'];
        foreach($tiers as $i=>$attackerTier)foreach($tiers as $j=>$targetTier)self::assertSame((float)$expected[$i][$j],$simulator->hitChance($base+['tier'=>$attackerTier],$base+['tier'=>$targetTier]));
    }

}
