<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Galaxy, OutboxMessage, Planet, PlanetBuilding, PlanetResource, Player, PlayerUniverse, PlayerTechnology, QueueItem, SolarSystem, Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{ColonyView, ResourceService};
use App\Service\Player\VacationEconomyService;
use App\Service\Queue\{LiveQueueTiming, QueueService};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class LiveQueueBonusTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private QueueService $queues;
    private PlayerUniverse $membership;
    private Planet $local;
    private Planet $remote;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-24T12:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $universe = new Universe();
        $universe->name = 'Bonus';
        $universe->slug = 'live-queue-fixture';
        $player = new Player();
        $player->email = 'queue-bonus@example.test';
        $player->username = 'Bonus';
        $player->password = 'test';
        $this->membership = new PlayerUniverse();
        $this->membership->player = $player;
        $this->membership->universe = $universe;
        $this->membership->joinedAt = $this->clock->now();
        $this->membership->catalogUnlocks = PlayerUniverse::INITIAL_CATALOG_UNLOCKS;
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $galaxy->name = 'L';
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $system->name = 'Système';
        $system->coordX = 0;
        $system->coordY = 0;
        foreach ([$universe, $player, $this->membership, $galaxy, $system] as $entity) {
            $this->em->persist($entity);
        }
        foreach (['local' => 4, 'remote' => 5] as $name => $position) {
            $planet = new Planet();
            $planet->owner = $this->membership;
            $planet->solarSystem = $system;
            $planet->position = $position;
            $planet->name = $name;
            $this->em->persist($planet);
            $this->{$name} = $planet;
        }
        $this->em->flush();
        $resources = self::getContainer()->get(ResourceService::class);
        foreach ([$this->local, $this->remote] as $planet) {
            $resources->initialize($planet);
            $this->em->flush();
            foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $planet]) as $resource) {
                $resource->currentStock = 100000000;
            }
        }
        $this->em->flush();
        $this->queues = self::getContainer()->get(QueueService::class);
    }

    public function testEarnedResearchChangesBothColoniesAndAnOldMessageCannotCompleteTheJob(): void
    {
        foreach ([$this->local, $this->remote] as $planet) {
            $this->building($planet, 'mine_metal', 12);
        }
        $local = $this->order($this->local, 'buildings', 'mine_metal', 'a');
        $next = $this->order($this->local, 'buildings', 'mine_metal', 'b');
        $remote = $this->order($this->remote, 'buildings', 'mine_metal', 'c');
        $legacy = $this->earnedAt($this->remote, 'shipyard', 'fighter', 1000, null);
        $bonus = $this->earnedAt($this->local, 'research', 'engineering_foundations', 40);
        $firstTick = $local->startedAt;
        $paid = $local->paidCosts;
        $waitingPaid = $next->paidCosts;
        $oldFull = $local->durationSeconds;
        $oldToken = $local->dispatchToken;
        $oldEnd = $local->finishAt();
        $start = $this->clock->now()->getTimestamp();
        $this->clock->sleep(40);
        $this->queues->complete($bonus->id, $bonus->dispatchToken);
        $full = LiveQueueTiming::fullDuration($local->liveTiming, ['basic' => 1]);
        $remaining = (int) ceil((1 - 40 / $oldFull) * $full - 1e-7);
        self::assertLessThan($oldEnd, $local->finishAt());
        self::assertEquals($firstTick, $local->startedAt);
        self::assertSame($start + 40 + $remaining, $local->finishAt()->getTimestamp());
        self::assertEquals($local->finishAt(), $remote->finishAt());
        self::assertSame($paid, $local->paidCosts);
        self::assertSame(1000, $legacy->durationSeconds);
        self::assertNull($next->startedAt);
        self::assertNotSame($oldToken, $local->dispatchToken);
        $replacement = $this->em->getRepository(OutboxMessage::class)->findOneBy(['kind' => 'queue', 'targetId' => $local->id, 'token' => $local->dispatchToken]);
        self::assertNotNull($replacement);
        self::assertEquals($local->finishAt(), $replacement->dueAt);

        $this->clock->sleep($remaining);
        $id = $local->id;
        $this->queues->complete($id, $oldToken);
        self::assertNotNull($this->em->find(QueueItem::class, $id));
        $token = $local->dispatchToken;
        $this->queues->complete($id, $token);
        $this->queues->complete($id, $token);
        self::assertNull($this->em->find(QueueItem::class, $id));
        self::assertEquals($this->clock->now(), $next->startedAt);
        self::assertSame($waitingPaid, $next->paidCosts);
        self::assertSame(LiveQueueTiming::fullDuration($next->liveTiming, ['basic' => 1]), $next->durationSeconds);
    }

    public function testTenHourQuotesAndOrdersUseTheInfrastructureOnTheColonyOfOrigin(): void
    {
        $this->useTenHourExamples();
        $this->infrastructureLevels($this->local, 'lab', [25, 25, 25]);
        $this->infrastructureLevels($this->remote, 'lab', [20, 20, 20]);
        $this->infrastructureLevels($this->local, 'shipyard', [20, 20, 20]);
        $this->infrastructureLevels($this->remote, 'shipyard', [25, 25, 25]);

        $researchQuote = $this->quote($this->local, 'technologies', 'engineering_foundations');
        $research = $this->order($this->local, 'technologies', 'engineering_foundations', 'a');
        self::assertSame(3780, $researchQuote['duration']);
        self::assertSame($researchQuote['duration'], $research->durationSeconds);
        self::assertSame(6300, $researchQuote['costs']['electricity']);
        self::assertSame($researchQuote['costs'], $research->paidCosts);
        self::assertEquals($this->clock->now()->modify('+63 minutes'), $research->finishAt());

        // Le niveau 2 attend la recherche du même empire, mais le labo de sa propre colonie s'applique.
        $waitingQuote = $this->quote($this->remote, 'technologies', 'engineering_foundations');
        $waiting = $this->order($this->remote, 'technologies', 'engineering_foundations', 'b');
        self::assertSame(11567, $waitingQuote['duration']);
        self::assertSame($waitingQuote['duration'], $waiting->durationSeconds);
        self::assertSame($waitingQuote['costs'], $waiting->paidCosts);
        self::assertNull($waiting->startedAt);

        foreach ([[$this->local, 8568, 'c'], [$this->remote, 3780, 'd']] as [$planet, $expected, $request]) {
            $shipQuote = $this->quote($planet, 'ships', 'fighter');
            $ship = $this->order($planet, 'ships', 'fighter', $request);
            self::assertSame($expected, $shipQuote['duration']);
            self::assertSame($expected, $ship->durationSeconds);
            self::assertSame($shipQuote['costs'], $ship->paidCosts);
            self::assertSame($this->clock->now()->getTimestamp() + $expected, $ship->finishAt()->getTimestamp());
        }

        $this->clock->sleep(3780);
        $this->queues->complete($research->id, $research->dispatchToken);
        self::assertEquals($this->clock->now(), $waiting->startedAt);
        self::assertSame(11567, $waiting->durationSeconds);
        self::assertSame($waitingQuote['costs'], $waiting->paidCosts);
    }

    public static function localOperationGroups(): iterable
    {
        yield 'recherche et laboratoire' => ['technologies', 'engineering_foundations', 'lab', 14580];
        yield 'vaisseau et chantier' => ['ships', 'fighter', 'shipyard', 10800];
    }

    #[DataProvider('localOperationGroups')]
    public function testExistingActiveAndWaitingOrdersKeepTheCapRecordedAtPurchase(
        string $group,
        string $operation,
        string $infrastructure,
        int $waitingDurationAtMax,
    ): void {
        $this->useTenHourExamples();
        $this->infrastructureLevels($this->local, $infrastructure, [25, 25, 24]);
        $active = $this->order($this->local, $group, $operation, 'a');
        $waiting = $this->order($this->local, $group, $operation, 'b');

        // Reconstituer les paramètres persistés d’un achat antérieur au changement de plafond.
        $levels = ['basic' => 25, 'intermediate' => 25, 'superior' => 24];
        foreach ([$active, $waiting] as $item) {
            $item->liveTiming['cap'] = 70.0;
            $item->durationSeconds = LiveQueueTiming::fullDuration($item->liveTiming, $levels);
            $item->liveTiming['full'] = $item->durationSeconds;
            $item->paidCosts['electricity'] = 100 * (int) ceil($item->durationSeconds / 60);
        }
        $this->em->flush();

        $oldActiveCosts = $active->paidCosts;
        $oldWaitingCosts = $waiting->paidCosts;
        $originalEnd = $active->finishAt();
        self::assertSame(11141, $active->durationSeconds);
        self::assertSame(18600, $oldActiveCosts['electricity']);
        self::assertNull($waiting->startedAt);
        $this->queues->synchronize($this->membership);
        self::assertEquals($originalEnd, $active->finishAt());

        $upgrade = $this->earnedAt($this->local, 'building', $infrastructure, 40, 25, 'superior');
        $this->clock->sleep(40);
        $this->queues->complete($upgrade->id, $upgrade->dispatchToken);
        // Le nouveau niveau accélère l’ancienne commande, mais vers 70 % et non vers 89,5 %.
        self::assertSame(10800, $active->liveTiming['full']);
        self::assertEqualsWithDelta(1 - 40 / 11141, $active->liveTiming['work'], 1e-12);
        self::assertSame($oldActiveCosts, $active->paidCosts);
        self::assertSame(70.0, $waiting->liveTiming['cap']);
        $queueName = $group === 'technologies' ? 'research' : 'shipyard';
        $projection = self::getContainer()->get(ColonyView::class)->data($this->membership, $this->local, section: $group)['queues'][$queueName];
        self::assertSame($waitingDurationAtMax, $projection[1]['duration']);

        $this->clock->sleep($active->durationSeconds - 40);
        $this->queues->complete($active->id, $active->dispatchToken);
        self::assertEquals($this->clock->now(), $waiting->startedAt);
        self::assertSame($waitingDurationAtMax, $waiting->durationSeconds);
        self::assertSame($oldWaitingCosts, $waiting->paidCosts);
    }

    public function testLocalLabRatesAreProjectedFromTheResearchOrigin(): void
    {
        foreach ([$this->local, $this->remote] as $planet) {
            $this->building($planet, 'lab', 1);
        }
        $active = $this->order($this->local, 'technologies', 'engineering_foundations', 'a');
        $queued = $this->order($this->remote, 'technologies', 'engineering_foundations', 'b');
        // Le laboratoire amélioré n’est pas celui de la recherche active.
        $lab = $this->earnedAt($this->remote, 'building', 'lab', 40, 2);
        $activeEnd = $active->finishAt();
        $activeToken = $active->dispatchToken;
        $this->clock->sleep(40);
        $this->queues->complete($lab->id, $lab->dispatchToken);
        self::assertEquals($activeEnd, $active->finishAt());
        self::assertSame($activeToken, $active->dispatchToken);
        $duration = LiveQueueTiming::fullDuration($queued->liveTiming, ['basic' => 2]);
        self::assertLessThan($queued->durationSeconds, $duration);
        $projection = self::getContainer()->get(ColonyView::class)->data($this->membership, $this->local, section: 'buildings')['queues']['research'];
        self::assertSame($duration, $projection[1]['duration']);
        self::assertSame($activeEnd->getTimestamp() + $duration, $projection[1]['finish']->getTimestamp());

        $localLab = $this->earnedAt($this->local, 'building', 'lab', 40, 2);
        $this->clock->sleep(40);
        $this->queues->complete($localLab->id, $localLab->dispatchToken);
        self::assertLessThan($activeEnd, $active->finishAt());
        self::assertNotSame($activeToken, $active->dispatchToken);
    }

    public function testLocalShipyardAcceleratesShipsButNeverDefenses(): void
    {
        $this->building($this->local, 'shipyard', 1);
        $this->building($this->remote, 'shipyard', 1);
        $this->building($this->remote, 'fortress', 1);
        $ship = $this->order($this->local, 'ships', 'fighter', 'a');
        $defense = $this->order($this->remote, 'defenses', 'ground_turret_anti_light', 'b');
        $localYard = $this->earnedAt($this->local, 'building', 'shipyard', 40, 2);
        $remoteYard = $this->earnedAt($this->remote, 'building', 'shipyard', 45, 2);
        $localYardId = $localYard->id;
        $shipEnd = $ship->finishAt();
        $defenseEnd = $defense->finishAt();
        $defenseToken = $defense->dispatchToken;
        self::assertNull($defense->liveTiming);
        $this->clock->sleep(45);
        $this->queues->complete($remoteYard->id, $remoteYard->dispatchToken);
        self::assertLessThan($shipEnd, $ship->finishAt());
        self::assertEquals($defenseEnd, $defense->finishAt());
        self::assertSame($defenseToken, $defense->dispatchToken);
        self::assertSame(40, $ship->liveTiming['checkpoint'] - $ship->startedAt->getTimestamp());
        // La fin arrivée en retard sur le worker a été traitée à sa vraie date.
        self::assertNull($this->em->find(QueueItem::class, $localYardId));
    }

    public function testBoostThenVacationPreservesTheWorkAtResume(): void
    {
        $this->building($this->local, 'mine_metal', 15);
        $head = $this->order($this->local, 'buildings', 'mine_metal', 'a');
        $this->clock->sleep(30);
        $this->queues->boost($this->membership, $head->id, 4 / 3, str_repeat('f', 32));
        $oldToken = $head->dispatchToken;
        $beforePause = $head->finishAt()->getTimestamp() - $this->clock->now()->getTimestamp();
        $work = $head->liveTiming['work'];
        // PHPUnit désactive volontairement cette fonction en dehors de cette fixture.
        $container = self::getContainer();
        $vacations = new VacationEconomyService(
            $container->get(\App\Manager\GameManager::class),
            $container->get(\App\Repository\GameRepository::class),
            $container->get(\App\Service\Colony\ActionGuard::class),
            $container->get(ResourceService::class),
            $this->queues,
            new \App\Service\Player\VacationPolicy($this->clock),
            $container->get(\App\Service\Player\EmpireActivityService::class),
            $container->get(\App\Service\Fleet\FleetService::class),
            $this->clock,
            true,
        );
        $vacations->start($this->membership);
        $this->clock->sleep(48 * 3600);
        $this->queues->complete($head->id, $oldToken);
        $vacations->finish($this->membership);
        self::assertSame($beforePause, $head->finishAt()->getTimestamp() - $this->clock->now()->getTimestamp());
        self::assertEqualsWithDelta($work, LiveQueueTiming::remainingWork($head->liveTiming, $this->clock->now()->getTimestamp()), 0.000001);
    }

    private function useTenHourExamples(): void
    {
        $this->membership->universe->gameSettings['catalog_overrides'] = [
            'technologies' => ['engineering_foundations' => ['research_time_base' => 36000]],
            'ships' => ['fighter' => ['build_time_base' => 36000]],
        ];
        $this->em->flush();
    }

    private function infrastructureLevels(Planet $planet, string $slug, array $levels): void
    {
        foreach (GameCatalog::TIERS as $index => $tier) {
            $this->building($planet, $slug, $levels[$index], $tier);
        }
    }

    private function quote(Planet $planet, string $group, string $slug): array
    {
        $cards = self::getContainer()->get(ColonyView::class)->data($this->membership, $planet, section: $group)['cards'][$group];
        foreach ($cards as $card) {
            if ($card['definition']['slug'] === $slug && $card['tier'] === 'basic') {
                return $card;
            }
        }
        self::fail('Devis du rang Basique absent : '.$slug);
    }

    private function building(Planet $planet, string $slug, int $level, string $tier = 'basic'): void
    {
        $building = new PlanetBuilding();
        $building->planet = $planet;
        $building->slug = $slug;
        $building->tier = $tier;
        $building->level = $level;
        $this->em->persist($building);
        $this->em->flush();
    }

    private function order(Planet $planet, string $group, string $slug, string $request): QueueItem
    {
        $this->queues->add($this->membership, $planet, $group, $slug, 1, str_repeat($request, 32));
        return $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $planet, 'itemSlug' => $slug], ['id' => 'DESC']);
    }

    private function earnedAt(Planet $planet, string $type, string $slug, int $duration, ?int $level = 1, string $tier = 'basic'): QueueItem
    {
        $item = new QueueItem();
        $item->planet = $planet;
        $item->playerUniverse = $this->membership;
        $item->queueType = $type;
        $item->itemSlug = $slug;
        $item->tier = $tier;
        $item->itemLevel = $level;
        $item->durationSeconds = $duration;
        $item->startedAt = $this->clock->now();
        $item->queuedAt = $this->clock->now();
        $item->dispatchToken = 'test-' . $slug;
        $this->em->persist($item);
        $this->em->flush();
        return $item;
    }
}
