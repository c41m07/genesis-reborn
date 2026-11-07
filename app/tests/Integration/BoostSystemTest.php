<?php
namespace App\Tests\Integration;

use App\Entity\{Player, PlayerBoost, PlayerUniverse, Universe, Planet, QueueItem, BoostEvent, Fleet};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\QueueService;
use App\Service\Boost\{BoostGrantService, BoostUseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class BoostSystemTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    private function playerAndPlanet(string $name = 'BoostTester'): array
    {
        $data = new RegistrationData();
        $data->email = strtolower($name) . '@example.test';
        $data->username = $name;
        $data->plainPassword = 'A-test-password-2026';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);

        return [$player, $planet];
    }

    public function testEventRewardsOverflowAndUseAcceleratesActiveQueue(): void
    {
        $clock = new MockClock('2026-10-21T12:00:00Z');
        self::getContainer()->set('clock', $clock);
        [$player, $planet] = $this->playerAndPlanet();
        $membership = $player->currentPlayerUniverse;
        $grants = self::getContainer()->get(BoostGrantService::class);

        for ($i = 1; $i <= 101; ++$i) {
            self::assertTrue($grants->awardEvent($membership, 'test-' . $i));
        }
        self::assertSame(100, $this->em->getRepository(PlayerBoost::class)->count(['playerUniverse' => $membership, 'status' => 'available']));
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count(['playerUniverse' => $membership, 'status' => 'pending']));
        self::assertCount(100, $grants->inventory($membership), 'Seuls les boosts disponibles appartiennent à l’inventaire utilisable.');

        $queues = self::getContainer()->get(QueueService::class);
        $queues->add($membership, $planet, 'buildings', 'mine_metal', 1, str_repeat('a', 32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $planet]);
        $initialFinish = $item->finishAt();
        $clock->sleep(1);

        self::getContainer()->get(BoostUseService::class)->useOnQueue($membership, $item->id, str_repeat('b', 32));

        self::assertSame('used', $this->em->getRepository(PlayerBoost::class)->findOneBy(['playerUniverse' => $membership, 'status' => 'used'])->status);
        self::assertSame(100, $this->em->getRepository(PlayerBoost::class)->count(['playerUniverse' => $membership, 'status' => 'available']), json_encode(array_map(static fn(PlayerBoost $b) => [$b->status, $b->sourceKey], $this->em->getRepository(PlayerBoost::class)->findBy(['playerUniverse' => $membership]))));
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count(['playerUniverse' => $membership, 'status' => 'pending']));
        self::assertLessThan($initialFinish, $item->finishAt());
    }

    public function testPendingBoostIsHiddenAndUnavailableBoostCannotBeUsedDirectly(): void
    {
        [$player, $planet] = $this->playerAndPlanet('HiddenPending');
        $membership = $player->currentPlayerUniverse;
        $grants = self::getContainer()->get(BoostGrantService::class);
        for ($i = 1; $i <= 101; ++$i) $grants->awardEvent($membership, 'pending-' . $i);
        self::assertCount(100, $grants->inventory($membership));
        foreach ($grants->inventory($membership) as $boost) $boost->status = 'pending';
        $this->em->flush();
        self::getContainer()->get(QueueService::class)->add($membership, $planet, 'buildings', 'mine_metal', 1, str_repeat('d', 32));
        $queue = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $planet]);

        $this->expectException(\DomainException::class);
        self::getContainer()->get(BoostUseService::class)->useOnQueue($membership, $queue->id, str_repeat('e', 32));
    }

    public function testWeeklyExplorationEventUsesTiedTopThreeAndGlobalGoal(): void
    {
        $clock = new MockClock('2026-11-02T00:00:00Z');
        self::getContainer()->set('clock', $clock);
        $players = [];
        for ($i = 1; $i <= 4; ++$i) {
            [$player] = $this->playerAndPlanet('Explorer' . $i);
            $players[] = $player;
        }
        $universe = $players[0]->currentPlayerUniverse->universe;
        $systems = self::getContainer()->get(\App\Repository\GameRepository::class)->systems($universe);
        self::assertGreaterThanOrEqual(11, count($systems));

        $eventService = self::getContainer()->get(\App\Service\Boost\BoostEventService::class);
        $eventService->tick();
        $event = $this->em->getRepository(BoostEvent::class)->findOneBy([
            'universe' => $universe,
            'templateKey' => 'exploration',
            'status' => 'active',
        ]);
        self::assertNotNull($event);

        $counts = [4, 3, 3, 1];
        $systemIndex = 0;
        foreach ($players as $playerIndex => $player) {
            for ($i = 0; $i < $counts[$playerIndex]; ++$i) {
                $system = $systems[$systemIndex++];
                self::assertSame(1, $eventService->recordExplorationDiscovery($player->currentPlayerUniverse, $system));
                self::assertSame(0, $eventService->recordExplorationDiscovery($player->currentPlayerUniverse, $system));
            }
        }
        self::assertSame(11.0, $event->totalContribution);

        $clock->sleep(604800);
        $eventService->tick();
        self::assertSame('closed', $event->status);
        foreach ([0, 1, 2] as $winnerIndex) {
            self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count([
                'playerUniverse' => $players[$winnerIndex]->currentPlayerUniverse,
                'sourceType' => 'event',
            ]));
        }
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count([
            'playerUniverse' => $players[3]->currentPlayerUniverse,
            'sourceType' => 'event',
        ]));
    }

    public function testCombatEventUsesPerPlayerDamageThreshold(): void
    {
        $clock = new MockClock('2026-11-09T00:00:00Z');
        self::getContainer()->set('clock', $clock);
        [$winner] = $this->playerAndPlanet('DamageWinner');
        [$below] = $this->playerAndPlanet('DamageBelow');
        $service = self::getContainer()->get(\App\Service\Boost\BoostEventService::class);
        $service->tick();
        $event = $this->em->getRepository(BoostEvent::class)->findOneBy([
            'universe' => $winner->currentPlayerUniverse->universe,
            'templateKey' => 'combat',
            'status' => 'active',
        ]);
        self::assertNotNull($event);
        self::assertSame(1, $service->recordCombatDamage($winner->currentPlayerUniverse, 501, 1, 5000.0));
        self::assertSame(0, $service->recordCombatDamage($winner->currentPlayerUniverse, 501, 1, 5000.0));
        self::assertSame(1, $service->recordCombatDamage($below->currentPlayerUniverse, 502, 1, 4999.0));

        $clock->sleep(604800);
        $service->tick();
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count([
            'playerUniverse' => $winner->currentPlayerUniverse,
            'sourceType' => 'event',
        ]));
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count([
            'playerUniverse' => $below->currentPlayerUniverse,
            'sourceType' => 'event',
        ]));
    }

    public function testTransportEventRewardsAnySuccessfulFreightContributor(): void
    {
        $clock = new MockClock('2026-11-16T00:00:00Z');
        self::getContainer()->set('clock', $clock);
        [$sender] = $this->playerAndPlanet('FreightSender');
        [$nonContributor] = $this->playerAndPlanet('FreightNone');
        $service = self::getContainer()->get(\App\Service\Boost\BoostEventService::class);
        $service->tick();
        $event = $this->em->getRepository(BoostEvent::class)->findOneBy([
            'universe' => $sender->currentPlayerUniverse->universe,
            'templateKey' => 'transport',
            'status' => 'active',
        ]);
        self::assertNotNull($event);
        self::assertSame(1, $service->recordTransportDelivery($sender->currentPlayerUniverse, 601, 1.0));
        self::assertSame(0, $service->recordTransportDelivery($nonContributor->currentPlayerUniverse, 602, 0.0));

        $clock->sleep(604800);
        $service->tick();
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count([
            'playerUniverse' => $sender->currentPlayerUniverse,
            'sourceType' => 'event',
        ]));
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count([
            'playerUniverse' => $nonContributor->currentPlayerUniverse,
            'sourceType' => 'event',
        ]));
    }

    public function testEventConcurrencyStartsOldestScheduledEventFirst(): void
    {
        $clock = new MockClock('2026-11-02T00:00:00Z');
        self::getContainer()->set('clock', $clock);
        [$player, $planet] = $this->playerAndPlanet('QueueTester');
        $universe = $player->currentPlayerUniverse->universe;
        $service = self::getContainer()->get(\App\Service\Boost\BoostEventService::class);
        $service->tick();

        $makeEvent = function (string $key, string $status, \DateTimeImmutable $scheduledAt) use ($clock): BoostEvent {
            $event = new BoostEvent();
            $event->eventKey = $key;
            $event->templateKey = 'transport';
            $event->universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
            $event->status = $status;
            $event->format = 'collective';
            $event->rewardMode = 'all_contributors';
            $event->contributionMetric = 'freight_delivered';
            $event->durationSeconds = 3600;
            $event->scheduledAt = $scheduledAt;
            if ($status === 'active') {
                $event->startedAt = $scheduledAt;
                $event->endsAt = $clock->now();
            }
            $this->em->persist($event);
            $this->em->flush();
            return $event;
        };
        $expired = $makeEvent('queue-expired-u' . $universe->id, 'active', $clock->now()->modify('-60 seconds'));
        $oldest = $makeEvent('queue-oldest-u' . $universe->id, 'scheduled', $clock->now()->modify('-120 seconds'));
        $newer = $makeEvent('queue-newer-u' . $universe->id, 'scheduled', $clock->now()->modify('-60 seconds'));

        $service->tick();

        self::assertSame('closed', $expired->status);
        self::assertSame('active', $oldest->status);
        self::assertSame('scheduled', $newer->status);
        self::assertSame(2, count(self::getContainer()->get(\App\Repository\GameRepository::class)->boostEventsForUniverse('active', $universe)));
    }

    public function testRiskMissionGrantResetsAtUtcMidnight(): void
    {
        $clock = new MockClock('2026-11-01T23:59:30Z');
        self::getContainer()->set('clock', $clock);
        [$player, $planet] = $this->playerAndPlanet('RiskTester');
        $membership = $player->currentPlayerUniverse;
        $makeFleet = function (string $mission) use ($membership, $planet, $clock): Fleet {
            $fleet = new Fleet();
            $fleet->playerUniverse = $membership;
            $fleet->homePlanet = $planet;
            $fleet->mission = $mission;
            $fleet->status = 'traveling';
            $fleet->departureAt = $clock->now();
            $fleet->arrivalAt = $clock->now()->modify('+100 seconds');
            $fleet->travelSeconds = 100;
            $fleet->dispatchToken = bin2hex(random_bytes(18));
            $this->em->persist($fleet);
            $this->em->flush();
            return $fleet;
        };
        $first = $makeFleet('explore');
        $second = $makeFleet('spy');
        $grants = self::getContainer()->get(BoostGrantService::class);
        self::assertTrue($grants->awardRiskMission($membership, $first));
        self::assertFalse($grants->awardRiskMission($membership, $second));
        $clock->sleep(60);
        self::assertTrue($grants->awardRiskMission($membership, $second));
        self::assertSame(2, $this->em->getRepository(PlayerBoost::class)->count([
            'playerUniverse' => $membership,
            'sourceType' => 'risk_mission',
        ]));
    }
}
