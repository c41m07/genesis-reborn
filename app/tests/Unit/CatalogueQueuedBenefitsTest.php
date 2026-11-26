<?php

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Planet, SolarSystem, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueInsights, CatalogueProductionContext, EconomicUnitCapacity, ResourceService};
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Event\GameEventService;
use App\Service\Player\VacationCalendar;
use App\Service\Requirement\{CatalogAccess, RequirementChecker};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CatalogueQueuedBenefitsTest extends TestCase
{
    private GameCatalog $catalog;
    private CatalogueInsights $insights;
    private Planet $planet;
    private CatalogueProductionContext $context;

    protected function setUp(): void
    {
        $this->catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willThrowException(new \LogicException('Les bénéfices préparés ne doivent pas relire la base.'));
        $repo = new GameRepository($em);$manager = new GameManager($em);
        $clock = new MockClock('2026-11-16T00:00:00Z');
        $capacity = new EconomicUnitCapacity($repo, $this->catalog);
        $resources = new ResourceService($repo, $manager, $this->catalog, $clock, new GameEventService($repo, $manager, $clock), new VacationCalendar($repo, $clock), $capacity);
        $requirements = new RequirementChecker($this->catalog);
        $access = new CatalogAccess($repo, $this->catalog, $requirements);
        $technologyEffects = new TechnologyEffectCalculator($this->catalog);
        $this->insights = new CatalogueInsights($this->catalog, $resources, $requirements, $technologyEffects, $access);
        $universe = new Universe();$universe->slug = 'queued-benefits';$universe->name = 'Gains en file';
        $galaxy = new Galaxy();$galaxy->universe = $universe;$system = new SolarSystem();$system->galaxy = $galaxy;
        $this->planet = new Planet();$this->planet->solarSystem = $system;$this->planet->position = 5;
        $this->context = new CatalogueProductionContext($this->planet, false, 1.0, ['present'=>[], 'incoming'=>[], 'construction'=>[]]);
    }

    public function testAllBuildingAndResearchCardsCompareOneLevelDespiteQueuedLevels(): void
    {
        $count = 0;
        foreach (['buildings', 'technologies'] as $group) foreach ($this->catalog->all($group) as $slug => $base) foreach (GameCatalog::TIERS as $tier) {
            $definition = $this->catalog->withTier($base, $tier);
            $actual = [$slug=>array_fill_keys(GameCatalog::TIERS, 1)];
            $preceding = $actual;$preceding[$slug][$tier] = 3;
            $pending = $this->insights->benefits($definition, $group, 4, $this->planet, $group==='buildings'?$actual:[], $group==='technologies'?$actual:[], $this->context);
            $completed = $this->insights->benefits($definition, $group, 4, $this->planet, $group==='buildings'?$preceding:[], $group==='technologies'?$preceding:[], $this->context);
            self::assertNotEmpty($pending, $group.'/'.$slug.'/'.$tier);
            self::assertSame($completed, $pending, $group.'/'.$slug.'/'.$tier.' : même gain pour le seul passage de 3 à 4.');
            self::assertSame([$slug=>array_fill_keys(GameCatalog::TIERS, 1)], $actual);
            $count++;
        }
        self::assertSame(96, $count);
    }

    public function testLaboratoryShipyardAndBunkerDoNotAddQueuedBonuses(): void
    {
        foreach (['lab'=>'Réduction du temps de recherche', 'shipyard'=>'Réduction du temps de construction des vaisseaux', 'fortress'=>'Structure supplémentaire des défenses'] as $slug => $label) {
            $definition = $this->catalog->withTier($this->catalog->get('buildings', $slug), 'basic');
            $rows = array_column($this->insights->benefits($definition, 'buildings', 4, $this->planet, [$slug=>['basic'=>1]], [], $this->context), null, 'label');
            $expected = $slug==='fortress' ? [3.154773645400453, 4.125541652632911] : [4.278612407039298, 5.669956452590854];
            self::assertEqualsWithDelta($expected[0], $rows[$label]['before'], 1e-10);
            self::assertEqualsWithDelta($expected[1], $rows[$label]['after'], 1e-10);
        }
    }

    public function testCurrentEffectsCoverAllExistingRankedBuildingAndResearchCards(): void
    {
        $count = 0;
        foreach (['buildings', 'technologies'] as $group) {
            foreach ($this->catalog->all($group) as $slug => $base) {
                foreach (GameCatalog::TIERS as $tier) {
                    $definition = $this->catalog->withTier($base, $tier);
                    $target = (int) ($definition['effect']['tier_max_levels'][$tier] ?? $definition['max_level'] ?? 4);
                    $owned = [$slug => array_fill_keys(GameCatalog::TIERS, 1)];
                    $owned[$slug][$tier] = $target - 1;
                    $buildings = $group === 'buildings' ? $owned : [];
                    $technologies = $group === 'technologies' ? $owned : [];
                    $comparison = $this->insights->benefits($definition, $group, $target, $this->planet, $buildings, $technologies, $this->context);
                    $current = $this->insights->benefits($definition, $group, null, $this->planet, $buildings, $technologies, $this->context);
                    $case = $group.'/'.$slug.'/'.$tier;
                    self::assertNotEmpty($current, $case);
                    self::assertSame(array_column($comparison, 'label'), array_column($current, 'label'), $case);
                    self::assertSame(array_column($comparison, 'unit'), array_column($current, 'unit'), $case);
                    self::assertSame(array_column($comparison, 'before'), array_column($current, 'before'), $case);
                    self::assertSame(array_column($current, 'before'), array_column($current, 'after'), $case);
                    $count++;
                }
            }
        }
        self::assertSame(96, $count);
        self::assertSame([], $this->insights->benefits(['slug' => 'synthetic_no_effect'], 'technologies', null, $this->planet, [], [], $this->context));
        self::assertSame([], $this->insights->benefits(['slug' => 'synthetic_no_effect'], 'buildings', null, $this->planet, [], [], $this->context));
    }

    public function testEachStorageRankAddsOnlyItsOwnSingleLevelsCapacity(): void
    {
        foreach (['metal', 'nano', 'hydrogen', 'electricity', 'antimatter'] as $resource) foreach (['basic'=>100000.0, 'intermediate'=>125000.0, 'superior'=>156250.0] as $tier => $expected) {
            $slug = 'storage_'.$resource;
            $definition = $this->catalog->withTier($this->catalog->get('buildings', $slug), $tier);
            $rows = $this->insights->benefits($definition, 'buildings', 4, $this->planet, [$slug=>[$tier=>1]], [], $this->context);
            $growth = ['basic'=>1.0025, 'intermediate'=>1.005, 'superior'=>1.01][$tier];
            self::assertEqualsWithDelta($expected * (4 * $growth ** 4 - 3 * $growth ** 3), $rows[0]['after']-$rows[0]['before'], 1e-7);
        }
    }
}
