<?php
namespace App\Tests\Integration;

use App\Entity\{CombatEvent,Fleet,Galaxy,OccupationEvent,Planet,PlanetOccupation,Player,PlayerUniverse,SolarSystem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Combat\OccupationPolicy;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\{SchemaTool,SchemaValidator};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class PlanetOccupationTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private OccupationPolicy $policy;
    private Planet $planet;
    private PlanetOccupation $occupation;
    private Fleet $fleet;
    private CombatEvent $combat;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');$this->policy=new OccupationPolicy($this->clock);
        $universe=new Universe();$universe->slug='occupation';$universe->name='Occupation';
        $galaxy=new Galaxy();$galaxy->universe=$universe;$galaxy->name='Test';
        $system=new SolarSystem();$system->galaxy=$galaxy;$system->name='Test';$system->coordX=0;$system->coordY=0;
        $this->planet=new Planet();$this->planet->solarSystem=$system;$this->planet->position=1;$this->planet->name='PNJ';$this->planet->npcPlanet=true;
        foreach([$universe,$galaxy,$system,$this->planet] as $row) $this->em->persist($row);
        $this->em->flush();$this->fleet=$this->fleet('first');$this->combat=$this->combat();
        $this->occupation=new PlanetOccupation();$this->occupation->planet=$this->planet;
    }

    public function testReservationAndTakeoverKeepHistoryWithoutTransferringPlanet(): void
    {
        $first=$this->begin($this->occupation,$this->combat,$this->fleet);$owner=$this->fleet->playerUniverse;
        $repo=self::getContainer()->get(GameRepository::class);
        self::assertSame(1,$repo->occupationReservations($owner));
        self::assertNull($this->planet->owner);self::assertSame('idle',$this->fleet->status);
        $secondFleet=$this->fleet('second');$secondCombat=$this->combat();$this->clock->sleep(600);
        $second=$this->begin($this->occupation,$secondCombat,$secondFleet);
        self::assertSame(0,$repo->occupationReservations($owner));self::assertSame(1,$repo->occupationReservations($secondFleet->playerUniverse));
        self::assertSame(1,$this->em->getRepository(PlanetOccupation::class)->count([]));
        self::assertSame(2,$this->em->getRepository(OccupationEvent::class)->count([]));
        self::assertSame($owner->id,$first->snapshot['after']['occupant_id']);
        self::assertSame($owner->id,$second->snapshot['before']['occupant_id']);
        self::assertSame(86400,$this->occupation->captureAt->getTimestamp()-$this->clock->now()->getTimestamp());
    }

    public function testOldCombatReplayRollsBackTheAttemptedTakeover(): void
    {
        $this->begin($this->occupation,$this->combat,$this->fleet);
        $second=$this->fleet('second');$this->begin($this->occupation,$this->combat(),$second);
        $connection=$this->em->getConnection();$holder=$second->playerUniverse->id;
        try {
            self::getContainer()->get(GameManager::class)->transaction(function(): void {
                $event=$this->policy->begin($this->occupation,$this->combat,$this->fleet,true,true);
                $this->em->persist($event);
            });
            self::fail('Un combat déjà journalisé ne doit pas reprendre la planète.');
        } catch(UniqueConstraintViolationException) {
            self::assertSame($holder,(int)$connection->fetchOne('SELECT occupant_id FROM planet_occupation WHERE id = ?',[$this->occupation->id]));
            self::assertSame(2,(int)$connection->fetchOne('SELECT COUNT(*) FROM occupation_event'));
        }
    }

    public function testOnlyOneOccupationRecordCanExistForAPlanet(): void
    {
        $this->begin($this->occupation,$this->combat,$this->fleet);
        $duplicate=new PlanetOccupation();$duplicate->planet=$this->planet;
        $event=$this->policy->begin($duplicate,$this->combat(),$this->fleet,true,true);
        $this->em->persist($duplicate);$this->em->persist($event);
        $this->expectException(UniqueConstraintViolationException::class);$this->em->flush();
    }

    public function testFailedOccupationReleasesReservationAndKeepsItsHistory(): void
    {
        $this->begin($this->occupation,$this->combat,$this->fleet);
        $event=$this->policy->finish($this->occupation,'colony_ship_destroyed');$this->em->persist($event);$this->em->flush();
        self::assertSame(0,self::getContainer()->get(GameRepository::class)->occupationReservations($this->fleet->playerUniverse));
        self::assertSame(2,$this->em->getRepository(OccupationEvent::class)->count([]));self::assertNull($this->planet->owner);
    }

    public function testMigrationRoundTripMatchesMappingWithoutChangingExistingPlanet(): void
    {
        $connection=$this->em->getConnection();$before=$connection->fetchAllAssociative('SELECT * FROM planet ORDER BY id');
        $this->runDeploymentMigration('down');
        $connection->executeStatement('DROP TABLE occupation_event');$connection->executeStatement('DROP TABLE planet_occupation');
        $this->runMigration('up');$this->runDeploymentMigration('up');self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
        self::assertSame($before,$connection->fetchAllAssociative('SELECT * FROM planet ORDER BY id'));
        $this->runDeploymentMigration('down');$this->runMigration('down');$this->runMigration('up');$this->runDeploymentMigration('up');
        self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
    }

    public function testMigrationRollbackProtectsOccupationHistory(): void
    {
        $this->begin($this->occupation,$this->combat,$this->fleet);
        $this->expectException(\Doctrine\Migrations\Exception\AbortMigration::class);$this->runMigration('down');
    }

    private function begin(PlanetOccupation $occupation,CombatEvent $combat,Fleet $fleet): OccupationEvent
    {
        $event=$this->policy->begin($occupation,$combat,$fleet,true,true);$this->em->persist($occupation);$this->em->persist($event);$this->em->flush();return $event;
    }
    private function combat(): CombatEvent
    {
        $combat=new CombatEvent();$combat->planet=$this->planet;$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now();$combat->seed=42;
        $this->em->persist($combat);$this->em->flush();return $combat;
    }
    private function fleet(string $name): Fleet
    {
        $player=new Player();$player->email=$name.'@example.test';$player->username=$name;$player->password='unused';
        $member=new PlayerUniverse();$member->player=$player;$member->universe=$this->planet->solarSystem->galaxy->universe;$member->joinedAt=$this->clock->now();
        $fleet=new Fleet();$fleet->playerUniverse=$member;$fleet->homePlanet=$this->planet;$fleet->destination=$this->planet;$fleet->mission='conquer';
        foreach([$player,$member,$fleet] as $row) $this->em->persist($row);$this->em->flush();return $fleet;
    }
    private function runDeploymentMigration(string $direction): void
    {
        require_once dirname(__DIR__,2).'/migrations/Version20261108040000.php';
        $migration=new \DoctrineMigrations\Version20261108040000($this->em->getConnection(),new \Psr\Log\NullLogger());
        $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes());
    }
    private function runMigration(string $direction): void
    {
        require_once dirname(__DIR__,2).'/migrations/Version20261108030000.php';
        $migration=new \DoctrineMigrations\Version20261108030000($this->em->getConnection(),new \Psr\Log\NullLogger());
        $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes());
    }
}
