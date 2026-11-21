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
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword=bin2hex(random_bytes(18));
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
        self::assertSame(0,static::getContainer()->get(JournalService::class)->unreadCount($this->player->currentPlayerUniverse));
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

    public function testConsultationMarksTheWholeSnapshotAndKeepsLaterSameSecondArrivals(): void
    {
        $this->overview();
        $snapshot = [];
        for ($i = 0; $i < 8; ++$i) {
            $snapshot[] = $this->notice('Événement initial '.$i);
        }
        $this->overview();
        self::assertSelectorCount(5, '[data-journal-notice]');
        self::assertSelectorTextContains('.dashboard-journal', '5 des 8 événements');
        self::assertSelectorNotExists('[name="notice_ids[]"]');
        $form = $this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();
        $initial = $form->getPhpValues();
        $url = $form->getUri();
        $oldest = $snapshot[0];
        $late = $this->notice('Arrivée après affichage', 'arrival');
        // Une autre ouverture rend le nouvel événement disponible sans changer la seconde.
        $this->overview();
        self::assertSelectorTextContains('[data-journal-notice]', 'Arrivée après affichage');
        $connection = $this->em->getConnection();
        self::assertNotNull($connection->fetchOne('SELECT journal_available_at FROM notification WHERE id = ?', [$late->id]));

        $this->client->request('POST', $url, $initial, [], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSame(['unread' => true], json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
        $read = $connection->fetchOne('SELECT consulted_at FROM notification WHERE id = ?', [$oldest->id]);
        self::assertNotNull($read);
        self::assertSame(8, (int) $connection->fetchOne('SELECT COUNT(*) FROM notification WHERE consulted_at IS NOT NULL'));
        self::assertNull($connection->fetchOne('SELECT consulted_at FROM notification WHERE id = ?', [$late->id]));

        $this->clock->sleep(10);
        $this->client->request('POST', $url, $initial);
        self::assertResponseRedirects(null, 303);
        self::assertSame($read, $connection->fetchOne('SELECT consulted_at FROM notification WHERE id = ?', [$oldest->id]));
        $this->overview();
        self::assertSelectorCount(1, '[data-journal-notice]');
        self::assertSelectorTextContains('[data-journal-notice]', 'Arrivée après affichage');
        self::assertSame(9, $this->em->getRepository(Notification::class)->count([]));
    }

    public function testJsonRefreshReturnsOnlyRegionsAndNeverOpensTheJournal(): void
    {
        $this->overview();
        $visited = $this->player->currentPlayerUniverse->overviewVisitedAt;
        $notice = $this->notice('Événement encore non consulté');
        $this->clock->sleep(60);
        $server = ['HTTP_X_GENESIS_LIVE' => '1', 'HTTP_ACCEPT' => 'application/json'];
        foreach (['overview', 'buildings', 'technologies', 'ships', 'defenses'] as $section) {
            $this->client->request('GET', '/planetes/'.$this->home->id.'?section='.$section, [], [], $server);
            self::assertResponseIsSuccessful();
            $response = $this->client->getResponse();
            self::assertStringContainsString('application/json', $response->headers->get('Content-Type'));
            self::assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
            $regions = json_decode($response->getContent(), true, 512, JSON_THROW_ON_ERROR)['regions'];
            self::assertArrayHasKey('resources', $regions);
            self::assertArrayHasKey('queues', $regions);
            self::assertArrayHasKey($section === 'overview' ? 'journal' : 'catalog', $regions);
            if ($section === 'overview') {
                self::assertArrayHasKey('overview-attention', $regions);
                self::assertStringContainsString('data-live-region="overview-attention"', $regions['overview-attention']);
                self::assertStringContainsString('Ouvrir les bâtiments', $regions['queues']);
            } else {
                self::assertArrayNotHasKey('overview-attention', $regions);
                self::assertStringNotContainsString('overview-queue-links', $regions['queues']);
            }
            self::assertStringNotContainsString('<!DOCTYPE', implode('', $regions));
        }
        self::assertEquals($visited, $this->player->currentPlayerUniverse->overviewVisitedAt);
        self::assertNull($this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?', [$notice->id]));
        $this->overview();
        $this->client->request('GET', '/planetes/'.$this->home->id, [], [], $server);
        self::assertResponseIsSuccessful();
        $regions = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['regions'];
        self::assertStringContainsString('Événement encore non consulté', $regions['journal']);
        [, $foreign] = $this->player('JournalForeignJson');
        $this->client->request('GET', '/planetes/'.$foreign->id, [], [], $server);
        self::assertResponseStatusCodeSame(404);
    }

    public function testJournalTicketIsPrivateBoundToPlanetAndExpires(): void
    {
        $this->overview();
        $own = $this->notice('Événement propre');
        $this->overview();
        $form = $this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();
        $values = $form->getPhpValues();
        $url = $form->getUri();
        $this->client->request('POST', $url, array_replace($values, ['_token' => 'invalid']));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url, array_replace($values, ['membership' => 999999]));
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url, array_replace($values, ['journal_ticket' => $values['journal_ticket'].'&through=other']));
        self::assertResponseStatusCodeSame(404);

        [, $foreign] = $this->player('JournalOther');
        $this->client->request('POST', '/planetes/'.$foreign->id.'/journal/consulter', $values);
        self::assertResponseStatusCodeSame(404);
        $clock = $this->clock;
        $clock->sleep(31 * 60);
        $this->client->request('POST', $url, $values);
        self::assertResponseStatusCodeSame(404);
        self::assertNull($this->em->getConnection()->fetchOne('SELECT consulted_at FROM notification WHERE id = ?', [$own->id]));
    }

    public function testNativeFallbackPreservesHistoryNonJournalAndAnotherTabSelection(): void
    {
        $this->overview();
        foreach (JournalService::KINDS as $kind) {
            $this->notice('Événement '.$kind, $kind);
        }
        $nonJournal = $this->notice('Message hors journal', null);
        $this->overview();
        self::assertSelectorCount(5, '[data-journal-notice]');
        self::assertSelectorTextNotContains('.dashboard-journal', 'Message hors journal');
        $form = $this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();

        $membership = $this->player->currentPlayerUniverse;
        $repo = static::getContainer()->get(GameRepository::class);
        $other = array_values(array_filter($repo->planets($membership->universe, null, true), static fn (Planet $p): bool => !$p->npcPlanet))[0];
        $other->owner = $membership;
        $this->em->flush();
        $this->client->request('GET', '/planetes/'.$other->id);
        self::assertResponseIsSuccessful();
        $this->client->request('POST', '/planetes/'.$other->id.'/journal/consulter', $form->getPhpValues());
        self::assertResponseStatusCodeSame(404);
        $this->client->submit($form);
        self::assertResponseRedirects('/', 303);
        $this->client->followRedirect();
        self::assertResponseRedirects('/planetes/'.$other->id);
        $this->client->followRedirect();
        self::assertSelectorCount(0, '[data-journal-notice]');

        $this->overview();
        $this->notice('Encore non consulté');
        $this->overview();
        $form = $this->client->getCrawler()->filter('form[action$="/journal/consulter"]')->form();
        $turbo = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        static::getContainer()->get(UniverseService::class)->select($this->em->find(Player::class, $this->player->id), $turbo->id);
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(404);

        $connection = $this->em->getConnection();
        self::assertSame(6, (int) $connection->fetchOne('SELECT COUNT(*) FROM notification WHERE consulted_at IS NOT NULL'));
        self::assertNull($connection->fetchOne('SELECT consulted_at FROM notification WHERE id = ?', [$nonJournal->id]));
        self::assertSame(8, $this->em->getRepository(Notification::class)->count([]));
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
