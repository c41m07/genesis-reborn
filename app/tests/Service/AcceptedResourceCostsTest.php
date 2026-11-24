<?php

namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Balance\CostDistributionCandidate;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;
final class AcceptedResourceCostsTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/Fixtures/resource-costs-2026-11-19.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testAll156AcceptedProfilesKeepTheirOwnPreviousBudgets(): void
    {
        $fixture = $this->fixture();
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $count = 0;
        $shares = [
            'basic' => ['metal' => 48, 'nano' => 52, 'antimatter' => 0, 'hydrogen' => 0],
            'intermediate' => ['metal' => 34, 'nano' => 36, 'antimatter' => 30, 'hydrogen' => 0],
            'superior' => ['metal' => 28, 'nano' => 31, 'antimatter' => 31, 'hydrogen' => 10],
        ];
        foreach (['buildings' => 13, 'technologies' => 19, 'ships' => 16, 'defenses' => 4] as $group => $size) {
            self::assertCount($size, $catalog->all($group));
            foreach ($fixture['profiles'][$group] as $slug => $entry) {
                foreach (GameCatalog::TIERS as $tier) {
                    $costs = $catalog->costs($catalog->withTier($catalog->get($group, $slug), $tier));
                    $budget = CostDistributionCandidate::value($entry[$tier]);
                    self::assertSame($budget, CostDistributionCandidate::value($costs), "{$group}/{$slug}/{$tier}");
                    if ($tier === 'basic') {
                        self::assertSame($entry[$tier], $costs, "{$group}/{$slug}/{$tier}");
                    }
                    foreach ($shares[$tier] as $resource => $percent) {
                        if ($percent === 0) {
                            self::assertSame(0, $costs[$resource], "{$group}/{$slug}/{$tier}/{$resource}");
                        }
                        self::assertGreaterThanOrEqual(0, $costs[$resource]);
                        self::assertLessThanOrEqual(8.0, abs(CostDistributionCandidate::WEIGHTS[$resource] * $costs[$resource] - $budget * $percent / 100));
                    }
                    $count++;
                }
            }
        }
        self::assertSame(156, $count);
        self::assertSame(1875, CostDistributionCandidate::value($catalog->costs($catalog->withTier($catalog->get('buildings', 'mine_antimatter'), 'superior'))));
    }

    public function testCurveBoundariesAndBatchesPreserveQuotesWithinRounding(): void
    {
        $fixture = $this->fixture();
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        // Comparaison des deux répartitions de novembre, avec les courbes de cette étude.
        $property = new \ReflectionProperty(GameCatalog::class, 'data');
        $data = $property->getValue($catalog);
        $data['rules']['progression_curves'] = \App\Tests\Support\ProducerBalanceReference::fixture()['historical_rules']['progression_curves'];
        unset($data['rules']['rank_entry_material_percent']);
        $property->setValue($catalog, $data);
        $calculator = new OperationCalculator($catalog);
        $current = new Universe();
        $current->slug = 'accepted-prices';
        $current->name = 'Prix retenus';
        $previous = new Universe();
        $previous->slug = 'previous-prices';
        $previous->name = 'Prix précédents';
        foreach ($fixture['profiles'] as $group => $entries) {
            foreach ($entries as $slug => $entry) {
                $previous->gameSettings['catalog_overrides'][$group][$slug]['cost_profiles'] = $entry;
            }
        }
        $previous->gameSettings['catalog_overrides']['technologies']['colonial_expansion']['research_time_base'] = $fixture['research_time_base'];
        $now = $catalog->forUniverse($current);
        $before = $catalog->forUniverse($previous);
        $count = 0;
        foreach ($now['catalog'] as $group => $definitions) {
            foreach ($definitions as $slug => $definition) {
                $levels = in_array($group, ['buildings', 'technologies'], true) ? array_values(array_filter([
                    1,
                    2,
                    5,
                    6,
                    10,
                    11,
                    15,
                    16,
                    20,
                    21,
                    40,
                    41,
                    80,
                    81,
                    120,
                    121,
                ], static fn(int $level): bool => $level <= ($definition['max_level'] ?? PHP_INT_MAX))) : [1];
                $quantities = in_array($group, ['ships', 'defenses'], true) ? [1, 10, 100] : [1];
                foreach (GameCatalog::TIERS as $tier) {
                    foreach ($levels as $level) {
                        foreach ($quantities as $quantity) {
                            foreach ([[], ['basic' => 10, 'intermediate' => 5], array_fill_keys(GameCatalog::TIERS, 25)] as $ranks) {
                                $tech = array_fill_keys(['nanomaterial_synthesis', 'engineering_foundations', 'naval_engineering'], $ranks);
                                $buildings = ['lab' => $ranks, 'shipyard' => $ranks];
                                $old = $calculator->calculate($catalog->getTierForUniverse($previous, $group, $slug, $tier), $group, $level, $quantity, $buildings, $before, $tech);
                                $new = $calculator->calculate($catalog->getTierForUniverse($current, $group, $slug, $tier), $group, $level, $quantity, $buildings, $now, $tech);
                                if ($slug === 'colonial_expansion') {
                                    // L'ancienne durée est déjà arrondie. L'écart final peut atteindre une seconde.
                                    self::assertEqualsWithDelta($old['duration'] / 10, $new['duration'], 1);
                                    self::assertSame(100 * (int) ceil($new['duration'] / 60), $new['costs']['electricity']);
                                } else {
                                    self::assertSame($old['duration'], $new['duration']);
                                    self::assertSame($old['costs']['electricity'] ?? 0, $new['costs']['electricity'] ?? 0);
                                }
                                if ($tier === 'basic') {
                                    unset($old['costs']['electricity'], $new['costs']['electricity']);
                                    self::assertSame($old['costs'], $new['costs']);
                                } else {
                                    // Croissance puis remise peuvent chacune arrondir par ressource.
                                    $difference = abs(CostDistributionCandidate::value($new['costs']) - CostDistributionCandidate::value($old['costs']));
                                    self::assertLessThanOrEqual(28, $difference, "{$group}/{$slug}/{$tier}");
                                }
                                $count++;
                            }
                        }
                    }
                }
            }
        }
        self::assertSame(3636, $count);
    }

    public function testPartialUniverseOverrideRemainsAuthoritative(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'custom-prices';
        $universe->name = 'Prix personnalisés';
        $settings = [
            'multipliers' => ['research_time' => 0.5],
            'catalog_overrides' => [
                'ships' => ['fighter' => ['cost_profiles' => ['superior' => ['hydrogen' => 999]]]],
                'technologies' => ['colonial_expansion' => ['research_time_base' => 288000]],
            ],
        ];
        $universe->gameSettings = $settings;
        $definition = $catalog->getTierForUniverse($universe, 'ships', 'fighter', 'superior');
        self::assertSame(999, $definition['cost_profiles']['superior']['hydrogen']);
        self::assertSame($catalog->get('ships', 'fighter')['cost_profiles']['superior']['metal'], $definition['cost_profiles']['superior']['metal']);
        $research = $catalog->getTierForUniverse($universe, 'technologies', 'colonial_expansion', 'intermediate');
        $quote = (new OperationCalculator($catalog))->calculate($research, 'technologies', 2, 1, [], $catalog->forUniverse($universe));
        self::assertSame(243000, $quote['duration']);
        self::assertSame(405000, $quote['costs']['electricity']);
        self::assertSame($settings, $universe->gameSettings);
    }
}
