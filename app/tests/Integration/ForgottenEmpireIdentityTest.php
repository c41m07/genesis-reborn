<?php
namespace App\Tests\Integration;

use App\Entity\{Fleet,FleetShip,Planet,Player,PlayerTechnology,PlayerUniverse,QueueItem,SocialMessage,Universe};
use App\Manager\GameManager;
use App\Service\Colony\{ActionGuard,GameShell};
use App\Service\Player\{EmpireActivityService,ForgottenEmpirePreviewService,LeaderboardService,RegistrationService};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\{SchemaTool,SchemaValidator};
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class ForgottenEmpireIdentityTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private PlayerUniverse $old;
    private Planet $planet;

    protected function setUp(): void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropDatabase();$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');
        static::getContainer()->set('clock',$this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        $this->player=$this->register('former');
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($this->player,$universe->id);
        $this->old=$this->player->currentPlayerUniverse;
        $this->planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->old]);
    }

    public function testRestartPreservesOldIdentityAndDoesNotInheritResearchOrUnlocks(): void
    {
        $technology=new PlayerTechnology();$technology->playerUniverse=$this->old;
        $technology->slug='weapon_calibration';$technology->level=12;
        $fleet=new Fleet();$fleet->playerUniverse=$this->old;$fleet->homePlanet=$this->planet;
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=42;
        $this->old->catalogUnlocks=['technologies'=>['weapon_calibration'=>['superior'=>true]]];
        foreach([$technology,$fleet,$ship] as $row) $this->em->persist($row);
        $this->em->flush();
        $oldId=$this->old->id;
        $activityAt=$this->old->lastActivityAt;
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        self::assertNull($this->old->player);
        self::assertSame($this->player,$this->old->formerPlayer);
        self::assertNull($this->player->currentPlayerUniverse);
        static::getContainer()->get(UniverseService::class)->select($this->player,$this->old->universe->id);
        $new=$this->player->currentPlayerUniverse;
        self::assertNotSame($oldId,$new->id);
        self::assertSame($this->player,$new->player);
        self::assertSame([],$new->catalogUnlocks);
        self::assertSame(0,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$new]));
        $this->em->refresh($this->planet);$this->em->refresh($ship);$this->em->refresh($technology);
        self::assertSame($oldId,$this->planet->owner->id);
        self::assertSame(42,$ship->quantity);self::assertSame(12,$technology->level);
        self::assertEquals($activityAt,$this->old->lastActivityAt);
        $id=$new->id;
        static::getContainer()->get(UniverseService::class)->select($this->player,$new->universe->id);
        self::assertSame($id,$this->player->currentPlayerUniverse->id);
        $this->client->loginUser($this->player);
        $this->client->request('GET','/planetes/'.$this->planet->id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testSeveralForgottenEmpiresCanCoexistWithoutDuplicatingActiveMembership(): void
    {
        $universeId=$this->old->universe->id;
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        $service=static::getContainer()->get(UniverseService::class);
        $service->select($this->player,$universeId);
        $second=$this->player->currentPlayerUniverse;
        $second->detachPlayer($this->clock->now());$this->em->flush();
        $service->select($this->player,$universeId);
        self::assertSame(2,$this->em->getRepository(PlayerUniverse::class)->count(['formerPlayer'=>$this->player]));
        self::assertSame(1,$this->em->getRepository(PlayerUniverse::class)->count(['player'=>$this->player]));
        $duplicate=new PlayerUniverse();$duplicate->player=$this->player;$duplicate->universe=$this->old->universe;
        $duplicate->joinedAt=$this->clock->now();$this->em->persist($duplicate);
        $this->expectException(UniqueConstraintViolationException::class);
        $this->em->flush();
    }

    public function testStaleSelectionCannotReadOrCommandForgottenEmpire(): void
    {
        $this->old->detachPlayer($this->clock->now());
        $this->player->currentPlayerUniverse=$this->old;
        $this->em->flush();$this->client->loginUser($this->player);
        $this->client->request('GET','/planetes/'.$this->planet->id);
        self::assertResponseRedirects('/univers');
        $this->client->request('POST','/planetes/'.$this->planet->id.'/construire',[]);
        self::assertResponseRedirects('/univers');
        $this->client->request('GET','/profil/preferences');
        self::assertResponseIsSuccessful();self::assertSelectorNotExists('#vacances');
        self::assertNull(static::getContainer()->get(GameShell::class)->get($this->player)['membership']);
    }

    public function testDirectActionGuardRejectsFormerOwner(): void
    {
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        $this->expectException(\DomainException::class);
        static::getContainer()->get(GameManager::class)->transaction(fn()=>static::getContainer()->get(ActionGuard::class)->lockMembership($this->old));
    }

    public function testForgottenIdentityDoesNotResetActivityOrAppearInPlayerRankingAndPreview(): void
    {
        $this->old->lastActivityAt=$this->clock->now()->modify('-31 days');
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        $at=$this->old->lastActivityAt;
        static::getContainer()->get(EmpireActivityService::class)->recordVisit($this->old);
        self::assertEquals($at,$this->old->lastActivityAt);
        self::assertSame('forgotten',static::getContainer()->get(EmpireActivityService::class)->snapshot($this->old)['status']);
        self::assertSame([],static::getContainer()->get(ForgottenEmpirePreviewService::class)->preview()['empires']);
        self::assertSame([],static::getContainer()->get(LeaderboardService::class)->forUniverse($this->old));
    }

    public function testHistoricalMessagesStillRenderAfterDetachment(): void
    {
        $peer=$this->register('peer');
        static::getContainer()->get(UniverseService::class)->select($peer,$this->old->universe->id);
        $message=new SocialMessage();$message->sender=$this->old;$message->recipient=$peer->currentPlayerUniverse;
        $message->content='Un ancien échange conservé.';$message->createdAt=$this->clock->now();
        $this->em->persist($message);$this->old->detachPlayer($this->clock->now());$this->em->flush();
        $this->client->loginUser($peer);
        $this->client->request('GET','/messages?with='.$this->old->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.message-thread','Un ancien échange conservé.');
        self::assertSelectorTextContains('.message-pane-heading','empire oublié');
        self::assertSelectorNotExists('.message-compose');
    }

    public function testSchemaRoundTripAndIdempotentDetachment(): void
    {
        self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
        $at=$this->clock->now();$this->old->detachPlayer($at);$this->em->flush();
        $this->clock->sleep(60);$this->old->detachPlayer($this->clock->now());$this->em->flush();
        self::assertEquals($at,$this->old->forgottenAt);
        self::assertSame($this->player,$this->old->formerPlayer);
    }

    public function testPaidResearchFinishesWithoutRestoringPlayerControl(): void
    {
        $queue=new QueueItem();$queue->planet=$this->planet;$queue->playerUniverse=$this->old;
        $queue->queueType='research';$queue->itemSlug='weapon_calibration';$queue->itemLevel=1;
        $queue->durationSeconds=60;$queue->startedAt=$this->clock->now();$queue->queuedAt=$queue->startedAt;
        $queue->dispatchToken='forgotten-paid-research';$queue->paidCosts=['metal'=>100];
        $this->planet->researchQueueStartedAt=$queue->startedAt;
        $this->em->persist($queue);$this->old->detachPlayer($this->clock->now());$this->em->flush();
        $id=$queue->id;$this->clock->sleep(60);
        static::getContainer()->get(\App\Service\Queue\QueueService::class)->complete($id,$queue->dispatchToken);
        self::assertNull($this->em->find(QueueItem::class,$id));
        self::assertSame(1,$this->em->getRepository(PlayerTechnology::class)->findOneBy(['playerUniverse'=>$this->old,'slug'=>'weapon_calibration'])->level);
        self::assertFalse($this->old->isPlayerControlled());self::assertNull($this->old->player);
    }

    public function testFormerPlayerDoesNotGainAccessWhenAnotherUniverseIsSelected(): void
    {
        $other=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);
        static::getContainer()->get(UniverseService::class)->select($this->player,$other->id);
        $selected=$this->player->currentPlayerUniverse;
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        self::assertSame($selected,$this->player->currentPlayerUniverse);
        $this->client->loginUser($this->player);
        $this->client->request('GET','/planetes/'.$this->planet->id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testDetachedEmpireCannotReceiveTransportOrNewPrivateMessages(): void
    {
        $peer=$this->register('peer');
        static::getContainer()->get(UniverseService::class)->select($peer,$this->old->universe->id);
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        self::assertFalse(static::getContainer()->get(\App\Service\Fleet\TransportService::class)->canReceive($peer->currentPlayerUniverse,$this->planet));
        $this->expectException(\DomainException::class);
        static::getContainer()->get(\App\Service\Player\SocialMessageService::class)->sendPrivate($peer->currentPlayerUniverse,$this->old->id,'Un nouvel échange refusé.');
    }

    public function testMigrationPreservesExistingRowsAndMatchesEntityMapping(): void
    {
        $connection=$this->em->getConnection();
        $table=$connection->createSchemaManager()->introspectTable('player_universe');
        foreach($table->getForeignKeys() as $foreignKey) {
            if($foreignKey->getLocalColumns()===['former_player_id']) {
                $connection->executeStatement('ALTER TABLE player_universe DROP FOREIGN KEY '.$connection->getDatabasePlatform()->quoteSingleIdentifier($foreignKey->getName()));
            }
        }
        $connection->executeStatement('ALTER TABLE player_universe DROP INDEX membership_former_player_idx, DROP former_player_id, DROP forgotten_at, MODIFY player_id INT NOT NULL');
        $before=$connection->fetchAllAssociative('SELECT id, player_id, universe_id, joined_at, last_activity_at FROM player_universe ORDER BY id');
        $this->runMigration('up');
        self::assertSame($before,$connection->fetchAllAssociative('SELECT id, player_id, universe_id, joined_at, last_activity_at FROM player_universe ORDER BY id'));
        self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
        $this->runMigration('down');
        self::assertSame($before,$connection->fetchAllAssociative('SELECT id, player_id, universe_id, joined_at, last_activity_at FROM player_universe ORDER BY id'));
        $this->runMigration('up');
        self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
    }

    public function testMigrationRollbackRefusesToLoseForgottenHistory(): void
    {
        $this->old->detachPlayer($this->clock->now());$this->em->flush();
        $this->expectException(\Doctrine\Migrations\Exception\AbortMigration::class);
        $this->runMigration('down');
    }

    private function runMigration(string $direction): void
    {
        require_once dirname(__DIR__,2).'/migrations/Version20261108010000.php';
        $migration=new \DoctrineMigrations\Version20261108010000($this->em->getConnection(),new \Psr\Log\NullLogger());
        $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes());
    }

    private function register(string $name): Player
    {
        $data=new RegistrationData();$data->email=$name.'@example.test';$data->username=$name;
        $data->plainPassword='A-test-password-2026';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);
        $player->emailVerifiedAt=$this->clock->now();$this->em->flush();
        return $player;
    }
}
