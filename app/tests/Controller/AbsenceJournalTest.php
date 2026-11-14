<?php
namespace App\Tests\Controller;

use App\Entity\{Notification,Planet,Player,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Notification\{JournalService,NotificationService};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class AbsenceJournalTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Planet $home;

    protected function setUp(): void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-12T12:00:00Z');static::getContainer()->set('clock',$this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        [$this->player,$this->home]=$this->player('JournalOwner');
        $this->client->loginUser($this->player);
    }

    private function player(string $name): array
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        return [$player,$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse])];
    }

    private function notice(string $message,?string $kind='queue'): Notification
    {
        static::getContainer()->get(NotificationService::class)->enqueue($this->em->find(PlayerUniverse::class,$this->player->currentPlayerUniverse->id),$message,$kind);
        $this->em->flush();
        return $this->em->getRepository(Notification::class)->findOneBy(['message'=>$message]);
    }

    private function overview(array $server=[]): void
    {
        $this->client->request('GET','/planetes/'.$this->home->id,[],[],$server);self::assertResponseIsSuccessful();
    }

    public function testFirstOpeningKeepsHistoryAndLiveRefreshNeverOpensOrConsultsJournal(): void
    {
        $old=$this->notice('Ancien événement');
        self::assertSame('queue',$old->journalKind);
        self::assertSame(0,static::getContainer()->get(JournalService::class)->data($this->player->currentPlayerUniverse)['unreadCount']);
        self::assertEquals($this->clock->now(),$this->home->acquiredAt);
        $this->overview();
        self::assertSelectorNotExists('[data-journal-notice]');
        self::assertNull($this->em->getConnection()->fetchOne('SELECT journal_kind FROM notification WHERE id = ?',[$old->id]));
        self::assertNull($this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?',[$old->id]));
        self::assertNotNull($this->player->currentPlayerUniverse->journalStartedAt);
        $visited=$this->player->currentPlayerUniverse->overviewVisitedAt;
        // Même seconde que la première ouverture : le nouvel événement ne doit pas être perdu.
        $new=$this->notice('Nouvelle construction');
        self::assertSame('queue',$new->journalKind);
        $this->clock->sleep(60);
        $this->overview(['HTTP_X_GENESIS_LIVE'=>'1']);
        self::assertSelectorNotExists('[data-journal-notice]');
        self::assertEquals($visited,$this->player->currentPlayerUniverse->overviewVisitedAt);
        $this->overview();
        self::assertSelectorTextContains('[data-journal-notice]','Nouvelle construction');
        self::assertSelectorTextNotContains('.dashboard-journal','Ancien événement');
        $this->overview();
        self::assertSelectorCount(1,'[data-journal-notice]');
        self::assertSame(2,$this->em->getRepository(Notification::class)->count([]));
    }

    public function testConsultationOnlyMarksSubmittedVisibleEventsAndIsIdempotent(): void
    {
        $this->overview();$first=$this->notice('À consulter');$this->overview();
        $form=$this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();
        $values=$form->getPhpValues();$url=$form->getUri();
        $late=$this->notice('Arrivée après affichage','arrival');
        $this->client->submit($form);self::assertResponseRedirects();
        $read=$this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?',[$first->id]);
        self::assertNotNull($read);self::assertNull($this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?',[$late->id]));
        $this->clock->sleep(10);$this->client->request('POST',$url,$values);self::assertResponseRedirects();
        self::assertSame($read,$this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?',[$first->id]));
        $this->overview();self::assertSelectorCount(1,'[data-journal-notice]');self::assertSelectorTextContains('[data-journal-notice]','Arrivée après affichage');
        self::assertSame(2,$this->em->getRepository(Notification::class)->count([]));
    }

    public function testInvalidCsrfMembershipAndForeignNotificationsAreRejected(): void
    {
        $this->overview();$own=$this->notice('Propre');$this->overview();
        $form=$this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();$values=$form->getPhpValues();$url=$form->getUri();
        $this->client->request('POST',$url,array_replace($values,['_token'=>'invalid']));self::assertResponseStatusCodeSame(403);
        $this->client->request('POST',$url,array_replace($values,['membership'=>999999]));self::assertResponseStatusCodeSame(403);
        [$other,$planet]=$this->player('JournalOther');
        $foreign=new Notification();$foreign->recipient=$other->currentPlayerUniverse;$foreign->message='Privé';$foreign->createdAt=$this->clock->now();$foreign->journalKind='combat';$foreign->journalAvailableAt=$this->clock->now();
        $this->em->persist($foreign);$this->em->flush();
        $this->client->request('GET','/planetes/'.$planet->id);self::assertResponseStatusCodeSame(404);
        $this->client->request('POST',$url,array_replace($values,['notice_ids'=>[$foreign->id]]));self::assertResponseStatusCodeSame(404);
        self::assertNull($this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?',[$own->id]));
    }

    public function testCategoriesPaginationAndCrossUniverseFormsPreserveAllEvents(): void
    {
        $this->overview();
        for($i=0;$i<55;++$i) $this->notice('Événement '.$i,JournalService::KINDS[$i%count(JournalService::KINDS)]);
        $this->notice('Message hors journal',null);$this->overview();
        self::assertSelectorCount(50,'[data-journal-notice]');
        self::assertSelectorTextNotContains('.dashboard-journal','Message hors journal');
        $form=$this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();
        $this->client->submit($form);self::assertResponseRedirects();$this->overview();self::assertSelectorCount(5,'[data-journal-notice]');
        self::assertSame(56,$this->em->getRepository(Notification::class)->count([]));
        $form=$this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();$url=$form->getUri();$values=$form->getPhpValues();
        $turbo=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);
        static::getContainer()->get(UniverseService::class)->select($this->em->find(Player::class,$this->player->id),$turbo->id);
        $this->client->request('POST',$url,$values);self::assertResponseStatusCodeSame(404);
        self::assertSame(50,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notification WHERE consulted_at IS NOT NULL'));
    }

    public function testAllColonyListsUseTheSameAcquisitionOrderWithoutInventingLegacyDates(): void
    {
        $membership=$this->player->currentPlayerUniverse;$repo=static::getContainer()->get(GameRepository::class);
        $free=array_values(array_filter($repo->planets($membership->universe,null,true),static fn(Planet $p):bool=>!$p->npcPlanet));
        $legacy=$free[0];$older=$free[2];$recent=$free[1];
        foreach([$legacy,$older,$recent] as $planet) $planet->owner=$membership;
        $legacy->acquiredAt=null;$older->acquiredAt=$this->clock->now()->modify('-1 day');$recent->acquiredAt=$this->clock->now()->modify('+1 day');$this->em->flush();
        $expected=[$legacy->id,$older->id,$this->home->id,$recent->id];
        self::assertSame($expected,array_column($repo->planets($membership->universe,$membership),'id'));
        $this->overview();
        self::assertSelectorNotExists('[data-colony-summary]');
        self::assertSelectorExists('.dashboard-metrics a[href="/planetes"]');
        self::assertSame(array_map('strval',$expected),$this->client->getCrawler()->filter('#sidebar-colony option')->each(fn($node)=>$node->attr('value')));
        $this->client->request('GET','/planetes');self::assertResponseIsSuccessful();
        self::assertSame(array_map(static fn($id)=>'planet-'.$id,$expected),$this->client->getCrawler()->filter('.planet-index-card')->each(fn($node)=>$node->attr('id')));
        self::assertSelectorTextContains('#planet-'.$legacy->id,'Ancienneté approximative');
        self::assertNull($legacy->acquiredAt);
    }
}
