<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Guild, GuildHall, Planet, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Entity\{Fleet, FleetShip, GuildDockOrder, GuildHallBuilding, GuildHallConvoy, GuildHallStock, OutboxMessage, PlanetResource};
use App\Message\{FleetArrived, GuildDockOrderDue, GuildHallConstructionDue};
use App\MessageHandler\{FleetArrivedHandler, GuildDockOrderDueHandler, GuildHallConstructionDueHandler};
use App\Repository\GuildHallRepository;
use App\Service\Notification\OutboxRelay;
use App\Service\Colony\ResourceService;
use App\Service\Guild\{GuildHallBuildingOrders, GuildHallConvoyDeparture, GuildHallFunding, GuildHallScheduleProvider, GuildHallService};
use App\Service\Player\{DiplomacyService, RegistrationService, RelationResolver};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Tests\Support\GuildHallFixture;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

final class GuildHallTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $parameters = $this->em->getConnection()->getParams();
        if (($parameters['host'] ?? '') !== 'database-test' || ($parameters['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base isolée attendue.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    public function testRealConvoyCreditsTheHallAndKeepsOverflowOnReturnWithoutReplayingEitherEffect(): void
    {
        $clock = new MockClock('now', new \DateTimeZone('UTC'));
        $container = self::getContainer();
        $container->set('clock', $clock);
        $container->set(GuildHallScheduleProvider::class, new GuildHallScheduleProvider(
            '{"levels":{"warehouse":{"1":{"cost":{"metal":15},"duration":60}}},"capacities":{"1":{"metal":100}}}',
        ));
        [$owner, $origin, $guild] = $this->guild('ConvoyOwner', 'CVO');
        $hallService = GuildHallFixture::enable($container);
        $hall = $hallService->contribute($owner, $guild->id, $origin->id, 0, ['hydrogen' => 32]);
        $hall = $hallService->place($owner, $guild->id, $origin->solarSystem->id, $hall->revision);
        $hallId = $hall->id;
        $guildId = $guild->id;
        $originId = $origin->id;
        $ownerId = $owner->id;

        // Le trajet part du vrai service. Seuls les vaisseaux de départ et le
        // barème sont des fixtures ; la flotte en vol n'est pas construite à la main.
        $idle = $this->em->getRepository(Fleet::class)->findOneBy([
            'playerUniverse' => $owner, 'homePlanet' => $origin, 'status' => 'idle',
        ]);
        if ($idle === null) {
            $idle = new Fleet();
            $idle->playerUniverse = $owner;
            $idle->homePlanet = $origin;
            $this->em->persist($idle);
        }
        $transportShip = new FleetShip();
        $transportShip->fleet = $idle;
        $transportShip->slug = 'transport';
        $transportShip->quantity = 1;
        $this->em->persist($transportShip);
        $this->em->flush();
        $idleId = $idle->id;

        $departure = $container->get(GuildHallConvoyDeparture::class);
        $convoy = $departure->send(
            $owner, $guild->id, $hallId, $originId, ['transport' => ['basic' => 1]], 1.0, ['metal' => 40], str_repeat('c', 32),
        );
        self::assertInstanceOf(GuildHallConvoy::class, $convoy);
        $id = $convoy->fleet->id;
        $outboundToken = $convoy->outboundToken;
        self::assertSame($outboundToken, $convoy->fleet->dispatchToken);
        self::assertSame(0, $convoy->fleet->navigation['target'][4]);

        $relay = $container->get(OutboxRelay::class);
        $messages = $container->get('messenger.transport.async');
        $handler = $container->get(FleetArrivedHandler::class);
        $relay->run();
        self::assertSame([], $this->convoyMessages($messages->getSent(), $id));

        $clock->sleep($convoy->fleet->travelSeconds + 1);
        $relay->run();
        $outbound = $this->convoyMessages($messages->getSent(), $id);
        self::assertCount(1, $outbound);
        self::assertSame($outboundToken, $outbound[0]->dispatchToken);
        $handler(new FleetArrived($id, 'jeton-obsolete'));
        $this->em->clear();
        self::assertNull($this->em->find(GuildHallStock::class, $hallId));
        $handler($outbound[0]);
        $this->em->clear();
        $handler($outbound[0]);
        $this->em->clear();
        $fleet = $this->em->find(Fleet::class, $id);
        self::assertSame('returning', $fleet->status);
        self::assertSame(['metal' => 15], array_filter($this->em->find(GuildHallStock::class, $hallId)->materials));
        self::assertEqualsWithDelta(25.0, $fleet->cargo['metal'], 0.001);
        self::assertNotSame($outboundToken, $fleet->dispatchToken);

        $clock->sleep($fleet->travelSeconds + 1);
        $origin = $this->em->find(Planet::class, $originId);
        $capacity = $container->get(ResourceService::class)->capacity($origin, 'metal', []);
        $metal = $this->em->getRepository(PlanetResource::class)->findOneBy([
            'planet' => $origin, 'resourceType' => 'metal',
        ]);
        // Figer le stock juste avant le traitement pour isoler le débordement.
        $metal->currentStock = $capacity - 10.0;
        $metal->lastUpdatedAt = $clock->now();
        $this->em->flush();

        $relay->run();
        $legs = $this->convoyMessages($messages->getSent(), $id);
        self::assertCount(2, $legs);
        self::assertSame($fleet->dispatchToken, $legs[1]->dispatchToken);
        $handler($legs[1]);
        $this->em->clear();
        $handler($legs[1]);
        $this->em->clear();
        $idle = $this->em->find(Fleet::class, $idleId);
        $origin = $this->em->find(Planet::class, $originId);
        self::assertSame($ownerId, $idle->playerUniverse->id);
        self::assertSame('completed', $this->em->find(Fleet::class, $id)->status);
        self::assertEqualsWithDelta($capacity, $this->stock($origin, 'metal'), 0.001);
        self::assertEqualsWithDelta(15.0, $idle->cargo['metal'], 0.001);
        self::assertSame(1, $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $idle, 'slug' => 'transport'])->quantity);
        self::assertSame(['metal' => 15], array_filter($this->em->find(GuildHallStock::class, $hallId)->materials));

        // La collecte paie le premier entrepôt ; son niveau n’est acquis qu’à la fin.
        $orders = $container->get(GuildHallBuildingOrders::class);
        $building = $orders->start($idle->playerUniverse, $guildId, $hallId, GuildHallBuilding::WAREHOUSE, 0);
        $buildingId = $building->id;
        self::assertSame(0, $building->level);
        self::assertEquals($clock->now()->modify('+60 seconds'), $building->finishesAt);
        self::assertSame([], array_filter($this->em->find(GuildHallStock::class, $hallId)->materials));
        $relay->run();
        self::assertCount(0, array_filter($messages->getSent(), static fn ($entry) => $entry->getMessage() instanceof GuildHallConstructionDue));

        $clock->sleep(60);
        $relay->run();
        $builds = array_values(array_filter($messages->getSent(), static fn ($entry) => $entry->getMessage() instanceof GuildHallConstructionDue));
        self::assertCount(1, $builds);
        $completion = $builds[0]->getMessage();
        self::assertSame($buildingId, $completion->buildingId);
        $constructionHandler = $container->get(GuildHallConstructionDueHandler::class);
        $constructionHandler($completion);
        $this->em->clear();
        $constructionHandler($completion);
        $this->em->clear();
        $built = $this->em->find(GuildHallBuilding::class, $buildingId);
        self::assertSame(1, $built->level);
        self::assertNull($built->finishesAt);
        self::assertSame([], array_filter($this->em->find(GuildHallStock::class, $hallId)->materials));
    }

    /** @return list<FleetArrived> */
    private function convoyMessages(array $envelopes, int $fleetId): array
    {
        $messages = [];
        foreach ($envelopes as $envelope) {
            $message = $envelope->getMessage();
            if ($message instanceof FleetArrived && $message->fleetId === $fleetId) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    public function testDockOutboxWaitsAndReplaysWithoutDoubleCredit(): void
    {
        $clock = new MockClock('now');
        self::getContainer()->set('clock', $clock);
        [$owner, $origin, $guild] = $this->guild('DockOwner', 'DKO');
        $order = $this->fixtureDockOrder($owner, $origin, $guild, $clock);
        $id = $order->id;
        $planetId = $origin->id;
        $token = $order->dispatchToken;
        $relay = self::getContainer()->get(OutboxRelay::class);
        $transport = self::getContainer()->get('messenger.transport.async');

        $relay->run();
        self::assertCount(0, array_filter($transport->getSent(), static fn ($entry) => $entry->getMessage() instanceof GuildDockOrderDue));
        $clock->sleep(60);
        $relay->run();
        $messages = array_values(array_filter($transport->getSent(), static fn ($entry) => $entry->getMessage() instanceof GuildDockOrderDue));
        self::assertCount(1, $messages);
        $due = $messages[0]->getMessage();
        self::assertSame($id, $due->orderId);
        self::assertSame($token, $due->dispatchToken);

        $handler = self::getContainer()->get(GuildDockOrderDueHandler::class);
        $before = $this->dockHullCount($owner->id, $planetId);
        $handler(new GuildDockOrderDue($id, 'expired-token'));
        self::assertSame($before, $this->dockHullCount($owner->id, $planetId));
        $handler($due);
        $this->em->clear();
        $handler($due);
        self::assertSame($before + 7, $this->dockHullCount($owner->id, $planetId));
        self::assertNotNull($this->em->find(GuildDockOrder::class, $id)->completedAt);
    }

    public function testDockKeepsPersonalHullsOnTheReceiptWhenAColonyChangesHands(): void
    {
        $clock = new MockClock('now');
        self::getContainer()->set('clock', $clock);
        [$owner, $origin, $guild] = $this->guild('DockBefore', 'DKB');
        [$newOwner] = $this->player('DockAfter');
        $order = $this->fixtureDockOrder($owner, $origin, $guild, $clock);
        $id = $order->id;
        $originId = $origin->id;
        $newOwnerId = $newOwner->id;
        $due = new GuildDockOrderDue($id, $order->dispatchToken);
        $origin->owner = $newOwner;
        $this->em->flush();
        $this->em->clear();

        $clock->sleep(60);
        $handler = self::getContainer()->get(GuildDockOrderDueHandler::class);
        $handler($due);
        $this->em->clear();
        $handler($due);
        self::assertSame(0, $this->dockHullCount($newOwnerId, $originId));
        $remaining = $this->em->find(GuildDockOrder::class, $id);
        self::assertNull($remaining->completedAt);
        self::assertSame($owner->id, $remaining->membershipId);
        self::assertSame(7, $remaining->quantity);
    }

    private function fixtureDockOrder(PlayerUniverse $owner, Planet $origin, Guild $guild, MockClock $clock): GuildDockOrder
    {
        // Préparer un ordre payé pour cibler le relais et la restitution, sans prétendre tester le départ.
        $order = new GuildDockOrder();
        $order->universe = $owner->universe;
        $order->hallId = $guild->id;
        $order->membershipId = $owner->id;
        $order->originPlanetId = $origin->id;
        $order->requestKey = str_repeat('a', 32);
        $order->shipSlug = 'fighter';
        $order->sourceTier = 'basic';
        $order->targetTier = 'intermediate';
        $order->quantity = 7;
        $order->dispatchToken = str_repeat('b', 36);
        $order->startedAt = $clock->now()->setTimezone(new \DateTimeZone('UTC'));
        $order->dueAt = $order->startedAt->modify('+60 seconds');
        $this->em->persist($order);
        $this->em->flush();

        $outbox = new OutboxMessage();
        $outbox->kind = GuildDockOrderDue::KIND;
        $outbox->targetId = $order->id;
        $outbox->token = $order->dispatchToken;
        $outbox->dueAt = $order->dueAt;
        $this->em->persist($outbox);
        $this->em->flush();

        return $order;
    }

    private function dockHullCount(int $membershipId, int $planetId): int
    {
        return (int) $this->em->getConnection()->fetchOne(
            "SELECT COALESCE(SUM(ship.quantity), 0) FROM fleet_ship ship JOIN fleet fleet ON fleet.id = ship.fleet_id
             WHERE fleet.player_universe_id = ? AND fleet.home_planet_id = ? AND fleet.status = 'idle'
             AND ship.slug = 'fighter' AND ship.tier = 'intermediate'",
            [$membershipId, $planetId],
        );
    }

    public function testFixtureDoesNotActivateHallOnFreshKernel(): void
    {
        self::assertNull(self::getContainer()->get(GuildHallFunding::class)->target());
        self::ensureKernelShutdown();
        self::bootKernel();

        GuildHallFixture::enable(self::getContainer());
        self::assertSame(32, self::getContainer()->get(GuildHallFunding::class)->target());
        self::ensureKernelShutdown();
        self::bootKernel();

        self::assertNull(self::getContainer()->get(GuildHallFunding::class)->target());
    }

    public function testGuildPoolsContributionsAndExposesOnlyDiscoveredCoordinate(): void
    {
        [$leader, $origin, $guild] = $this->guild('HallLeader', 'HLA');
        [$member, $colony] = $this->player('HallMember');
        [$neighbor] = $this->player('HallNeighbor');
        [$otherUniverse] = $this->player('HallVisitor', 'turbo-1');
        \App\Tests\Support\GuildRecruitmentFixture::enrol(self::getContainer()->get(DiplomacyService::class), self::getContainer()->get(\App\Repository\GameRepository::class), $member, $guild->id);
        $service = GuildHallFixture::enable(self::getContainer());
        $planetCount = $this->em->getRepository(Planet::class)->count();
        $halls = self::getContainer()->get(GuildHallRepository::class);
        $system = $this->unknownSystem($neighbor);
        $this->discover($leader, $system);
        $beforeHydro = $this->stock($origin, 'hydrogen');
        $beforeMetal = $this->stock($colony, 'metal');

        // Chaque groupe de ressources est un équivalent de 8 hydrogènes.
        $hall = $service->contribute($leader, $guild->id, $origin->id, 0, ['hydrogen' => '8']);
        self::assertSame(8, $hall->strength);
        self::assertSame(1, $hall->revision);
        $hall = $service->contribute($member, $guild->id, $colony->id, 1, ['metal' => 4, 'nano' => 2, 'antimatter' => 1]);
        self::assertSame(32, $hall->strength);
        self::assertSame(32, $hall->requiredStrength);
        self::assertEqualsWithDelta($beforeHydro - 8, $this->stock($origin, 'hydrogen'), 0.1);
        self::assertEqualsWithDelta($beforeMetal - 4, $this->stock($colony, 'metal'), 0.1);
        self::assertNull($hall->solarSystem);
        self::assertSame([], $halls->claimsForKnownSystems($leader, [$system->id]));
        $placed = $service->place($leader, $guild->id, $system->id, 2);
        self::assertSame($hall->id, $placed->id);
        self::assertSame(3, $placed->revision);
        self::assertNotNull($placed->placedAt);
        self::assertSame($planetCount, $this->em->getRepository(Planet::class)->count());
        self::assertSame([], $halls->claimsForKnownSystems($neighbor, [$system->id]));
        $this->discover($neighbor, $system);
        self::assertSame([$system->id => ['tag' => 'HLA', 'position' => 0, 'guildId' => $guild->id]], $halls->claimsForKnownSystems($neighbor, [$system->id]));
        self::assertSame([], $halls->claimsForKnownSystems($otherUniverse, [$system->id]));
    }

    public function testTwoCommandersCanOnlyClaimOneHallAtTheSameSystem(): void
    {
        [$first, $firstPlanet, $firstGuild] = $this->guild('HallRaceAlpha', 'HRA');
        [$second, $secondPlanet, $secondGuild] = $this->guild('HallRaceBeta', 'HRB');
        $service = GuildHallFixture::enable(self::getContainer());
        $service->contribute($first, $firstGuild->id, $firstPlanet->id, 0, ['antimatter' => 4]);
        $service->contribute($second, $secondGuild->id, $secondPlanet->id, 0, ['antimatter' => 4]);
        $this->discover($second, $firstPlanet->solarSystem);
        $system = $firstPlanet->solarSystem->id;

        $results = $this->race($first->universe->id, [
            ['action' => 'place', 'member' => $first->id, 'guild' => $firstGuild->id, 'system' => $system],
            ['action' => 'place', 'member' => $second->id, 'guild' => $secondGuild->id, 'system' => $system],
        ]);
        self::assertContains('accepted', $results);
        self::assertContains('L’emplacement du Hall dans ce système est déjà occupé.', $results);
        $db = $this->em->getConnection();
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM guild_hall WHERE solar_system_id = ?', [$system]));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM guild_hall WHERE solar_system_id IS NULL AND strength = 32'));
    }

    public function testTwoDonationsFromTheSameRevisionOnlyDebitOneColony(): void
    {
        [$leader, $origin, $guild] = $this->guild('HallFundingRace', 'HFR');
        [$member, $planet] = $this->player('HallDonor');
        \App\Tests\Support\GuildRecruitmentFixture::enrol(self::getContainer()->get(DiplomacyService::class), self::getContainer()->get(\App\Repository\GameRepository::class), $member, $guild->id);
        $before = $this->stock($origin, 'hydrogen') + $this->stock($planet, 'hydrogen');
        $results = $this->race($leader->universe->id, [
            ['action' => 'contribute', 'member' => $leader->id, 'guild' => $guild->id, 'planet' => $origin->id],
            ['action' => 'contribute', 'member' => $member->id, 'guild' => $guild->id, 'planet' => $planet->id],
        ]);
        self::assertContains('accepted', $results);
        self::assertContains('Le Hall a changé. Rechargez la page avant de réessayer.', $results);
        self::assertEqualsWithDelta($before - 8, $this->stock($origin, 'hydrogen') + $this->stock($planet, 'hydrogen'), 0.1);
        self::assertSame(8, (int) $this->em->getConnection()->fetchOne('SELECT strength FROM guild_hall WHERE guild_id = ?', [$guild->id]));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM guild_hall_contribution'));
        self::assertSame(8, (int) $this->em->getConnection()->fetchOne('SELECT SUM(strength) FROM guild_hall_contribution'));
    }

    public function testActualGuildDissolutionReleasesAnInstalledHallWithoutRefund(): void
    {
        [$leader, $colony, $guild] = $this->guild('HallDisband', 'HDB');
        [$rival, $rivalPlanet, $rivalGuild] = $this->guild('HallSuccessor', 'HSU');
        $service = GuildHallFixture::enable(self::getContainer());
        $service->contribute($leader, $guild->id, $colony->id, 0, ['hydrogen' => 32]);
        $system = $colony->solarSystem;
        $service->place($leader, $guild->id, $system->id, 1);
        $before = $this->stock($colony, 'hydrogen');
        $db = $this->em->getConnection();
        $guildId = $guild->id;

        // La dissolution réelle supprime la guilde, contrairement à son archivage J30.
        self::getContainer()->get(DiplomacyService::class)->disband($leader);
        self::assertFalse($db->fetchOne('SELECT id FROM guild WHERE id = ?', [$guildId]));
        self::assertFalse($db->fetchOne('SELECT id FROM guild_hall WHERE guild_id = ?', [$guildId]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM guild_hall_contribution'));
        self::assertSame($before, $this->stock($colony, 'hydrogen'));
        $this->discover($rival, $system);
        $service->contribute($rival, $rivalGuild->id, $rivalPlanet->id, 0, ['hydrogen' => 32]);
        self::assertSame($system->id, $service->place($rival, $rivalGuild->id, $system->id, 1)->solarSystem->id);
    }

    public function testOfficialQuorumCountsMembershipAcrossColoniesAndUsesExactly500000Points(): void
    {
        [$leader, $member, $third, $origin, $secondPlanet, $guild, $hall, $service] = $this->fundOfficialHall();
        $repo = self::getContainer()->get(GuildHallRepository::class);
        self::assertSame(450000, $hall->strength);
        self::assertSame(2, $repo->qualifiedDonors($hall));
        self::assertSame(49999, $repo->donor($hall, $member)->strength);
        self::assertSame(3, $repo->currentRosterSize($guild, $hall->requiredDonors));

        // Une deuxième colonie de la même participation complète son reçu,
        // elle ne devient jamais une quatrième identité.
        $otherColony = $this->em->getRepository(Planet::class)->findOneBy(['owner' => null, 'solarSystem' => $secondPlanet->solarSystem]);
        $otherColony->acquireBy($member, new \DateTimeImmutable());
        self::getContainer()->get(\App\Service\Colony\ResourceService::class)->initialize($otherColony, colony: true);
        $this->em->flush();
        $hall = $service->contribute($member, $guild->id, $otherColony->id, $hall->revision, ['hydrogen' => 1]);
        self::assertSame(450001, $hall->strength);
        $hall = $service->contribute($leader, $guild->id, $origin->id, $hall->revision, ['antimatter' => 6249, 'hydrogen' => 6]);
        self::assertSame(499999, $hall->strength);
        $hall = $service->contribute($leader, $guild->id, $origin->id, $hall->revision, ['hydrogen' => 1]);
        self::assertSame(500000, $hall->strength);
        self::assertSame(50000, $repo->donor($hall, $member)->strength);
        self::assertSame(3, $repo->qualifiedDonors($hall));
        self::assertSame(3, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM guild_hall_contribution WHERE hall_id = ?', [$hall->id]));

        $diplomacy = self::getContainer()->get(DiplomacyService::class);
        $diplomacy->leave($member);
        $diplomacy->leave($third);
        self::assertSame(1, $repo->currentRosterSize($guild, 3));
        self::assertSame(3, $repo->qualifiedDonors($hall));
        \App\Tests\Support\GuildRecruitmentFixture::enrol($diplomacy, self::getContainer()->get(\App\Repository\GameRepository::class), $member, $guild->id);
        [$newcomer] = $this->player('HallNewcomer');
        \App\Tests\Support\GuildRecruitmentFixture::enrol($diplomacy, self::getContainer()->get(\App\Repository\GameRepository::class), $newcomer, $guild->id);
        self::assertSame(3, $repo->currentRosterSize($guild, 3));
        self::assertSame(3, $repo->qualifiedDonors($hall));
        self::assertNull($repo->donor($hall, $newcomer));
        self::assertSame(50000, $repo->donor($hall, $member)->strength);
        $placed = $service->place($leader, $guild->id, $origin->solarSystem->id, $hall->revision);
        self::assertNotNull($placed->solarSystem);
        $diplomacy->leave($member);
        $diplomacy->leave($newcomer);
        self::assertSame($origin->solarSystem->id, $repo->forGuild($guild)->solarSystem->id);
    }

    public function testCannotPlaceAt499999(): void
    {
        // Chaque refus doit laisser la revendication et les dons inchangés.
        [$leader, $member, , $origin, $secondPlanet, $guild, $hall, $service] = $this->fundOfficialHall();
        $hall = $service->contribute($member, $guild->id, $secondPlanet->id, $hall->revision, ['hydrogen' => 1]);
        $hall = $service->contribute($leader, $guild->id, $origin->id, $hall->revision, ['antimatter' => 6249, 'hydrogen' => 6]);
        try {
            $service->place($leader, $guild->id, $origin->solarSystem->id, $hall->revision);
            self::fail('499999 points ne suffisent pas.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('entièrement financé', $exception->getMessage());
        }
        $db = $this->em->getConnection();
        self::assertSame(499999, (int) $db->fetchOne('SELECT strength FROM guild_hall WHERE id = ?', [$hall->id]));
        self::assertNull($db->fetchOne('SELECT solar_system_id FROM guild_hall WHERE id = ?', [$hall->id]));
    }

    public function testCommanderAloneCannotPlaceEvenWhenThreeFormerDonorsAreRecorded(): void
    {
        [$leader, $member, $third, $origin, $secondPlanet, $guild, $hall, $service] = $this->fundOfficialHall();
        $hall = $service->contribute($member, $guild->id, $secondPlanet->id, $hall->revision, ['hydrogen' => 1]);
        $hall = $service->contribute($leader, $guild->id, $origin->id, $hall->revision, ['antimatter' => 6249, 'hydrogen' => 7]);
        $diplomacy = self::getContainer()->get(DiplomacyService::class);
        $diplomacy->leave($member);
        $diplomacy->leave($third);
        try {
            $service->place($leader, $guild->id, $origin->solarSystem->id, $hall->revision);
            self::fail('Un ancien trio ne donne pas le droit de placer seul.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('membres actuels', $exception->getMessage());
        }
        $db = $this->em->getConnection();
        self::assertNull($db->fetchOne('SELECT solar_system_id FROM guild_hall WHERE id = ?', [$hall->id]));
        self::assertSame(3, (int) $db->fetchOne('SELECT COUNT(*) FROM guild_hall_contribution WHERE hall_id = ?', [$hall->id]));
    }

    public function testAnUnqualifiedMemberCanLeaveWithoutBlockingTheRemaining50000Points(): void
    {
        [$leader, $member, , $origin, , $guild, $hall, $service] = $this->fundOfficialHall();
        $diplomacy = self::getContainer()->get(DiplomacyService::class);
        $diplomacy->leave($member);
        [$replacement, $colony] = $this->player('FreshHallDonor');
        \App\Tests\Support\GuildRecruitmentFixture::enrol($diplomacy, self::getContainer()->get(\App\Repository\GameRepository::class), $replacement, $guild->id);
        $this->em->getConnection()->executeStatement("UPDATE planet_resource SET current_stock = 20000 WHERE planet_id = ? AND resource_type = 'antimatter'", [$colony->id]);
        $hall = $service->contribute($replacement, $guild->id, $colony->id, $hall->revision, ['antimatter' => 6250]);
        self::assertSame(500000, $hall->strength);
        $repo = self::getContainer()->get(GuildHallRepository::class);
        self::assertSame(3, $repo->qualifiedDonors($hall));
        self::assertSame(49999, $repo->donor($hall, $member)->strength);
        self::assertSame($origin->solarSystem->id, $service->place($leader, $guild->id, $origin->solarSystem->id, $hall->revision)->solarSystem->id);
    }

    public function testA49999GiftCannotConsumeACompleteReservedShare(): void
    {
        $service = $this->officialService();
        [$leader, $origin, $guild] = $this->guild('HallShareLeader', 'HSL');
        [$member, $colony] = $this->player('HallShareMember');
        \App\Tests\Support\GuildRecruitmentFixture::enrol(self::getContainer()->get(DiplomacyService::class), self::getContainer()->get(\App\Repository\GameRepository::class), $member, $guild->id);
        $db = $this->em->getConnection();
        $db->executeStatement("UPDATE planet_resource SET current_stock = 100000 WHERE planet_id = ? AND resource_type = 'antimatter'", [$origin->id]);
        $hall = $service->contribute($leader, $guild->id, $origin->id, 0, ['antimatter' => 50000]);
        $before = $this->stock($colony, 'antimatter');
        try {
            $service->contribute($member, $guild->id, $colony->id, $hall->revision, ['antimatter' => 6249, 'hydrogen' => 7]);
            self::fail('Le don doit au moins qualifier le membre quand il touche la réserve.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('au moins 50 000 points', $exception->getMessage());
        }
        self::assertSame($before, $this->stock($colony, 'antimatter'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM guild_hall_contribution'));
        self::assertSame(400000, (int) $db->fetchOne('SELECT strength FROM guild_hall'));
    }

    public function testAVeteranCannotConsumeTheReservedSharesInOneDonation(): void
    {
        $service = $this->officialService();
        [$leader, $origin, $guild] = $this->guild('OfficialVeteran', 'OVT');
        $before = $this->stock($origin, 'antimatter');
        try {
            $service->contribute($leader, $guild->id, $origin->id, 0, ['antimatter' => 62500]);
            self::fail('Il faut réserver les parts des autres donateurs.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('400 000 points', $exception->getMessage());
        }
        self::assertSame($before, $this->stock($origin, 'antimatter'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM guild_hall'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM guild_hall_contribution'));
    }

    /** @return array{PlayerUniverse, PlayerUniverse, PlayerUniverse, Planet, Planet, Guild, GuildHall, GuildHallService} */
    private function fundOfficialHall(): array
    {
        $service = $this->officialService();
        [$leader, $origin, $guild] = $this->guild('OfficialLeader', 'OLD');
        [$member, $secondPlanet] = $this->player('OfficialMember');
        [$third, $thirdPlanet] = $this->player('OfficialThird');
        $diplomacy = self::getContainer()->get(DiplomacyService::class);
        \App\Tests\Support\GuildRecruitmentFixture::enrol($diplomacy, self::getContainer()->get(\App\Repository\GameRepository::class), $member, $guild->id);
        \App\Tests\Support\GuildRecruitmentFixture::enrol($diplomacy, self::getContainer()->get(\App\Repository\GameRepository::class), $third, $guild->id);
        $db = $this->em->getConnection();
        foreach ([$origin, $secondPlanet, $thirdPlanet] as $planet) {
            // Stocks de recette isolés : ce volume n'est pas un paquet de jeu.
            $db->executeStatement("UPDATE planet_resource SET current_stock = 100000 WHERE planet_id = ? AND resource_type = 'antimatter'", [$planet->id]);
        }
        $hall = $service->contribute($leader, $guild->id, $origin->id, 0, ['antimatter' => 43750, 'hydrogen' => 1]);
        $hall = $service->contribute($member, $guild->id, $secondPlanet->id, $hall->revision, ['antimatter' => 6249, 'hydrogen' => 7]);
        $hall = $service->contribute($third, $guild->id, $thirdPlanet->id, $hall->revision, ['antimatter' => 6250]);

        return [$leader, $member, $third, $origin, $secondPlanet, $guild, $hall, $service];
    }

    private function officialService(): GuildHallService
    {
        return GuildHallFixture::enable(self::getContainer(), 500000, 3, 50000);
    }

    #[DataProvider('refusals')]
    public function testRefusedCommandsDoNotDebitOrWrite(string $scenario, string $reason): void
    {
        [$leader, $origin, $guild] = $this->guild('HallLead', 'HLD');
        [$member, $memberPlanet] = $this->player('HallAlly');
        [$stranger, $strangerPlanet, $otherGuild] = $this->guild('HallStranger', 'HST');
        [, $otherWorldPlanet] = $this->player('HallOtherWorld', 'turbo-1');
        $diplomacy = self::getContainer()->get(DiplomacyService::class);
        \App\Tests\Support\GuildRecruitmentFixture::enrol($diplomacy, self::getContainer()->get(\App\Repository\GameRepository::class), $member, $guild->id);
        $service = GuildHallFixture::enable(self::getContainer());
        $hall = $service->contribute($leader, $guild->id, $origin->id, 0, ['metal' => $scenario === 'early' ? 1 : 16]);
        if ($scenario === 'taken') {
            $service->place($leader, $guild->id, $origin->solarSystem->id, 1);
            $service->contribute($stranger, $otherGuild->id, $strangerPlanet->id, 0, ['metal' => 16]);
            $this->discover($stranger, $origin->solarSystem);
        }
        $unknown = $this->unknownSystem($leader);
        $expected = $hall->revision;
        $db = $this->em->getConnection();
        if ($scenario === 'stock') {
            $db->executeStatement("UPDATE planet_resource SET current_stock = 0, last_updated_at = UTC_TIMESTAMP() WHERE planet_id = ? AND resource_type = 'metal'", [$strangerPlanet->id]);
        }
        $beforeHall = $db->fetchAllAssociative('SELECT id, strength, solar_system_id, revision FROM guild_hall ORDER BY id');
        $beforeStocks = $db->fetchAllAssociative('SELECT id, current_stock, last_updated_at FROM planet_resource ORDER BY id');
        try {
            match ($scenario) {
                'other-guild' => $service->contribute($stranger, $guild->id, $strangerPlanet->id, $expected, ['metal' => 1]),
                'not-leader' => $service->place($member, $guild->id, $memberPlanet->solarSystem->id, $expected),
                'early' => $service->place($leader, $guild->id, $origin->solarSystem->id, $expected),
                'other-world' => $service->place($leader, $guild->id, $otherWorldPlanet->solarSystem->id, $expected),
                'unknown' => $service->place($leader, $guild->id, $unknown->id, $expected),
                'taken' => $service->place($stranger, $otherGuild->id, $origin->solarSystem->id, 1),
                'foreign-planet' => $service->contribute($stranger, $otherGuild->id, $origin->id, 0, ['metal' => 1]),
                'stale' => $service->contribute($leader, $guild->id, $origin->id, 0, ['metal' => 1]),
                'overfund' => $service->contribute($stranger, $otherGuild->id, $strangerPlanet->id, 0, ['metal' => 17]),
                'stock' => $service->contribute($stranger, $otherGuild->id, $strangerPlanet->id, 0, ['metal' => 16]),
            };
            self::fail('La commande aurait dû être refusée.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString($reason, $exception->getMessage());
        }
        self::assertSame($beforeHall, $db->fetchAllAssociative('SELECT id, strength, solar_system_id, revision FROM guild_hall ORDER BY id'));
        self::assertSame($beforeStocks, $db->fetchAllAssociative('SELECT id, current_stock, last_updated_at FROM planet_resource ORDER BY id'));
    }

    public static function refusals(): iterable
    {
        yield 'droits guilde' => ['other-guild', 'Guilde inaccessible'];
        yield 'membre simple' => ['not-leader', 'commandant actuel'];
        yield 'financement incomplet' => ['early', 'entièrement financé'];
        yield 'système autre univers' => ['other-world', 'Système inaccessible'];
        yield 'jamais découvert' => ['unknown', 'Système inaccessible'];
        yield 'revendication concurrente déjà validée' => ['taken', 'déjà occupé'];
        yield 'stock tierce colonie' => ['foreign-planet', 'ne vous appartient pas'];
        yield 'ancienne révision' => ['stale', 'Le Hall a changé'];
        yield 'don supérieur au reste' => ['overfund', 'dépasse la force'];
        yield 'stock insuffisant' => ['stock', 'Ressources insuffisantes'];
    }

    /** @return array{PlayerUniverse, Planet, Guild} */
    private function guild(string $name, string $tag): array
    {
        [$participant, $planet] = $this->player($name);
        self::getContainer()->get(DiplomacyService::class)->create($participant, $name, $tag);

        return [$participant, $planet, self::getContainer()->get(RelationResolver::class)->guild($participant)];
    }

    /** @return array{PlayerUniverse, Planet} */
    private function player(string $name, string $universeSlug = 'omega-1'): array
    {
        $credentials = new RegistrationData();
        $credentials->email = strtolower($name).'@example.test';
        $credentials->username = $name;
        $credentials->plainPassword = bin2hex(random_bytes(16)).'Aa!';
        $player = self::getContainer()->get(RegistrationService::class)->register($credentials);
        $player->emailVerifiedAt = new \DateTimeImmutable('-1 minute');
        $this->em->flush();
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $universeSlug]);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $membership = $player->currentPlayerUniverse;
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $membership]);

        return [$membership, $planet];
    }

    private function unknownSystem(PlayerUniverse $viewer): SolarSystem
    {
        $candidate = $this->em->getConnection()->fetchOne(
            'SELECT s.id FROM solar_system s JOIN galaxy a ON a.id = s.galaxy_id
            WHERE a.universe_id = ? AND NOT EXISTS (SELECT 1 FROM player_discovery d WHERE d.player_universe_id = ? AND d.solar_system_id = s.id)
            ORDER BY s.id LIMIT 1',
            [$viewer->universe->id, $viewer->id],
        );

        return $this->em->find(SolarSystem::class, (int) $candidate);
    }

    private function discover(PlayerUniverse $viewer, SolarSystem $system): void
    {
        if ($this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse' => $viewer, 'solarSystem' => $system])) {
            return;
        }
        $discovery = new PlayerDiscovery();
        $discovery->playerUniverse = $viewer;
        $discovery->solarSystem = $system;
        $this->em->persist($discovery);
        $this->em->flush();
    }

    /**
     * @param list<array<string, int|string>> $actions
     * @return list<string>
     */
    private function race(int $universe, array $actions): array
    {
        $directory = sys_get_temp_dir().'/genesis-hall-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $db = $this->em->getConnection();
        $processes = [];
        $db->beginTransaction();
        try {
            // Les deux acteurs ont leur propre connexion. Tenir le point commun
            // de sérialisation permet de prouver qu'ils attendent ensemble.
            $db->fetchOne('SELECT id FROM universe WHERE id = ? FOR UPDATE', [$universe]);
            foreach ($actions as $index => $action) {
                $input = $action + ['ready' => $directory.'/'.$index, 'barrier' => $directory.'/go'];
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_guild_hall.php', json_encode($input, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(25);
                $process->start();
                $processes[] = $process;
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = glob($directory.'/[0-9]') ?: [];
                if (count($ready) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $ready, implode('; ', array_map(static fn (Process $p): string => $p->getErrorOutput(), $processes)));
            $ids = array_map(static fn (string $file): int => (int) file_get_contents($file), $ready);
            self::assertCount(2, array_unique($ids));
            touch($directory.'/go');
            do {
                $blocked = 0;
                foreach ($db->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $blocked, 'Les deux commandes doivent être réellement concurrentes.');
            $db->commit();

            return array_map(static function (Process $process): string {
                self::assertSame(0, $process->wait(), $process->getOutput().$process->getErrorOutput());

                return trim($process->getOutput());
            }, $processes);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($processes as $process) {
                if ($process->isRunning()) {
                    $process->stop();
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function stock(Planet $planet, string $resource): float
    {
        return (float) $this->em->getConnection()->fetchOne('SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = ?', [$planet->id, $resource]);
    }
}
