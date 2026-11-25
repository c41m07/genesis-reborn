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
use App\Service\Player\FirstStep;
use App\Service\Player\FirstStepRewards;
use App\Service\Player\FirstStepRewardPolicy;
use App\Service\Player\FirstSteps;
use App\Service\Player\FirstStepsView;
use App\Service\Colony\ResourceService;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function testExercisePresentationDoesNotGrantProofBeforeSuccessfulResponse(): void
    {
        [$membership, $planet] = $this->select($this->player(), 'omega-1');
        foreach (FirstStep::cases() as $step) {
            if ($step !== FirstStep::PirateExercise) {
                $this->entityManager->getConnection()->insert('first_step_completion', [
                    'player_universe_id' => $membership->id,
                    'step_key' => $step->value,
                    'completed_at' => '2026-12-01 08:00:00',
                ]);
            }
        }
        $view = self::getContainer()->get(FirstStepsView::class);
        self::assertTrue($view->hasPending($membership));
        self::assertFalse($view->hasPending($membership, exerciseInResponse: true));
        self::assertArrayNotHasKey(FirstStep::PirateExercise->value, $this->guide->completed($membership));
        self::assertTrue($view->hasPending($membership));
    }

    public function testHistoricCompletionDoesNotCombineSeveralColoniesOrCreateProofs(): void
    {
        [$membership, $home] = $this->select($this->player(), 'omega-1');
        $second = $this->pickFreeKnownPlanet($home);
        $second->owner = $membership;
        $this->entityManager->flush();

        $connection = $this->entityManager->getConnection();
        foreach (FirstStep::cases() as $step) {
            if (in_array($step, [FirstStep::MetalMine, FirstStep::Laboratory], true)) {
                continue;
            }
            $connection->insert('first_step_completion', [
                'player_universe_id' => $membership->id,
                'step_key' => $step->value,
                'completed_at' => '2026-12-01 08:00:00',
            ]);
        }
        $this->seedBuilding($home, 'mine_metal');
        $this->seedBuilding($second, 'lab');
        $view = self::getContainer()->get(FirstStepsView::class);

        // À égalité on choisit le même identifiant de colonie pour tout l’empire.
        // Aucun des deux bâtiments isolés ne permet de compléter l’autre.
        $expectedNext = $home->id < $second->id ? 'laboratory' : 'metal_mine';
        $homeLevels = ['mine_metal' => ['basic' => 1]];
        $secondLevels = ['lab' => ['basic' => 1]];
        self::assertTrue($view->hasPending($membership));
        self::assertSame(11, $view->panel($membership)['count']);
        self::assertSame($expectedNext, $view->panel($membership)['next']);
        self::assertSame($expectedNext, $view->progress($membership, $homeLevels)['next']);
        self::assertSame($expectedNext, $view->progress($membership, $secondLevels)['next']);

        $this->seedBuilding($home, 'lab');
        self::assertFalse($view->hasPending($membership));
        self::assertNull($view->panel($membership));
        self::assertNull($view->progress($membership, $homeLevels)['next']);
        self::assertNull($view->progress($membership, $secondLevels)['next']);
        self::assertCount(10, $this->guide->completed($membership));
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
        $systems = $this->entityManager->getRepository(SolarSystem::class)->findBy(['galaxy' => $planet->solarSystem->galaxy], ['id' => 'ASC']);
        $unexplored = array_values(array_filter($systems, static fn (SolarSystem $system): bool => !$discovery->count([
            'playerUniverse' => $membership, 'solarSystem' => $system,
        ])));
        self::assertGreaterThanOrEqual(3, count($unexplored));
        $discoveredDuringTravel = array_shift($unexplored);
        $unknown = $unexplored;
        $known = $this->launchProbe($fleetService, $membership, $planet, $discoveredDuringTravel, '7');
        // Une découverte préexistante à l’arrivée ne fournit pas la preuve de cette mission.
        $priorDiscovery = new PlayerDiscovery();
        $priorDiscovery->playerUniverse = $membership;
        $priorDiscovery->solarSystem = $discoveredDuringTravel;
        $this->entityManager->persist($priorDiscovery);
        $this->entityManager->flush();
        $this->clock->sleep($known->travelSeconds + 1);
        $fleetService->arrive($known->id, $known->dispatchToken);
        self::assertSame('completed', $known->status);
        self::assertSame(['ship' => true], $this->guide->completed($membership));
        self::assertSame(1, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $planet->solarSystem]));
        self::assertSame(1, $discovery->count(['playerUniverse' => $membership, 'solarSystem' => $discoveredDuringTravel]));
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

    public function testPaidGuideRewardsWaitForRealCompletionAndRemainPendingUntilAnAtomicClaim(): void
    {
        $player = $this->player();
        [$membership, $planet] = $this->select($player, 'omega-1');
        $rewards = self::getContainer()->get(FirstStepRewards::class);
        $this->seedBuilding($planet, 'lab');
        $view = self::getContainer()->get(FirstStepsView::class);
        $recognized = $view->progress($membership, ['lab' => ['basic' => 1]]);
        self::assertTrue($recognized['existing']['laboratory']);
        self::assertSame([], $rewards->available($membership));
        self::assertSame([], $this->guide->completed($membership));
        // Conserver la colonie principale intacte pour les vraies commandes payées ci-dessous.
        [$parallelMember, $parallelPlanet] = $this->select($this->player('Parallel'), 'omega-1');
        $levels = ['mine_metal' => ['basic' => 1], 'power_plant' => ['basic' => 1], 'mine_nano' => ['basic' => 1]];
        foreach (array_keys($levels) as $slug) {
            $this->seedBuilding($parallelPlanet, $slug);
        }
        $pendingStorage = $view->progress($parallelMember, $levels);
        self::assertSame('metal_storage', $pendingStorage['next']);
        self::assertSame('laboratory', $pendingStorage['parallel']);
        $this->seedBuilding($parallelPlanet, 'lab');
        $levels['lab']['basic'] = 1;
        self::assertSame('research', $view->progress($parallelMember, $levels)['parallel']);

        $cancelled = $this->order($membership, $planet, 'buildings', 'mine_metal', '1', guided: true);
        $cancelledId = $cancelled->id;
        $cancelledToken = $cancelled->dispatchToken;
        $this->queues->cancel($membership, $cancelledId, str_repeat('2', 32));
        $this->clock->sleep($cancelled->durationSeconds + 1);
        $this->queues->complete($cancelledId, $cancelledToken);
        self::assertSame([], $rewards->available($membership));

        $mine = $this->order($membership, $planet, 'buildings', 'mine_metal', '3', guided: true);
        $paidMine = FirstStepRewardPolicy::paidRefund($mine);
        self::assertSame(['metal' => 96, 'nano' => 52], $paidMine);
        self::assertTrue($mine->firstStepsGuided);
        $this->queues->complete($mine->id, $mine->dispatchToken);
        self::assertSame([], $rewards->available($membership));
        $this->clock->sleep($mine->durationSeconds + 1);
        $mineId = $mine->id;
        $mineToken = $mine->dispatchToken;
        $this->queues->complete($mineId, $mineToken);
        $this->queues->complete($mineId, $mineToken);
        self::assertCount(1, $rewards->available($membership));

        $technology = $this->order($membership, $planet, 'technologies', 'engineering_foundations', '4', guided: true);
        $paidResearch = FirstStepRewardPolicy::paidRefund($technology);
        self::assertSame(['metal' => 240, 'nano' => 130], $paidResearch);
        $this->clock->sleep($technology->durationSeconds + 1);
        $this->queues->complete($technology->id, $technology->dispatchToken);
        self::assertCount(2, $rewards->available($membership));

        $resources = self::getContainer()->get(ResourceService::class);
        // Synchroniser toutes les ressources au même instant avant de fixer les stocks du scénario.
        // Le gain observé ensuite doit provenir exclusivement de la récompense réclamée.
        $rows = $resources->materialize($planet);
        $metal = $rows['metal'];
        $metal->currentStock = 10000;
        $rows['nano']->currentStock = 8000;
        $this->entityManager->flush();
        $stocks = $resources->snapshot($planet);
        self::assertSame('full', $rewards->claim($membership, $planet, FirstStep::MetalMine));
        self::assertSame($stocks['nano']['stock'], $resources->snapshot($planet)['nano']['stock'], 'Ne pas verser les nanos seuls.');
        self::assertCount(2, $rewards->available($membership));

        $metal->currentStock = 8000;
        $this->entityManager->flush();
        $before = $resources->snapshot($planet);
        self::assertSame('claimed', $rewards->claim($membership, $planet, FirstStep::MetalMine));
        self::assertSame('unavailable', $rewards->claim($membership, $planet, FirstStep::MetalMine));
        self::assertEquals($before['metal']['stock'] + $paidMine['metal'], $resources->snapshot($planet)['metal']['stock']);
        self::assertEquals($before['nano']['stock'] + $paidMine['nano'], $resources->snapshot($planet)['nano']['stock']);
        self::assertCount(1, $rewards->available($membership));

        [$other, $otherPlanet] = $this->select($player, 'turbo-1');
        self::assertSame('unavailable', $rewards->claim($other, $otherPlanet, FirstStep::Research));
        $otherMine = $this->order($other, $otherPlanet, 'buildings', 'mine_metal', '5', guided: true);
        $this->clock->sleep($otherMine->durationSeconds + 1);
        $this->queues->complete($otherMine->id, $otherMine->dispatchToken);
        self::assertSame(['metal_mine' => true], $this->guide->completed($other));
        self::assertSame([], $rewards->available($other), 'Pas de deuxième crédit ailleurs malgré une nouvelle preuve locale.');
        self::assertCount(1, $rewards->available($membership));
    }

    #[DataProvider('guidedSpies')]
    public function testGuidedNpcSpyReplacesOneConsumedProbeOnceForEitherReport(int $seed, string $expected): void
    {
        [$membership, $origin] = $this->select($this->player(), 'omega-1');
        $target = $this->pickFreeKnownPlanet($origin);
        $target->npcPlanet = true;
        $target->conquestLevel = 5;
        $this->seedShips($membership, $origin, 'probe', 2);
        self::getContainer()->set(\Random\Randomizer::class, new \Random\Randomizer(new \Random\Engine\Mt19937($seed)));
        $service = self::getContainer()->get(FleetService::class);
        $reports = $this->entityManager->getRepository(EspionageReport::class);
        $idle = $this->entityManager->getRepository(Fleet::class)->findOneBy(['playerUniverse' => $membership, 'status' => 'idle']);
        $ship = $this->entityManager->getRepository(FleetShip::class);
        $idleProbe = $ship->findOneBy(['fleet' => $idle, 'slug' => 'probe']);

        $flight = $this->launchPlanetMission($service, $membership, $origin, $target, 'spy', 'probe', 'a', guided: true);
        self::assertTrue($flight->firstStepsSpy);
        self::assertSame(1, $idleProbe->quantity);
        self::assertSame([], $this->guide->completed($membership));
        $token = $flight->dispatchToken;
        $this->clock->sleep($flight->travelSeconds + 1);
        $service->arrive($flight->id, $token);
        $service->arrive($flight->id, $token);
        self::assertSame($expected, $reports->findOneBy(['sender' => $membership])?->result);
        self::assertSame(0, $ship->findOneBy(['fleet' => $flight, 'slug' => 'probe'])?->quantity, 'La sonde en vol a vraiment été consumée.');
        self::assertSame(2, $idleProbe->quantity, 'Une seule a été recréée au hangar.');
        self::assertSame(['espionage' => true], $this->guide->completed($membership));

        $repeat = $this->launchPlanetMission($service, $membership, $origin, $target, 'spy', 'probe', 'b', guided: true);
        $this->clock->sleep($repeat->travelSeconds + 1);
        $service->arrive($repeat->id, $repeat->dispatchToken);
        self::assertSame(1, $idleProbe->quantity, 'Aucun deuxième remplacement après un nouveau rapport.');
        $ordinary = $this->launchPlanetMission($service, $membership, $origin, $target, 'spy', 'probe', 'c');
        $this->clock->sleep($ordinary->travelSeconds + 1);
        $service->arrive($ordinary->id, $ordinary->dispatchToken);
        self::assertFalse($ordinary->firstStepsSpy);
        self::assertSame(0, $idleProbe->quantity, 'Le déroulement de l’espionnage normal est inchangé.');
    }

    public function testGuidedSpyReturnsItsOriginalProbeIfTargetStopsBeingNpc(): void
    {
        [$membership, $origin] = $this->select($this->player(), 'omega-1');
        $target = $this->pickFreeKnownPlanet($origin);
        $target->npcPlanet = true;
        $this->seedShips($membership, $origin, 'probe', 1);
        $service = self::getContainer()->get(FleetService::class);
        $reports = $this->entityManager->getRepository(EspionageReport::class);

        $flight = $this->launchPlanetMission($service, $membership, $origin, $target, 'spy', 'probe', 'c', guided: true);
        $target->npcPlanet = false;
        $this->entityManager->flush();
        $this->clock->sleep($flight->travelSeconds + 1);
        $service->arrive($flight->id, $flight->dispatchToken);
        self::assertSame('returning', $flight->status);
        self::assertSame(0, $reports->count(['sender' => $membership]));
        self::assertSame([], $this->guide->completed($membership));
        self::assertSame(0, (int) $this->entityManager->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM first_step_reward WHERE player_id = ?',
            [$membership->player->id],
        ));

        $this->clock->sleep($flight->travelSeconds + 1);
        $service->arrive($flight->id, $flight->dispatchToken);
        $idle = $this->entityManager->getRepository(Fleet::class)->findOneBy(['playerUniverse' => $membership, 'status' => 'idle']);
        self::assertSame(1, $this->entityManager->getRepository(FleetShip::class)->findOneBy(['fleet' => $idle, 'slug' => 'probe'])?->quantity);
    }

    public static function guidedSpies(): iterable
    {
        yield 'rapport détecté' => [50, 'detected'];
        yield 'rapport non détecté' => [30, 'success'];
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

    private function launchPlanetMission(FleetService $service, PlayerUniverse $membership, Planet $origin, Planet $target, string $mission, string $ship, string $key, bool $guided = false): Fleet
    {
        self::assertFalse($service->send($membership, $origin, $mission, $target->id, [$ship => 1], .5, [], str_repeat($key, 32), requireExactComposition: true, tutorialSpy: $guided));
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

    private function player(string $suffix = ''): Player
    {
        $data = new RegistrationData();
        $data->username = 'GuideFixture'.$suffix;
        $data->email = 'guide-fixture'.strtolower($suffix).'@example.test';
        $data->plainPassword = bin2hex(random_bytes(24)).'Aa1!';
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

    private function order(PlayerUniverse $membership, Planet $planet, string $group, string $slug, string $key, bool $guided = false): QueueItem
    {
        self::assertSame(1, $this->queues->add($membership, $planet, $group, $slug, 1, str_repeat($key, 32), guided: $guided));
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
