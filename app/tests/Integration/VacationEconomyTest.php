<?php
namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Planet, PlanetBuilding, PlanetResource, Player, PlayerTechnology, QueueItem, Universe, VacationPeriod};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\{ActionGuard, ResourceService};
use App\Service\Player\{RegistrationService, VacationEconomyService, VacationPolicy};
use App\Service\Queue\QueueService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class VacationEconomyTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Planet $planet;
    private ResourceService $resources;
    private QueueService $queues;

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
        $this->clock = new MockClock('2026-11-07T12:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
        $data = new RegistrationData();
        $data->email = 'vacation@example.test';
        $data->username = 'Vacation';
        $data->plainPassword = 'A-test-password-2026';
        $this->player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($this->player, $universe->id);
        $this->planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $this->player->currentPlayerUniverse]);
        $this->resources = self::getContainer()->get(ResourceService::class);
        $this->queues = self::getContainer()->get(QueueService::class);
    }

    public function testStocksFreezeAndQueuesResumeWithExactlyTheirRemainingWork(): void
    {
        $this->clock->sleep(600);
        $head = $this->queue('building', 'mine_metal', 600, 1);
        $dependent = $this->queue('building', 'mine_metal', 900, 2, $head);
        $research = $this->queue('research', 'weapon_calibration', 600, 1);
        $ships = $this->queue('shipyard', 'fighter', 600, null);
        $oldToken = $head->dispatchToken;
        $oldFinish = $head->finishAt();
        $paid = $head->paidCosts;
        $before = $this->resources->snapshot($this->planet);
        $period = $this->economy()->start($this->player->currentPlayerUniverse);
        self::assertNotSame($oldToken, $head->dispatchToken);
        self::assertSame(90 * 86400, $head->finishAt()->getTimestamp() - $oldFinish->getTimestamp());
        self::assertNull($dependent->startedAt);
        $this->clock->sleep(48 * 3600);
        $this->queues->complete($head->id, $oldToken);
        $this->queues->synchronize($this->player->currentPlayerUniverse);
        $frozen = $this->resources->snapshot($this->planet);
        foreach ($before as $slug => $resource) {
            self::assertEqualsWithDelta($resource['stock'], $frozen[$slug]['stock'], 0.000001);
            self::assertSame(0.0, $frozen[$slug]['rate']);
        }
        self::assertSame($paid, $head->paidCosts);
        self::assertNotNull($this->em->find(QueueItem::class, $research->id));
        self::assertNotNull($this->em->find(QueueItem::class, $ships->id));
        $pausedToken = $head->dispatchToken;
        $this->economy()->finish($this->player->currentPlayerUniverse);
        self::assertSame(480, $head->finishAt()->getTimestamp() - $this->clock->now()->getTimestamp());
        self::assertEquals($head->startedAt, $this->planet->buildingQueueStartedAt);
        self::assertEquals($research->startedAt, $this->planet->researchQueueStartedAt);
        self::assertEquals($ships->startedAt, $this->planet->shipyardQueueStartedAt);
        self::assertNotSame($pausedToken, $head->dispatchToken);
        $resumedToken = $head->dispatchToken;
        $this->economy()->finish($this->player->currentPlayerUniverse);
        self::assertSame($resumedToken, $head->dispatchToken);
        self::assertEquals($this->clock->now(), $period->endedAt);
        $this->clock->sleep(479);
        $this->queues->synchronize($this->player->currentPlayerUniverse);
        self::assertNotNull($this->em->find(QueueItem::class, $head->id));
        $this->clock->sleep(1);
        $this->queues->complete($head->id, $resumedToken);
        self::assertSame(1, $this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet' => $this->planet, 'slug' => 'mine_metal'])->level);
        self::assertSame(1, $this->em->getRepository(PlayerTechnology::class)->findOneBy(['playerUniverse' => $this->player->currentPlayerUniverse, 'slug' => 'weapon_calibration'])->level);
        self::assertSame(2, $this->em->getRepository(FleetShip::class)->findOneBy(['slug' => 'fighter'])->quantity);
        self::assertEquals($this->clock->now(), $dependent->startedAt);
        self::assertSame(900, $dependent->durationSeconds);
    }

    public function testAutomaticExpiryResumesWithoutAVisitOrExplicitFinish(): void
    {
        $head = $this->queue('building', 'mine_metal', 600, 1);
        $before = $this->resources->snapshot($this->planet)['metal'];
        $period = $this->economy()->start($this->player->currentPlayerUniverse);
        $this->clock->sleep(90 * 86400 + 30);
        self::assertNull($period->endedAt);
        self::assertFalse($period->isActiveAt($this->clock->now()));
        $after = $this->resources->snapshot($this->planet)['metal'];
        self::assertEqualsWithDelta($before['stock'] + 30 * $before['rate'], $after['stock'], 0.000001);
        $this->clock->sleep(450);
        $this->queues->complete($head->id, $head->dispatchToken);
        self::assertSame(1, $this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet' => $this->planet, 'slug' => 'mine_metal'])->level);
    }

    public function testLateProcessingSettlesDependentBuildingsAtTheirRealPostVacationDates(): void
    {
        $head = $this->queue('building', 'mine_metal', 600, 1);
        $this->queue('building', 'mine_metal', 900, 2, $head);
        foreach($this->em->getRepository(\App\Entity\PlanetResource::class)->findBy(['planet'=>$this->planet]) as $row)$row->currentStock=1000;
        $this->em->flush();
        $before = $this->resources->snapshot($this->planet)['metal'];
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->clock->sleep(90 * 86400 + 480 + 900 + 3600);
        $this->queues->synchronize($this->player->currentPlayerUniverse);
        $levelOneRate = $this->resources->rate($this->planet, 'metal', 0, ['mine_metal' => ['basic' => 1]]);
        $levelTwoRate = $this->resources->rate($this->planet, 'metal', 0, ['mine_metal' => ['basic' => 2]]);
        $expected = $before['stock'] + 480 * $before['rate'] + 900 * $levelOneRate + 3600 * $levelTwoRate;
        self::assertEqualsWithDelta($expected, $this->resources->snapshot($this->planet)['metal']['stock'], 0.000001);
        self::assertSame(0, $this->em->getRepository(QueueItem::class)->count());
    }

    public function testIndustrialConsumptionAlsoStopsDuringVacation(): void
    {
        $building = new PlanetBuilding();
        $building->planet = $this->planet;
        $building->slug = 'mine_antimatter';
        $building->level = 1;
        $this->em->persist($building);
        $this->em->flush();
        $before = $this->resources->snapshot($this->planet);
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->clock->sleep(48 * 3600);
        self::getContainer()->get(GameManager::class)->transaction(fn () => $this->resources->materialize($this->planet));
        foreach ($this->resources->snapshot($this->planet) as $slug => $resource) {
            self::assertEqualsWithDelta($before[$slug]['stock'], $resource['stock'], 0.000001);
        }
        self::assertSame(['electricity'=>0.0,'hydrogen'=>0.0], $this->resources->factoryConsumption($this->planet, 'mine_antimatter', ['mine_antimatter' => ['basic' => 1]]));
        $this->economy()->finish($this->player->currentPlayerUniverse);
        self::assertGreaterThan(0, $this->resources->factoryConsumption($this->planet, 'mine_antimatter', ['mine_antimatter' => ['basic' => 1]])['hydrogen']);
    }

    public function testProductionEventsAreAppliedOnlyOutsideTheVacationWindow(): void
    {
        $this->player->roles = ['ROLE_ADMIN'];
        $this->em->flush();
        $events = self::getContainer()->get(\App\Service\Event\GameEventService::class);
        $events->save($this->player, null, [
            'name'=>'Bonus pendant les vacances', 'description'=>'Test', 'type'=>'production_bonus', 'status'=>'active',
            'starts_at'=>'2026-11-08T12:00', 'ends_at'=>'2026-11-10T12:00', 'universe_id'=>$this->planet->owner->universe->id,
            'parameters'=>['percentage'=>100], 'visible'=>true,
        ]);
        $before = $this->resources->snapshot($this->planet)['electricity'];
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->clock->sleep(48 * 3600);
        $this->economy()->finish($this->player->currentPlayerUniverse);
        $this->clock->sleep(86400 + 3600);
        $after = $this->resources->snapshot($this->planet)['electricity'];
        self::assertEqualsWithDelta($before['stock'] + $before['rate'] * (2 * 86400 + 3600), $after['stock'], 0.000001);
    }

    public function testDisabledMechanismCannotStartAVacation(): void
    {
        $this->expectException(\DomainException::class);
        $this->economy(false)->start($this->player->currentPlayerUniverse);
    }

    public function testQueuesCannotBeBoostedWhilePaused(): void
    {
        $head = $this->queue('building', 'mine_metal', 600, 1);
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->expectException(\DomainException::class);
        $this->queues->boost($this->player->currentPlayerUniverse, $head->id, 2, str_repeat('b', 32));
    }

    public function testQueuesCannotBeCancelledWhilePaused(): void
    {
        $head = $this->queue('building', 'mine_metal', 600, 1);
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->expectException(\DomainException::class);
        $this->queues->cancel($this->player->currentPlayerUniverse, $head->id, str_repeat('b', 32));
    }

    public function testNewQueueItemsAreBlockedDuringVacation(): void
    {
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->expectException(\DomainException::class);
        $this->queues->add($this->player->currentPlayerUniverse, $this->planet, 'buildings', 'mine_metal', 1, str_repeat('b', 32));
    }

    public function testMilitaryMissionsBlockTheEconomicPause(): void
    {
        $fleet = new Fleet();
        $fleet->playerUniverse = $this->player->currentPlayerUniverse;
        $fleet->homePlanet = $this->planet;
        $fleet->mission = 'attack';
        $fleet->status = 'returning';
        $this->em->persist($fleet);
        $this->em->flush();
        $this->expectException(\DomainException::class);
        $this->economy()->start($this->player->currentPlayerUniverse);
    }

    public function testEarlyExitIsRejectedByTheTransactionalService(): void
    {
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->clock->sleep(48 * 3600 - 1);
        $this->expectException(\DomainException::class);
        $this->economy()->finish($this->player->currentPlayerUniverse);
    }

    public function testAnIncomingAttackPreventsVacationWithoutPartiallyPausingTheEmpire(): void
    {
        $head = $this->queue('building', 'mine_metal', 600, 1);
        $originalStart = $head->startedAt;
        $data = new RegistrationData();
        $data->email = 'vacation-attacker@example.test';
        $data->username = 'VacationAttacker';
        $data->plainPassword = 'A-test-password-2026';
        $attacker = self::getContainer()->get(RegistrationService::class)->register($data);
        self::getContainer()->get(UniverseService::class)->select($attacker, $this->planet->owner->universe->id);
        $origin = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $attacker->currentPlayerUniverse]);
        $fleet = new Fleet();
        $fleet->playerUniverse = $attacker->currentPlayerUniverse;
        $fleet->homePlanet = $origin;
        $fleet->destination = $this->planet;
        $fleet->mission = 'attack';
        $fleet->status = 'traveling';
        $this->em->persist($fleet);
        $this->em->flush();
        try {
            $this->economy()->start($this->player->currentPlayerUniverse);
            self::fail('Une attaque entrante doit empêcher les vacances.');
        } catch (\DomainException) {}
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vacation_period'));
        self::assertSame($originalStart->format('Y-m-d H:i:s'), $this->em->getConnection()->fetchOne('SELECT started_at FROM queue_item WHERE id = ?', [$head->id]));
    }

    public function testTurningOffNewActivationsDoesNotPreventFinishingAnExistingPeriod(): void
    {
        $before = $this->resources->snapshot($this->planet)['metal'];
        $this->economy()->start($this->player->currentPlayerUniverse);
        $this->clock->sleep(48 * 3600);
        self::assertEqualsWithDelta($before['stock'], $this->resources->snapshot($this->planet)['metal']['stock'], 0.000001);
        $this->economy(false)->finish($this->player->currentPlayerUniverse);
        $this->clock->sleep(60);
        self::assertEqualsWithDelta($before['stock'] + 60 * $before['rate'], $this->resources->snapshot($this->planet)['metal']['stock'], 0.000001);
    }

    public function testVacationsRemainAvailableUntilActualConversion(): void
    {
        $member=$this->player->currentPlayerUniverse;$member->lastActivityAt=$this->clock->now()->modify('-31 days');
        $this->em->flush();
        $period=$this->economy()->start($member);
        self::assertTrue($member->isPlayerControlled());self::assertTrue($period->isActiveAt($this->clock->now()));
        self::assertFalse(self::getContainer()->get(\App\Service\Player\EmpireActivityService::class)->snapshot($member)['conversion_due']);
    }

    public function testForgottenEmpireCannotStartVacations(): void
    {
        $member=$this->player->currentPlayerUniverse;$member->detachPlayer($this->clock->now());$this->em->flush();
        $this->expectException(\DomainException::class);
        $this->economy()->start($member);
    }

    private function economy(bool $enabled = true): VacationEconomyService
    {
        return new VacationEconomyService(
            self::getContainer()->get(GameManager::class), self::getContainer()->get(GameRepository::class),
            self::getContainer()->get(ActionGuard::class), $this->resources, $this->queues,
            new VacationPolicy($this->clock), self::getContainer()->get(\App\Service\Player\EmpireActivityService::class), self::getContainer()->get(\App\Service\Fleet\FleetService::class), $this->clock, $enabled,
        );
    }

    private function queue(string $type, string $slug, int $duration, ?int $level, ?QueueItem $previous = null): QueueItem
    {
        $item = new QueueItem();
        $item->playerUniverse = $this->player->currentPlayerUniverse;
        $item->planet = $this->planet;
        $item->queueType = $type;
        $item->itemSlug = $slug;
        $item->itemLevel = $level;
        $item->quantity = $type === 'shipyard' ? 2 : 1;
        $item->durationSeconds = $duration;
        $item->queuedAt = $this->clock->now()->modify('-120 seconds');
        $item->startedAt = $previous === null ? $item->queuedAt : null;
        $item->dependsOn = $previous;
        $item->dispatchToken = 'vacation-test-' . $type . '-' . ($level ?? 0);
        $item->paidCosts = ['metal' => 100];
        if ($previous === null) {
            $field = $type . 'QueueStartedAt';
            $this->planet->$field = $item->startedAt;
        }
        $this->em->persist($item);
        $this->em->flush();
        return $item;
    }
}
