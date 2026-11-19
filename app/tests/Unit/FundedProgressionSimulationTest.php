<?php

namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Balance\FundedProgressionSimulation;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{EconomicUnitCapacity, ResourceService};
use App\Service\Combat\{CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\Event\GameEventService;
use App\Service\Fleet\MovementCalculator;
use App\Service\Player\VacationCalendar;
use App\Service\Queue\OperationCalculator;
use App\Service\Requirement\RequirementChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
final class FundedProgressionSimulationTest extends TestCase
{
    private GameCatalog $catalog;
    private FundedProgressionSimulation $simulation;
    private Universe $universe;
    protected function setUp(): void
    {
        $this->catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willThrowException(new \LogicException('Le parcours ne doit pas lire la base.'));
        $repo = new GameRepository($em);
        $manager = new GameManager($em);
        $clock = new MockClock('2026-11-16T00:00:00Z');
        $economicUnits = new EconomicUnitCapacity($repo, $this->catalog);
        $resources = new ResourceService($repo, $manager, $this->catalog, $clock, new GameEventService($repo, $manager, $clock), new VacationCalendar($repo, $clock), $economicUnits);
        $this->simulation = new FundedProgressionSimulation($this->catalog, new OperationCalculator($this->catalog), new RequirementChecker($this->catalog), $resources, $economicUnits, new CombatUnitFactory($this->catalog, new TechnologyEffectCalculator($this->catalog)), new MovementCalculator());
        $this->universe = new Universe();
        $this->universe->slug = 'funded-x1';
        $this->universe->name = 'Parcours';
    }

    public function testFreshFundedPathUsesPrerequisitesAndConservesStocksWithParallelQueues(): void
    {
        $goals = [
            ['group' => 'buildings', 'slug' => 'mine_metal', 'level' => 1],
            ['group' => 'buildings', 'slug' => 'power_plant', 'level' => 1],
            ['group' => 'technologies', 'slug' => 'engineering_foundations', 'level' => 1],
            ['group' => 'ships', 'slug' => 'probe', 'quantity' => 1],
            ['group' => 'defenses', 'slug' => 'ground_turret_anti_light', 'quantity' => 1],
        ];
        $result = $this->simulation->run($this->universe, $goals, ['trace' => true, 'max_seconds' => 2592000]);
        self::assertSame('complete', $result['status']);
        foreach ([
            'mine_metal' => 2,
            'mine_nano' => 2,
            'lab' => 1,
            'shipyard' => 1,
            'fortress' => 1,
        ] as $slug => $level) {
            self::assertGreaterThanOrEqual($level, $result['checkpoint']['buildings'][$slug]['basic']);
        }
        // Le Bunker dépend des mines : ce parcours n’a plus besoin d’anciens niveaux de chantier ou de laboratoire.
        self::assertSame(1, $result['checkpoint']['buildings']['lab']['basic']);
        self::assertSame(1, $result['checkpoint']['buildings']['shipyard']['basic']);
        self::assertSame(1, $result['checkpoint']['units']['probe']['basic']);
        self::assertSame(1, $result['checkpoint']['defenses']['ground_turret_anti_light']['basic']);
        foreach ($result['end_stocks'] as $resource => $stock) {
            $metrics = $result['metrics'];
            $expected = 10000 + $metrics['production'][$resource] - $metrics['consumption'][$resource] - $metrics['uncredited'][$resource] - $metrics['spent'][$resource];
            self::assertEqualsWithDelta($expected, $stock, 0.0001, $resource);
        }
        $research = array_values(array_filter($result['operations'], static fn(array $row): bool => $row['group'] === 'technologies'))[0];
        $parallel = array_filter($result['operations'], static fn(array $row): bool => $row['group'] === 'buildings' && $row['started_at'] < $research['completed_at'] && $row['completed_at'] > $research['started_at']);
        self::assertNotEmpty($parallel, 'La recherche et les bâtiments doivent pouvoir progresser en parallèle.');
    }

    public function testAPathWithoutPowerReportsItsElectricityBlockInsteadOfInventingRescueResources(): void
    {
        $stocks = $this->catalog->rules()['initial_stock'];
        $stocks['electricity'] = 0;
        $result = $this->simulation->run($this->universe, [['group' => 'ships', 'slug' => 'probe', 'quantity' => 1]], ['initial' => ['stocks' => $stocks]]);
        self::assertSame('blocked', $result['status']);
        self::assertEquals(0, $result['end_stocks']['electricity']);
        self::assertSame(0, $result['checkpoint']['buildings']['power_plant']['basic'] ?? 0);
        self::assertSame('probe', $result['pending_sample'][0]['slug']);
        self::assertEquals(0, $result['metrics']['spent']['electricity']);
    }

    public function testUnlockTimeIsRecordedWithoutBuyingOrGrantingTheUnlockedShips(): void
    {
        $result = $this->simulation->run($this->universe, [['group' => 'buildings', 'slug' => 'shipyard', 'level' => 1]]);
        self::assertSame('complete', $result['status']);
        self::assertSame($result['seconds'], $result['milestones']['access:ships:fighter:basic']);
        self::assertSame($result['seconds'], $result['milestones']['access:ships:probe:basic']);
        self::assertSame([], $result['checkpoint']['units']);
        self::assertSame(0, $result['metrics']['queue_dependency_wait_seconds']['fleet']);
    }

    public function testTheLongColonizationResearchOccupiesTheCommonQueueBeforeTheFollowingResearch(): void
    {
        $buildings = ['lab' => ['basic' => 2], 'storage_electricity' => ['basic' => 10]];
        $technologies = ['engineering_foundations' => ['basic' => 2], 'naval_engineering' => ['basic' => 1]];
        $goals = [
            ['group' => 'technologies', 'slug' => 'colonial_expansion', 'level' => 1, 'label' => 'colonization'],
            [
                'group' => 'technologies',
                'slug' => 'engineering_foundations',
                'level' => 3,
                'after_label' => 'colonization',
                'label' => 'following',
            ],
        ];
        $initial = [
            'buildings' => $buildings,
            'technologies' => $technologies,
            'stocks' => array_fill_keys(array_keys($this->catalog->rules()['resources']), 600000),
        ];
        $result = $this->simulation->run($this->universe, $goals, ['initial' => $initial, 'trace' => true]);
        self::assertSame('complete', $result['status']);
        $quote = (new OperationCalculator($this->catalog))->calculate($this->catalog->getTierForUniverse($this->universe, 'technologies', 'colonial_expansion', 'basic'), 'technologies', 1, 1, $buildings, $this->catalog->forUniverse($this->universe), $technologies);
        self::assertSame($quote['duration'], $result['milestones']['colonization']);
        $following = array_values(array_filter($result['operations'], static fn(array $row): bool => ($row['label'] ?? '') === 'following'))[0];
        self::assertGreaterThanOrEqual($result['milestones']['colonization'], $following['started_at']);
        self::assertSame((float) ($quote['costs']['electricity'] + $following['plan']['costs']['electricity']), $result['metrics']['spent']['electricity']);
    }

    public function testIntermittentSessionsDelayPurchasesButNotProductionOrCompletion(): void
    {
        $result = $this->simulation->run($this->universe, [
            ['group' => 'buildings', 'slug' => 'mine_metal', 'level' => 2],
        ], [
            'trace' => true,
            'session_interval' => 3600,
            'session_seconds' => 30,
        ]);
        [$first, $second] = $result['operations'];
        self::assertSame('complete', $result['status']);
        self::assertSame(0, $first['started_at']);
        self::assertSame(60, $first['completed_at']);
        self::assertSame(3600, $second['started_at']);
        self::assertSame(3668, $second['completed_at']);
        self::assertGreaterThan(0, $result['metrics']['production']['metal']);
        self::assertSame(0, $result['metrics']['vacation_seconds']);
        self::assertSame(3540, $result['metrics']['queue_session_wait_seconds']['building']);
    }

    public function testVacationsPauseTheRunningQueueWithoutChangingItsPaidDuration(): void
    {
        $buildings = ['lab' => ['basic' => 1]];
        $goal = ['group' => 'technologies', 'slug' => 'engineering_foundations', 'level' => 1];
        $result = $this->simulation->run($this->universe, [$goal], [
            'initial' => ['buildings' => $buildings],
            'trace' => true,
            'calendar' => [['from' => 100, 'until' => 200, 'vacation' => true]],
        ]);
        $node = $result['operations'][0];
        self::assertSame('complete', $result['status']);
        self::assertSame($node['plan']['duration'] + 100, $node['completed_at']);
        self::assertSame(100, $result['metrics']['vacation_seconds']);
        self::assertSame($node['plan']['duration'], $result['metrics']['queue_busy_seconds']['research']);
    }

    public function testSatelliteConstructionUsesCapacityWhileExcessCollectorsRemainInactive(): void
    {
        $initial = [
            'buildings' => ['power_plant' => ['basic' => 1], 'mine_hydrogen' => ['basic' => 5]],
            'access' => ['ships:solar_satellite:basic', 'ships:hydrogen_collector:basic'],
        ];
        $goals = [
            ['group' => 'ships', 'slug' => 'solar_satellite', 'quantity' => 3],
            ['group' => 'ships', 'slug' => 'hydrogen_collector', 'quantity' => 7],
        ];
        $result = $this->simulation->run($this->universe, $goals, ['initial' => $initial]);
        self::assertSame('complete', $result['status']);
        self::assertGreaterThanOrEqual(2, $result['checkpoint']['buildings']['power_plant']['basic']);
        self::assertSame(3, $result['civil_state']['solar_satellite']['basic']['active']);
        self::assertSame(5, $result['civil_state']['hydrogen_collector']['basic']['active']);
        self::assertSame(2, $result['civil_state']['hydrogen_collector']['basic']['inactive']);
    }

    public function testSpyUsesTheActualFuelQuoteAndConsumesItsProbe(): void
    {
        $goals = [
            [
                'group' => 'missions',
                'mission' => 'spy',
                'composition' => ['probe' => ['basic' => 1]],
                'normal_distance' => 10,
                'hyper_distance' => 0,
                'same_system' => true,
            ],
        ];
        $result = $this->simulation->run($this->universe, $goals, ['initial' => ['units' => ['probe' => ['basic' => 1]]]]);
        self::assertSame('complete', $result['status']);
        self::assertSame(30, $result['seconds']);
        self::assertSame(0, $result['checkpoint']['units']['probe']['basic']);
        self::assertSame(1, $result['metrics']['missions_completed']);
        self::assertEqualsWithDelta(0.2, $result['metrics']['fuel_charged'], 1.0E-9);
    }

    public function testVacationCalendarRejectsAnInFlightMissionInsteadOfFreezingItsNavigation(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Un calendrier de vacances ne peut pas interrompre une mission en vol.');
        $this->simulation->run($this->universe, [
            [
                'group' => 'missions',
                'mission' => 'spy',
                'composition' => ['probe' => ['basic' => 1]],
                'normal_distance' => 100,
                'hyper_distance' => 100,
            ],
        ], [
            'initial' => ['units' => ['probe' => ['basic' => 1]]],
            'calendar' => [['from' => 1, 'until' => 61, 'vacation' => true]],
        ]);
    }
}
