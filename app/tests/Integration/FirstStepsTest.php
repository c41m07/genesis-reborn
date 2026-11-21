<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\Fleet;
use App\Entity\FleetShip;
use App\Entity\Planet;
use App\Entity\PlayerDiscovery;
use App\Entity\SolarSystem;
use App\Service\Fleet\FleetService;
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
        self::assertSame($firstFinish, $this->proofTime($membership, 'metal_mine'));

        $upgrade = $this->order($membership, $planet, 'buildings', 'mine_metal', '4');
        $this->clock->sleep($upgrade->durationSeconds + 1);
        $this->queues->synchronize($membership);
        self::assertSame(['metal_mine' => true], $this->guide->completed($membership));
        self::assertSame($firstFinish, $this->proofTime($membership, 'metal_mine'));

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

    public function testRealShipConstructionAndNewlyDiscoveredSystemAreIndependentProofs(): void
    {
        $player = $this->player();
        [$membership, $planet] = $this->select($player, 'omega-1');
        $this->seedBuilding($planet, 'shipyard');
        $idle = new Fleet();
        $idle->playerUniverse = $membership;
        $idle->homePlanet = $planet;
        $probeStock = new FleetShip();
        $probeStock->fleet = $idle;
        $probeStock->slug = 'probe';
        $probeStock->quantity = 2;
        $this->entityManager->persist($idle);
        $this->entityManager->persist($probeStock);
        $this->entityManager->flush();
        $discovery = $this->entityManager->getRepository(PlayerDiscovery::class);
        self::assertSame(1, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $planet->solarSystem]));
        self::assertSame([], $this->guide->completed($membership), 'Ni système natal ni vaisseaux déjà possédés ne font preuve.');

        $build = $this->order($membership, $planet, 'ships', 'probe', '6');
        $this->queues->complete($build->id, $build->dispatchToken);
        self::assertSame([], $this->guide->completed($membership));
        $this->clock->sleep($build->durationSeconds + 1);
        $this->queues->complete($build->id, $build->dispatchToken);
        self::assertSame(3, $probeStock->quantity);
        self::assertSame(['ship' => true], $this->guide->completed($membership));

        $fleetService = self::getContainer()->get(FleetService::class);
        $known = $this->launchProbe($fleetService, $membership, $planet, $planet->solarSystem, '7');
        $this->clock->sleep($known->travelSeconds + 1);
        $fleetService->arrive($known->id, $known->dispatchToken);
        self::assertSame('completed', $known->status);
        self::assertSame(['ship' => true], $this->guide->completed($membership));
        self::assertSame(1, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $planet->solarSystem]));

        $systems = $this->entityManager->getRepository(SolarSystem::class)->findBy(['galaxy' => $planet->solarSystem->galaxy], ['id' => 'ASC']);
        $unknown = array_values(array_filter($systems, static fn (SolarSystem $system): bool => $system !== $planet->solarSystem));
        self::assertGreaterThanOrEqual(2, count($unknown));
        $first = $this->launchProbe($fleetService, $membership, $planet, $unknown[0], '8');
        $firstToken = $first->dispatchToken;
        $arrival = $first->arrivalAt->format('Y-m-d H:i:s');
        $fleetService->arrive($first->id, $firstToken);
        self::assertSame('traveling', $first->status);
        self::assertSame(0, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $unknown[0]]));
        self::assertSame(['ship' => true], $this->guide->completed($membership));
        $this->clock->sleep($first->travelSeconds + 1);
        $fleetService->arrive($first->id, $firstToken);
        $fleetService->arrive($first->id, $firstToken);
        self::assertSame('completed', $first->status);
        self::assertSame(1, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $unknown[0]]));
        self::assertSame(0, $this->entityManager->getRepository(FleetShip::class)->findOneBy(['fleet' => $first, 'slug' => 'probe'])?->quantity);
        self::assertEquals(['ship' => true, 'exploration' => true], $this->guide->completed($membership));
        self::assertSame($arrival, $this->proofTime($membership, 'exploration'));

        $later = $this->launchProbe($fleetService, $membership, $planet, $unknown[1], '9');
        $this->clock->sleep($later->travelSeconds + 1);
        $fleetService->arrive($later->id, $later->dispatchToken);
        self::assertSame('completed', $later->status);
        self::assertSame(1, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $unknown[1]]));
        self::assertSame($arrival, $this->proofTime($membership, 'exploration'));

        [$other] = $this->select($player, 'turbo-1');
        self::assertSame([], $this->guide->completed($other));
        self::assertEquals(['ship' => true, 'exploration' => true], $this->guide->completed($membership));
    }

    private function launchProbe(FleetService $service, PlayerUniverse $membership, Planet $planet, SolarSystem $system, string $key): Fleet
    {
        self::assertFalse($service->send($membership, $planet, 'explore', $system->id, ['probe' => 1], .5, [], str_repeat($key, 32), requireExactComposition: true));
        $flight = $this->entityManager->getRepository(Fleet::class)->findOneBy([
            'playerUniverse' => $membership,
            'destinationSystem' => $system,
            'status' => 'traveling',
        ]);
        self::assertNotNull($flight);
        self::assertNotNull($flight->arrivalAt);

        return $flight;
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

    private function proofTime(PlayerUniverse $membership, string $step): string
    {
        return (string) $this->entityManager->getConnection()->fetchOne(
            'SELECT completed_at FROM first_step_completion WHERE player_universe_id = ? AND step_key = ?',
            [$membership->id, $step],
        );
    }
}
