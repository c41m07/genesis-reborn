<?php

namespace App\Tests\Integration;

use App\Entity\{ActionReceipt, Galaxy, Planet, PlanetBuilding, PlanetResource, Player, PlayerUniverse, QueueItem, SolarSystem, Universe};
use App\Message\QueueItemFinished;
use App\MessageHandler\QueueItemFinishedHandler;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

final class ConcurrentQueueTest extends KernelTestCase
{
    private const NOW = '2026-11-15T12:00:00Z';

    private EntityManagerInterface $entityManager;
    private PlayerUniverse $membership;
    private Planet $planet;

    protected function setUp(): void
    {
        self::bootKernel();
        self::getContainer()->set('clock', new MockClock(self::NOW));
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->entityManager->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $universe = new Universe();
        $universe->slug = 'concurrent-queue';
        $universe->name = 'Concurrence synthétique';
        $player = new Player();
        $player->email = 'concurrent-queue@example.test';
        $player->username = 'Concurrence';
        $player->password = 'unused';
        $this->membership = new PlayerUniverse();
        $this->membership->player = $player;
        $this->membership->universe = $universe;
        $this->membership->joinedAt = new \DateTimeImmutable(self::NOW);
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $galaxy->name = 'Synthétique';
        $system = new SolarSystem();
        $system->galaxy = $galaxy;
        $system->name = 'Synthétique';
        $system->coordX = 0;
        $system->coordY = 0;
        $this->planet = new Planet();
        $this->planet->owner = $this->membership;
        $this->planet->solarSystem = $system;
        $this->planet->position = 5;
        $this->planet->name = 'Colonie concurrente';
        foreach ([$universe, $player, $this->membership, $galaxy, $system, $this->planet] as $entity) {
            $this->entityManager->persist($entity);
        }
        $this->entityManager->flush();
        self::getContainer()->get(ResourceService::class)->initialize($this->planet);
        $this->entityManager->flush();
    }

