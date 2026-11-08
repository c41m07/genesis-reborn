<?php
namespace App\Tests\Integration;

use App\Entity\{AllianceContract, BoostEventContribution, Fleet, FleetShip, Planet, PlanetResource, Player, PlayerDiscovery, TransportReport, Universe};
use App\Service\Fleet\{FleetService, TransportService};
use App\Service\Admin\{AdminCaseService, AdminService, AdminSurveillanceService};
use App\Service\Colony\ResourceService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class AdminCaseTest extends WebTestCase
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

    public function testDepartureTraceKeepsOriginalShipsAndResources(): void
    {
        $fleet=$this->send();
        $trace=$this->em->getRepository(\App\Entity\FleetMissionTrace::class)->findOneBy(['fleet'=>$fleet]);
        self::assertNotNull($trace);
        self::assertTrue($trace->targetWasPlayer);
        self::assertSame(1,$trace->snapshot['ships'][0]['quantity']);
        self::assertEquals(123,$trace->snapshot['freight']['metal']);
        $fleet->cargo=[];$fleet->departureAt=$this->clock->now()->modify('+1 hour');$this->em->flush();
        $this->em->refresh($trace);
        self::assertEquals(123,$trace->snapshot['freight']['metal']);
        self::assertNotEquals($fleet->departureAt,$trace->departedAt);
    }

    private function trace(string $mission,bool $player=true,?\DateTimeImmutable $at=null,bool $self=false): void
    {
        $fleet=new Fleet();$fleet->playerUniverse=$this->sender->currentPlayerUniverse;$fleet->homePlanet=$this->origin;$fleet->destination=$this->target;$fleet->status='completed';$fleet->mission=$mission;
        $this->em->persist($fleet);
        $trace=new \App\Entity\FleetMissionTrace();$trace->fleet=$fleet;$trace->universe=$fleet->playerUniverse->universe;$trace->sender=$fleet->playerUniverse;
        $trace->recipient=$self ? $trace->sender : $this->recipient->currentPlayerUniverse;$trace->targetWasPlayer=$player;$trace->mission=$mission;$trace->departedAt=$at ?? $this->clock->now();
        $this->em->persist($trace);$this->em->flush();
    }

    public function testAlertsRespectThresholdsWindowNpcAndDistinctPlayers(): void
    {
        $service=self::getContainer()->get(AdminSurveillanceService::class);$universe=$this->sender->currentPlayerUniverse->universe;
        for($i=0;$i<4;$i++) $this->trace('transport');
        for($i=0;$i<9;$i++) $this->trace('attack');
        $this->trace('attack',false);$this->trace('transport',true,null,true);$this->trace('transport',true,$this->clock->now()->modify('-25 hours'));
        self::assertSame([],$service->alerts($universe));
        $this->trace('transport');$this->trace('attack');
        $alerts=$service->alerts($universe);
        self::assertCount(2,$alerts);
        self::assertStringStartsWith('5 transports',$alerts[0]['title']);
        self::assertStringStartsWith('10 attaques',$alerts[1]['title']);
        $this->clock->sleep(24*3600+1);
        self::assertSame([],$service->alerts($universe));
    }

    public function testLateMissionRequiresStrictlyMoreThanFiveMinutes(): void
    {
        $fleet=$this->send();$fleet->arrivalAt=$this->clock->now()->modify('-5 minutes');$this->em->flush();
        $service=self::getContainer()->get(AdminSurveillanceService::class);$universe=$fleet->playerUniverse->universe;
        self::assertSame([],$service->alerts($universe));
        $this->clock->sleep(1);
        $alerts=$service->alerts($universe);self::assertCount(1,$alerts);self::assertSame('late:'.$fleet->id,$alerts[0]['key']);
    }

    public function testCaseAndNotesAreIdempotentAndPreserveAuthorAndHistory(): void
    {
        $service=self::getContainer()->get(AdminCaseService::class);$membership=$this->recipient->currentPlayerUniverse;
        $case=$service->create($this->sender,'Vérification','Un signal à examiner',$membership->universe->id,$membership->id,0,'',str_repeat('b',32));
        self::assertSame($case->id,$service->create($this->sender,'Vérification','Un signal à examiner',$membership->universe->id,$membership->id,0,'',str_repeat('b',32))->id);
        self::assertTrue($service->update($this->sender,$case,'investigating','Première observation',str_repeat('c',32),'open'));
        self::assertFalse($service->update($this->sender,$case,'investigating','Première observation',str_repeat('c',32),'open'));
        self::assertTrue($service->update($this->sender,$case,'closed','Contrôle terminé',str_repeat('d',32),'investigating'));
        $notes=$this->em->getRepository(\App\Entity\AdminCaseNote::class)->findBy(['caseFile'=>$case],['id'=>'ASC']);
        self::assertCount(2,$notes);self::assertSame('Première observation',$notes[0]->body);self::assertSame($this->sender->id,$notes[0]->author->id);
        self::assertSame('closed',$case->status);self::assertSame(0,$service->activeCount());
    }

    public function testStaleCaseStatusCannotBeOverwrittenByAnotherForm(): void
    {
        $service=self::getContainer()->get(AdminCaseService::class);
        $case=$service->create($this->sender,'Suivi','',0,0,0,'',str_repeat('b',32));
        $service->update($this->sender,$case,'closed','Résolu',str_repeat('c',32),'open');
        $this->expectException(\DomainException::class);
        $service->update($this->sender,$case,'open','Note sur une ancienne fiche',str_repeat('d',32),'open');
    }

    public function testThresholdSettingsSurviveGeneralUniverseSettings(): void
    {
        $universe=$this->sender->currentPlayerUniverse->universe;
        self::getContainer()->get(AdminCaseService::class)->saveThresholds($this->sender,$universe,['late_minutes'=>7,'transport_count'=>8,'attack_count'=>12]);
        self::getContainer()->get(AdminService::class)->setUniverseSettings($this->sender,$universe->id,['speed_profile'=>'custom','multipliers'=>['building_time'=>2,'research_time'=>2,'ship_build_time'=>2,'resource_production'=>2,'travel_time'=>2],'limits'=>['max_planets'=>5,'max_fleet_size'=>1000],'features'=>[]]);
        self::assertSame(['late_minutes'=>7,'transport_count'=>8,'attack_count'=>12],$universe->gameSettings['admin_surveillance']);
    }

    public function testNonAdminCannotCreateCase(): void
    {
        $this->expectException(\DomainException::class);
        self::getContainer()->get(AdminCaseService::class)->create($this->recipient,'Tentative','',0,0,0,'',str_repeat('b',32));
    }

    public function testPreparedMigrationMatchesDoctrine(): void
    {
        $schema=new SchemaTool($this->em);
        $schema->dropSchema(array_map(fn(string $class)=>$this->em->getClassMetadata($class),[\App\Entity\AdminCaseNote::class,\App\Entity\AdminCase::class,\App\Entity\FleetMissionTrace::class]));
        require_once dirname(__DIR__,2).'/migrations/Version20261109020000.php';
        $migration=new \DoctrineMigrations\Version20261109020000($this->em->getConnection(),new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement());
        self::assertSame([],$schema->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
    }
}
