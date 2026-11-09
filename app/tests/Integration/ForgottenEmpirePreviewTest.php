<?php
namespace App\Tests\Integration;

use App\Command\ForgottenEmpirePreviewCommand;
use App\Entity\{AllianceContract,CombatEvent,CombatParticipant,Fleet,Galaxy,Guild,GuildMember,Planet,Player,PlayerTechnology,PlayerUniverse,QueueItem,SolarSystem,Universe,VacationPeriod};
use App\Service\Player\ForgottenEmpirePreviewService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ForgottenEmpirePreviewTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);
        $metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-07T23:00:00Z');
        self::getContainer()->set('clock',$this->clock);
    }

    public function testExactThresholdUnknownActivityAndInaccessibleUniverses(): void
    {
        $early=$this->membership('early','-30 days +1 second');
        $due=$this->membership('due','-30 days');
        $this->membership('unknown',null);
        $closed=$this->membership('closed','-50 days');
        $closed->universe->status='closed';
        $future=$this->membership('future','-50 days');
        $future->universe->startsAt=$this->clock->now()->modify('+1 second');
        $ended=$this->membership('ended','-50 days');
        $ended->universe->endsAt=$this->clock->now();
        $this->em->flush();
        self::assertSame([$due->id],$this->ids());
        $this->clock->sleep(1);
        self::assertSame([$due->id,$future->id],$this->ids());
        $this->clock->sleep(86399);
        self::assertSame([$early->id,$due->id,$future->id],$this->ids());
    }

    public function testVacationFilteringHappensBeforeBatchLimitAndHonorsEffectiveEnd(): void
    {
        $active=$this->membership('holiday','-50 days');
        $this->vacation($active,'-10 days',null);
        $paused=$this->membership('paused','-40 days');
        $this->vacation($paused,'-20 days','-5 days');
        $due=$this->membership('due','-40 days');
        $this->vacation($due,'-20 days','-10 days');
        $result=$this->service()->preview(1);
        self::assertSame([$due->id],array_column($result['empires'],'membership_id'));
        self::assertSame($this->clock->now()->format(DATE_ATOM),$result['empires'][0]['due_at']);
        self::assertTrue($result['batch_full']);
    }

    public function testExpiredVacationIsCappedAndMultiplePeriodsAreSubtracted(): void
    {
        $expired=$this->membership('expired','-121 days');
        $this->vacation($expired,'-120 days','-1 day');
        $multiple=$this->membership('multiple','-40 days');
        $this->vacation($multiple,'-30 days','-25 days');
        $this->vacation($multiple,'-10 days','-5 days');
        self::assertSame([$expired->id,$multiple->id],$this->ids());
    }

    public function testParisMidnightFilteringHappensBeforeBatchLimit(): void
    {
        $this->clock->sleep(12*3600);
        $this->membership('later_today','-30 days');$due=$this->membership('already_due','-31 days');
        self::assertSame([$due->id],array_column($this->service()->preview(1)['empires'],'membership_id'));
    }

    public function testPaginationCanPassEmpiresWithPendingOperations(): void
    {
        $first=$this->membership('first','-31 days');
        $second=$this->membership('second','-32 days');
        $page=$this->service()->preview(1);
        self::assertSame([$first->id],array_column($page['empires'],'membership_id'));
        $next=$this->service()->preview(1,$page['last_scanned_id']);
        self::assertSame([$second->id],array_column($next['empires'],'membership_id'));
        self::assertSame([],$this->service()->preview(1,$next['last_scanned_id'])['empires']);
    }

    public function testInventoryIncludesForeignCombatAndDoesNotWrite(): void
    {
        $membership=$this->membership('inventory','-31 days');
        $foreign=$this->membership('foreign','-1 day');
        $foreign->universe=$membership->universe;
        $this->em->flush();
        $planet=$this->planet($membership);
        $otherPlanet=$this->planet($foreign);
        $fleet=new Fleet();
        $fleet->playerUniverse=$membership;
        $fleet->homePlanet=$planet;
        $fleet->destination=$otherPlanet;
        $fleet->mission='attack';
        $fleet->status='attacking';
        $fleet->dispatchToken='preserve-this-token';
        $fleet->cargo=['metal'=>1234];
        $event=new CombatEvent();
        $event->planet=$otherPlanet;
        $event->startedAt=$this->clock->now();
        $event->resolveAt=$this->clock->now()->modify('+1 minute');
        $event->seed=42;
        $participant=new CombatParticipant();
        $participant->combatEvent=$event;
        $participant->fleet=$fleet;
        $participant->playerUniverse=$membership;
        $participant->role='attacker';
        $participant->arrivalAt=$this->clock->now();
        $queue=new QueueItem();
        $queue->playerUniverse=$membership;
        $queue->planet=$planet;
        $queue->queueType='research';
        $queue->itemSlug='weapon_calibration';
        $queue->queuedAt=$this->clock->now();
        $queue->durationSeconds=600;
        $queue->dispatchToken='preserve-queue-token';
        $technology=new PlayerTechnology();
        $technology->playerUniverse=$membership;
        $technology->slug='weapon_calibration';
        $technology->level=12;
        $guest=new Fleet();$guest->playerUniverse=$foreign;$guest->homePlanet=$otherPlanet;
        $guest->destination=$planet;$guest->status='orbiting';$guest->mission='orbit';
        $alliance=new AllianceContract();$alliance->playerA=$membership;$alliance->playerB=$foreign;
        $alliance->proposer=$membership;$alliance->status='accepted';
        $guild=new Guild();$guild->universe=$membership->universe;$guild->name='Test guild';$guild->leader=$membership;
        $guildMember=new GuildMember();$guildMember->guild=$guild;$guildMember->playerUniverse=$membership;
        foreach([$fleet,$event,$participant,$queue,$technology,$guest,$alliance,$guild,$guildMember] as $entity) $this->em->persist($entity);
        $this->em->flush();
        $before=$this->databaseSnapshot();
        $empire=$this->service()->preview()['empires'][0];
        self::assertSame(1,$empire['inventory']['colonies']);
        self::assertSame(1,$empire['inventory']['deployed_fleets']);
        self::assertSame(1,$empire['inventory']['open_combats']);
        self::assertSame(1,$empire['inventory']['queued_orders']);
        self::assertSame(1,$empire['inventory']['technologies']);
        self::assertSame(1,$empire['inventory']['incoming_or_stationed_fleets']);
        self::assertSame(1,$empire['inventory']['alliances']);
        self::assertSame(1,$empire['inventory']['guild_memberships']);
        self::assertSame(1,$empire['inventory']['led_guilds']);
        self::assertContains('open_combats',$empire['attention']);
        $tester=new CommandTester(self::getContainer()->get(ForgottenEmpirePreviewCommand::class));
        self::assertSame(Command::SUCCESS,$tester->execute([]));
        self::assertStringContainsString('sans écriture ni envoi',$tester->getDisplay());
        self::assertSame($before,$this->databaseSnapshot());
        self::assertStringNotContainsString($membership->player->email,$tester->getDisplay());
    }

    public function testAccountRestrictionsAreReportedRatherThanSilentlyIgnored(): void
    {
        $unverified=$this->membership('unverified','-31 days');
        $unverified->player->emailVerifiedAt=null;
        $locked=$this->membership('locked','-31 days');
        $locked->player->lockedAt=$this->clock->now();
        $this->em->flush();
        $empires=$this->service()->preview()['empires'];
        self::assertContains('unverified_account',$empires[0]['attention']);
        self::assertContains('locked_account',$empires[1]['attention']);
    }

    public function testCommandRejectsInvalidOptionsAndCannotApplyChanges(): void
    {
        $tester=new CommandTester(self::getContainer()->get(ForgottenEmpirePreviewCommand::class));
        foreach([['--limit'=>'0'],['--limit'=>'501'],['--limit'=>'abc'],['--after-id'=>'-1']] as $options) {
            self::assertSame(Command::INVALID,$tester->execute($options));
        }
        self::assertFalse(self::getContainer()->get(ForgottenEmpirePreviewCommand::class)->getDefinition()->hasOption('apply'));
    }

    private function service(): ForgottenEmpirePreviewService { return self::getContainer()->get(ForgottenEmpirePreviewService::class); }
    private function ids(): array { return array_column($this->service()->preview()['empires'],'membership_id'); }

    private function databaseSnapshot(): array
    {
        $data=[];
        foreach(['player','player_universe','planet','fleet','queue_item','player_technology','combat_event','combat_participant','alliance_contract','guild','guild_member','outbox_message','empire_inactivity_warning'] as $table) {
            $data[$table]=$this->em->getConnection()->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id');
        }
        return $data;
    }

    private function membership(string $slug,?string $lastActivity): PlayerUniverse
    {
        $player=new Player();
        $player->email=$slug.'@example.test';$player->username=$slug;$player->password='unused';
        $player->emailVerifiedAt=$this->clock->now();
        $universe=new Universe();$universe->slug=$slug;$universe->name=$slug;
        $membership=new PlayerUniverse();$membership->player=$player;$membership->universe=$universe;
        $membership->joinedAt=$this->clock->now()->modify('-200 days');
        $membership->lastActivityAt=$lastActivity!==null?$this->clock->now()->modify($lastActivity):null;
        foreach([$player,$universe,$membership] as $entity) $this->em->persist($entity);
        $this->em->flush();
        return $membership;
    }

    private function vacation(PlayerUniverse $membership,string $start,?string $end): void
    {
        $period=new VacationPeriod();$period->membership=$membership;
        $period->startedAt=$this->clock->now()->modify($start);
        $period->scheduledEndAt=$period->startedAt->modify('+90 days');
        $period->endedAt=$end!==null?$this->clock->now()->modify($end):null;
        $this->em->persist($period);$this->em->flush();
    }

    private function planet(PlayerUniverse $membership): Planet
    {
        $galaxy=new Galaxy();$galaxy->universe=$membership->universe;$galaxy->name='Test '.$membership->id;
        $system=new SolarSystem();$system->galaxy=$galaxy;$system->name='Test';$system->coordX=0;$system->coordY=0;
        $planet=new Planet();$planet->solarSystem=$system;$planet->owner=$membership;$planet->name='Test';$planet->position=1;
        foreach([$galaxy,$system,$planet] as $entity) $this->em->persist($entity);
        $this->em->flush();
        return $planet;
    }
}
