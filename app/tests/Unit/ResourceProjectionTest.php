<?php

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Planet, PlayerUniverse, SolarSystem, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueProductionContext, EconomicUnitCapacity, ResourceService};
use App\Service\Event\GameEventService;
use App\Service\Player\VacationCalendar;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
final class ResourceProjectionTest extends TestCase
{
    private ResourceService $resources;
    private Planet $planet;
    private array $levels;
    private array $inventory;
    private array $stocks;
    protected function setUp(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willThrowException(new \LogicException('Une projection ne doit pas lire la base.'));
        $repo = new GameRepository($em);
        $manager = new GameManager($em);
        $clock = new MockClock('2026-11-16T00:00:00Z');
        $this->resources = new ResourceService($repo, $manager, $catalog, $clock, new GameEventService($repo, $manager, $clock), new VacationCalendar($repo, $clock), new EconomicUnitCapacity($repo, $catalog));
        $universe = new Universe();
        $universe->slug = 'projection-x1';
        $universe->name = 'Projection';
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $member = new PlayerUniverse();
        $member->universe = $universe;
        $this->planet = new Planet();
        $this->planet->solarSystem = $system;
        $this->planet->position = 5;
        $this->planet->owner = $member;
        $this->levels = [
            'mine_metal' => ['basic' => 8],
            'mine_nano' => ['basic' => 8],
            'mine_hydrogen' => ['basic' => 5],
            'power_plant' => ['basic' => 1],
            'mine_antimatter' => ['basic' => 5],
        ];
        $this->inventory = [
            'present' => ['hydrogen_collector' => ['basic' => 5], 'solar_satellite' => ['basic' => 2]],
            'incoming' => [],
            'construction' => [],
        ];
        $this->stocks = [
            'metal' => 9950,
            'nano' => 9900,
            'hydrogen' => 50,
            'electricity' => 20,
            'antimatter' => 100,
        ];
    }

    private function context(bool $vacation = false, float $event = 1): CatalogueProductionContext
    {
        return new CatalogueProductionContext($this->planet, $vacation, $event, $this->inventory);
    }

    public function testProjectionConservesEveryResourceAcrossShortagesAndStorageCapsWithoutDatabaseReads(): void
    {
        $result = $this->resources->project($this->planet, $this->levels, $this->stocks, 86400, $this->context());
        foreach ($this->stocks as $slug => $initial) {
            $expected = $initial + $result['totals']['production'][$slug] - $result['totals']['consumption'][$slug] - $result['totals']['uncredited'][$slug];
            self::assertEqualsWithDelta($expected, $result['stocks'][$slug], 1.0E-5, $slug);
            self::assertGreaterThanOrEqual(0, $result['stocks'][$slug]);
            self::assertLessThanOrEqual(10000, $result['stocks'][$slug]);
        }
        self::assertGreaterThan(0, $result['totals']['consumption']['hydrogen']);
        self::assertGreaterThan(0, $result['totals']['uncredited']['metal']);
        self::assertArrayHasKey('hydrogen_collector', $result['sources']['hydrogen']);
        self::assertArrayHasKey('solar_satellite', $result['sources']['electricity']);
    }

    public function testCumulativeFlowsAndStocksAreIndependentOfTheChosenIntegrationSplit(): void
    {
        $whole = $this->resources->project($this->planet, $this->levels, $this->stocks, 86400, $this->context());
        $stocks = $this->stocks;
        $totals = array_fill_keys(['production', 'consumption', 'uncredited'], array_fill_keys(array_keys($stocks), 0.0));
        for ($i = 0; $i < 24; $i++) {
            $part = $this->resources->project($this->planet, $this->levels, $stocks, 3600, $this->context());
            $stocks = $part['stocks'];
            foreach ($part['totals'] as $field => $amounts) {
                foreach ($amounts as $slug => $amount) {
                    $totals[$field][$slug] += $amount;
                }
            }
        }
        foreach ($stocks as $slug => $amount) {
            self::assertEqualsWithDelta($whole['stocks'][$slug], $amount, 1.0E-5, $slug);
            foreach ($totals as $field => $amounts) {
                self::assertEqualsWithDelta($whole['totals'][$field][$slug], $amounts[$slug], 0.0001, $field . '/' . $slug);
            }
        }
    }

    public function testExplicitVacationPausesFlowsAndEventsMultiplyCivilSourcesOnce(): void
    {
        $paused = $this->resources->project($this->planet, $this->levels, $this->stocks, 3600, $this->context(true, 2));
        self::assertEquals($this->stocks, $paused['stocks']);
        self::assertEquals(0, array_sum($paused['rates']));
        foreach ($paused['totals'] as $amounts) {
            self::assertEquals(0, array_sum($amounts));
        }
        $normal = $this->resources->project($this->planet, $this->levels, $this->stocks, 0, $this->context());
        $event = $this->resources->project($this->planet, $this->levels, $this->stocks, 0, $this->context(false, 2));
        foreach (['hydrogen' => 'hydrogen_collector', 'electricity' => 'solar_satellite'] as $resource => $slug) {
            self::assertEqualsWithDelta(2 * $normal['sources'][$resource][$slug], $event['sources'][$resource][$slug], 1.0E-8);
        }
    }

    public function testOptionalFlowMetricsLeaveTheNormalProductionResultsIdentical(): void
    {
        $base = array_fill_keys(array_keys($this->stocks), 0.0);
        $base['metal'] = 10 / 3600;
        $factories = [
            [
                'slug' => 'metal',
                'resource' => 'metal',
                'rate' => 1.0,
                'electricity' => 0.4,
                'hydrogen' => 0,
            ],
            [
                'slug' => 'hydrogen',
                'resource' => 'hydrogen',
                'rate' => 0.5,
                'electricity' => 0.1,
                'hydrogen' => 0,
            ],
            [
                'slug' => 'antimatter',
                'resource' => 'antimatter',
                'rate' => 0.125,
                'electricity' => 0.15,
                'hydrogen' => 0.5,
            ],
            [
                'slug' => 'power',
                'resource' => 'electricity',
                'rate' => 0.2,
                'electricity' => 0,
                'hydrogen' => 0,
            ],
        ];
        $caps = array_fill_keys(array_keys($this->stocks), 10000.0);
        $normal = \App\Service\Colony\FactoryEconomy::simulate($base, $factories, $this->stocks, $caps, 86400);
        $measured = \App\Service\Colony\FactoryEconomy::simulate($base, $factories, $this->stocks, $caps, 86400, 0.1, true);
        unset($measured['totals']);
        self::assertSame($normal, $measured);
    }

    public function testProjectionRejectsImplicitInventoriesBeforeAnyDatabaseAccess(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->resources->project($this->planet, $this->levels, $this->stocks, 1, new CatalogueProductionContext($this->planet, false, 1));
    }

    public function testProjectionRejectsAnotherPlanetContext(): void
    {
        $this->expectException(\LogicException::class);
        $this->resources->project(clone $this->planet, $this->levels, $this->stocks, 1, $this->context());
    }
}
