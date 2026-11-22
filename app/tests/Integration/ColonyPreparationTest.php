<?php

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, Planet, PlanetBuilding, PlanetDefense, Player, PlayerDiscovery, PlayerTechnology, PlayerUniverse, Universe, SolarSystem};
use App\Repository\GameRepository;
use App\Service\Colony\{ColonyView, EmpireDetails, ResourceService};
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
        $this->membership->catalogUnlocks = PlayerUniverse::INITIAL_CATALOG_UNLOCKS;
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

    public function testBuildingCardsKeepEconomicValuesAfterEventsVacationsAndQueueCompletion(): void
    {
        $catalog = self::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        $repository = self::getContainer()->get(GameRepository::class);
        $resources = self::getContainer()->get(ResourceService::class);
        $view = self::getContainer()->get(ColonyView::class);
        $clock = self::getContainer()->get('clock');
        $this->planet->position = 9;
        $this->membership->universe->gameSettings = ['multipliers' => ['resource_production' => 3]];
        foreach (['mine_metal', 'mine_antimatter', 'power_plant', 'storage_metal'] as $slug) {
            foreach (['basic' => 2, 'intermediate' => 3, 'superior' => 1] as $tier => $level) {
                $building = new PlanetBuilding();
                $building->planet = $this->planet;
                $building->slug = $slug;
                $building->tier = $tier;
                $building->level = $level;
                $this->em->persist($building);
            }
        }
        $queue = new \App\Entity\QueueItem();
        $queue->planet = $this->planet;
        $queue->playerUniverse = $this->membership;
        $queue->queueType = 'building';
        $queue->itemSlug = 'mine_metal';
        $queue->itemLevel = 3;
        $queue->durationSeconds = 10;
        $queue->startedAt = $queue->queuedAt = $clock->now();
        $queue->dispatchToken = 'rework-economic-queue';
        $this->planet->buildingQueueStartedAt = $clock->now();
        $this->em->persist($queue);
        $event = new \App\Entity\GameEvent();
        $event->name = 'Bonus synthétique';
        $event->type = 'production_bonus';
        $event->startsAt = $clock->now();
        $event->endsAt = $clock->now()->modify('+1 hour');
        $event->parameters = ['percentage' => 50];
        $event->universe = $this->membership->universe;
        $this->em->persist($event);
        $period = new \App\Entity\VacationPeriod();
        $period->membership = $this->membership;
        $period->startedAt = $clock->now();
        $period->scheduledEndAt = $clock->now()->modify('+1 hour');
        $period->endedAt = $clock->now();
        $this->em->persist($period);
        $this->em->flush();

        foreach (['normal', 'event', 'vacation', 'resumed'] as $state) {
            $event->status = $state === 'normal' ? 'draft' : 'active';
            $period->endedAt = $state === 'vacation' ? null : $clock->now();
            $this->em->flush();
            $data = $view->data($this->membership, $this->planet, true, 'buildings');
            $levels = $repository->levelsByTier(PlanetBuilding::class, ['planet' => $this->planet]);
            $tech = $repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $this->membership]);
            foreach ($data['cards']['buildings'] as $card) {
                $definition = $card['definition'];
                self::assertSame(self::getContainer()->get(\App\Service\Colony\CatalogueDetails::class)->describe($definition, 'buildings', $card['target'], $this->planet, $levels, $tech), $card['details'], $state);
                self::assertSame(self::getContainer()->get(\App\Service\Colony\CatalogueInsights::class)->benefits($definition, 'buildings', $card['target'], $this->planet, $levels, $tech), $card['benefits'], $state);
                if ($definition['slug'] === 'mine_metal' && $card['tier'] === 'basic') {
                    self::assertSame(4, $card['target']);
                    self::assertSame(2, $card['level']);
                }
            }
            self::assertSame($resources->snapshot($this->planet, true), $data['resources'], 'La barre garde son calcul indépendant.');
        }
        $clock->sleep(11);
        $data = $view->data($this->membership, $this->planet, true, 'buildings');
        $metal = array_values(array_filter($data['cards']['buildings'], static fn (array $card): bool => $card['definition']['slug'] === 'mine_metal' && $card['tier'] === 'basic'))[0];
        self::assertSame(3, $metal['level'], 'La synchronisation précède la préparation économique.');
        self::assertSame(4, $metal['target']);
    }

    #[DataProvider('sections')]
    public function testOnlyTheRequestedCatalogIsPrepared(string $section): void
    {
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $data = self::getContainer()->get(ColonyView::class)->data($this->membership, $this->planet, section: $section);
        $budget = 100;
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

    public function testDiscoveredPlanetsAreGroupedAndExcludeUnknownSystemsAndOtherUniverses(): void
    {
        $knownIds = [];
        for ($index = 0; $index < 12; ++$index) {
            $system = new SolarSystem();
            $system->galaxy = $this->planet->solarSystem->galaxy;
            $system->name = 'Système synthétique '.$index;
            $system->coordX = ($index + 1) * 3;
            $system->coordY = 0;
            if ($index === 11) {
                $universe = new Universe();
                $universe->slug = 'foreign-rework';
                $universe->name = 'Autre univers';
                $galaxy = new Galaxy();
                $galaxy->universe = $universe;
                $galaxy->name = 'Autre galaxie';
                $this->em->persist($universe);
                $this->em->persist($galaxy);
                $system->galaxy = $galaxy;
            }
            $planet = new Planet();
            $planet->solarSystem = $system;
            $planet->position = 1;
            $planet->name = 'Monde synthétique '.$index;
            $this->em->persist($system);
            $this->em->persist($planet);
            // Inclure une découverte incohérente : l’univers doit quand même être refusé.
            if ($index !== 10) {
                $discovery = new PlayerDiscovery();
                $discovery->playerUniverse = $this->membership;
                $discovery->solarSystem = $system;
                $this->em->persist($discovery);
            }
            $this->em->flush();
            if ($index < 10) $knownIds[] = $system->id;
        }
        $repository = self::getContainer()->get(GameRepository::class);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        self::assertCount(10, $repository->discoveredPlanets($this->membership));
        self::assertLessThan(4, count($holder->getData()['default'] ?? []));
        self::assertCount(2, $repository->discoveredPlanets($this->membership, array_slice($knownIds, 0, 2)));
        self::assertSame([], $repository->discoveredPlanets($this->membership, []));
    }

    public function testFleetDetailsKeepOwnedCompositionsAndExcludeAnotherMembership(): void
    {
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse' => $this->membership]);
        $foreign = clone $fleet;
        $foreign->id = $fleet->id + 100;
        $foreign->playerUniverse = clone $this->membership;
        $foreign->playerUniverse->id = $this->membership->id + 100;
        $details = self::getContainer()->get(EmpireDetails::class)->fleets($this->membership, [$fleet, $foreign]);
        self::assertSame([$fleet->id], array_keys($details));
        self::assertSame(7, $details[$fleet->id]['ships'][0]['quantity']);
        self::assertSame([], self::getContainer()->get(EmpireDetails::class)->fleets($this->membership, [$foreign]));
    }

    public function testFleetCombatsAreGroupedAndKeepTheFirstHistoricalParticipant(): void
    {
        $event = new \App\Entity\CombatEvent();
        $event->planet = $this->planet;
        $event->startedAt = $event->resolveAt = new \DateTimeImmutable('2026-11-14T12:00:00Z');
        $event->seed = 1;
        $this->em->persist($event);
        $fleets = [];
        for ($index = 0; $index < 12; ++$index) {
            $fleet = new Fleet();
            $fleet->playerUniverse = $this->membership;
            $fleet->homePlanet = $this->planet;
            $fleet->status = 'attacking';
            $participant = new \App\Entity\CombatParticipant();
            $participant->fleet = $fleet;
            $participant->playerUniverse = $this->membership;
            $participant->combatEvent = $event;
            $participant->role = 'attacker';
            $participant->arrivalAt = $event->startedAt;
            $this->em->persist($fleet);
            $this->em->persist($participant);
            $fleets[] = $fleet;
        }
        $this->em->flush();
        $repository = self::getContainer()->get(GameRepository::class);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        self::assertCount(12, $repository->fleetCombatEvents($this->membership, $fleets));
        self::assertLessThan(3, count($holder->getData()['default'] ?? []));
        self::assertSame([], $repository->fleetCombatEvents($this->membership, []));
        $event->status = 'resolved';
        $next = clone $event;
        $next->id = null;
        $next->status = 'pending';
        $participant = new \App\Entity\CombatParticipant();
        $participant->fleet = $fleets[0];
        $participant->playerUniverse = $this->membership;
        $participant->combatEvent = $next;
        $participant->role = 'attacker';
        $participant->arrivalAt = $event->startedAt;
        $this->em->persist($next);
        $this->em->persist($participant);
        $this->em->flush();
        self::assertSame([], $repository->fleetCombatEvents($this->membership, $fleets));
    }

    public function testComparisonQueuesUseOnlyPrivateScopedRowsAndPersistedTimes(): void
    {
        $other = new Planet();
        $other->solarSystem = $this->planet->solarSystem;
        $other->owner = $this->membership;
        $other->position = 6;
        $other->name = 'Seconde colonie synthétique';
        $foreign = clone $other;
        $foreign->owner = null;
        $foreign->position = 7;
        $foreign->name = 'Nom à ne pas exposer';
        $foreignPlayer = new Player();
        $foreignPlayer->email = 'other-comparison@example.test';
        $foreignPlayer->username = 'Autre comparatif';
        $foreignPlayer->password = 'unused';
        $foreignMembership = new PlayerUniverse();
        $foreignMembership->player = $foreignPlayer;
        $foreignMembership->universe = $this->membership->universe;
        $foreignMembership->joinedAt = new \DateTimeImmutable('2026-11-14T12:00:00Z');
        foreach ([$other, $foreign, $foreignPlayer, $foreignMembership] as $entity) {
            $this->em->persist($entity);
        }
        $now = new \DateTimeImmutable('2026-11-14T12:00:00Z');
        $create = function (Planet $planet, string $kind, string $slug, ?\DateTimeImmutable $started, int $duration, ?int $level = null, int $quantity = 1, ?PlayerUniverse $owner = null) use ($now): \App\Entity\QueueItem {
            $row = new \App\Entity\QueueItem();
            $row->planet = $planet;
            $row->playerUniverse = $owner ?? $this->membership;
            $row->queueType = $kind;
            $row->itemSlug = $slug;
            $row->itemLevel = $level;
            $row->quantity = $quantity;
            $row->startedAt = $started;
            $row->queuedAt = $now->modify('-10 minutes');
            $row->durationSeconds = $duration;
            $row->dispatchToken = \Symfony\Component\Uid\Uuid::v4()->toRfc4122();
            $this->em->persist($row);
            return $row;
        };
        $building = $create($this->planet, 'building', 'mine_metal', $now->modify('-1 minute'), 180, 2);
        $waitingBuilding = $create($this->planet, 'building', 'mine_metal', null, 200, 3);
        $readyYard = $create($other, 'shipyard', 'ground_turret_anti_light', $now->modify('-3 minutes'), 60, quantity: 7);
        $create($other, 'shipyard', 'fighter', null, 180, quantity: 2);
        $create($this->planet, 'research', 'engineering_foundations', $now->modify('-10 seconds'), 80, 2);
        $headResearch = $create($other, 'research', 'signature_reduction', $now->modify('-5 seconds'), 20, 3);
        $create($this->planet, 'research', 'engineering_foundations', null, 80, 3);
        $create($foreign, 'building', 'lab', null, 100, 7);
        $create($other, 'building', 'lab', null, 100, 9, owner: $foreignMembership);
        $this->em->flush();

        $repo = self::getContainer()->get(GameRepository::class);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        foreach ([[$this->planet], [$this->planet, $other, $foreign]] as $input) {
            $holder->reset();
            $rows = $repo->comparisonQueueRows($this->membership, $input);
            self::assertSame(1, count($holder->getData()['default'] ?? []));
            self::assertNotContains($foreign->id, array_column($rows, 'originId'));
            self::assertNotContains(9, array_column($rows, 'level'));
        }
        $comparison = self::getContainer()->get(\App\Service\Colony\ColonyComparisonActivities::class);
        $view = $comparison->forPlanets($this->membership, [$this->planet, $other]);
        $local = $view['local'][$this->planet->id]['building'];
        self::assertSame('running', $local['status']);
        self::assertSame(2, $local['level']);
        self::assertSame(1, $local['waiting']);
        self::assertEquals($now->modify('+2 minutes'), $local['finish']);
        $yard = $view['local'][$other->id]['shipyard'];
        self::assertSame('due', $yard['status']);
        self::assertSame(7, $yard['quantity']);
        self::assertSame(1, $yard['waiting']);
        self::assertNull($yard['finish']);
        self::assertSame($other->id, $view['researchOrigin']);
        self::assertSame(3, $view['research']['level']);
        self::assertSame(1, $view['research']['waiting']);
        self::assertSame(1, $view['research']['additionalActive']);
        self::assertEquals($now->modify('+15 seconds'), $view['research']['finish']);

        $period = new \App\Entity\VacationPeriod();
        $period->membership = $this->membership;
        $period->startedAt = $now->modify('-1 second');
        $period->scheduledEndAt = $now->modify('+1 hour');
        $this->em->persist($period);
        $this->em->flush();
        $paused = $comparison->forPlanets($this->membership, [$this->planet, $other]);
        self::assertSame('paused', $paused['research']['status']);
        self::assertNull($paused['research']['finish']);
        self::assertSame(1, $paused['research']['additionalActive']);
        self::assertSame('paused', $paused['local'][$this->planet->id]['building']['status']);
        self::assertNull($paused['local'][$this->planet->id]['building']['finish']);

        $other->owner = null;
        $this->em->flush();
        $withoutOrigin = $comparison->forPlanets($this->membership, [$this->planet]);
        self::assertNull($withoutOrigin['researchOrigin']);
        self::assertSame($view['research']['label'], $withoutOrigin['research']['label']);
        self::assertSame($view['research']['level'], $withoutOrigin['research']['level']);
        self::assertSame('waiting', $withoutOrigin['research']['status']);
        self::assertNull($withoutOrigin['research']['finish']);
        self::assertArrayNotHasKey($other->id, $withoutOrigin['local']);

        // Une lecture ne purge pas l’ordre échu, ne démarre pas l’attente et ne retouche pas l’origine.
        $this->em->clear();
        $repository = $this->em->getRepository(\App\Entity\QueueItem::class);
        self::assertNotNull($repository->find($readyYard->id));
        self::assertEquals($building->startedAt, $repository->find($building->id)->startedAt);
        self::assertNull($repository->find($waitingBuilding->id)->startedAt);
        self::assertSame($other->id, $repository->find($headResearch->id)->planet->id);
    }

    public function testColonyQueueCountsAreGroupedAndExcludeUnownedPlanets(): void
    {
        $foreign = new Planet();
        $foreign->name = 'Monde non possédé';
        $foreign->solarSystem = $this->planet->solarSystem;
        $foreign->position = 1;
        $this->em->persist($foreign);
        foreach ([$this->planet, $foreign] as $planet) {
            for ($index = 0; $index < 12; ++$index) {
                $item = new \App\Entity\QueueItem();
                $item->planet = $planet;
                $item->playerUniverse = $this->membership;
                $item->itemSlug = 'mine_metal';
                $item->queueType = 'building';
                $item->queuedAt = new \DateTimeImmutable('2026-11-14T12:00:00Z');
                $item->durationSeconds = 100;
                $item->dispatchToken = 'synthetic-count-'.$index;
                $this->em->persist($item);
            }
        }
        $this->em->flush();
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $repository = self::getContainer()->get(GameRepository::class);
        self::assertSame([$this->planet->id=>12], $repository->queueCounts($this->membership, [$this->planet, $foreign]));
        self::assertLessThan(3, count($holder->getData()['default'] ?? []));
        self::assertSame([], $repository->queueCounts($this->membership, []));
        self::assertCount(12, $repository->queue($this->planet));
    }
}