    public function testSimultaneousSpendingCannotPayForTwoOrders(): void
    {
        $price = $this->price(1)['metal'];
        $stock = $this->metal();
        $stock->currentStock = $price * 1.5;
        $this->entityManager->flush();

        $codes = $this->race([$this->addAction('a'), $this->addAction('b')]);
        sort($codes);
        self::assertSame([0, 10], $codes);
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(ActionReceipt::class)->count());
        self::assertEqualsWithDelta($price * 0.5, $this->metal()->currentStock, 0.000001);
    }

    public function testSimultaneousIdenticalSubmissionHasOneCostAndOneOrder(): void
    {
        $before = $this->metal()->currentStock;
        self::assertSame([0, 0], $this->race([$this->addAction('a'), $this->addAction('a')]));
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(ActionReceipt::class)->count());
        self::assertEqualsWithDelta($before - $this->price(1)['metal'], $this->metal()->currentStock, 0.000001);
    }

    public function testSimultaneousAddsAtNineLeaveExactlyTenOrders(): void
    {
        $this->seedLegacyBuildings(9);

        $codes = $this->race([$this->addAction('a'), $this->addAction('b')]);
        sort($codes);
        self::assertSame([0, 10], $codes);
        self::assertSame(10, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(ActionReceipt::class)->count());
    }

    public function testLegacyBacklogKeepsOrdersUntilTheyFinishOrAreCancelled(): void
    {
        $legacy = $this->seedLegacyBuildings(11);
        $service = self::getContainer()->get(\App\Service\Queue\QueueService::class);
        $before = $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, item_level, duration_seconds, paid_costs FROM queue_item ORDER BY id');

        self::assertSame([10], $this->race([$this->addAction('a')]));
        self::assertSame($before, $this->entityManager->getConnection()->fetchAllAssociative('SELECT id, item_level, duration_seconds, paid_costs FROM queue_item ORDER BY id'));
        self::assertSame(0, $this->entityManager->getRepository(ActionReceipt::class)->count());

        // Annuler une commande réellement en attente reste possible au-dessus du plafond.
        $service->cancel($this->membership, $legacy[10]->id, str_repeat('b', 32));
        self::assertSame(10, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame([10], $this->race([$this->addAction('c')]));

        self::getContainer()->get('clock')->sleep(3600);
        self::getContainer()->get(QueueItemFinishedHandler::class)(new QueueItemFinished($legacy[0]->id, $legacy[0]->dispatchToken));
        self::assertSame(9, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(PlanetBuilding::class)->findOneBy(['slug' => 'power_plant'])->level);
        self::assertNotNull($legacy[1]->startedAt);
        self::assertSame(['metal' => 25], $legacy[1]->paidCosts);
        self::assertSame(1, $service->add($this->membership, $this->planet, 'buildings', 'mine_metal', 1, str_repeat('d', 32)));
        self::assertSame(10, $this->entityManager->getRepository(QueueItem::class)->count());
    }

    /** @return list<QueueItem> Commandes synthétiques déjà payées avant le changement de plafond. */
    private function seedLegacyBuildings(int $count): array
    {
        $orders = [];
        $now = new \DateTimeImmutable(self::NOW);
        for ($index = 0; $index < $count; ++$index) {
            $item = new QueueItem();
            $item->planet = $this->planet;
            $item->playerUniverse = $this->membership;
            $item->queueType = 'building';
            $item->itemSlug = 'power_plant';
            $item->itemLevel = $index + 1;
            $item->durationSeconds = 3600;
            $item->queuedAt = $now;
            $item->startedAt = $index === 0 ? $now : null;
            $item->dispatchToken = 'backlog-'.$index;
            $item->paidCosts = ['metal' => 25];
            $this->entityManager->persist($item);
            $orders[] = $item;
        }
        $this->planet->buildingQueueStartedAt = $now;
        $this->entityManager->flush();

        return $orders;
    }

    public function testDuplicateWorkerMessagesCompleteOnlyOnce(): void
    {
        $item = $this->dueItem();
        $action = ['action' => 'complete', 'queueId' => $item->id, 'token' => $item->dispatchToken];
        self::assertSame([0, 0], $this->race([$action, $action]));
        $this->entityManager->clear();
        self::assertSame(0, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(PlanetBuilding::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(PlanetBuilding::class)->findOneBy([])->level);
    }

    public function testCompletionDuringAnOrderKeepsTheNextLevelAndPayment(): void
    {
        $item = $this->dueItem();
        $before = $this->metal()->currentStock;
        self::assertSame([0, 0], $this->race([
            ['action' => 'complete', 'queueId' => $item->id, 'token' => $item->dispatchToken],
            $this->addAction('a'),
        ]));
        $this->entityManager->clear();
        $items = $this->entityManager->getRepository(QueueItem::class)->findAll();
        self::assertCount(1, $items);
        self::assertSame(2, $items[0]->itemLevel);
        self::assertNotNull($items[0]->startedAt);
        self::assertNull($items[0]->dependsOn);
        self::assertSame(1, $this->entityManager->getRepository(PlanetBuilding::class)->findOneBy([])->level);
        self::assertEqualsWithDelta($before - $this->price(2)['metal'], $this->metal()->currentStock, 0.000001);
    }

    public function testObsoleteWorkerMessagesKeepTheCurrentCommand(): void
    {
        $item = $this->dueItem();
        $id = $item->id;
        $token = $item->dispatchToken;
        $action = ['action' => 'complete', 'queueId' => $id, 'token' => 'obsolete-token'];
        self::assertSame([0, 0], $this->race([$action, $action]));
        $this->entityManager->clear();
        self::assertSame(1, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(0, $this->entityManager->getRepository(PlanetBuilding::class)->count());
        $handler = self::getContainer()->get(QueueItemFinishedHandler::class);
        $handler(new QueueItemFinished($id, $token));
        $handler(new QueueItemFinished($id, 'obsolete-token'));
        self::assertSame(0, $this->entityManager->getRepository(QueueItem::class)->count());
        self::assertSame(1, $this->entityManager->getRepository(PlanetBuilding::class)->findOneBy([])->level);
    }

    private function dueItem(): QueueItem
    {
        $item = new QueueItem();
        $item->planet = $this->planet;
        $item->playerUniverse = $this->membership;
        $item->queueType = 'building';
        $item->itemSlug = 'mine_metal';
        $item->itemLevel = 1;
        $item->durationSeconds = 1;
        $item->queuedAt = $item->startedAt = new \DateTimeImmutable(self::NOW . ' -1 second');
        $item->dispatchToken = 'current-token';
        $this->planet->buildingQueueStartedAt = $item->startedAt;
        $this->entityManager->persist($item);
        $this->entityManager->flush();
        return $item;
    }

    private function addAction(string $key): array
    {
        return ['action' => 'add', 'key' => str_repeat($key, 32)];
    }

    private function price(int $level): array
    {
        $catalog = self::getContainer()->get(GameCatalog::class);
        return $catalog->costs($catalog->getTierForUniverse($this->membership->universe, 'buildings', 'mine_metal', 'basic'), $level, 1, 'buildings');
    }

    private function metal(): PlanetResource
    {
        return $this->entityManager->getRepository(PlanetResource::class)->findOneBy(['planet' => $this->planet, 'resourceType' => 'metal']);
    }

    private function race(array $actions): array
    {
        $directory = sys_get_temp_dir() . '/genesis-concurrent-queue-' . bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $barrier = $directory . '/go';
        $processes = [];
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        // Les deux connexions doivent attendre ce verrou avant de les laisser travailler.
        $connection->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$this->membership->id]);
        try {
            foreach ($actions as $index => $action) {
                $input = $action + [
                    'membershipId' => $this->membership->id,
                    'planetId' => $this->planet->id,
                    'now' => self::NOW,
                    'ready' => $directory . '/' . $index,
                    'barrier' => $barrier,
                ];
                $process = new Process([PHP_BINARY, dirname(__DIR__) . '/Support/concurrent_queue.php', json_encode($input, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(30);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 15;
            do {
                $ready = glob($directory . '/[0-9]') ?: [];
                if (count($ready) === count($actions)) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(count($actions), $ready, 'Les processus enfants doivent être prêts.');
            $ids = array_map(static fn (string $file): int => (int) file_get_contents($file), $ready);
            touch($barrier);
            do {
                $blocked = 0;
                foreach ($connection->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === count($actions)) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(count($actions), $blocked, 'Les deux actions doivent attendre simultanément le verrou.');
            $connection->commit();
            return array_map(static function (Process $process): int {
                $code = $process->wait();
                self::assertContains($code, [0, 10], $process->getErrorOutput() . $process->getOutput());
                return $code;
            }, $processes);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
        }
    }
}
