<?php

namespace App\Tests\Unit;

use App\Entity\{Galaxy, Planet, SolarSystem, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueDetails, CatalogueInsights, CatalogueProductionContext, EconomicUnitCapacity, ResourceService};
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Event\GameEventService;
use App\Service\Player\VacationCalendar;
use App\Service\Requirement\{CatalogAccess, RequirementChecker};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EconomicUnitProductionPreviewTest extends TestCase
{
    private GameCatalog $catalog;
    private ResourceService $resources;
    private CatalogueInsights $insights;
    private CatalogueDetails $details;
    private Planet $planet;
    private Universe $universe;

    protected function setUp(): void
    {
        $this->catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willThrowException(new \LogicException('Le contexte fourni doit éviter toute lecture de base.'));
        $repo = new GameRepository($em);
        $manager = new GameManager($em);
        $clock = new MockClock('2026-11-16T00:00:00Z');
        $capacity = new EconomicUnitCapacity($repo, $this->catalog);
        $this->resources = new ResourceService($repo, $manager, $this->catalog, $clock, new GameEventService($repo, $manager, $clock), new VacationCalendar($repo, $clock), $capacity, new \App\Service\Boost\TimedBoostEffects($repo, $clock));
        $requirements = new RequirementChecker($this->catalog);
        $access = new CatalogAccess($repo, $this->catalog, $requirements);
        $effects = new TechnologyEffectCalculator($this->catalog);
        $this->insights = new CatalogueInsights($this->catalog, $this->resources, $requirements, $effects, $access);
        $this->details = new CatalogueDetails($this->catalog, $this->resources, $effects, $requirements, $access, $capacity);
        $this->universe = new Universe();$this->universe->slug = 'preview-x1';$this->universe->name = 'Aperçu';
        $galaxy = new Galaxy();$galaxy->universe = $this->universe;
        $system = new SolarSystem();$system->galaxy = $galaxy;
        $this->planet = new Planet();$this->planet->solarSystem = $system;
    }

    public function testCardsAndDetailsMatchOneActiveUnitsProductionOnTheSelectedColony(): void
    {
        foreach ([1 => ['electricity'=>1.2, 'hydrogen'=>0.8], 5 => ['electricity'=>1.0, 'hydrogen'=>1.0], 9 => ['electricity'=>0.8, 'hydrogen'=>1.2]] as $position => $factors) {
            $this->planet->position = $position;
            foreach ([1.0, 3.0] as $universeMultiplier) {
                $this->universe->gameSettings['multipliers']['resource_production'] = $universeMultiplier;
                foreach ([1.0, 1.5] as $eventMultiplier) foreach ([false, true] as $vacation) {
                    foreach (['solar_satellite', 'hydrogen_collector'] as $slug) foreach (GameCatalog::TIERS as $tier) {
                        $definition = $this->catalog->getTierForUniverse($this->universe, 'ships', $slug, $tier);
                        $economic = $definition['economic_unit'];$resource = $economic['resource'];
                        $context = new CatalogueProductionContext($this->planet, $vacation, $eventMultiplier, [
                            'present'=>[$slug=>[$tier=>1]], 'incoming'=>[], 'construction'=>[],
                        ]);
                        $levels = [$economic['capacity_building']=>[$tier=>1]];
                        $expected = $vacation ? 0.0 : $economic['production_per_hour'][$tier] * $factors[$resource] * $universeMultiplier * $eventMultiplier;
                        $rows = array_column($this->insights->benefits($definition, 'ships', 1, $this->planet, $levels, [], $context), null, 'label');
                        $preview = $rows['Production par unité active · cette colonie'];
                        self::assertEqualsWithDelta($expected, $preview['value'], 1e-7);
                        self::assertSame($resource==='hydrogen'?'H / h':'E / h', $preview['unit']);
                        $details = $this->details->describe($definition, 'ships', 1, $this->planet, $levels, [], $context);
                        $sections = array_column($details['sections'], null, 'title');
                        $detailRows = array_column($sections['Production et places locales']['rows'], null, 'label');
                        self::assertEqualsWithDelta($expected, $detailRows['Production par unité active sur cette colonie']['value'], 1e-7);
                        // Comparaison avec le moteur réel : la contribution civile est distincte des usines.
                        $stocks = array_fill_keys(array_keys($this->catalog->rules()['resources']), 0.0);
                        $projection = $this->resources->project($this->planet, $levels, $stocks, 1, $context);
                        self::assertEqualsWithDelta($expected, ($projection['sources'][$resource][$slug]??0.0)*3600, 1e-7);
                    }
                }
            }
        }
    }

    public function testUniverseProfileOverridesAreIncludedWithoutAnotherTierMultiplier(): void
    {
        $this->planet->position = 9;
        $this->universe->gameSettings = [
            'multipliers'=>['resource_production'=>2.0],
            'catalog_overrides'=>['ships'=>['hydrogen_collector'=>['economic_unit'=>['production_per_hour'=>['superior'=>1234.5]]]]],
        ];
        $definition = $this->catalog->getTierForUniverse($this->universe, 'ships', 'hydrogen_collector', 'superior');
        $context = new CatalogueProductionContext($this->planet, false, 1.25);
        self::assertEqualsWithDelta(3703.5, $this->resources->economicUnitRate($this->planet, $definition, $context)*3600, 1e-7);
    }

    public function testAnotherColonysContextCannotBeReused(): void
    {
        $this->planet->position = 5;
        $definition = $this->catalog->getTierForUniverse($this->universe, 'ships', 'solar_satellite', 'basic');
        $this->expectException(\LogicException::class);
        $this->resources->economicUnitRate($this->planet, $definition, new CatalogueProductionContext(clone $this->planet, false, 1.0));
    }
}
