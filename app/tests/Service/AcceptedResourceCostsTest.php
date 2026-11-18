<?php

namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;
final class AcceptedResourceCostsTest extends TestCase
{
    private function fixture(): array
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/Fixtures/resource-costs-2026-11-16.json'), true, 512, JSON_THROW_ON_ERROR);
    }

    private function value(array $costs): int
    {
        return 2 * ($costs['metal'] ?? 0) + 4 * ($costs['nano'] ?? 0) + 8 * ($costs['antimatter'] ?? 0) + ($costs['hydrogen'] ?? 0);
    }

    public function testAll156AcceptedProfilesKeepTheirOwnPreviousBudgets(): void
    {
        $fixture = $this->fixture();
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $count = 0;
        $shares = [
            'basic' => ['metal' => 48, 'nano' => 52, 'antimatter' => 0, 'hydrogen' => 0],
            'intermediate' => ['metal' => 35, 'nano' => 35, 'antimatter' => 30, 'hydrogen' => 0],
            'superior' => ['metal' => 25, 'nano' => 35, 'antimatter' => 30, 'hydrogen' => 10],
        ];
        foreach (['buildings' => 13, 'technologies' => 19, 'ships' => 16, 'defenses' => 4] as $group => $size) {
            self::assertCount($size, $catalog->all($group));
            foreach ($fixture['entries'][$group] as $slug => $entry) {
                foreach (GameCatalog::TIERS as $tier) {
                    $costs = $catalog->costs($catalog->withTier($catalog->get($group, $slug), $tier), 1, 1, $group);
                    $budget = $this->value($entry['profiles'][$tier]);
                    self::assertSame($budget, $this->value($costs), "{$group}/{$slug}/{$tier}");
                    if ($tier === 'superior') {
                        self::assertSame($entry['profiles'][$tier], $costs, "{$group}/{$slug}/{$tier}");
                    }
                    foreach ($shares[$tier] as $resource => $percent) {
                        if ($percent === 0) {
                            self::assertSame(0, $costs[$resource], "{$group}/{$slug}/{$tier}/{$resource}");
                        }
                        self::assertGreaterThanOrEqual(0, $costs[$resource]);
                        self::assertLessThanOrEqual(8.0, abs($fixture['weights'][$resource] * $costs[$resource] - $budget * $percent / 100));
                    }
                    $count++;
                }
            }
        }
        self::assertSame(156, $count);
        self::assertSame(1875, $this->value($catalog->costs($catalog->withTier($catalog->get('buildings', 'mine_antimatter'), 'superior'))));
    }

    public function testCurveBoundariesResearchAndBatchesKeepDurationAndElectricityTax(): void
    {
        $fixture = $this->fixture();
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $calculator = new OperationCalculator($catalog);
        $current = new Universe();
        $current->slug = 'accepted-prices';
        $current->name = 'Prix retenus';
        $previous = new Universe();
        $previous->slug = 'previous-prices';
        $previous->name = 'Prix précédents';
        foreach ($fixture['entries'] as $group => $entries) {
            foreach ($entries as $slug => $entry) {
                $previous->gameSettings['catalog_overrides'][$group][$slug]['cost_profiles'] = $entry['profiles'];
            }
        }
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
                                self::assertSame($old['duration'], $new['duration']);
                                self::assertSame($old['costs']['electricity'] ?? 0, $new['costs']['electricity'] ?? 0);
                                if ($tier === 'superior') {
                                    self::assertSame($old['costs'], $new['costs']);
                                } else {
                                    // La croissance et la remise arrondissent chaque ressource séparément.
                                    $difference = abs($this->value($new['costs']) - $this->value($old['costs']));
                                    self::assertLessThanOrEqual(14, $difference, "{$group}/{$slug}/{$tier}");
                                }
                                $count++;
                            }
                        }
                    }
                }
            }
        }
        self::assertSame($fixture['quote_validation']['quotes'], $count);
    }

    public function testPartialUniverseOverrideRemainsAuthoritative(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'custom-prices';
        $universe->name = 'Prix personnalisés';
        $settings = ['catalog_overrides' => ['ships' => ['fighter' => ['cost_profiles' => ['superior' => ['hydrogen' => 999]]]]]];
        $universe->gameSettings = $settings;
        $definition = $catalog->getTierForUniverse($universe, 'ships', 'fighter', 'superior');
        self::assertSame(999, $definition['cost_profiles']['superior']['hydrogen']);
        self::assertSame($catalog->get('ships', 'fighter')['cost_profiles']['superior']['metal'], $definition['cost_profiles']['superior']['metal']);
        self::assertSame($settings, $universe->gameSettings);
    }
}
