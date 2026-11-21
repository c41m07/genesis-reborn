<?php

namespace App\Tests\Unit;

use App\Entity\{Planet, PlayerUniverse, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueInsights, EconomicUnitCapacity, ResourceService, TechnologyGuideStep};
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Event\GameEventService;
use App\Service\Player\VacationCalendar;
use App\Service\Requirement\{CatalogAccess, RequirementChecker};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class TechnologyGuideStepTest extends TestCase
{
    public function testClosestMissingLevelWinsAndTreatsRanksIndependently(): void
    {
        $lab = self::requirement('buildings', 'lab', 'basic', 5, 7);
        $masking = self::requirement('technologies', 'signature_masking', 'basic', 2, 2);
        $nano = self::requirement('technologies', 'nanomaterial_synthesis', 'basic', 2, 3);
        $nanoIntermediate = self::requirement('technologies', 'nanomaterial_synthesis', 'intermediate', 0, 3);
        $nodes = [
            'technologies:signature_reduction-basic' => self::node([$lab, $masking, $nano, $nanoIntermediate]),
            'buildings:lab-basic' => self::node([], true, true),
            'technologies:nanomaterial_synthesis-basic' => self::node([], true, true),
            'technologies:nanomaterial_synthesis-intermediate' => self::node([], true, true),
        ];
        $inspected = [];
        $step = TechnologyGuideStep::resolve('technologies:signature_reduction-basic', static function (string $key) use ($nodes, &$inspected): ?array {
            $inspected[$key] = true;
            return $nodes[$key] ?? null;
        });
        self::assertSame(['state' => 'step', 'requirement' => $nano], $step);
        self::assertSame(['technologies:signature_reduction-basic', 'technologies:nanomaterial_synthesis-basic'], array_keys($inspected));
    }

    public function testTieKeepsCatalogueOrderAndDirectBeatsBlockedCloserNode(): void
    {
        $lab = self::requirement('buildings', 'lab', 'superior', 2, 4);
        $first = self::requirement('buildings', 'first', 'basic', 0, 2);
        $blocked = self::requirement('technologies', 'blocked', 'basic', 0, 1);
        $nodes = [
            'root' => self::node([$lab, $first, $blocked]),
            'buildings:lab-superior' => self::node([], true),
            'buildings:first-basic' => self::node([], true),
            'technologies:blocked-basic' => self::node([$first]),
        ];
        self::assertSame(['state' => 'step', 'requirement' => $lab], $this->resolve($nodes));
    }

    public function testBlockedResearchWithAcquiredAccessFallsBackToRealLocalLaboratory(): void
    {
        $lab = self::requirement('buildings', 'lab', 'intermediate', 0, 1);
        $nano = self::requirement('technologies', 'nano', 'intermediate', 0, 3);
        $obsolete = self::requirement('buildings', 'obsolete', 'basic', 0, 1);
        $nodes = [
            'root' => self::node([$nano]),
            'technologies:nano-intermediate' => self::node([$obsolete], false, true, $lab),
            'buildings:lab-intermediate' => self::node([], true),
            'buildings:obsolete-basic' => self::node([], true),
        ];
        self::assertSame(['state' => 'step', 'requirement' => $lab], $this->resolve($nodes));
        $nodes['buildings:lab-intermediate'] = self::node([$nano]);
        self::assertSame(['state' => 'unavailable'], $this->resolve($nodes));
    }

    public function testUnreachableLevelDoesNotProposeAnUpgradeOrAnUnnecessaryAncestor(): void
    {
        $ancestor = self::requirement('buildings', 'upstream', 'basic', 0, 1);
        $capped = self::requirement('technologies', 'capped', 'basic', 4, 6);
        $nodes = [
            'root' => self::node([$capped]),
            'technologies:capped-basic' => self::node([$ancestor], false, false, null, 5),
            'buildings:upstream-basic' => self::node([], true),
        ];
        self::assertSame(['state' => 'unavailable'], $this->resolve($nodes));
    }

    public function testAlreadyAcquiredAndSatisfiedAreDistinctFromUnavailable(): void
    {
        $obsolete = self::requirement('buildings', 'obsolete', 'basic', 0, 1);
        self::assertSame(['state' => 'acquired'], $this->resolve(['root' => self::node([$obsolete], false, true)]));
        self::assertSame(['state' => 'prerequisites_met'], $this->resolve(['root' => self::node([], true)]));
        self::assertSame(['state' => 'unavailable'], $this->resolve(['root' => self::node()]));
        self::assertSame(['state' => 'unavailable'], $this->resolve([]));
    }

    public function testActualCatalogueAndGuideShareTheRecommendationWithoutDatabaseReads(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getRepository')->willThrowException(new \LogicException('La projection ne doit pas relire les niveaux.'));
        $repo = new GameRepository($entityManager);
        $manager = new GameManager($entityManager);
        $clock = new MockClock('2026-11-21T00:00:00Z');
        $resources = new ResourceService(
            $repo, $manager, $catalog, $clock, new GameEventService($repo, $manager, $clock),
            new VacationCalendar($repo, $clock), new EconomicUnitCapacity($repo, $catalog),
        );
        $requirements = new RequirementChecker($catalog);
        $insights = new CatalogueInsights(
            $catalog, $resources, $requirements, new TechnologyEffectCalculator($catalog), new CatalogAccess($repo, $catalog, $requirements),
        );
        $membership = new PlayerUniverse();
        $membership->universe = new Universe();
        $membership->universe->slug = 'technology-guide';
        $membership->universe->name = 'Recette Guide techno';
        $planet = new Planet();
        $planet->id = 101;
        $levels = ['lab' => ['basic' => 5, 'intermediate' => 25]];
        $technologies = [
            'engineering_foundations' => ['basic' => 3],
            'signature_masking' => ['basic' => 2],
            'nanomaterial_synthesis' => ['basic' => 2, 'intermediate' => 3],
        ];
        $compact = fn (array $buildings, array $research): ?array => $insights->goalSummary($membership, $planet, 'technologies', 'signature_reduction', 'basic', $buildings, $research);
        $goal = $compact($levels, $technologies);
        self::assertSame('Revêtement absorbant', $goal['label']);
        self::assertSame('Métallurgie des nanomatériaux', $goal['recommendation']['requirement']['label']);
        self::assertSame(2, $goal['recommendation']['requirement']['current']);
        self::assertSame(3, $goal['recommendation']['requirement']['level']);

        $indexed = array_column(array_merge(...array_values($insights->tree($levels, $technologies, $membership, $planet))), null, 'key');
        self::assertSame($goal['recommendation'], $indexed['technologies:signature_reduction-basic']['recommendation']);
        $technologies['nanomaterial_synthesis']['basic'] = 3;
        self::assertSame('lab', $compact($levels, $technologies)['recommendation']['requirement']['slug']);
        $levels['lab']['basic'] = 7;
        self::assertSame(['state' => 'prerequisites_met'], $compact($levels, $technologies)['recommendation']);
        $membership->catalogUnlocks[CatalogAccess::key($planet, 'technologies', 'signature_reduction', 'basic')] = true;
        self::assertSame(['state' => 'acquired'], $compact([], [])['recommendation']);
    }

    private function resolve(array $nodes): array
    {
        return TechnologyGuideStep::resolve('root', static fn (string $key): ?array => $nodes[$key] ?? null);
    }

    private static function requirement(string $group, string $slug, string $tier, int $current, int $level): array
    {
        return compact('group', 'slug', 'tier', 'current', 'level') + ['met' => $current >= $level];
    }

    private static function node(array $prerequisites = [], bool $canAdvance = false, bool $acquired = false, ?array $localRequirement = null, int $maximum = PHP_INT_MAX): array
    {
        return compact('prerequisites', 'canAdvance', 'acquired', 'localRequirement', 'maximum');
    }
}
