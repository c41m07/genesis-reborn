<?php
namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\CombatSimulator;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class R14RegressionTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/combat-r14.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testCombatStatsResearchAndBudgetsMatchFrozenStudyInputs(): void
    {
        $fixture = $this->fixture();
        $prices = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/ship-costs-2026-11-05.json'), true, 512, JSON_THROW_ON_ERROR);
        $commonPrices = json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/catalog-costs-2026-11-05.json'), true, 512, JSON_THROW_ON_ERROR);
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $universe = new Universe();
        $universe->slug = 'simulation';
        $universe->name = 'Simulation';
        $technology = new TechnologyEffectCalculator($catalog);
        $operations = new OperationCalculator($catalog);
        foreach ($fixture['definitions'] as $research => $tiers) {
            $ranks = $research === 'max' ? ['basic'=>25, 'intermediate'=>25, 'superior'=>25] : ['basic'=>10, 'intermediate'=>5, 'superior'=>0];
            $levels = $research === 'none' ? [] : array_fill_keys(['weapons_engineering','target_acquisition','structural_armor','shield_harmonics','evasive_navigation','weapon_calibration','rapid_fire_cycle','signature_reduction','naval_engineering'], $ranks);
            foreach ($tiers as $tier => $definitions) foreach ($definitions as $slug => $expected) {
                $unit = $catalog->getTierForUniverse($universe, 'ships', $slug, $tier);
                $costs = $operations->calculate($unit, 'ships', 1, 1, [], $catalog->forUniverse($universe), $levels)['costs'];
                $unit = $technology->applyToShips([$unit], $levels, $universe)[0];
                foreach ($expected['unit'] as $field => $value) self::assertEquals($value, $unit[$field] ?? null, "$slug/$tier/$research/$field");
                $price = $prices['ships'][$slug]['costs'][$tier][$research][1];
                self::assertSame($expected['cost'], $price['old']['costs'], "$slug/$tier/$research/référence historique R14");
                self::assertSame($commonPrices['entries']['ships'][$slug]['costs'][$tier][$research][1][1]['new']['costs'], $costs, "$slug/$tier/$research/nouvelle répartition réelle");
                if ($research === 'none') {
                    $value = static fn(array $c): int => 2*$c['metal']+4*$c['nano']+8*$c['antimatter']+$c['hydrogen'];
                    self::assertSame($value($expected['cost']), $value($costs), "$slug/$tier/budget R14 conservé");
                }
            }
        }
        $actual = $catalog->get('combat_modifiers', 'modifiers');
        foreach ($fixture['modifiers'] as $attacker => $targets) foreach ($targets as $target => $factor) self::assertEquals($factor, $actual[$attacker][$target], "$attacker/$target");
    }

    public function testFullCombatLogsMatchIndependentReference(): void
    {
        $fixture = $this->fixture();
        $simulator = new CombatSimulator();
        foreach ($fixture['cases'] as $index => $case) {
            $participants = [];
            foreach ($case['compositions'] as $i => $composition) {
                $units = [];
                foreach ($composition as $slug => $quantity) $units[] = $fixture['definitions'][$case['research']][$case['tiers'][$i]][$slug]['unit'] + ['quantity'=>$quantity];
                $role = $i === (int)$case['flip'] ? 'attacker' : 'defender';
                $participants[] = ['id'=>$i === 0 ? 'a' : 'b', 'role'=>$role, 'camp'=>$role === 'attacker' ? 'attackers' : 'defenders', 'arrival_round'=>1, 'units'=>$units];
            }
            $result = $simulator->simulate($participants, [], $fixture['modifiers'], 10, $case['seed'], null, 5, false);
            self::assertSame($case['result_sha256'], hash('sha256', json_encode($result, JSON_THROW_ON_ERROR)), "Combat R14 #$index, graine ".$case['seed']);
        }
    }
}
