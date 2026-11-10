<?php
namespace App\Tests\Controller;

use App\Entity\{AllianceContract, BoostEventContribution, Fleet, FleetShip, Planet, PlanetResource, Player, PlayerDiscovery, TransportReport, Universe};
use App\Service\Fleet\{FleetService, TransportService};
use App\Service\Colony\ResourceService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class TransportTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private $client;
    private Player $sender;
    private Player $recipient;
    private Planet $origin;
    private Planet $target;

    protected function setUp(): void
    {
        $this->client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->clock = new MockClock('2026-11-16T00:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $this->sender = $this->player('Sender');
        $this->recipient = $this->player('Recipient');
        $this->origin = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $this->sender->currentPlayerUniverse]);
        $this->target = $this->em->getRepository(Planet::class)->findOneBy(['solarSystem' => $this->origin->solarSystem, 'owner' => null]);
        $this->target->owner = $this->recipient->currentPlayerUniverse;
        self::getContainer()->get(ResourceService::class)->initialize($this->target);
        $this->recipient->preferences['transportReception'] = 'all';
        $fleet = new Fleet();$fleet->homePlanet = $this->origin;$fleet->playerUniverse = $this->sender->currentPlayerUniverse;
        $this->em->persist($fleet);
        $ship = new FleetShip();$ship->fleet = $fleet;$ship->slug = 'transport';$ship->quantity = 1;$this->em->persist($ship);
        $this->em->flush();
    }

    private function player(string $name): Player
    {
        $data = new RegistrationData();$data->email = strtolower($name).'@example.test';$data->username = $name;$data->plainPassword = 'A-test-password-2026';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        return $player;
    }

    private function send(array $cargo = ['metal' => 123, 'hydrogen' => 67]): Fleet
    {
        self::getContainer()->get(FleetService::class)->send($this->sender->currentPlayerUniverse, $this->origin, 'transport', $this->target->id, ['transport' => 1], .5, $cargo, str_repeat('a', 32));
        return $this->em->getRepository(Fleet::class)->findOneBy(['status' => 'traveling']);
    }

    private function arrive(Fleet $fleet): void
    {
        $this->clock->sleep($fleet->travelSeconds + 1);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $fleet->dispatchToken);
    }

    public function testPreparedMigrationMatchesDoctrineMapping(): void
    {
        $tool = new SchemaTool($this->em);
        $tool->dropSchema([$this->em->getClassMetadata(TransportReport::class)]);
        require_once dirname(__DIR__, 2).'/migrations/Version20261107150000.php';
        $migration = new \DoctrineMigrations\Version20261107150000($this->em->getConnection(), new \Psr\Log\NullLogger());
        try {
            $migration->up(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement());
            self::assertSame([], $tool->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
        } finally {
            // Restaurer les noms de contraintes Doctrine pour les tests suivants.
            $metadata = [$this->em->getClassMetadata(TransportReport::class)];
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }
    }

    public function testReceptionDefaultsToAlliesAndOwnColoniesRemainAllowed(): void
    {
        $this->recipient->preferences = [];
        $this->em->flush();
        $service = self::getContainer()->get(TransportService::class);
        $sender = $this->sender->currentPlayerUniverse;
        self::assertFalse($service->canReceive($sender, $this->target));
        $contract = new AllianceContract();
        [$contract->playerA, $contract->playerB] = $sender->id < $this->recipient->currentPlayerUniverse->id ? [$sender, $this->recipient->currentPlayerUniverse] : [$this->recipient->currentPlayerUniverse, $sender];
        $contract->proposer = $sender;$contract->status = 'accepted';$this->em->persist($contract);$this->em->flush();
        self::assertTrue($service->canReceive($sender, $this->target));
        $this->recipient->preferences['transportReception'] = 'none';$this->em->flush();
        self::assertFalse($service->canReceive($sender, $this->target));
        $this->sender->preferences['transportReception'] = 'none';$this->em->flush();
        self::assertTrue($service->canReceive($sender, $this->origin));
    }

    public function testDirectRequestCannotBypassRefusal(): void
    {
        $this->recipient->preferences['transportReception'] = 'none';$this->em->flush();
        $this->expectException(\DomainException::class);
        $this->send();
    }

    public function testUnknownSystemCannotBeTargeted(): void
    {
        $this->em->createQuery('DELETE FROM App\\Entity\\PlayerDiscovery d WHERE d.playerUniverse = :member')->setParameter('member', $this->sender->currentPlayerUniverse)->execute();
        $this->expectException(\DomainException::class);
        $this->send();
    }

    public function testDeliveryKeepsReturnFuelAndCannotBeCountedTwiceOrEarnBoosts(): void
    {
        self::getContainer()->get(\App\Service\Boost\BoostEventService::class)->tick();
        $joinedAt = $this->recipient->currentPlayerUniverse->joinedAt;
        $fleet = $this->send();$token = $fleet->dispatchToken;
        $this->arrive($fleet);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        self::assertSame('delivered', $report->status);
        self::assertEquals(123, $report->snapshot['delivered']['metal']);
        self::assertEquals(67, $report->snapshot['delivered']['hydrogen']);
        self::assertEqualsWithDelta($fleet->reservedFuel, $fleet->cargo['hydrogen'], .000001);
        self::assertSame(0, $this->em->getRepository(BoostEventContribution::class)->count(['playerUniverse' => $this->sender->currentPlayerUniverse]));
        self::assertEquals($joinedAt, $this->recipient->currentPlayerUniverse->joinedAt);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $token);
        self::assertSame(1, $this->em->getRepository(TransportReport::class)->count());
        $this->arrive($fleet);
        self::assertSame('completed', $fleet->status);
        self::assertArrayHasKey('returned_at', $report->snapshot);
    }

    public function testFullStorageReturnsFreightIncludingHydrogen(): void
    {
        $fleet = $this->send();
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $this->target]) as $row) $row->currentStock = 1e9;
        $this->em->flush();
        $this->arrive($fleet);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        self::assertEquals(0, $report->snapshot['delivered']['metal']);
        self::assertEquals(123, $report->snapshot['returned']['metal']);
        self::assertEquals(67, $report->snapshot['returned']['hydrogen']);
        self::assertEqualsWithDelta(67 + $fleet->reservedFuel, $fleet->cargo['hydrogen'], .000001);
        $this->arrive($fleet);
        self::assertEquals(123, $report->snapshot['returned_deposited']['metal']);
    }

    public function testPartialDeliveryAndOwnColonySurplusArePreserved(): void
    {
        $this->target->owner = $this->sender->currentPlayerUniverse;
        $rows = $this->em->getRepository(PlanetResource::class)->findBy(['planet' => $this->target]);
        $capacity = self::getContainer()->get(\App\Service\Catalog\GameCatalog::class)->rules()['base_storage_capacity'];
        foreach ($rows as $row) {
            $row->currentStock = $row->resourceType === 'metal' ? $capacity - 50 : $capacity;
            $row->lastUpdatedAt = $this->clock->now()->modify('+1 hour');
        }
        $this->em->flush();
        $fleet = $this->send(['metal' => 123]);
        $this->arrive($fleet);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        self::assertEquals(50, $report->snapshot['delivered']['metal']);
        self::assertEquals(73, $report->snapshot['returned']['metal']);
        self::assertEquals(73, $fleet->cargo['metal']);
    }

    public function testAnotherGalaxyIsRejected(): void
    {
        $galaxy = new \App\Entity\Galaxy();
        $galaxy->universe = $this->sender->currentPlayerUniverse->universe;
        $galaxy->name = 'Other';
        $this->em->persist($galaxy);
        $system = new \App\Entity\SolarSystem();$system->galaxy = $galaxy;$system->name = 'Other';$system->coordX = 0;$system->coordY = 0;
        $this->em->persist($system);$this->target->solarSystem = $system;
        $discovery = new PlayerDiscovery();$discovery->playerUniverse = $this->sender->currentPlayerUniverse;$discovery->solarSystem = $system;$this->em->persist($discovery);$this->em->flush();
        $this->expectException(\DomainException::class);
        $this->send();
    }

    public function testPreferenceChangedDuringFlightReturnsEverything(): void
    {
        $fleet = $this->send();
        $this->recipient->preferences['transportReception'] = 'none';$this->em->flush();
        $this->arrive($fleet);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        self::assertSame('refused', $report->status);
        self::assertEquals(123, $fleet->cargo['metal']);
    }

    public function testOwnershipChangedDuringFlightReturnsEverythingEvenIfNewOwnerAccepts(): void
    {
        $fleet = $this->send();
        $this->target->owner = $this->sender->currentPlayerUniverse;$this->em->flush();
        $this->arrive($fleet);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        self::assertSame('refused', $report->status);
        self::assertEquals(123, $fleet->cargo['metal']);
    }

    public function testReportsArePrivateAndAdminHistoryRequiresAdminRole(): void
    {
        $fleet = $this->send();$this->arrive($fleet);
        $id = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet])->id;
        $this->client->loginUser($this->sender);
        $this->client->request('GET', '/rapports/transport/'.$id);self::assertResponseIsSuccessful();
        $this->client->request('GET', '/rapports?type=economy');self::assertResponseIsSuccessful();self::assertSelectorTextContains('.report-list', 'Sender');
        $this->client->loginUser(self::getContainer()->get(EntityManagerInterface::class)->find(Player::class, $this->recipient->id));
        $this->client->request('GET', '/rapports/transport/'.$id);self::assertResponseIsSuccessful();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $outsider = $this->player('Outsider');$this->client->loginUser($outsider);
        $this->client->request('GET', '/rapports/transport/'.$id);self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/admin/transports');self::assertResponseRedirects('/acces-refuse');
        $em = self::getContainer()->get(EntityManagerInterface::class);$outsider = $em->find(Player::class, $outsider->id);$outsider->roles = ['ROLE_ADMIN'];$em->flush();
        $this->client->loginUser($outsider);$this->client->request('GET', '/admin/transports');self::assertResponseRedirects('/admin/flottes?mission=transport&status=&page=1');
        $this->client->followRedirect();self::assertResponseIsSuccessful();self::assertSelectorTextContains('h1', 'Surveillance des missions');
    }
}
