<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Balance\CostDistributionCandidate;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class RoleBasedCatalogCostTest extends TestCase
{
    public function testAllProfilesRetainTheirBudgetAndKeepTheElectricityRule(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $backup = file_get_contents(dirname(__DIR__, 3) . '/docs/SAUVEGARDE_CATALOGUE_COUTS_AVANT_RP.md');
        $before = json_decode(substr(explode(str_repeat(chr(96), 3), $backup)[1], 4), true, 512, JSON_THROW_ON_ERROR);
        $calculator = new OperationCalculator($catalog);

        $actual = new Universe();
        $actual->slug = 'role-based-test';
        $actual->name = 'Recettes RP';
        $previous = clone $actual;
        foreach ($before as $group => $entries) {
            foreach ($entries as $slug => $ranks) {
                $previous->gameSettings['catalog_overrides'][$group][$slug]['cost_profiles'] = $ranks;
            }
        }
        $oldConfiguration = $catalog->forUniverse($previous);
        $newConfiguration = $catalog->forUniverse($actual);
        $count = 0;
        foreach ($newConfiguration['catalog'] as $group => $entries) {
            self::assertSame(array_keys($entries), array_keys($before[$group]));
            foreach ($entries as $slug => $definition) {
                foreach (GameCatalog::TIERS as $tier) {
                    $oldDefinition = $catalog->getTierForUniverse($previous, $group, $slug, $tier);
                    $newDefinition = $catalog->getTierForUniverse($actual, $group, $slug, $tier);
                    $oldBudget = CostDistributionCandidate::value($catalog->costs($oldDefinition));
                    $newBudget = CostDistributionCandidate::value($catalog->costs($newDefinition));
                    self::assertSame($oldBudget, $newBudget, "$group/$slug/$tier");
                    $orders = in_array($group, ['ships', 'defenses'], true) ? [[1, 1], [1, 100]] : [[1, 1], [5, 1]];
                    foreach ($orders as [$level, $quantity]) {
                        $old = $calculator->calculate($oldDefinition, $group, $level, $quantity, [], $oldConfiguration);
                        $new = $calculator->calculate($newDefinition, $group, $level, $quantity, [], $newConfiguration);
                        self::assertSame($old['duration'], $new['duration']);
                        self::assertSame($old['costs']['electricity'] ?? 0, $new['costs']['electricity'] ?? 0);
                        $valueBefore = CostDistributionCandidate::value($old['costs']);
                        $valueAfter = CostDistributionCandidate::value($new['costs']);
                        // L’arrondi d’un prérequis avancé se propage au rang d’entrée et à sa courbe.
                        $roundingTolerance = max(14, (int) ceil($valueBefore / 1000));
                        self::assertLessThanOrEqual($roundingTolerance, abs($valueBefore - $valueAfter), "$group/$slug/$tier/$level/$quantity");
                    }
                    ++$count;
                }
            }
        }
        self::assertSame(156, $count);
    }

    public function testRepresentativeRecipesUseMaterialsCorrespondingToTheirRole(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        foreach (GameCatalog::TIERS as $tier) {
            $armor = $catalog->costs($catalog->withTier($catalog->get('technologies', 'structural_armor'), $tier));
            self::assertGreaterThan(0, $armor['metal']);
            self::assertSame(0, $armor['nano']);
            self::assertSame(0, $armor['hydrogen']);
            self::assertSame(0, $armor['antimatter']);
            $bomber = $catalog->costs($catalog->withTier($catalog->get('ships', 'bomber'), $tier));
            self::assertGreaterThan(0, $bomber['hydrogen']);
            self::assertSame(0, $bomber['antimatter']);
        }
        $trap = $catalog->costs($catalog->withTier($catalog->get('buildings', 'mine_antimatter'), 'basic'));
        self::assertGreaterThan(0, $trap['hydrogen']);
        self::assertSame(0, $trap['antimatter']);
        $shield = $catalog->costs($catalog->withTier($catalog->get('technologies', 'shield_harmonics'), 'basic'));
        self::assertGreaterThan(0, $shield['antimatter']);
    }
}
