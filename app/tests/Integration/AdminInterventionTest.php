<?php
namespace App\Tests\Integration;

use App\Entity\{AllianceContract, BoostEventContribution, Fleet, FleetShip, Planet, PlanetResource, Player, PlayerDiscovery, TransportReport, Universe};
use App\Service\Fleet\{FleetService, TransportService};
use App\Service\Admin\AdminInterventionService;
use App\Service\Colony\ResourceService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class AdminInterventionTest extends WebTestCase
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
        $this->sender->roles = ['ROLE_ADMIN'];
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

    public function testResourceCorrectionIsAtomicAuditedAndIdempotent(): void
    {
        $service = self::getContainer()->get(AdminInterventionService::class);
        $resource = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->target, 'resourceType'=>'metal']);
        $resource->currentStock=5000;$this->em->flush();
        $stock = $resource->currentStock;
        self::assertTrue($service->resources($this->sender, $this->recipient->id, $this->target, 'add', ['metal'=>'500.25'], 'Réparation ticket 42', str_repeat('b',32)));
        self::assertEqualsWithDelta($stock+500.25, $resource->currentStock, .001);
        self::assertFalse($service->resources($this->sender, $this->recipient->id, $this->target, 'add', ['metal'=>'500.25'], 'Réparation ticket 42', str_repeat('b',32)));
        self::assertEqualsWithDelta($stock+500.25, $resource->currentStock, .001);
        $log = $this->em->getRepository(\App\Entity\AdminAuditLog::class)->findOneBy(['action'=>'admin_resources_adjust']);
        self::assertSame('Réparation ticket 42', $log->details['reason']);
        self::assertEqualsWithDelta($stock, $log->details['before']['metal'], .001);
        self::assertEqualsWithDelta($stock+500.25, $log->details['after']['metal'], .001);
    }

    public function testOverflowRollsBackEveryResourceAndReceipt(): void
    {
        $connection = $this->em->getConnection();
        $before = $connection->fetchAllAssociative('SELECT resource_type,current_stock,last_updated_at FROM planet_resource WHERE planet_id=? ORDER BY resource_type', [$this->target->id]);
        $this->expectException(\DomainException::class);
        try {
            self::getContainer()->get(AdminInterventionService::class)->resources($this->sender, $this->recipient->id, $this->target, 'add', ['metal'=>'1','nano'=>'99999999'], 'Contrôle capacité', str_repeat('c',32));
        } finally {
            self::assertSame($before, $connection->fetchAllAssociative('SELECT resource_type,current_stock,last_updated_at FROM planet_resource WHERE planet_id=? ORDER BY resource_type', [$this->target->id]));
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM action_receipt WHERE request_key=?', [str_repeat('c',32)]));
        }
    }

    public function testNegativeBalanceIsRefused(): void
    {
        $this->expectException(\DomainException::class);
        self::getContainer()->get(AdminInterventionService::class)->resources($this->sender, $this->recipient->id, $this->target, 'remove', ['metal'=>'99999999'], 'Contrôle retrait', str_repeat('d',32));
    }

    public function testWrongOwnerIsRefused(): void
    {
        $this->expectException(\DomainException::class);
        self::getContainer()->get(AdminInterventionService::class)->resources($this->sender, $this->sender->id, $this->target, 'add', ['metal'=>'1'], 'Contrôle propriété', str_repeat('e',32));
    }

    public function testNonAdminCannotUseService(): void
    {
        $this->expectException(\DomainException::class);
        self::getContainer()->get(AdminInterventionService::class)->resources($this->recipient, $this->recipient->id, $this->target, 'add', ['metal'=>'1'], 'Contrôle droits', str_repeat('f',32));
    }

    public function testRescheduleInvalidatesOldWorkerAndRecallKeepsFreight(): void
    {
        $fleet = $this->send();$oldToken = $fleet->dispatchToken;
        $service = self::getContainer()->get(AdminInterventionService::class);
        self::assertTrue($service->fleet($this->sender, $fleet, 'reschedule', 30, 'Débloquer le trajet', hash('sha256',$oldToken), str_repeat('b',32)));
        self::assertNotSame($oldToken, $fleet->dispatchToken);
        self::assertSame($this->clock->now()->modify('+30 seconds')->format(DATE_ATOM), $fleet->arrivalAt->format(DATE_ATOM));
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $oldToken);
        self::assertSame('traveling', $fleet->status);
        self::assertTrue($service->fleet($this->sender, $fleet, 'recall', 0, 'Annulation administrative', hash('sha256',$fleet->dispatchToken), str_repeat('c',32)));
        self::assertSame('returning', $fleet->status);
        $report = $this->em->getRepository(TransportReport::class)->findOneBy(['fleet'=>$fleet]);
        self::assertSame('refused', $report->status);
        self::assertSame('admin_recall', $report->snapshot['refusal_reason']);
        self::assertEqualsWithDelta(123, $report->snapshot['returned']['metal'], .001);
        self::assertEqualsWithDelta(67, $report->snapshot['returned']['hydrogen'], .001);
        $this->clock->sleep($fleet->travelSeconds+1);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $fleet->dispatchToken);
        self::assertSame('completed', $fleet->status);
    }

    public function testStaleFleetFormIsRefused(): void
    {
        $fleet = $this->send();
        $this->expectException(\DomainException::class);
        self::getContainer()->get(AdminInterventionService::class)->fleet($this->sender, $fleet, 'reschedule', 30, 'Ancien formulaire', hash('sha256','old-token'), str_repeat('b',32));
    }

    public function testPendingCombatBlocksInterventionEvenIfFleetStatusIsStale(): void
    {
        $fleet = $this->send();
        $event = new \App\Entity\CombatEvent();$event->planet=$this->target;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+1 minute');$event->seed=1;
        $participant = new \App\Entity\CombatParticipant();$participant->fleet=$fleet;$participant->playerUniverse=$fleet->playerUniverse;$participant->combatEvent=$event;$participant->role='attacker';$participant->arrivalAt=$this->clock->now();
        $this->em->persist($event);$this->em->persist($participant);$this->em->flush();
        $this->expectException(\DomainException::class);
        self::getContainer()->get(AdminInterventionService::class)->fleet($this->sender,$fleet,'reschedule',30,'Mission engagée',hash('sha256',$fleet->dispatchToken),str_repeat('b',32));
    }
}
