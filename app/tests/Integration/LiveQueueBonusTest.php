<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Galaxy, OutboxMessage, Planet, PlanetBuilding, PlanetResource, Player, PlayerUniverse, PlayerTechnology, QueueItem, SolarSystem, Universe};
use App\Service\Colony\{ColonyView, ResourceService};
use App\Service\Player\VacationEconomyService;
use App\Service\Queue\{LiveQueueTiming, QueueService};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
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

    private function building(Planet $planet, string $slug, int $level): void
    {
        $building = new PlanetBuilding();
        $building->planet = $planet;
        $building->slug = $slug;
        $building->level = $level;
        $this->em->persist($building);
        $this->em->flush();
    }

    private function order(Planet $planet, string $group, string $slug, string $request): QueueItem
    {
        $this->queues->add($this->membership, $planet, $group, $slug, 1, str_repeat($request, 32));
        return $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $planet, 'itemSlug' => $slug], ['id' => 'DESC']);
    }

    private function earnedAt(Planet $planet, string $type, string $slug, int $duration, ?int $level = 1): QueueItem
    {
        $item = new QueueItem();
        $item->planet = $planet;
        $item->playerUniverse = $this->membership;
        $item->queueType = $type;
        $item->itemSlug = $slug;
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
