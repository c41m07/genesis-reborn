<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Planet;
use App\Entity\PlanetBuilding;
use App\Entity\Player;
use App\Entity\PlayerTechnology;
use App\Entity\PlayerUniverse;
use App\Entity\QueueItem;
use App\Entity\Universe;
use App\Service\Player\FirstSteps;
use App\Service\Player\RegistrationService;
use App\Service\Queue\QueueService;
use App\Service\Universe\UniverseImporter;
use App\Service\Universe\UniverseService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class FirstStepsTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private MockClock $clock;
    private QueueService $queues;
    private FirstSteps $guide;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $parameters = $this->entityManager->getConnection()->getParams();
        if (($parameters['host'] ?? '') !== 'database-test' || ($parameters['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $tool = new SchemaTool($this->entityManager);
        $metadata = $this->entityManager->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);

        $this->clock = new MockClock('2026-12-01T08:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->queues = self::getContainer()->get(QueueService::class);
        $this->guide = self::getContainer()->get(FirstSteps::class);
    }

    public function testCompletedOrderIsDurableAndIsolatedButQueueAndExistingLevelsAreNotProofs(): void
    {
        $player = $this->player();
        [$membership, $planet] = $this->select($player, 'omega-1');
        $this->seedBuilding($planet, 'mine_nano');
        self::assertSame([], $this->guide->completed($membership));

        $cancelled = $this->order($membership, $planet, 'buildings', 'mine_metal', '1');
        self::assertSame([], $this->guide->completed($membership));
        $cancelledId = $cancelled->id;
        $cancelledToken = $cancelled->dispatchToken;
        $this->queues->cancel($membership, $cancelledId, str_repeat('2', 32));
        $this->clock->sleep($cancelled->durationSeconds + 1);
        $this->queues->complete($cancelledId, $cancelledToken);
        self::assertSame([], $this->guide->completed($membership));

        $first = $this->order($membership, $planet, 'buildings', 'mine_metal', '3');
        $this->queues->complete($first->id, $first->dispatchToken);
        self::assertSame([], $this->guide->completed($membership));
        $firstFinish = $first->finishAt()->format('Y-m-d H:i:s');
        $this->clock->sleep($first->durationSeconds + 1);
        $this->queues->complete($first->id, str_repeat('0', 36));
        self::assertSame([], $this->guide->completed($membership));
        $this->queues->complete($first->id, $first->dispatchToken);
        self::assertSame(['metal_mine' => true], $this->guide->completed($membership));
        self::assertSame($firstFinish, $this->firstMetalProof($membership));

        $upgrade = $this->order($membership, $planet, 'buildings', 'mine_metal', '4');
        $this->clock->sleep($upgrade->durationSeconds + 1);
        $this->queues->synchronize($membership);
        self::assertSame(['metal_mine' => true], $this->guide->completed($membership));
        self::assertSame($firstFinish, $this->firstMetalProof($membership));

        [$other] = $this->select($player, 'turbo-1');
        self::assertNotSame($membership->id, $other->id);
        self::assertSame([], $this->guide->completed($other));
        self::assertSame(['metal_mine' => true], $this->guide->completed($membership));
    }

    public function testResearchOnlyCountsAfterTheLevelExists(): void
    {
        [$membership, $planet] = $this->select($this->player(), 'omega-1');
        $this->seedBuilding($planet, 'lab');
        $item = $this->order($membership, $planet, 'technologies', 'engineering_foundations', '5');
        self::assertSame([], $this->guide->completed($membership));
        $this->clock->sleep($item->durationSeconds + 1);
        $this->queues->synchronize($membership);
        self::assertSame(['research' => true], $this->guide->completed($membership));
        $technology = $this->entityManager->getRepository(PlayerTechnology::class)->findOneBy([
            'playerUniverse' => $membership,
            'slug' => 'engineering_foundations',
        ]);
        self::assertSame(1, $technology?->level);
    }

    private function player(): Player
    {
        $data = new RegistrationData();
        $data->username = 'GuideFixture';
        $data->email = 'guide-fixture@example.test';
        $data->plainPassword = 'Only-a-local-fixture-123!';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $player->emailVerifiedAt = $this->clock->now();
        $this->entityManager->flush();

        return $player;
    }

    /** @return array{PlayerUniverse, Planet} */
    private function select(Player $player, string $slug): array
    {
        $universe = $this->entityManager->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
        self::assertNotNull($universe);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $membership = $player->currentPlayerUniverse;
        self::assertNotNull($membership);
        $planet = $this->entityManager->getRepository(Planet::class)->findOneBy(['owner' => $membership]);
        self::assertNotNull($planet);

        return [$membership, $planet];
    }

    private function seedBuilding(Planet $planet, string $slug): void
    {
        $building = new PlanetBuilding();
        $building->planet = $planet;
        $building->slug = $slug;
        $building->level = 1;
        $this->entityManager->persist($building);
        $this->entityManager->flush();
    }

    private function order(PlayerUniverse $membership, Planet $planet, string $group, string $slug, string $key): QueueItem
    {
        self::assertSame(1, $this->queues->add($membership, $planet, $group, $slug, 1, str_repeat($key, 32)));
        $order = $this->entityManager->getRepository(QueueItem::class)->findOneBy(['planet' => $planet, 'itemSlug' => $slug]);
        self::assertNotNull($order);
        self::assertNotNull($order->startedAt);

        return $order;
    }

    private function firstMetalProof(PlayerUniverse $membership): string
    {
        return (string) $this->entityManager->getConnection()->fetchOne(
            "SELECT completed_at FROM first_step_completion WHERE player_universe_id = ? AND step_key = 'metal_mine'",
            [$membership->id],
        );
    }
}
