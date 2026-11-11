<?php
namespace App\Tests\Integration;

use App\Entity\{AllianceContract,CombatEvent,CombatParticipant,EmpireConversion,Fleet,FleetShip,Guild,GuildMember,Planet,Player,PlayerTechnology,PlayerUniverse,QueueItem,SocialMessage,TransportReport,Universe,VacationPeriod};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Fleet\FleetService;
use App\Service\Player\{EmpireActivityService,EmpireConversionService,RegistrationService,RelationResolver,VacationCalendar,VacationPolicy};
use App\Service\Queue\QueueService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class EmpireConversionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PlayerUniverse $old;
    private Planet $planet;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T22:59:59Z');self::getContainer()->set('clock',$this->clock);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->old=$this->membership('former');$this->old->lastActivityAt=$this->clock->now()->modify('-31 days');
        $this->planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->old]);$this->em->flush();
    }

    public function testExactDeadlinePreservationAndIdempotency(): void
    {
        $player=$this->old->player;
        $technology=new PlayerTechnology();$technology->playerUniverse=$this->old;$technology->slug='weapon_calibration';$technology->level=7;
        $this->em->persist($technology);$this->old->lastActivityAt=$this->clock->now()->modify('-30 days');$this->em->flush();
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);
        $this->clock->sleep(1);
        self::assertSame('converted',$this->conversion()->convert($this->old)['status']);
        self::assertNull($this->old->player);self::assertSame($player,$this->old->formerPlayer);
        self::assertNull($player->currentPlayerUniverse);self::assertSame($this->old,$this->planet->owner);
        self::assertSame(7,$technology->level);
        self::assertSame('already_converted',$this->conversion()->convert($this->old)['status']);
        self::assertSame(1,$this->em->getRepository(EmpireConversion::class)->count([]));
        $log=$this->em->getRepository(EmpireConversion::class)->findOneBy([]);
        self::assertEquals($this->clock->now(),$log->dueAt);
        self::assertSame([], $log->effects['returned_fleet_ids']);
    }

    public function testDisabledServiceDoesNotDetachOrJournal(): void
    {
        try { $this->conversion(false)->convert($this->old);self::fail('La conversion doit être désactivée.'); }
        catch(\DomainException $error) { self::assertStringContainsString('désactivées',$error->getMessage()); }
        self::assertTrue($this->old->isPlayerControlled());self::assertSame(0,$this->em->getRepository(EmpireConversion::class)->count([]));
    }

    public function testUnknownActivityAndVacationsAreNotConverted(): void
    {
        $this->old->lastActivityAt=null;$this->em->flush();
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);
        $this->old->lastActivityAt=$this->clock->now()->modify('-40 days');
        $vacation=new VacationPeriod();$vacation->membership=$this->old;$vacation->startedAt=$this->clock->now()->modify('-15 days');
        $vacation->scheduledEndAt=$this->clock->now()->modify('+75 days');$this->em->persist($vacation);$this->em->flush();
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);
        $vacation->endedAt=$this->clock->now();$this->em->flush();
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);
        $this->clock->sleep(5*86400);
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);$this->clock->sleep(1);
        self::assertSame('converted',$this->conversion()->convert($this->old)['status']);
    }

    public function testAccountRestrictionsDoNotCreateAnExemptionFromKnownInactivity(): void
    {
        $this->old->player->lockedAt=$this->clock->now();$this->old->player->emailVerifiedAt=null;$this->em->flush();
        self::assertSame('converted',$this->conversion()->convert($this->old)['status']);
    }

    public function testRecentVisitIsReloadedUnderLockBeforeConversion(): void
    {
        $this->em->getConnection()->executeStatement('UPDATE player_universe SET last_activity_at = ? WHERE id = ?',
            [$this->clock->now()->format('Y-m-d H:i:s'),$this->old->id]);
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);
        self::assertTrue($this->old->isPlayerControlled());
        self::assertEquals($this->clock->now(),$this->old->lastActivityAt);
    }

    public function testOpenCombatElsewhereDefersAllChanges(): void
    {
        $peer=$this->membership('peer');$target=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$peer]);
        $fleet=$this->fleet($this->old,$target,'attacking');
        $event=new CombatEvent();$event->planet=$target;$event->seed=42;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+1 minute');
        $participant=new CombatParticipant();$participant->combatEvent=$event;$participant->fleet=$fleet;$participant->playerUniverse=$this->old;
        $participant->role='attacker';$participant->arrivalAt=$this->clock->now();
        $this->em->persist($event);$this->em->persist($participant);$this->em->flush();
        self::assertSame('deferred_combat',$this->conversion()->convert($this->old)['status']);
        self::assertTrue($this->old->isPlayerControlled());self::assertSame('attacking',$fleet->status);
        self::assertSame(0,$this->em->getRepository(EmpireConversion::class)->count([]));
        $event->status='resolved';$this->em->flush();
        self::assertSame('converted',$this->conversion()->convert($this->old)['status']);self::assertSame('returning',$fleet->status);
    }

    public function testRecallPreservesShipsAndCargoAndIgnoresOldMissionToken(): void
    {
        $peer=$this->membership('peer');$target=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$peer]);
        $fleet=$this->fleet($this->old,$target,'traveling');$oldToken=$fleet->dispatchToken;
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=10;$this->em->persist($ship);
        $guest=$this->fleet($peer,$this->planet,'orbiting');$guest->mission='orbit';$guest->orbitFuel=12;$guest->reservedFuel=32;
        $guest->departureAt=$this->clock->now()->modify('-30 seconds');$guest->arrivalAt=$this->clock->now()->modify('+30 seconds');
        $incoming=$this->fleet($peer,$this->planet,'traveling');$incomingToken=$incoming->dispatchToken;
        $returning=$this->fleet($this->old,$target,'returning');$returnToken=$returning->dispatchToken;
        $this->em->flush();
        $this->conversion()->convert($this->old);
        self::assertSame('returning',$fleet->status);self::assertNotSame($oldToken,$fleet->dispatchToken);
        self::assertSame(37,$fleet->cargo['metal']);self::assertEquals(80,$fleet->cargo['hydrogen']);self::assertEquals(20,$fleet->reservedFuel);
        self::assertSame(10,$ship->quantity);self::assertSame('returning',$guest->status);
        self::assertEquals(94,$guest->cargo['hydrogen']);self::assertEquals(20,$guest->reservedFuel);self::assertEquals(0,$guest->orbitFuel);
        self::assertSame('traveling',$incoming->status);self::assertSame($incomingToken,$incoming->dispatchToken);
        self::assertSame($returnToken,$returning->dispatchToken);
        $service=self::getContainer()->get(FleetService::class);$service->arrive($fleet->id,$oldToken);
        self::assertSame('returning',$fleet->status);
        $this->clock->sleep(60);$token=$fleet->dispatchToken;$service->arrive($fleet->id,$token);
        self::assertSame('completed',$fleet->status);
        $idle=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$this->old,'homePlanet'=>$this->planet,'status'=>'idle']);
        self::assertSame(10,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$idle,'slug'=>'fighter'])->quantity);
        $service->arrive($fleet->id,$token);
        self::assertSame(10,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$idle,'slug'=>'fighter'])->quantity);
    }

    public function testGuildSuccessionUsesOldestActiveMemberAndEndsAlliances(): void
    {
        $inactive=$this->membership('inactive');$inactive->lastActivityAt=$this->clock->now()->modify('-15 days');
        $active=$this->membership('active');$later=$this->membership('later');
        $guild=$this->guild([$this->old,$inactive,$active,$later]);
        $contract=new AllianceContract();$contract->playerA=$this->old;$contract->playerB=$active;$contract->proposer=$this->old;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        $this->conversion()->convert($this->old);
        self::assertSame($active,$guild->leader);self::assertNull($guild->archivedAt);self::assertSame('ended',$contract->status);
        self::assertNull($this->em->getRepository(GuildMember::class)->findOneBy(['playerUniverse'=>$this->old]));
        self::assertSame(3,$this->em->getRepository(GuildMember::class)->count(['guild'=>$guild]));
    }

    public function testGuildArchiveRetainsMessagesAndRosterHistory(): void
    {
        $inactive=$this->membership('inactive');$inactive->lastActivityAt=$this->clock->now()->modify('-15 days');
        $guild=$this->guild([$this->old,$inactive]);
        $message=new SocialMessage();$message->sender=$this->old;$message->guild=$guild;$message->content='Historique conservé';$message->createdAt=$this->clock->now();$this->em->persist($message);$this->em->flush();
        $this->conversion()->convert($this->old);
        self::assertEquals($this->clock->now(),$guild->archivedAt);self::assertSame(0,$this->em->getRepository(GuildMember::class)->count(['guild'=>$guild]));
        self::assertSame(1,$this->em->getRepository(SocialMessage::class)->count(['guild'=>$guild]));
        $log=$this->em->getRepository(EmpireConversion::class)->findOneBy(['membership'=>$this->old]);
        self::assertCount(2,$log->effects['social']['guilds'][0]['roster']);
        self::assertNull(self::getContainer()->get(RelationResolver::class)->guild($inactive));
        self::getContainer()->get(\App\Service\Player\DiplomacyService::class)->create($inactive,'Une nouvelle guilde');
        self::assertNotNull(self::getContainer()->get(RelationResolver::class)->guild($inactive));
    }

    public function testVisitBeforeActualConversionCancelsEvenADelayedConversion(): void
    {
        $activity=$this->activity();
        self::assertTrue($activity->isAwaitingConversion($this->old));$activity->recordVisit($this->old);
        self::assertEquals($this->clock->now(),$this->old->lastActivityAt);$activity->assertCanControl($this->old);
        self::assertSame('not_due',$this->conversion()->convert($this->old)['status']);
    }

    public function testIncomingTransportIsRefusedAfterOwnerDetachment(): void
    {
        $peer=$this->membership('peer');$fleet=$this->fleet($peer,$this->planet,'traveling');$fleet->mission='transport';
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=1;$this->em->persist($ship);
        $transports=self::getContainer()->get(\App\Service\Fleet\TransportService::class);
        $transports->recordDeparture($fleet,['metal'=>37]);$this->em->flush();
        $this->conversion()->convert($this->old);$this->clock->sleep(60);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id,$fleet->dispatchToken);
        self::assertSame('returning',$fleet->status);self::assertSame(37,$fleet->cargo['metal']);
        self::assertSame('refused',$this->em->getRepository(TransportReport::class)->findOneBy(['fleet'=>$fleet])->status);
    }

    public function testRecalledTransportHasAConsistentRefusalReport(): void
    {
        $peer=$this->membership('peer');$target=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$peer]);
        $fleet=$this->fleet($this->old,$target,'traveling');$fleet->mission='transport';
        self::getContainer()->get(\App\Service\Fleet\TransportService::class)->recordDeparture($fleet,['metal'=>37]);$this->em->flush();
        $this->conversion()->convert($this->old);
        $report=$this->em->getRepository(TransportReport::class)->findOneBy(['fleet'=>$fleet]);
        self::assertSame('refused',$report->status);self::assertSame('empire_conversion',$report->snapshot['refusal_reason']);
        self::assertSame(37,$report->snapshot['returned']['metal']);self::assertSame([],$report->snapshot['delivered']);
    }

    public function testFailureRollsBackFleetRecallSocialChangesAndDetachment(): void
    {
        $peer=$this->membership('peer');$target=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$peer]);
        $peer->lastActivityAt=$this->clock->now()->modify('-15 days');$guild=$this->guild([$this->old,$peer]);
        $fleet=$this->fleet($this->old,$target,'traveling');$oldToken=$fleet->dispatchToken;
        $contract=new AllianceContract();$contract->playerA=$this->old;$contract->playerB=$peer;$contract->proposer=$this->old;$contract->status='accepted';
        $this->em->persist($contract);
        $queue=new QueueItem();$queue->planet=$this->planet;$queue->playerUniverse=$this->old;$queue->queueType='research';
        $queue->itemSlug='invalid_for_rollback_test';$queue->durationSeconds=1;$queue->startedAt=$this->clock->now()->modify('-2 seconds');
        $queue->queuedAt=$queue->startedAt;$queue->dispatchToken='rollback-queue';$queue->itemLevel=1;
        $this->em->persist($queue);$this->em->flush();
        $connection=$this->em->getConnection();$before=(int)$connection->fetchOne('SELECT COUNT(*) FROM outbox_message');
        try { $this->conversion()->convert($this->old);self::fail('La commande invalide doit annuler la transaction.'); }
        catch(\DomainException $error) { self::assertStringContainsString('catalogue',$error->getMessage()); }
        self::assertSame('traveling',$connection->fetchOne('SELECT status FROM fleet WHERE id = ?',[$fleet->id]));
        self::assertSame($oldToken,$connection->fetchOne('SELECT dispatch_token FROM fleet WHERE id = ?',[$fleet->id]));
        self::assertSame('accepted',$connection->fetchOne('SELECT status FROM alliance_contract WHERE id = ?',[$contract->id]));
        self::assertNull($connection->fetchOne('SELECT forgotten_at FROM player_universe WHERE id = ?',[$this->old->id]));
        self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM empire_conversion'));
        self::assertSame($before,(int)$connection->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        self::assertNull($connection->fetchOne('SELECT archived_at FROM guild WHERE id = ?',[$guild->id]));
        self::assertSame(2,(int)$connection->fetchOne('SELECT COUNT(*) FROM guild_member WHERE guild_id = ?',[$guild->id]));
    }

    public function testMigrationRoundTripPreservesGuildAndMatchesMapping(): void
    {
        $guild=$this->guild([$this->old]);$connection=$this->em->getConnection();
        $connection->executeStatement('DROP TABLE empire_conversion');$connection->executeStatement('ALTER TABLE guild DROP archived_at');
        $this->runMigration('up');
        self::assertTrue((new \Doctrine\ORM\Tools\SchemaValidator($this->em))->schemaInSyncWithMetadata());
        self::assertSame('Conversion',$connection->fetchOne('SELECT name FROM guild WHERE id = ?',[$guild->id]));
        $this->runMigration('down');$this->runMigration('up');
        self::assertTrue((new \Doctrine\ORM\Tools\SchemaValidator($this->em))->schemaInSyncWithMetadata());
    }

    public function testMigrationRollbackRefusesToDeleteConversionHistory(): void
    {
        $this->conversion()->convert($this->old);
        $this->expectException(\Doctrine\Migrations\Exception\AbortMigration::class);$this->runMigration('down');
    }

    private function runMigration(string $direction): void
    {
        require_once dirname(__DIR__,2).'/migrations/Version20261108020000.php';
        $migration=new \DoctrineMigrations\Version20261108020000($this->em->getConnection(),new \Psr\Log\NullLogger());
        $migration->$direction(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes());
    }

    public function testBatchAndCommandDefaultToPreviewWithoutChangingTheEmpire(): void
    {
        $player=$this->old->player;$activity=$this->old->lastActivityAt;
        $counts=[];foreach(['empire_conversion','outbox_message','notification','queue_item'] as $table) $counts[$table]=$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table);
        $result=$this->batch(false)->run();self::assertTrue($result['preview']);self::assertSame(1,$result['eligible']);
        self::assertSame('eligible',$result['empires'][0]['status']);self::assertSame(0,$result['converted']);
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\EmpireConversionCommand($this->batch(false)));
        self::assertSame(0,$tester->execute([]));self::assertStringContainsString('Prévisualisation sans écriture ni envoi',$tester->getDisplay());
        self::assertSame($player,$this->old->player);self::assertEquals($activity,$this->old->lastActivityAt);self::assertSame($this->old,$this->planet->owner);
        foreach($counts as $table=>$count) self::assertSame($count,$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table));
    }

    public function testDisabledBatchRefusesApplyEvenWhenEligibleOrEmpty(): void
    {
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\EmpireConversionCommand($this->batch(false)));
        self::assertSame(1,$tester->execute(['--apply'=>true]));self::assertStringContainsString('désactivées',$tester->getDisplay());
        self::assertSame(0,$this->em->getRepository(EmpireConversion::class)->count([]));self::assertTrue($this->old->isPlayerControlled());
        $this->old->lastActivityAt=$this->clock->now();$this->em->flush();
        self::assertSame(1,$tester->execute(['--apply'=>true]));self::assertSame(0,$this->em->getRepository(EmpireConversion::class)->count([]));
    }

    public function testBatchCanPassBlockedCombatThenRevisitItWithoutDuplicateConversion(): void
    {
        $event=new CombatEvent();$event->planet=$this->planet;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+10 minutes');$event->seed=42;$this->em->persist($event);
        $second=$this->membership('second');$second->lastActivityAt=$this->clock->now()->modify('-31 days');
        $third=$this->membership('third');$third->lastActivityAt=$this->clock->now()->modify('-32 days');$this->em->flush();
        $first=$this->batch()->run(true,1);self::assertSame(1,$first['deferred']);self::assertSame($this->old->id,$first['last_scanned_id']);self::assertTrue($first['batch_full']);
        $next=$this->batch()->run(true,1,$first['last_scanned_id']);self::assertSame(1,$next['converted']);self::assertTrue($second->isForgotten());
        $last=$this->batch()->run(true,1,$next['last_scanned_id']);self::assertSame(1,$last['converted']);self::assertTrue($third->isForgotten());
        self::assertTrue($this->old->isPlayerControlled());self::assertSame(0,$this->batch()->run(true,1,$last['last_scanned_id'])['eligible']);
        $event->status='resolved';$this->em->flush();self::assertSame(1,$this->batch()->run(true)['converted']);
        self::assertSame(0,$this->batch()->run(true)['converted']);self::assertSame(3,$this->em->getRepository(EmpireConversion::class)->count([]));
    }

    public function testBatchIncludesRestrictedAccountsAndExcludesUnknownDatesAndVacations(): void
    {
        $this->old->player->lockedAt=$this->clock->now();$this->old->player->emailVerifiedAt=null;
        $unknown=$this->membership('unknown');$unknown->lastActivityAt=null;
        $holiday=$this->membership('holiday');$holiday->lastActivityAt=$this->clock->now()->modify('-40 days');
        $period=new VacationPeriod();$period->membership=$holiday;$period->startedAt=$this->clock->now()->modify('-20 days');$period->scheduledEndAt=$this->clock->now()->modify('+70 days');$this->em->persist($period);
        $paused=$this->membership('paused');$paused->lastActivityAt=$this->clock->now()->modify('-40 days');
        $period=new VacationPeriod();$period->membership=$paused;$period->startedAt=$this->clock->now()->modify('-20 days');$period->scheduledEndAt=$this->clock->now()->modify('+70 days');$period->endedAt=$this->clock->now()->modify('-5 days');$this->em->persist($period);$this->em->flush();
        $result=$this->batch()->run(true,1);self::assertSame(1,$result['converted']);self::assertTrue($this->old->isForgotten());
        self::assertTrue($unknown->isPlayerControlled());self::assertTrue($holiday->isPlayerControlled());self::assertTrue($paused->isPlayerControlled());
        self::assertSame(0,$this->batch()->run(true)['eligible']);
    }

    public function testBatchCommandRequiresExplicitApplyAndReportsItsResults(): void
    {
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\EmpireConversionCommand($this->batch()));
        self::assertSame(0,$tester->execute([]));self::assertTrue($this->old->isPlayerControlled());
        self::assertSame(0,$tester->execute(['--apply'=>true,'--limit'=>'1']));self::assertTrue($this->old->isForgotten());
        self::assertStringContainsString('convertis : 1',$tester->getDisplay());self::assertStringContainsString('--after-id='.$this->old->id,$tester->getDisplay());
        self::assertSame(0,$tester->execute(['--apply'=>true]));self::assertStringContainsString('convertis : 0',$tester->getDisplay());
        self::assertSame(1,$this->em->getRepository(EmpireConversion::class)->count([]));
    }

    public function testBatchCommandRejectsInvalidPaginationBeforeWriting(): void
    {
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\EmpireConversionCommand($this->batch()));
        foreach([['--limit'=>'0'],['--limit'=>'501'],['--limit'=>'no'],['--after-id'=>'-1'],['--after-id'=>'no']] as $options) self::assertSame(2,$tester->execute($options+['--apply'=>true]));
        self::assertTrue($this->old->isPlayerControlled());self::assertSame(0,$this->em->getRepository(EmpireConversion::class)->count([]));
    }

    public function testScheduledFullPassDoesNotGetStuckBehindAFullBatchOfOpenCombats(): void
    {
        $event=new CombatEvent();$event->planet=$this->planet;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+10 minutes');$event->seed=42;$this->em->persist($event);
        $later=$this->membership('later');$later->lastActivityAt=$this->clock->now()->modify('-31 days');$this->em->flush();
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\EmpireConversionCommand($this->batch()));
        self::assertSame(0,$tester->execute(['--all'=>true,'--limit'=>'1']));
        self::assertTrue($this->old->isPlayerControlled());self::assertTrue($later->isPlayerControlled());
        self::assertSame(0,$tester->execute(['--apply'=>true,'--all'=>true,'--limit'=>'1']));
        self::assertTrue($this->old->isPlayerControlled());self::assertTrue($later->isForgotten());
        self::assertStringContainsString('Parcours terminé : 1 conversion(s), 1 report(s)',$tester->getDisplay());
        self::assertStringContainsString('Repartir sans curseur',$tester->getDisplay());
        self::assertSame(0,$tester->execute(['--apply'=>true,'--all'=>true,'--limit'=>'1']));
        self::assertSame(1,$this->em->getRepository(EmpireConversion::class)->count([]));
    }

    public function testBatchStopsAfterRollbackAndKeepsPreviousConversionsAndResumeCursor(): void
    {
        $bad=$this->membership('bad');$bad->lastActivityAt=$this->clock->now()->modify('-31 days');
        $later=$this->membership('later');$later->lastActivityAt=$this->clock->now()->modify('-32 days');
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$bad]);
        $queue=new QueueItem();$queue->playerUniverse=$bad;$queue->planet=$planet;$queue->queueType='building';$queue->itemSlug='invalid_building';$queue->itemLevel=2;
        $queue->queuedAt=$this->clock->now()->modify('-10 minutes');$queue->startedAt=$queue->queuedAt;$queue->durationSeconds=1;$queue->dispatchToken='bad-order';
        $this->em->persist($queue);$this->em->flush();$oldId=$this->old->id;$badId=$bad->id;$laterId=$later->id;$queueId=$queue->id;
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\EmpireConversionCommand($this->batch()));
        self::assertSame(1,$tester->execute(['--apply'=>true]));
        self::assertStringContainsString('--after-id='.$oldId,$tester->getDisplay());self::assertStringContainsString('convertis : 1',$tester->getDisplay());
        $connection=$this->em->getConnection();self::assertSame(1,(int)$connection->fetchOne('SELECT COUNT(*) FROM empire_conversion'));
        self::assertNull($connection->fetchOne('SELECT player_id FROM player_universe WHERE id = ?',[$oldId]));
        self::assertNotNull($connection->fetchOne('SELECT player_id FROM player_universe WHERE id = ?',[$badId]));
        self::assertNotNull($connection->fetchOne('SELECT player_id FROM player_universe WHERE id = ?',[$laterId]));
        self::assertSame(1,(int)$connection->fetchOne('SELECT COUNT(*) FROM queue_item WHERE id = ?',[$queueId]));
        self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM planet_building WHERE slug = ?', ['invalid_building']));
    }

    private function batch(bool $enabled=true): \App\Service\Player\EmpireConversionBatch
    {
        return new \App\Service\Player\EmpireConversionBatch(self::getContainer()->get(\App\Service\Player\ForgottenEmpirePreviewService::class),$this->conversion(),
            self::getContainer()->get(GameRepository::class),new \Psr\Log\NullLogger(),$enabled);
    }

    private function activity(): EmpireActivityService
    {
        return new EmpireActivityService(self::getContainer()->get(GameManager::class),$this->clock,self::getContainer()->get(VacationCalendar::class),self::getContainer()->get(VacationPolicy::class),true);
    }
    private function conversion(bool $enabled=true): EmpireConversionService
    {
        return new EmpireConversionService(self::getContainer()->get(GameManager::class),self::getContainer()->get(GameRepository::class),self::getContainer()->get(ActionGuard::class),$this->activity(),self::getContainer()->get(FleetService::class),self::getContainer()->get(QueueService::class),$this->clock,self::getContainer()->get(\App\Service\Combat\OccupationService::class),$enabled,self::getContainer()->get(\App\Service\Player\EmpireReconstructionScheduler::class));
    }
    private function membership(string $name): PlayerUniverse
    {
        $data=new RegistrationData();$data->email=$name.'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);$player->emailVerifiedAt=$this->clock->now();$this->em->flush();
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player->currentPlayerUniverse;
    }
    private function fleet(PlayerUniverse $membership,Planet $target,string $status): Fleet
    {
        $fleet=new Fleet();$fleet->playerUniverse=$membership;$fleet->homePlanet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$membership]);
        $fleet->destination=$target;$fleet->status=$status;$fleet->mission='attack';$fleet->travelSeconds=60;$fleet->reservedFuel=40;
        $fleet->cargo=['metal'=>37,'hydrogen'=>100];$fleet->departureAt=$this->clock->now();$fleet->arrivalAt=$this->clock->now()->modify('+60 seconds');
        $fleet->dispatchToken='token-'.bin2hex(random_bytes(8));$this->em->persist($fleet);$this->em->flush();return $fleet;
    }
    private function guild(array $memberships): Guild
    {
        $guild=new Guild();$guild->name='Conversion';$guild->universe=$this->old->universe;$guild->leader=$this->old;$this->em->persist($guild);
        foreach($memberships as $membership) { $row=new GuildMember();$row->guild=$guild;$row->playerUniverse=$membership;$this->em->persist($row); }
        $this->em->flush();return $guild;
    }
}
