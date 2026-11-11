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
    public function testForcedReturnOfExpiredOrphanKeepsFreightAndInvalidatesArrival(): void
    {
        $fleet = $this->send();
        $fleet->dispatchToken = '';
        $this->clock->sleep($fleet->travelSeconds + 600);
        $this->em->flush();
        $service = self::getContainer()->get(AdminInterventionService::class);
        $targetStock = $this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type', [$this->target->id]);
        $key = str_repeat('1', 32);
        self::assertTrue($service->fleet($this->sender, $fleet, 'force_return', 0, 'Retour du voyage bloqué', hash('sha256',''), $key));
        self::assertSame('returning', $fleet->status);
        self::assertNotSame('', $fleet->dispatchToken);
        self::assertEquals($this->clock->now()->modify('+'.$fleet->travelSeconds.' seconds'), $fleet->arrivalAt);
        self::assertEqualsWithDelta(123, $fleet->cargo['metal'], .001);
        $fuel = $fleet->reservedFuel;
        self::assertFalse($service->fleet($this->sender, $fleet, 'force_return', 0, 'Retour du voyage bloqué', hash('sha256',''), $key));
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, '');
        self::assertSame('returning', $fleet->status);
        self::assertSame($targetStock, $this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type', [$this->target->id]));
        self::assertCount(1, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->findBy(['action'=>'admin_fleet_force_return']));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind='fleet' AND target_id=? AND token=?", [$fleet->id, $fleet->dispatchToken]));
        // Un retour lui-même bloqué peut être reprogrammé sans consommer deux fois le carburant.
        $this->clock->sleep($fleet->travelSeconds + 1);
        $token = $fleet->dispatchToken;
        self::assertTrue($service->fleet($this->sender, $fleet, 'force_return', 0, 'Retour non traité', hash('sha256',$token), str_repeat('2',32)));
        self::assertSame($fuel, $fleet->reservedFuel);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $token);
        self::assertSame('returning', $fleet->status);
        $this->clock->sleep($fleet->travelSeconds + 1);
        $returnToken = $fleet->dispatchToken;
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $returnToken);
        self::assertSame('completed', $fleet->status);
        self::assertSame([], $fleet->cargo);
        $stock = $this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type', [$this->origin->id]);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id, $returnToken);
        self::assertSame($stock, $this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type', [$this->origin->id]));
    }

    public function testForcedReturnBeforeDeadlineIsRefused(): void
    {
        $fleet = $this->send();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('réservé aux missions dont l’échéance est atteinte');
        self::getContainer()->get(AdminInterventionService::class)->fleet($this->sender,$fleet,'force_return',0,'Essai prématuré',hash('sha256',$fleet->dispatchToken),str_repeat('1',32));
    }

    public function testExpiredNormalRecallStillRequiresExplicitForcedReturn(): void
    {
        $fleet = $this->send();
        $this->clock->sleep($fleet->travelSeconds + 1);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('retour forcé');
        self::getContainer()->get(AdminInterventionService::class)->fleet($this->sender,$fleet,'recall',0,'Essai rappel normal',hash('sha256',$fleet->dispatchToken),str_repeat('1',32));
    }

    public function testForcedReturnCannotBypassPendingCombat(): void
    {
        $fleet = $this->send();
        $event = new \App\Entity\CombatEvent();$event->planet=$this->target;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+1 day');$event->seed=1;
        $participant = new \App\Entity\CombatParticipant();$participant->fleet=$fleet;$participant->playerUniverse=$fleet->playerUniverse;$participant->combatEvent=$event;$participant->role='attacker';$participant->arrivalAt=$this->clock->now();
        $this->em->persist($event);$this->em->persist($participant);$this->em->flush();
        $this->clock->sleep($fleet->travelSeconds + 1);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('combat en cours');
        self::getContainer()->get(AdminInterventionService::class)->fleet($this->sender,$fleet,'force_return',0,'Essai combat',hash('sha256',$fleet->dispatchToken),str_repeat('1',32));
    }

    public function testFleetHealthDetectsOrphanAndLateReturnWithEmptyOutboxReadOnly(): void
    {
        $fleet = $this->send();
        $fleet->dispatchToken = '';
        $fleet->arrivalAt = $this->clock->now()->modify('+1 hour');
        $this->em->flush();
        $db = $this->em->getConnection();
        $db->executeStatement('DELETE FROM outbox_message');
        $operations = self::getContainer()->get(\App\Service\Admin\AdminOperationsService::class);
        $before = $db->fetchAllAssociative('SELECT * FROM fleet ORDER BY id');
        $snapshot = $operations->snapshot();
        self::assertSame(0, $snapshot['outbox_total']);
        self::assertTrue($snapshot['fleet_health']['available']);
        self::assertSame(1, $snapshot['fleet_health']['total']);
        self::assertSame(1, $snapshot['fleet_health']['unscheduled']);
        self::assertSame(0, $snapshot['fleet_health']['overdue']);
        self::assertSame($before, $db->fetchAllAssociative('SELECT * FROM fleet ORDER BY id'));
        self::assertArrayNotHasKey('dispatch_token', $snapshot['fleet_health']['pending'][0]);
        $fleet->dispatchToken = 'scheduled-token';$fleet->status='returning';
        $fleet->arrivalAt=$this->clock->now()->modify('-5 minutes');$this->em->flush();
        self::assertSame(0, $operations->snapshot()['fleet_health']['total']);
        $this->clock->sleep(1);
        self::assertSame(1, $operations->snapshot()['fleet_health']['overdue']);
        $universe=$fleet->playerUniverse->universe;
        $universe->gameSettings['admin_surveillance']['late_minutes']=10;$this->em->flush();
        self::assertSame(0, $operations->snapshot()['fleet_health']['total']);
        $this->clock->sleep(300);
        self::assertSame(1, $operations->snapshot()['fleet_health']['overdue']);
        $fleet->status='completed';$this->em->flush();
        self::assertSame(0, $operations->snapshot()['fleet_health']['total']);
        $fleet->status='attacking';$this->em->flush();
        self::assertSame(0, $operations->snapshot()['fleet_health']['total']);
    }

    public function testFleetHealthLimitsDetailsWithoutTruncatingCounters(): void
    {
        for ($i=0;$i<23;$i++) {
            $fleet=new Fleet();$fleet->playerUniverse=$this->sender->currentPlayerUniverse;$fleet->homePlanet=$this->origin;
            $fleet->status='traveling';$fleet->arrivalAt=$this->clock->now()->modify('-10 minutes');$this->em->persist($fleet);
        }
        $this->em->flush();
        $health=self::getContainer()->get(\App\Service\Admin\AdminOperationsService::class)->snapshot()['fleet_health'];
        self::assertTrue($health['available']);
        self::assertSame(23,$health['total']);
        self::assertSame(23,$health['unscheduled']);
        self::assertSame(23,$health['overdue']);
        self::assertCount(20,$health['pending']);
    }

}
