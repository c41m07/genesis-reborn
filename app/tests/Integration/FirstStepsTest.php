<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\EspionageReport;
use App\Entity\Fleet;
use App\Entity\FleetShip;
use App\Entity\Planet;
use App\Entity\PlayerDiscovery;
use App\Entity\SolarSystem;
use App\Service\Fleet\FleetService;
use App\Entity\PlanetBuilding;
use App\Entity\PlanetResource;
use App\Entity\Player;
use App\Entity\PlayerTechnology;
use App\Entity\PlayerUniverse;
use App\Entity\QueueItem;
use App\Entity\Universe;
use App\Service\Player\FirstSteps;
use App\Service\Player\PirateExercise;
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

    public function testNpcEspionageCountsDetectedReportAndCanBeRetriedWithoutAnotherMilestone(): void
    {
        [$membership, $origin] = $this->select($this->player(), 'omega-1');
        $target = $this->pickFreeKnownPlanet($origin);
        $target->npcPlanet = true;
        $target->conquestLevel = 5;
        $this->seedShips($membership, $origin, 'probe', 3);
        // Uniquement la fixture : le tirage déterministe rend le cas « détecté » reproductible.
        self::getContainer()->set(\Random\Randomizer::class, new \Random\Randomizer(new \Random\Engine\Mt19937(50)));
        $service = self::getContainer()->get(FleetService::class);
        $reports = $this->entityManager->getRepository(EspionageReport::class);

        $flight = $this->launchPlanetMission($service, $membership, $origin, $target, 'spy', 'probe', 'a');
        $token = $flight->dispatchToken;
        $service->arrive($flight->id, $token);
        self::assertSame(0, $reports->count(['sender' => $membership]));
        self::assertSame([], $this->guide->completed($membership));

        $this->clock->sleep($flight->travelSeconds + 1);
        $service->arrive($flight->id, $token);
        $service->arrive($flight->id, $token);
        $report = $reports->findOneBy(['sender' => $membership, 'target' => $target]);
        self::assertNotNull($report);
        self::assertSame('detected', $report->result);
        self::assertSame(0, $report->intelCategories);
        self::assertSame([], $report->snapshot);
        self::assertSame(1, $report->probesSent);
        self::assertSame(0, $this->entityManager->getRepository(FleetShip::class)->findOneBy(['fleet' => $flight, 'slug' => 'probe'])?->quantity);
        self::assertSame(['espionage' => true], $this->guide->completed($membership));
        self::assertSame(1, $reports->count(['sender' => $membership]));
        $firstProof = $this->proofTime($membership, 'espionage');
        self::assertSame($report->createdAt->format('Y-m-d H:i:s'), $firstProof);

        // Une nouvelle vraie mission reste disponible et livre son propre rapport, sans remplacer la preuve.
        $later = $this->launchPlanetMission($service, $membership, $origin, $target, 'spy', 'probe', 'b');
        $this->clock->sleep($later->travelSeconds + 1);
        $service->arrive($later->id, $later->dispatchToken);
        self::assertSame(2, $reports->count(['sender' => $membership, 'target' => $target]));
        self::assertSame($firstProof, $this->proofTime($membership, 'espionage'));
        [$other] = $this->select($membership->player, 'turbo-1');
        self::assertSame([], $this->guide->completed($other));
    }

    public function testEspionageReportAndProofRollbackTogetherWhileNonNpcReportCannotCount(): void
    {
        [$membership, $origin] = $this->select($this->player(), 'omega-1');
        $neutral = $this->pickFreeKnownPlanet($origin);
        $npc = $this->pickFreeKnownPlanet($origin, $neutral);
        $npc->npcPlanet = true;
        $this->seedShips($membership, $origin, 'probe', 2);
        $service = self::getContainer()->get(FleetService::class);
        $reports = $this->entityManager->getRepository(EspionageReport::class);

        $nonNpc = $this->launchPlanetMission($service, $membership, $origin, $neutral, 'spy', 'probe', 'c');
        $this->clock->sleep($nonNpc->travelSeconds + 1);
        $service->arrive($nonNpc->id, $nonNpc->dispatchToken);
        self::assertSame(1, $reports->count(['sender' => $membership, 'target' => $neutral]));
        self::assertSame([], $this->guide->completed($membership));

        $flight = $this->launchPlanetMission($service, $membership, $origin, $npc, 'spy', 'probe', 'd');
        $flightId = $flight->id;
        $token = $flight->dispatchToken;
        $membershipId = $membership->id;
        $npcId = $npc->id;
        $this->clock->sleep($flight->travelSeconds + 1);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $service->arrive($flightId, $token);
            self::assertSame(1, $reports->count(['sender' => $membership, 'target' => $npc]));
            self::assertSame(['espionage' => true], $this->guide->completed($membership));
        } finally {
            $connection->rollBack();
            $this->entityManager->clear();
        }

        $membership = $this->entityManager->find(PlayerUniverse::class, $membershipId);
        $npc = $this->entityManager->find(Planet::class, $npcId);
        self::assertSame(0, $reports->count(['sender' => $membership, 'target' => $npc]));
        self::assertSame('traveling', $this->entityManager->find(Fleet::class, $flightId)?->status);
        self::assertSame([], $this->guide->completed($membership));
        $service->arrive($flightId, $token);
        self::assertSame(1, $reports->count(['sender' => $membership, 'target' => $npc]));
        self::assertSame(['espionage' => true], $this->guide->completed($membership));
    }

    public function testColonizationCountsOnlyFreePlanetTransferAndRollsBackWithIt(): void
    {
        [$membership, $origin] = $this->select($this->player(), 'omega-1');
        $blocked = $this->pickFreeKnownPlanet($origin);
        $available = $this->pickFreeKnownPlanet($origin, $blocked);
        $technology = new PlayerTechnology();
        $technology->playerUniverse = $membership;
        $technology->slug = 'colonial_expansion';
        $technology->level = 1;
        $this->entityManager->persist($technology);
        $this->seedShips($membership, $origin, 'colony_ship', 2);
        $service = self::getContainer()->get(FleetService::class);
        self::assertNotNull($origin->acquiredAt);
        self::assertSame([], $this->guide->completed($membership), 'La planète de départ n’est pas une colonisation.');

        $unavailable = $this->launchPlanetMission($service, $membership, $origin, $blocked, 'colonize', 'colony_ship', 'e');
        $blocked->npcPlanet = true;
        $this->entityManager->flush();
        $this->clock->sleep($unavailable->travelSeconds + 1);
        $service->arrive($unavailable->id, $unavailable->dispatchToken);
        self::assertSame('returning', $unavailable->status);
        $this->clock->sleep($unavailable->travelSeconds + 1);
        $service->arrive($unavailable->id, $unavailable->dispatchToken);
        self::assertSame('completed', $unavailable->status);
        self::assertNull($blocked->owner);
        self::assertSame([], $this->guide->completed($membership));

        $flight = $this->launchPlanetMission($service, $membership, $origin, $available, 'colonize', 'colony_ship', 'f');
        $token = $flight->dispatchToken;
        $flightId = $flight->id;
        $membershipId = $membership->id;
        $targetId = $available->id;
        self::assertNull($available->owner);
        self::assertSame([], $this->guide->completed($membership));
        $this->clock->sleep($flight->travelSeconds + 1);
        $connection = $this->entityManager->getConnection();
        $connection->beginTransaction();
        try {
            $service->arrive($flightId, $token);
            self::assertSame($membership, $available->owner);
            self::assertSame(['colonization' => true], $this->guide->completed($membership));
        } finally {
            $connection->rollBack();
            $this->entityManager->clear();
        }

        $membership = $this->entityManager->find(PlayerUniverse::class, $membershipId);
        $available = $this->entityManager->find(Planet::class, $targetId);
        self::assertNull($available->owner);
        self::assertNull($available->acquiredAt);
        self::assertSame([], $this->guide->completed($membership));
        $service->arrive($flightId, $token);
        $firstProof = $this->proofTime($membership, 'colonization');
        $this->clock->sleep(60);
        $service->arrive($flightId, $token);
        self::assertSame($membership, $available->owner);
        self::assertSame($available->acquiredAt?->format('Y-m-d H:i:s'), $firstProof);
        self::assertSame($firstProof, $this->proofTime($membership, 'colonization'));
        self::assertSame(5, $this->entityManager->getRepository(PlanetResource::class)->count(['planet' => $available]));
        self::assertSame(['colonization' => true], $this->guide->completed($membership), 'La colonisation ne nécessite pas les autres jalons.');
        [$other] = $this->select($membership->player, 'turbo-1');
        self::assertSame([], $this->guide->completed($other));
    }

    public function testPirateExerciseRecordsOnlyTheFirstCompletionOfThisEmpire(): void
    {
        $player = $this->player();
        [$membership, $origin] = $this->select($player, 'omega-1');
        $this->seedShips($membership, $origin, 'probe', 2);
        $connection = $this->entityManager->getConnection();
        $fleetSnapshot = static fn (): array => $connection->fetchAllAssociative(
            'SELECT f.id, f.status, s.slug, s.quantity FROM fleet f INNER JOIN fleet_ship s ON f.id = s.fleet_id WHERE f.player_universe_id = ? ORDER BY s.id',
            [$membership->id],
        );
        $stockSnapshot = static fn (): array => $connection->fetchAllAssociative(
            'SELECT resource_type, current_stock, last_updated_at FROM planet_resource WHERE planet_id = ? ORDER BY resource_type',
            [$origin->id],
        );
        $fleets = $fleetSnapshot();
        $stocks = $stockSnapshot();
        $proofTime = $this->clock->now()->format('Y-m-d H:i:s');
        $exercise = self::getContainer()->get(PirateExercise::class);

        self::assertSame('attacker_wins', $exercise->report(1)['outcome']);
        self::assertSame([], $this->guide->completed($membership), 'Produire le calcul seul ne décide pas du jalon.');
        $this->guide->recordPirateExercise($membership);
        self::assertSame(['pirate_exercise' => true], $this->guide->completed($membership));
        self::assertSame($proofTime, $this->proofTime($membership, 'pirate_exercise'));

        $this->clock->sleep(60);
        self::assertSame('defender_wins', $exercise->report(0)['outcome']);
        $this->guide->recordPirateExercise($membership);
        self::assertSame($proofTime, $this->proofTime($membership, 'pirate_exercise'));
        self::assertNotEmpty($fleets);
        self::assertNotEmpty($stocks);
        self::assertSame($fleets, $fleetSnapshot());
        self::assertSame($stocks, $stockSnapshot());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM combat_log'));

        [$other] = $this->select($player, 'turbo-1');
        self::assertSame([], $this->guide->completed($other));
        self::assertSame(['pirate_exercise' => true], $this->guide->completed($membership));
    }

    private function pickFreeKnownPlanet(Planet $origin, ?Planet $excluded = null): Planet
    {
        $planets = $this->entityManager->getRepository(Planet::class)->findBy(['solarSystem' => $origin->solarSystem, 'owner' => null], ['id' => 'ASC']);
        foreach ($planets as $planet) {
            if ($planet !== $excluded) {
                // Indépendant de la génération de la fixture, même quand un monde est initialement PNJ.
                $planet->npcPlanet = false;

                return $planet;
            }
        }

        self::fail('Deux destinations libres sont nécessaires dans le système natal de test.');
    }

    private function seedShips(PlayerUniverse $membership, Planet $origin, string $slug, int $count): void
    {
        $idle = new Fleet();
        $idle->playerUniverse = $membership;
        $idle->homePlanet = $origin;
        $ships = new FleetShip();
        $ships->fleet = $idle;
        $ships->slug = $slug;
        $ships->quantity = $count;
        $this->entityManager->persist($idle);
        $this->entityManager->persist($ships);
        $this->entityManager->flush();
    }

    private function launchPlanetMission(FleetService $service, PlayerUniverse $membership, Planet $origin, Planet $target, string $mission, string $ship, string $key): Fleet
    {
        self::assertFalse($service->send($membership, $origin, $mission, $target->id, [$ship => 1], .5, [], str_repeat($key, 32), requireExactComposition: true));
        $flight = $this->entityManager->getRepository(Fleet::class)->findOneBy([
            'playerUniverse' => $membership,
            'destination' => $target,
            'mission' => $mission,
            'status' => 'traveling',
        ]);
        self::assertNotNull($flight);
        self::assertNotNull($flight->arrivalAt);

        return $flight;
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
