<?php
namespace App\Tests\Controller;

use App\Entity\{AdminAuditLog,Guild,GuildMember,Player,PlayerReport,Universe};
use App\Service\Player\{DailyLeaderboard,DiplomacyService,PlayerReportService,RegistrationService,RelationResolver};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class GuildTagPlayerReportTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private Player $reporter;
    private Player $target;
    private Player $admin;
    private Player $foreign;

    protected function setUp(): void
    {
        $this->client=self::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);
        // Conserver l’horloge simulée lors des requêtes HTTP successives.
        $this->client->disableReboot();
        $this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();self::getContainer()->set('clock',new MockClock('2026-11-12T12:00:00Z'));
        $this->reporter=$this->player('ReportAuthor');$this->target=$this->player('ReportTarget');$this->admin=$this->player('ReportAdmin');
        $this->foreign=$this->player('ReportForeign','turbo-1');$this->admin->roles=['ROLE_ADMIN'];$this->em->flush();
        $this->client->loginUser($this->reporter);
    }
    private function player(string $name,string $slug='omega-1'): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);$universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function context(): array
    {
        return ['reporter_id'=>$this->reporter->currentPlayerUniverse->id,'target_id'=>$this->target->currentPlayerUniverse->id,
            'universe_id'=>$this->reporter->currentPlayerUniverse->universe->id,'category'=>'fleet','rank'=>2,
            'leaderboard_at'=>'2026-11-12T00:00:00+01:00','target_username_seen'=>$this->target->username];
    }
    private function submit(): PlayerReport
    {
        self::getContainer()->get(PlayerReportService::class)->submit($this->reporter->currentPlayerUniverse,$this->target->currentPlayerUniverse,'name','Contexte précis',$this->context(),str_repeat('a',32));
        return $this->em->getRepository(PlayerReport::class)->findOneBy(['requestKey'=>str_repeat('a',32)]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidTags')]
    public function testMissingOrInvalidTagCannotCreateGuild(string $tag): void
    {
        $this->expectException(\DomainException::class);
        try { self::getContainer()->get(DiplomacyService::class)->create($this->reporter->currentPlayerUniverse,'Une guilde',$tag); }
        finally { self::assertSame(0,$this->em->getRepository(Guild::class)->count([])); }
    }
    public static function invalidTags(): array { return [[''],['A'],['ABCDEF'],['A-B'],['ÉÉ']]; }

    public function testTagNormalizesAndRankingAloneDecoratesName(): void
    {
        self::getContainer()->get(DiplomacyService::class)->create($this->reporter->currentPlayerUniverse,'Guilde du classement',' a1z ');
        $guild=self::getContainer()->get(RelationResolver::class)->guild($this->reporter->currentPlayerUniverse);self::assertSame('A1Z',$guild->tag);
        $crawler=$this->client->request('GET','/classement');self::assertResponseIsSuccessful();
        self::assertStringContainsString('[A1Z] ReportAuthor',$crawler->filter('tbody th[scope="row"]')->reduce(fn($node)=>str_contains($node->text(),'ReportAuthor'))->text());
        $this->client->request('GET','/guildes');self::assertResponseIsSuccessful();
        self::assertStringNotContainsString('[A1Z] ReportAuthor',$this->client->getResponse()->getContent());
        self::assertSame('ReportAuthor',$this->reporter->username);
    }

    public function testDuplicateTagInSameUniverseIsRejected(): void
    {
        $service=self::getContainer()->get(DiplomacyService::class);$service->create($this->reporter->currentPlayerUniverse,'Première guilde','ALPHA');
        $connection=$this->em->getConnection();$this->expectException(\DomainException::class);
        try { $service->create($this->target->currentPlayerUniverse,'Deuxième guilde','alpha'); }
        finally { self::assertSame(1,(int)$connection->fetchOne('SELECT COUNT(*) FROM guild')); }
    }

    public function testTagCanBeReusedInAnotherUniverse(): void
    {
        $foreign=$this->foreign->currentPlayerUniverse;$foreign->universe->gameSettings['features']['guilds_enabled']=true;$this->em->flush();
        $service=self::getContainer()->get(DiplomacyService::class);$service->create($this->reporter->currentPlayerUniverse,'Guilde un','SAME');
        $service->create($foreign,'Guilde deux','SAME');self::assertSame(2,$this->em->getRepository(Guild::class)->count(['tag'=>'SAME']));
    }

    public function testLegacyGuildRemainsUntaggedUntilCurrentCommanderChooses(): void
    {
        $leader=$this->reporter->currentPlayerUniverse;$guild=new Guild();$guild->universe=$leader->universe;$guild->leader=$leader;$guild->name='Guilde ancienne';$this->em->persist($guild);
        $member=new GuildMember();$member->guild=$guild;$member->playerUniverse=$leader;$this->em->persist($member);$this->em->flush();
        self::assertNull($guild->tag);
        $this->client->request('GET','/classement');self::assertResponseIsSuccessful();self::assertStringNotContainsString('[]',$this->client->getResponse()->getContent());
        $em=self::getContainer()->get(EntityManagerInterface::class);$leader=$em->find(\App\Entity\PlayerUniverse::class,$leader->id);
        self::getContainer()->get(DiplomacyService::class)->setTag($leader,'OLD');
        self::assertSame('OLD',$em->find(Guild::class,$guild->id)->tag);
    }

    public function testTagPermissionFollowsTransferredCommand(): void
    {
        $service=self::getContainer()->get(DiplomacyService::class);$leader=$this->reporter->currentPlayerUniverse;$successor=$this->target->currentPlayerUniverse;
        $service->create($leader,'Transfert','ONE');$guild=self::getContainer()->get(RelationResolver::class)->guild($leader);$service->join($successor,$guild->id);
        $service->transfer($leader,$successor->id);$service->setTag($successor,'TWO');self::assertSame('TWO',$guild->tag);
        $connection=$this->em->getConnection();$id=$guild->id;$this->expectException(\DomainException::class);
        try { $service->setTag($leader,'BAD'); }
        finally { self::assertSame('TWO',$connection->fetchOne('SELECT tag FROM guild WHERE id=?',[$id])); }
    }

    public function testReportIsIdempotentAndModerationPreservesContext(): void
    {
        $report=$this->submit();$service=self::getContainer()->get(PlayerReportService::class);$context=$report->context;
        $this->admin->preferences['admin_last_seen']=['reports'=>100];$this->em->flush();
        $notifications=self::getContainer()->get(\App\Service\Admin\AdminNotificationService::class);
        self::assertSame(1,$notifications->counts($this->admin)['reports']);
        self::assertSame(0,$notifications->counts($this->reporter)['reports']);
        $notifications->markVisited($this->admin,['player_reports'=>$report->id]);
        self::assertSame(0,$notifications->counts($this->admin)['reports']);
        self::assertSame(100,$this->admin->preferences['admin_last_seen']['reports']);

        self::assertFalse($service->submit($this->reporter->currentPlayerUniverse,$this->target->currentPlayerUniverse,'name','Contexte précis',$this->context(),str_repeat('a',32)));
        self::assertSame(1,$this->em->getRepository(PlayerReport::class)->count([]));
        self::assertTrue($service->review($this->admin,$report->id,'actioned'));self::assertFalse($service->review($this->admin,$report->id,'dismiss'));
        self::assertSame($context,$report->context);self::assertSame('actioned',$report->status);self::assertNotNull($report->reviewedAt);
        self::assertSame(1,$this->em->getRepository(AdminAuditLog::class)->count(['action'=>'player_report_actioned']));
        self::assertNull($this->target->lockedAt);self::assertSame('ReportTarget',$this->target->username);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidReports')]
    public function testInvalidReportHasNoEffect(string $scenario): void
    {
        $context=$this->context();$target=$this->target->currentPlayerUniverse;$reason='other';$comment='';$key=str_repeat('b',32);
        if($scenario==='foreign') { $target=$this->foreign->currentPlayerUniverse;$context['target_id']=$target->id; }
        if($scenario==='self') { $target=$this->reporter->currentPlayerUniverse;$context['target_id']=$target->id; }
        if($scenario==='category') $context['category']='unknown';
        if($scenario==='rank') $context['rank']=-1;
        if($scenario==='reason') $reason='unknown';
        if($scenario==='comment') $comment=str_repeat('x',1001);
        if($scenario==='key') $key='wrong';
        if($scenario==='locked') { $this->reporter->lockedAt=new \DateTimeImmutable();$this->em->flush(); }
        $connection=$this->em->getConnection();$this->expectException(\DomainException::class);
        try { self::getContainer()->get(PlayerReportService::class)->submit($this->reporter->currentPlayerUniverse,$target,$reason,$comment,$context,$key); }
        finally { self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM player_report')); }
    }
    public static function invalidReports(): array { return array_map(fn($s)=>[$s],['foreign','self','category','rank','reason','comment','key','locked']); }

    #[\PHPUnit\Framework\Attributes\DataProvider('reportDirections')]
    public function testHttpContextCannotBeForgedAndRenamingKeepsBothNames(string $direction,string $expectedDirection): void
    {
        $id=$this->target->currentPlayerUniverse->id;$url='/classement/signaler/'.$id.'?category=fleet&page=1'.($direction!==''?'&direction='.$direction:'');
        $crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();
        $returnUrl='/classement?category=fleet&direction='.$expectedDirection.'&page=1';
        self::assertSame($returnUrl,$crawler->selectLink('Revenir au classement')->attr('href'));
        $token=$crawler->filter('input[name="_token"]')->attr('value');$key=$crawler->filter('input[name="request_key"]')->attr('value');
        $em=self::getContainer()->get(EntityManagerInterface::class);$target=$em->find(Player::class,$this->target->id);$target->username='RenamedTarget';$em->flush();
        $forgedDirection=$expectedDirection==='asc'?'desc':'asc';
        $post=['_token'=>$token,'request_key'=>$key,'reason'=>'cheating','comment'=>'Détails utiles','rank'=>9999,'category'=>'planets','direction'=>$forgedDirection,'universe_id'=>$this->foreign->currentPlayerUniverse->universe->id];
        $postUrl='/classement/signaler/'.$id.'?category=planets&direction='.$forgedDirection.'&page=99';
        $this->client->request('POST',$postUrl,$post);self::assertResponseRedirects($returnUrl);
        $em=self::getContainer()->get(EntityManagerInterface::class);$report=$em->getRepository(PlayerReport::class)->findOneBy(['requestKey'=>$key]);
        self::assertSame('fleet',$report->context['category']);self::assertNotSame(9999,$report->context['rank']);
        self::assertSame('RenamedTarget',$report->context['target_username']);self::assertSame('ReportTarget',$report->context['target_username_seen']);
        $this->client->request('POST',$postUrl,$post);self::assertResponseRedirects($returnUrl);
        self::assertSame(1,self::getContainer()->get(EntityManagerInterface::class)->getRepository(PlayerReport::class)->count([]));
        $this->client->request('POST',$url,['_token'=>'forged']);self::assertResponseStatusCodeSame(403);
    }
    public static function reportDirections(): array
    {
        return ['default'=>['','desc'],'ascending'=>['asc','asc'],'descending'=>['desc','desc'],'invalid'=>['invalid','desc']];
    }

    public function testReportFormErrorsKeepInputAndAdminAccessIsRestricted(): void
    {
        $url='/classement/signaler/'.$this->target->currentPlayerUniverse->id;
        $crawler=$this->client->request('GET',$url);$form=$crawler->selectButton('Transmettre le signalement')->form(['reason'=>'other','comment'=>'Texte conservé']);
        $values=$form->getPhpValues();$values['reason']='invalid';
        $this->client->request('POST',$url,$values);self::assertResponseStatusCodeSame(422);self::assertSelectorTextContains('[role="alert"]','motif');
        self::assertSelectorTextContains('textarea','Texte conservé');
        $this->client->request('GET','/classement/signaler/'.$this->foreign->currentPlayerUniverse->id);self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/admin/moderation');self::assertResponseRedirects('/acces-refuse');
    }

    public function testOnlyAdminCanReviewAndEvidenceSurvivesDetachedIdentities(): void
    {
        $report=$this->submit();$id=$report->id;$context=$report->context;
        $this->em->getConnection()->executeStatement('UPDATE player_report SET reporter_id=NULL,target_id=NULL WHERE id=?',[$id]);$this->em->clear();
        $this->client->loginUser($this->admin);$crawler=$this->client->request('GET','/admin/moderation');self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#admin-player-reports','ReportTarget');self::assertSelectorTextContains('#admin-player-reports','Contexte précis');
        $form=$crawler->filter('#admin-player-reports')->selectButton('Classer sans suite')->form();$this->client->submit($form);self::assertResponseRedirects();
        $em=self::getContainer()->get(EntityManagerInterface::class);$report=$em->find(PlayerReport::class,$id);
        self::assertSame($context,$report->context);self::assertSame('dismissed',$report->status);
    }

    public function testNonAdminServiceReviewCannotChangeReport(): void
    {
        $report=$this->submit();$connection=$this->em->getConnection();$this->expectException(\DomainException::class);
        try { self::getContainer()->get(PlayerReportService::class)->review($this->reporter,$report->id,'dismiss'); }
        finally { self::assertSame('open',$connection->fetchOne('SELECT status FROM player_report WHERE id=?',[$report->id])); }
    }
    public function testGuildlessNavigationKeepsScopedLegacySearch(): void
    {
        $service=self::getContainer()->get(DiplomacyService::class);
        $service->create($this->target->currentPlayerUniverse,'GuildPublicLocal','LOC');
        $this->foreign->currentPlayerUniverse->universe->gameSettings['features']['guilds_enabled']=true;$this->em->flush();
        $service->create($this->foreign->currentPlayerUniverse,'GuildPublicForeign','EXT');
        $this->client->request('GET','/diplomatie?tab=diplomacy&q=GuildPublic');
        self::assertResponseRedirects('/guildes?q=GuildPublic');
        $crawler=$this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSame(['Trouver une guilde','Créer une guilde'],$crawler->filter('nav[aria-label="Navigation de guilde"] a')->each(fn($node)=>$node->text()));
        self::assertSame('page',$crawler->filter('nav[aria-label="Navigation de guilde"] a[href="/guildes"]')->attr('aria-current'));
        self::assertSelectorNotExists('#guild-name');
        self::assertCount(1,$crawler->filter('.guild-card'));
        self::assertSelectorNotExists('.guild-presence');
        self::assertSelectorTextContains('.guild-card','GuildPublicLocal');
        self::assertStringNotContainsString('GuildPublicForeign',$this->client->getResponse()->getContent());
        $this->client->request('GET','/diplomatie?tab=ranks');
        self::assertResponseRedirects('/guildes/creer');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#guild-ranks-title');
        self::assertSelectorExists('#guild-name');
        self::assertSelectorNotExists('#guild-search-query');
        $this->client->request('GET','/diplomatie/commandants');
        self::assertSelectorTextContains('section[aria-label="Relations entre commandants"] h2','Alliances et rivalités');
    }

    public function testCreationDraftSurvivesReloadAndStaysScopedUntilSuccess(): void
    {
        $service = self::getContainer()->get(DiplomacyService::class);
        $service->create($this->target->currentPlayerUniverse, 'Guilde existante', 'USED');
        $crawler = $this->client->request('GET', '/guildes/creer');
        $name = 'Une "guilde" <script>alert(1)</script>';
        $this->client->submit($crawler->selectButton('Fonder une guilde')->form([
            'name' => $name,
            'tag' => 'USED',
        ]));
        self::assertResponseRedirects('/guildes/creer');
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Ce tag est déjà utilisé');
        self::assertSame($name, $crawler->filter('#guild-name')->attr('value'));
        self::assertSelectorNotExists('.guild-create script');
        $crawler = $this->client->request('GET', '/guildes/creer');
        self::assertSame($name, $crawler->filter('#guild-name')->attr('value'));

        $this->client->loginUser($this->admin);
        $crawler = $this->client->request('GET', '/guildes/creer');
        self::assertSame('', $crawler->filter('#guild-name')->attr('value'));
        $this->client->loginUser($this->reporter);
        // L'échec transactionnel a détaché les anciennes fixtures : relire avant une nouvelle action métier.
        $foreignUniverse = $this->em->find(Universe::class, $this->foreign->currentPlayerUniverse->universe->id);
        $foreignUniverse->gameSettings['features']['guilds_enabled'] = true;
        $this->em->flush();
        $universes = self::getContainer()->get(UniverseService::class);
        $omegaId = $this->target->currentPlayerUniverse->universe->id;
        $universes->select($this->em->find(Player::class, $this->reporter->id), $foreignUniverse->id);
        $crawler = $this->client->request('GET', '/guildes/creer');
        self::assertSame('', $crawler->filter('#guild-name')->attr('value'));
        $universes->select($this->em->find(Player::class, $this->reporter->id), $omegaId);
        $crawler = $this->client->request('GET', '/guildes/creer');
        self::assertSame($name, $crawler->filter('#guild-name')->attr('value'));

        $this->client->submit($crawler->selectButton('Fonder une guilde')->form(['tag' => 'NEW']));
        self::assertResponseRedirects('/guildes');
        $this->client->followRedirect();
        self::assertSelectorTextContains('h2', $name);
        $service->disband($this->em->find(Player::class, $this->reporter->id)->currentPlayerUniverse);
        $crawler = $this->client->request('GET', '/guildes/creer');
        self::assertSame('', $crawler->filter('#guild-name')->attr('value'));
        self::assertSame('', $crawler->filter('#guild-tag')->attr('value'));
    }

    public function testInvalidCsrfDoesNotReplaceCreationDraft(): void
    {
        $crawler = $this->client->request('GET', '/guildes/creer');
        $this->client->submit($crawler->selectButton('Fonder une guilde')->form([
            'name' => 'Nom conservé',
            'tag' => 'X',
        ]));
        self::assertResponseRedirects('/guildes/creer');
        $this->client->request('POST', '/guildes/creer', [
            '_token' => 'invalid', 'action' => 'create', 'name' => 'Autre nom', 'tag' => 'OK',
        ]);
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/guildes/creer');
        self::assertSame('Nom conservé', $crawler->filter('#guild-name')->attr('value'));
        self::assertSame('X', $crawler->filter('#guild-tag')->attr('value'));
        self::assertSame(0, $this->em->getRepository(Guild::class)->count([]));
    }

    public function testOversizedCreationDraftRemainsInvalidAndBounded(): void
    {
        $crawler = $this->client->request('GET', '/guildes/creer');
        $form = $crawler->selectButton('Fonder une guilde')->form([
            'name' => str_repeat('É', 1000),
            'tag' => str_repeat('A', 1000),
        ]);
        $this->client->submit($form);
        $crawler = $this->client->followRedirect();
        $name = $crawler->filter('#guild-name')->attr('value');
        $tag = $crawler->filter('#guild-tag')->attr('value');
        self::assertGreaterThan(60, mb_strlen($name));
        self::assertLessThanOrEqual(120, mb_strlen($name));
        self::assertGreaterThan(5, mb_strlen($tag));
        self::assertLessThanOrEqual(10, mb_strlen($tag));
        $this->client->submit($crawler->selectButton('Fonder une guilde')->form());
        self::assertResponseRedirects('/guildes/creer');
        self::assertSame(0, $this->em->getRepository(Guild::class)->count([]));
    }

    public function testGuildNavigationSeparatesMembersAndRanksWithoutGrantingPermissions(): void
    {
        $service=self::getContainer()->get(DiplomacyService::class);
        $service->create($this->reporter->currentPlayerUniverse,'Guilde navigation','NAV');
        $guild=self::getContainer()->get(RelationResolver::class)->guild($this->reporter->currentPlayerUniverse);
        $service->join($this->target->currentPlayerUniverse,$guild->id);
        self::getContainer()->get(\App\Service\Player\GuildRankService::class)->save($this->reporter->currentPlayerUniverse,0,'Diplomate',10,[],0);
        $connection=$this->em->getConnection();
        $crawler=$this->client->request('GET','/guildes');
        self::assertResponseIsSuccessful();
        self::assertSame(['Vue générale','Membres','Grades et permissions','Projets collectifs','Personnalisation','Gestion'],$crawler->filter('nav[aria-label="Navigation de guilde"] a')->each(fn($node)=>$node->text()));
        self::assertSelectorNotExists('.guild-members-table');
        self::assertSelectorNotExists('#guild-ranks-title');
        $this->client->request('GET','/guildes/membres');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.guild-members-table','ReportTarget');
        self::assertStringContainsString('private',$this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringContainsString('no-store',$this->client->getResponse()->headers->get('Cache-Control'));
        self::assertSelectorExists('input[name="expected_rank"]');
        self::assertSelectorNotExists('input[name="action"][value="rank_save"]');
        $this->client->request('GET','/guildes/grades');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="action"][value="rank_save"]');
        self::assertSelectorExists('input[name="version"]');
        self::assertSelectorNotExists('input[name="action"][value="rank_assign"]');

        $this->client->loginUser($this->target);
        $crawler=$this->client->request('GET','/guildes/gestion');
        $token=$crawler->filter('input[name="_token"]')->first()->attr('value');
        $this->client->request('GET','/guildes/grades');
        self::assertSelectorTextContains('#guild-ranks-title','Grades et permissions');
        self::assertSelectorNotExists('input[name="action"][value="rank_save"]');
        $this->client->request('POST','/diplomatie',['_token'=>$token,'action'=>'rank_save','target'=>0,'name'=>'Interdit','position'=>1,'version'=>0,'permissions'=>[]]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]','Seul le dirigeant');
        self::assertSame(0,(int)$connection->fetchOne("SELECT COUNT(*) FROM guild_rank WHERE name='Interdit'"));
    }

    public function testPersonalProposalsStayAccessibleWithAGuildAndAreHighlightedOnTheirPage(): void
    {
        $service = self::getContainer()->get(DiplomacyService::class);
        $viewer = $this->reporter->currentPlayerUniverse;
        $service->create($viewer, 'Guilde compteur', 'CMP');
        $service->alliance($this->target->currentPlayerUniverse, $viewer->id, 'propose');
        $service->alliance($viewer, $this->admin->currentPlayerUniverse->id, 'propose');
        foreach (['/guildes', '/guildes/membres', '/guildes/grades', '/diplomatie'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('nav[aria-label="Navigation principale"] a[href="/diplomatie"]');
        }
        $this->client->request('GET', '/diplomatie/commandants');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('a[href="#alliance-members"]', '1 proposition personnelle à traiter');
        self::assertSelectorCount(1, '.commander-card.received-proposals');
    }

    public function testDirectPagesKeepActiveNavigationAndDoNotPrepareOtherSections(): void
    {
        $service = self::getContainer()->get(DiplomacyService::class);
        $service->create($this->reporter->currentPlayerUniverse, 'Navigation ciblée', 'CIB');
        foreach (['/guildes'=>'Ma guilde', '/guildes/membres'=>'Membres de ma guilde', '/guildes/grades'=>'Grades et permissions', '/guildes/gestion'=>'Gestion de ma guilde', '/diplomatie'=>'Relations entre guildes', '/diplomatie/commandants'=>'Relations entre commandants'] as $url=>$title) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', $title);
            self::assertSelectorCount(1, 'nav[aria-label="Navigation principale"] a[aria-current="page"]');
            self::assertSelectorNotExists('input[name="action"][value="create"]');
        }
        $this->client->request('GET', '/guildes');
        self::assertSelectorNotExists('.guild-members-table');
        self::assertSelectorNotExists('button[value="disband"]');
        $membership = self::getContainer()->get(EntityManagerInterface::class)->find(Player::class, $this->reporter->id)->currentPlayerUniverse;
        $guild = self::getContainer()->get(RelationResolver::class)->guild($membership);
        $data = self::getContainer()->get(\App\Service\Player\DiplomacyPageView::class)->prepare($membership, $guild, 'ranks', '', -1);
        self::assertSame([], $data['ownRow']['members']);
        self::assertSame([], $data['guildRelations']);
        self::assertSame([], $data['guildPreview']);
        self::assertNull($data['profile']);
        $this->client->request('POST', '/guildes/gestion', ['_token'=>'invalid','action'=>'disband']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPersonalRelationsArePagedWithoutHidingIndependentSearch(): void
    {
        $viewer = $this->reporter->currentPlayerUniverse;
        self::getContainer()->get(DiplomacyService::class)->create($viewer, 'Guilde pagination', 'PAGE');
        $guild = self::getContainer()->get(RelationResolver::class)->guild($viewer);
        for ($index = 0; $index < 55; ++$index) {
            $player = new Player();
            $player->username = 'Paged'.str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $player->email = strtolower($player->username).'@example.test';
            $player->password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT, ['cost'=>4]);
            $membership = new \App\Entity\PlayerUniverse();
            $membership->player = $player;
            $membership->universe = $viewer->universe;
            $membership->joinedAt = new \DateTimeImmutable();
            $member = new GuildMember();
            $member->guild = $guild;
            $member->playerUniverse = $membership;
            foreach ([$player, $membership, $member] as $entity) {
                $this->em->persist($entity);
            }
        }
        $this->em->flush();
        $crawler = $this->client->request('GET', '/diplomatie/commandants');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(50, '#alliance-members .commander-card');
        self::assertSelectorTextNotContains('#alliance-members', 'ReportTarget');
        $next = $crawler->selectLink('Relations suivantes')->attr('href');
        $this->client->request('GET', $next);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(5, '#alliance-members .commander-card');
        self::assertSelectorNotExists('nav[aria-label="Pages des relations"] a[href*="after="]');
        $this->client->request('GET', '/diplomatie/commandants?q=ReportTarget');
        self::assertSelectorTextContains('.relation-results', 'ReportTarget');
        self::assertSelectorCount(50, '#alliance-members .commander-card');
    }

    public function testGuildPresenceIsScopedAndDoesNotRevealActivityInAnotherUniverse(): void
    {
        $guilds=self::getContainer()->get(DiplomacyService::class);
        $viewer=$this->reporter->currentPlayerUniverse;$member=$this->target->currentPlayerUniverse;
        $guilds->create($viewer,'Guilde présence','PRE');
        $guild=self::getContainer()->get(RelationResolver::class)->guild($viewer);
        $guilds->join($member,$guild->id);
        $activity=self::getContainer()->get(\App\Service\Player\PlayerActivityService::class);
        $presence=self::getContainer()->get(\App\Service\Player\GuildPresence::class);
        $clock=self::getContainer()->get('clock');
        $activity->record($viewer);$activity->record($member);
        self::assertTrue($presence->forMember($viewer)[$member->id]);
        $clock->sleep(299);
        self::assertTrue($presence->forMember($viewer)[$member->id]);
        $clock->sleep(1);
        self::assertFalse($presence->forMember($viewer)[$member->id]);
        $activity->record($viewer);
        $activity->record($this->foreign->currentPlayerUniverse);
        $turbo=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);
        self::getContainer()->get(UniverseService::class)->select($this->target,$turbo->id);
        $otherMembership=$this->target->currentPlayerUniverse;
        $activity->record($otherMembership);
        $states=$presence->forMember($viewer);
        self::assertTrue($states[$viewer->id]);
        self::assertFalse($states[$member->id]);
        self::assertArrayNotHasKey($otherMembership->id,$states);
        self::assertArrayNotHasKey($this->foreign->currentPlayerUniverse->id,$states);
        self::assertSame([],$presence->forMember($this->foreign->currentPlayerUniverse));
        $guild->archivedAt=$clock->now();$this->em->flush();
        self::assertSame([],$presence->forMember($viewer));
    }

    public function testGuildPreviewKeepsOnlyThreeLatestMessagesWithoutMarkingRead(): void
    {
        $service=self::getContainer()->get(DiplomacyService::class);
        $reader=$this->reporter->currentPlayerUniverse;
        $service->create($reader,'Trois derniers messages','TROIS');
        $guild=self::getContainer()->get(RelationResolver::class)->guild($reader);
        $service->join($this->target->currentPlayerUniverse,$guild->id);
        for($i=1;$i<=5;$i++) {
            $message=new \App\Entity\SocialMessage();
            $message->sender=$this->target->currentPlayerUniverse;$message->guild=$guild;
            $message->content='Échange numéro '.$i;$message->createdAt=new \DateTimeImmutable('2026-11-12T11:00:00Z');
            $this->em->persist($message);
        }
        $this->em->flush();
        self::assertSame(5,self::getContainer()->get(\App\Service\Player\SocialMessageService::class)->unreadGuildCount($reader));
        $crawler=$this->client->request('GET','/guildes');
        self::assertResponseIsSuccessful();
        self::assertSame(['Échange numéro 3','Échange numéro 4','Échange numéro 5'],$crawler->filter('.guild-message-preview p')->each(fn($node)=>$node->text()));
        $fresh=self::getContainer()->get(EntityManagerInterface::class)->find(Player::class,$this->reporter->id)->currentPlayerUniverse;
        self::assertSame(5,self::getContainer()->get(\App\Service\Player\SocialMessageService::class)->unreadGuildCount($fresh));
        self::assertSelectorExists('a[href="/messages?channel=guild"]');
    }

}
