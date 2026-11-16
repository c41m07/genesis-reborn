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

    public function testSelectingOneRankPreservesAllOtherEffectiveProfilesAndTheSource(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'candidate-one-rank-x1';
        $universe->name = 'Candidat isolé';
        $before = $catalog->forUniverse($universe);
        $candidate = new CostDistributionCandidate($catalog);
        foreach (CostDistributionCandidate::DEFAULT_TIERS as $selected) {
            $comparison = $candidate->compare($universe, [$selected]);
            $changed = 0;
            foreach ($comparison['profiles'] as $row) {
                self::assertSame($row['value'], CostDistributionCandidate::value($row['candidate']));
                if ($row['tier'] !== $selected) {
                    self::assertSame($row['current'], $row['candidate'], $selected . ' ne doit pas modifier ' . $row['slug'] . '/' . $row['tier']);
                } elseif ($row['current'] !== $row['candidate']) {
                    $changed++;
                }
            }
            self::assertSame(52, $changed);
            self::assertSame($before, $catalog->forUniverse($universe));
            self::assertSame([], $universe->gameSettings);
        }
    }

    public function testSuperiorNeedsAnExplicitRedistributionProfile(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $this->expectException(\InvalidArgumentException::class);
        (new CostDistributionCandidate($catalog))->compare($universe, ['superior']);
    }


    public function testExplicitThreeRankProfileConservesEveryBudgetIncludingOddSuperiorValues(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'proposed-costs-x1';
        $universe->name = 'Grille proposée';
        $before = $catalog->forUniverse($universe);
        $profiles = [
            'basic' => ['metal'=>40, 'nano'=>60],
            'intermediate' => ['metal'=>30, 'nano'=>50, 'antimatter'=>20],
            'superior' => ['metal'=>25, 'nano'=>35, 'antimatter'=>30, 'hydrogen'=>10],
        ];
        $report = (new CostDistributionCandidate($catalog))->compare($universe, GameCatalog::TIERS, $profiles);
        self::assertCount(156, $report['profiles']);
        foreach ($report['profiles'] as $row) {
            self::assertSame($row['value'], CostDistributionCandidate::value($row['candidate']));
            foreach ($row['candidate'] as $amount) {
                self::assertIsInt($amount);
                self::assertGreaterThanOrEqual(0, $amount);
            }
            if ($row['tier'] !== 'superior') {
                self::assertSame(0, $row['candidate']['hydrogen']);
            }
        }
        $civil = $report['overrides']['ships']['hydrogen_collector']['cost_profiles'];
        self::assertSame(['metal'=>320,'nano'=>240,'antimatter'=>0,'hydrogen'=>0], $civil['basic']);
        self::assertSame(['metal'=>300,'nano'=>250,'antimatter'=>50,'hydrogen'=>0], $civil['intermediate']);
        $trap = $report['overrides']['buildings']['mine_antimatter']['cost_profiles']['superior'];
        self::assertSame(1875, CostDistributionCandidate::value($trap));
        self::assertGreaterThan(0, $trap['hydrogen']);
        self::assertSame($before, $catalog->forUniverse($universe));
        self::assertSame([], $universe->gameSettings);
    }

    public function testIncompletePercentagesAreRejectedInsteadOfBeingNormalized(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Chaque profil candidat doit totaliser 100 %.');
        (new CostDistributionCandidate($catalog))->compare(new Universe(), ['basic'], ['basic'=>['metal'=>40,'nano'=>50]]);
    }

    public function testOddSuperiorBudgetWithoutHydrogenIsRejectedInsteadOfChangingItsValue(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $universe = new Universe();
        $universe->slug = 'odd-superior';
        $universe->name = 'Budget impair';
        $this->expectException(\DomainException::class);
        (new CostDistributionCandidate($catalog))->compare($universe, ['superior'], ['superior'=>['metal'=>30,'nano'=>40,'antimatter'=>30]]);
    }

}
