<?php

namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Balance\CostDistributionCandidate;
use App\Service\Catalog\GameCatalog;
use PHPUnit\Framework\TestCase;
final class CostDistributionCandidateTest extends TestCase
{
    public function testAll156BudgetsIncludingDefensesAreConservedWithoutChangingTheSourceCatalog(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'candidate-x1';
        $universe->name = 'Candidat';
        $before = $catalog->forUniverse($universe);
        $report = (new CostDistributionCandidate($catalog))->compare($universe);
        self::assertCount(156, $report['profiles']);
        self::assertSame($before, $catalog->forUniverse($universe));
        foreach ($report['profiles'] as $row) {
            self::assertSame($row['value'], CostDistributionCandidate::value($row['candidate']), $row['slug'] . '/' . $row['tier']);
            foreach ($row['candidate'] as $amount) {
                self::assertGreaterThanOrEqual(0, $amount);
            }
            if ($row['tier'] === 'superior') {
                self::assertSame($row['current'], $row['candidate']);
            } else {
                self::assertSame(0, $row['candidate']['hydrogen']);
            }
            if ($row['tier'] === 'basic') {
                self::assertSame(0, $row['candidate']['antimatter']);
            }
        }
        self::assertSame([], $universe->gameSettings);
    }

    public function testCivilExamplesUseTheirOwnRankBudgetsOnce(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'candidate-x1';
        $universe->name = 'Candidat';
        $report = (new CostDistributionCandidate($catalog))->compare($universe);
        foreach (['hydrogen_collector', 'solar_satellite'] as $slug) {
            $profiles = $report['overrides']['ships'][$slug]['cost_profiles'];
            self::assertSame(['metal' => 400, 'nano' => 200, 'antimatter' => 0, 'hydrogen' => 0], $profiles['basic']);
            self::assertSame(['metal' => 400, 'nano' => 200, 'antimatter' => 50, 'hydrogen' => 0], $profiles['intermediate']);
            self::assertSame(2500, CostDistributionCandidate::value($profiles['superior']));
        }
    }
}
