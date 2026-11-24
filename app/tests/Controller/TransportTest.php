<?php
namespace App\Tests\Controller;

use App\Entity\{AllianceContract, BoostEventContribution, DiplomaticReport, Fleet, FleetShip, Planet, PlanetResource, Player, PlayerDiscovery, ReportNotice, TransportReport, Universe};
use App\Repository\ReportNoticeRepository;
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
        $this->em->flush();
        foreach ($this->em->getRepository(PlanetResource::class)->findAll() as $row) $row->currentStock = 1000;
        $this->recipient->preferences['transportReception'] = 'all';
        $fleet = new Fleet();$fleet->homePlanet = $this->origin;$fleet->playerUniverse = $this->sender->currentPlayerUniverse;
        $this->em->persist($fleet);
        $ship = new FleetShip();$ship->fleet = $fleet;$ship->slug = 'transport';$ship->quantity = 1;$this->em->persist($ship);
        $this->em->flush();
    }

    private function player(string $name): Player
    {
        $data = new RegistrationData();$data->email = strtolower($name).'@example.test';$data->username = $name;$data->plainPassword = bin2hex(random_bytes(18));
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
        $notice = $this->em->getClassMetadata(ReportNotice::class);
        $transport = $this->em->getClassMetadata(TransportReport::class);
        $tool->dropSchema([$notice, $transport]);
        require_once dirname(__DIR__, 2).'/migrations/Version20261107150000.php';
        $migration = new \DoctrineMigrations\Version20261107150000($this->em->getConnection(), new \Psr\Log\NullLogger());
        try {
            $migration->up(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement());
            // La table de notices dépend maintenant de la table que cette ancienne migration recrée.
            $tool->createSchema([$notice]);
            self::assertSame([], $tool->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
        } finally {
            // Restaurer les noms de contraintes Doctrine pour les tests suivants.
            $metadata = [$notice, $transport];
            $tool->dropSchema($metadata);
            $tool->createSchema($metadata);
        }
    }

    public function testReportNoticeMigrationMatchesMappingWithoutBackfill(): void
    {
        $tool = new SchemaTool($this->em);
        // Rejouer le chemin historique auquel s’ajoute l’extension diplomatique.
        $tool->dropSchema([
            $this->em->getClassMetadata(ReportNotice::class),
            $this->em->getClassMetadata(DiplomaticReport::class),
        ]);
        $connection = $this->em->getConnection();
        foreach (['Version20261122010100', 'Version20261124083000'] as $version) {
            require_once dirname(__DIR__, 2).'/migrations/'.$version.'.php';
            $class = 'DoctrineMigrations\\'.$version;
            $migration = new $class($connection, new \Psr\Log\NullLogger());
            $migration->up(new \Doctrine\DBAL\Schema\Schema());
            foreach ($migration->getSql() as $query) {
                $connection->executeStatement($query->getStatement());
            }
        }

        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM report_notice'));
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM diplomatic_report'));
        self::assertSame([], $tool->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
    }

    public function testUnreadTransportIsCreatedAtDepartureAndOnlyMarksOneAuthorizedReader(): void
    {
        $this->client->disableReboot();
        $fleet = $this->send();
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        $sender = $this->sender->currentPlayerUniverse;
        $recipient = $this->recipient->currentPlayerUniverse;
        $notices = self::getContainer()->get(ReportNoticeRepository::class);
        self::assertSame(1, $notices->count($sender));
        self::assertSame(1, $notices->count($recipient));

        $this->client->loginUser($this->recipient);
        $path = '/rapports/transport/'.$report->id;
        $this->client->request('GET', $path.'?type=economy&view=archives&page=2', [], [], ['HTTP_SEC_PURPOSE' => 'prefetch']);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $notices->count($recipient));
        self::assertSelectorTextContains('.report-unread-count', '1');
        $form = $this->client->getCrawler()->selectButton('Marquer ce rapport comme lu')->form();
        $this->client->request('POST', $form->getUri(), ['_token' => 'invalide']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $notices->count($recipient));

        $this->client->submit($form);
        self::assertResponseRedirects($path.'?type=economy&view=archives&page=2', 303);
        $this->client->request('GET', '/rapports/non-lus');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['membership' => $recipient->id, 'count' => 0], json_decode(
            $this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR,
        ));

        $connection = $this->em->getConnection();
        $readAt = $connection->fetchOne('SELECT consulted_at FROM report_notice WHERE player_universe_id = ?', [$recipient->id]);
        self::assertNotNull($readAt);
        $this->client->submit($form);
        self::assertResponseRedirects(null, 303);
        $this->arrive($fleet);
        self::assertSame(2, (int) $connection->fetchOne('SELECT COUNT(*) FROM report_notice'));
        self::assertSame($readAt, $connection->fetchOne('SELECT consulted_at FROM report_notice WHERE player_universe_id = ?', [$recipient->id]));
        self::assertSame(1, $notices->count($sender));
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
        $capacity = self::getContainer()->get(\App\Service\Colony\ResourceService::class)->snapshot($this->target)['metal']['capacity'];
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
        // Garder l’horloge simulée entre les requêtes évite de basculer vers la date réelle et d’expirer la session.
        $this->client->disableReboot();
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

    public function testIncomingTransportLinksToTheExistingReportForItsOriginalRecipient(): void
    {
        $this->client->disableReboot();
        $fleet = $this->send();
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet' => $fleet]);
        $fleetId = $fleet->id;
        $targetId = $this->target->id;
        $reportPath = '/rapports/transport/'.$report->id;
        $link = '[data-incoming-attack="'.$fleetId.'"] a[href="'.$reportPath.'"]';
        $newOwner = $this->player('NextOwner');
        $this->client->loginUser($this->recipient);

        $this->client->request('GET', '/flottes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains($link, 'Consulter le récapitulatif du transport');

        $this->client->request('GET', '/flottes/surveillance');
        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $panel = new \Symfony\Component\DomCrawler\Crawler($payload['html']);
        self::assertCount(1, $panel->filter($link));
        self::assertCount(0, $panel->filter('.report-resource-table'));

        $this->client->request('GET', $reportPath);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.core-report-state', 'Transport en route');
        $amounts = $this->client->getCrawler()->filter('.report-resource-table td[data-label="Envoyé"]')->each(
            static fn (\Symfony\Component\DomCrawler\Crawler $amount): string => $amount->text(),
        );
        self::assertContains('123,00', $amounts);
        self::assertContains('67,00', $amounts);

        // La visibilité de l'arrivée suit la colonie ; celle du rapport ne se transfère pas.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $target = $em->find(Planet::class, $targetId);
        $newOwner = $em->find(Player::class, $newOwner->id);
        $target->owner = $newOwner->currentPlayerUniverse;
        $em->flush();
        $this->client->loginUser($newOwner);
        $this->client->request('GET', '/flottes/surveillance');
        self::assertResponseIsSuccessful();
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        $panel = new \Symfony\Component\DomCrawler\Crawler($payload['html']);
        self::assertCount(1, $panel->filter('[data-incoming-attack="'.$fleetId.'"]'));
        self::assertCount(0, $panel->filter($link));
    }

    public function testExcessCargoIsReducedToTheRemainingBayWithoutUsingFuelTanks(): void
    {
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$this->origin]) as $resource) {
            $resource->currentStock=100000;$resource->lastUpdatedAt=$this->clock->now();
        }
        $this->em->flush();
        $quote=self::getContainer()->get(FleetService::class)->preview($this->sender->currentPlayerUniverse,$this->origin,'transport',$this->target->id,['transport'=>1],.5,['metal'=>40000,'nano'=>40000,'hydrogen'=>100000]);
        self::assertSame(40000,$quote['freight']['metal']);
        self::assertSame(10000,$quote['freight']['nano']);
        self::assertArrayNotHasKey('hydrogen',$quote['freight']);
        self::assertLessThanOrEqual($quote['freight_capacity'],array_sum($quote['freight']));
        self::assertTrue($quote['adjusted']);
    }

    public function testHydrogenMaximumReservesFuelAndSendingDoesNotOverspend(): void
    {
        $hydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->origin,'resourceType'=>'hydrogen']);
        $hydrogen->currentStock=1000;$hydrogen->lastUpdatedAt=$this->clock->now();$this->em->flush();
        $service=self::getContainer()->get(FleetService::class);
        $quote=$service->preview($this->sender->currentPlayerUniverse,$this->origin,'transport',$this->target->id,['transport'=>1],.5,['hydrogen'=>100000]);
        $expected=(int)floor(1000-$quote['total_fuel']);
        self::assertSame($expected,$quote['freight']['hydrogen']);
        self::assertSame($expected,$quote['cargo_maximum']['hydrogen']);
        $fleet=$this->send(['hydrogen'=>100000]);
        self::assertEqualsWithDelta($expected+$quote['total_fuel'],$fleet->cargo['hydrogen'],.000001);
        self::assertGreaterThanOrEqual(0,$hydrogen->currentStock);
        self::assertLessThan(1,$hydrogen->currentStock);
    }

    public function testFleetSelectionUsesOwnedStockInsteadOfTheOldOneHundredLimit(): void
    {
        $ship=$this->em->getRepository(FleetShip::class)->findOneBy(['slug'=>'transport']);
        $ship->quantity=200;$this->em->flush();
        $service=self::getContainer()->get(FleetService::class);
        $quote=$service->preview($this->sender->currentPlayerUniverse,$this->origin,'transport',$this->target->id,['transport'=>150],.5,[]);
        self::assertSame(150,$quote['composition']['transport']['basic']);
        $quote=$service->preview($this->sender->currentPlayerUniverse,$this->origin,'transport',$this->target->id,['transport'=>300],.5,[]);
        self::assertSame(200,$quote['composition']['transport']['basic']);
        self::assertTrue($quote['adjusted']);
    }
}
