<?php

namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Balance\{CostDistributionCandidate, FundedProgressionSimulation, MilitaryEconomyStudy, ProgressionEconomyStudy};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{EconomicUnitCapacity, ResourceService};
use App\Service\Combat\{CombatSimulator, CompactCombatEngine, CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\Event\GameEventService;
use App\Service\Fleet\MovementCalculator;
use App\Service\Player\VacationCalendar;
use App\Service\Queue\OperationCalculator;
use App\Service\Requirement\RequirementChecker;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
final class MilitaryEconomyStudyTest extends TestCase
{
    private MilitaryEconomyStudy $study;
    private Universe $universe;
    private array $checkpoint;
    protected function setUp(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willThrowException(new \LogicException('L’étude ne doit pas lire de compte.'));
        $em->method('persist')->willThrowException(new \LogicException('L’étude ne doit pas écrire de compte.'));
        $em->method('flush')->willThrowException(new \LogicException('L’étude ne doit pas écrire de compte.'));
        $repo = new GameRepository($em);
        $manager = new GameManager($em);
        $clock = new MockClock('2026-11-16T00:00:00Z');
        $capacity = new EconomicUnitCapacity($repo, $catalog);
        $resources = new ResourceService($repo, $manager, $catalog, $clock, new GameEventService($repo, $manager, $clock), new VacationCalendar($repo, $clock), $capacity);
        $operations = new OperationCalculator($catalog);
        $units = new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog));
        $movement = new MovementCalculator();
        $funding = new FundedProgressionSimulation($catalog, $operations, new RequirementChecker($catalog), $resources, $capacity, $units, $movement);
        $progression = new ProgressionEconomyStudy($catalog, new CostDistributionCandidate($catalog), $funding, $operations);
        $this->study = new MilitaryEconomyStudy($catalog, $operations, $resources, $capacity, $units, new CombatSimulator(new CompactCombatEngine(
            getenv('TEST_COMBAT_ENGINE_BINARY') ?: '/usr/local/bin/genesis-combat-engine',
        )), $movement, $funding, $progression);
        $this->universe = $progression->universe();
        $this->checkpoint = [
            'buildings' => ['mine_hydrogen' => ['basic' => 5], 'power_plant' => ['basic' => 5], 'shipyard' => ['basic' => 5], 'fortress' => ['basic' => 1]],
            'technologies' => ['naval_engineering' => ['basic' => 3], 'vector_propulsion' => ['basic' => 1], 'engineering_foundations' => ['basic' => 2]],
            'units' => ['hydrogen_collector' => ['basic' => 5]],
            'defenses' => [],
            'stocks' => array_fill_keys(array_keys($catalog->rules()['resources']), 10000),
            'access' => [],
        ];
    }

    public function testCollectorTransferRemovesSourceProductionDuringTheFlightAndKeepsFuelOnboard(): void
    {
        $rows = $this->study->collectorTransfers($this->universe, $this->checkpoint);
        self::assertCount(3, $rows);
        foreach ($rows as $row) {
            self::assertEquals(0, $row['source_civil_hydrogen_per_hour_in_flight']);
            self::assertEquals(0, $row['destination_civil_hydrogen_in_flight']);
            self::assertGreaterThan(0, $row['fuel_kept_onboard_at_destination']);
            self::assertLessThanOrEqual(10000, $row['destination_end_stocks']['hydrogen']);
            if ($row['destination_position'] === 5) {
                self::assertEqualsWithDelta($row['source_civil_hydrogen_per_hour_before'], $row['destination_civil_hydrogen_per_hour_after'], 1.0E-8);
            }
        }
        self::assertSame(5, $this->checkpoint['units']['hydrogen_collector']['basic']);
    }

    public function testMilitaryNavigationReportsFuelCapacityAndSeparateOrbitConsumption(): void
    {
        $rows = $this->study->navigation($this->universe, $this->checkpoint);
        self::assertCount(36, $rows);
        foreach (['small', 'large'] as $fleet) {
            $selected = array_values(array_filter($rows, static fn(array $r): bool => $r['fleet'] === $fleet && $r['tier'] === 'basic'));
            [$full, $orbit, $half, $halfOrbit] = $selected;
            self::assertEqualsWithDelta($full['current']['total_fuel'] / 2, $half['current']['total_fuel'], 1.0E-8);
            self::assertEqualsWithDelta($orbit['current']['orbit_fuel'], $halfOrbit['current']['orbit_fuel'], 1.0E-8);
            self::assertTrue($full['current_fuel_fits_capacity']);
            self::assertSame($full['legacy_fuel'] <= $full['current']['capacity'], $full['legacy_fuel_fits_capacity']);
            self::assertGreaterThanOrEqual($full['formation_seconds'], $full['legacy_formation_seconds']);
            self::assertGreaterThanOrEqual($full['formation_electricity'], $full['legacy_formation_electricity']);
        }
    }

    public function testTheStudyVolumeProbeResolvesAllUnitsPastTheFormerCeiling(): void
    {
        $result = $this->study->volumeLimit($this->universe, $this->checkpoint);
        self::assertSame('accepted', $result['status']);
        self::assertSame(5060, $result['requested_units']);
        self::assertSame($result['requested_units'], $result['resolved_units']);
    }

    public function testCivilCombatUsesTheRealSeededEngineAndNeverArmsTheCivilUnits(): void
    {
        $first = $this->study->combatScenario($this->universe, $this->checkpoint, 200, 'basic', 'basic', [42]);
        $again = $this->study->combatScenario($this->universe, $this->checkpoint, 200, 'basic', 'basic', [42]);
        self::assertSame($first['results'], $again['results']);
        self::assertSame(0, $first['results'][0]['civil_shots']);
        self::assertGreaterThan(0, $first['results'][0]['shots_targeting_civil']);
        self::assertSame(10, $first['civil_state']['solar_satellite']['basic']['active']);
        self::assertSame(5, $first['civil_state']['hydrogen_collector']['basic']['active']);
        self::assertGreaterThan(0, $first['civil_state']['hydrogen_collector']['basic']['inactive']);
        self::assertGreaterThan(0, $first['defender_quote']['value']);
    }
}
