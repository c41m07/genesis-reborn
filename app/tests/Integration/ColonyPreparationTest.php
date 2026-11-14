<?php

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, Planet, PlanetBuilding, PlanetDefense, Player, PlayerTechnology, PlayerUniverse, Universe, SolarSystem};
use App\Service\Colony\{ColonyView, ResourceService};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class ColonyPreparationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PlayerUniverse $membership;
    private Planet $planet;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->set('clock', new MockClock('2026-11-14T12:00:00Z'));

        $universe = new Universe();
        $universe->slug = 'rework-test';
        $universe->name = 'Référence synthétique';
        $player = new Player();
        $player->email = 'rework@example.test';
        $player->username = 'Rework';
        $player->password = 'unused';
        $this->membership = new PlayerUniverse();
        $this->membership->player = $player;
        $this->membership->universe = $universe;
        $this->membership->joinedAt = new \DateTimeImmutable('2026-11-14T12:00:00Z');
        $this->membership->catalogUnlocks = [];
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $galaxy->name = 'Référence';
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $system->name = 'Référence';
        $system->coordX = 0;
        $system->coordY = 0;
        $this->planet = new Planet();
        $this->planet->owner = $this->membership;
        $this->planet->solarSystem = $system;
        $this->planet->position = 5;
        $this->planet->name = 'Colonie synthétique';
        $building = new PlanetBuilding();
        $building->planet = $this->planet;
        $building->slug = 'lab';
        $building->level = 2;
        $technology = new PlayerTechnology();
        $technology->playerUniverse = $this->membership;
        $technology->slug = 'engineering_foundations';
        $technology->level = 1;
        $fleet = new Fleet();
        $fleet->playerUniverse = $this->membership;
        $fleet->homePlanet = $this->planet;
        $fleet->status = 'idle';
        $ship = new FleetShip();
        $ship->fleet = $fleet;
        $ship->slug = 'fighter';
        $ship->quantity = 7;
        $defense = new PlanetDefense();
        $defense->planet = $this->planet;
        $defense->slug = 'ground_turret_anti_light';
        $defense->quantity = 3;
        foreach ([$universe, $player, $this->membership, $galaxy, $system, $this->planet, $building, $technology, $fleet, $ship, $defense] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        self::getContainer()->get(ResourceService::class)->initialize($this->planet);
        $this->em->flush();
    }

    public function testCatalogValuesAndEconomicSnapshotStayConsistent(): void
    {
        $view = self::getContainer()->get(ColonyView::class);
        $data = $view->data($this->membership, $this->planet, true, 'ships');
        $fighter = array_values(array_filter($data['cards']['ships'], static fn (array $card): bool => $card['definition']['slug'] === 'fighter' && $card['tier'] === 'basic'))[0];
        self::assertSame(7, $fighter['quantity']);
        $data = $view->data($this->membership, $this->planet, true, 'defenses');
        self::assertSame(3, $data['defense_quantities']['ground_turret_anti_light']['basic']);
        self::assertSame(self::getContainer()->get(ResourceService::class)->snapshot($this->planet, true), $data['resources']);
        self::assertSame(0, $data['research_parallel_count']);
    }

    public static function sections(): iterable
    {
        foreach (['overview', 'buildings', 'technologies', 'ships', 'defenses', 'tree'] as $section) {
            yield $section => [$section];
        }
    }

    #[DataProvider('sections')]
    public function testOnlyTheRequestedCatalogIsPrepared(string $section): void
    {
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $data = self::getContainer()->get(ColonyView::class)->data($this->membership, $this->planet, section: $section);
        $budget = $section === 'buildings' ? 400 : 100;
        self::assertLessThan($budget, count($holder->getData()['default'] ?? []), 'La préparation ne doit pas relire tous les catalogues.');
        if ($section === 'tree') {
            self::assertNotEmpty($data['technology_tree']);
            self::assertArrayNotHasKey('active_fleets', $data);
            self::assertArrayNotHasKey('cards', $data);
            return;
        }
        self::assertSame($section === 'overview' ? [] : [$section], array_keys($data['cards']));
        self::assertArrayNotHasKey('technology_tree', $data);
        self::assertSame($this->planet, $data['planet']);
        self::assertArrayHasKey('queues', $data);
    }

    public function testMeasurePreparationWhenExplicitlyRequested(): void
    {
        if (getenv('REWORK_MEASURE') !== '1') {
            self::markTestSkipped('Mesure activée uniquement avec REWORK_MEASURE=1.');
        }
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $view = self::getContainer()->get(ColonyView::class);
        foreach (self::sections() as [$section]) {
            $samples = [];
            for ($iteration = 0; $iteration < 11; ++$iteration) {
            $holder->reset();
            $start = hrtime(true);
            $data = $view->data($this->membership, $this->planet, true, $section);
            $elapsed = (hrtime(true) - $start) / 1e6;
            $queries = $holder->getData()['default'] ?? [];
            $samples[] = [
                'pass' => $iteration === 0 ? 'first' : 'warm',
                'queries' => count($queries),
                'sql_ms' => array_sum(array_column($queries, 'executionMS')) * 1000,
                'server_ms' => $elapsed,
                'memory_bytes' => memory_get_usage(true),
                'peak_bytes' => memory_get_peak_usage(true),
                'cards' => array_map('count', $data['cards'] ?? []),
            ];
            }
            fwrite(STDOUT, "\nREWORK_METRICS ".$section.' '.json_encode($samples, JSON_THROW_ON_ERROR)."\n");
        }
        self::assertNotEmpty($data['technology_tree']);
    }
}
