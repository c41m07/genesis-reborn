<?php
namespace App\Tests\Controller;

use App\Entity\{CombatEvent, CombatLog, CombatParticipant, EspionageReport, Fleet, Planet, Player, ReportArchive, TransportReport, Universe};
use App\Service\Combat\ReportArchiveService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ReportArchiveTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private Player $player;
    private Player $recipient;
    private Player $outsider;
    private Player $foreign;
    private array $keys;

    protected function setUp(): void
    {
        $this->client = self::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if (($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->player=$this->player('ArchiveOwner');$this->recipient=$this->player('ArchiveRecipient');
        $this->outsider=$this->player('ArchiveOutsider');$this->foreign=$this->player('ArchiveForeign','turbo-1');
        $member=$this->player->currentPlayerUniverse;$target=$this->home($this->recipient);
        $event=new CombatEvent();$event->planet=$target;$event->startedAt=new \DateTimeImmutable('2026-11-12T10:00:00Z');$event->resolveAt=$event->startedAt;$event->seed=1;$event->status='resolved';$this->em->persist($event);
        $combatFleet=new Fleet();$combatFleet->playerUniverse=$member;$combatFleet->homePlanet=$this->home($this->player);$this->em->persist($combatFleet);
        $cp=new CombatParticipant();$cp->fleet=$combatFleet;$cp->combatEvent=$event;$cp->playerUniverse=$member;$cp->role='attacker';$cp->arrivalAt=$event->startedAt;$this->em->persist($cp);
        $combat=new CombatLog();$combat->combatEvent=$event;$combat->outcome='attacker_wins';$combat->resolvedAt=new \DateTimeImmutable('2026-11-12T11:00:00Z');$combat->snapshot=['participants'=>[]];$this->em->persist($combat);
        $spy=new EspionageReport();$spy->sender=$member;$spy->target=$target;$spy->createdAt=new \DateTimeImmutable('2026-11-12T12:00:00Z');$this->em->persist($spy);
        $transport=$this->transport($this->player,$this->recipient,'2026-11-12T13:00:00Z');
        $this->em->flush();$this->keys=['combat:'.$combat->id,'espionage:'.$spy->id,'economy:'.$transport->id];
        $this->client->loginUser($this->player);
    }

    private function player(string $name,string $slug='omega-1'): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function home(Player $player): Planet { return $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]); }
    private function transport(Player $sender,Player $recipient,string $date): TransportReport
    {
        $fleet=new Fleet();$fleet->playerUniverse=$sender->currentPlayerUniverse;$fleet->homePlanet=$this->home($sender);$this->em->persist($fleet);
        $report=new TransportReport();$report->fleet=$fleet;$report->sender=$sender->currentPlayerUniverse;$report->recipient=$recipient->currentPlayerUniverse;
        $report->createdAt=new \DateTimeImmutable($date);$report->snapshot=['sender'=>$sender->username,'recipient'=>$recipient->username,'origin'=>'Alpha:1:2','destination'=>'Alpha:1:1','sent'=>[]];$this->em->persist($report);return $report;
    }

    public function testUnreadCombatKeepsBirthScopeAndCurrentVisibilityIndependentOfArchives(): void
    {
        $id = (int) substr($this->keys[0], strlen('combat:'));
        $log = $this->em->find(CombatLog::class, $id);
        $participant = $this->em->getRepository(CombatParticipant::class)->findOneBy(['combatEvent' => $log->combatEvent]);
        $oldOwner = $this->recipient->currentPlayerUniverse;
        $newOwner = $this->outsider->currentPlayerUniverse;
        $foreign = $this->foreign->currentPlayerUniverse;
        $attacker = $this->player->currentPlayerUniverse;
        $notices = self::getContainer()->get(\App\Repository\ReportNoticeRepository::class);
        $archives = self::getContainer()->get(ReportArchiveService::class);
        $reader = self::getContainer()->get(\App\Service\Combat\CombatReportReader::class);
        self::assertSame(0, $notices->count($oldOwner), 'Pas de rétroremplissage de l’historique.');
        self::getContainer()->get(\App\Service\Report\ReportNoticeWriter::class)->combatCreated($log, [$participant, $participant]);
        $this->em->flush();
        self::assertSame(1, $notices->count($oldOwner));
        self::assertSame(1, $notices->count($attacker));
        self::assertSame(0, $notices->count($foreign));

        $key = 'combat:'.$id;
        $archives->change($attacker, [$key], true);
        self::assertSame(0, $notices->count($attacker));
        self::assertSame([$key => true], $notices->unreadKeys($attacker, [$key]));
        $archives->change($attacker, [$key], false);
        self::assertSame(1, $notices->count($attacker));
        $archives->change($attacker, [$key], true);
        self::assertTrue($notices->consult($attacker, 'combat', $id, new \DateTimeImmutable()));
        $archives->change($attacker, [$key], false);
        self::assertSame(0, $notices->count($attacker));
        self::assertSame([], $notices->unreadKeys($attacker, [$key]));
        $log->combatEvent->planet->owner = $newOwner;
        $this->em->flush();
        self::assertNotNull($reader->findVisible($newOwner, $id));
        self::assertNull($reader->findVisible($oldOwner, $id));
        self::assertSame(0, $notices->count($oldOwner));
        self::assertSame(0, $notices->count($newOwner));
        self::assertFalse($notices->consult($oldOwner, 'combat', $id, new \DateTimeImmutable()));
        self::assertFalse($notices->consult($foreign, 'combat', $id, new \DateTimeImmutable()));
    }

    public function testUnreadTransportIgnoresInconsistentLegacyParticipantsAcrossUniverses(): void
    {
        $report = $this->transport($this->player, $this->foreign, '2026-11-12T14:00:00Z');
        $member = $this->player->currentPlayerUniverse;
        $this->em->persist(new \App\Entity\ReportNotice($member, $report));
        $this->em->flush();
        $notices = self::getContainer()->get(\App\Repository\ReportNoticeRepository::class);
        $summary = self::getContainer()->get(\App\Repository\ReportSummaryRepository::class);
        self::assertSame([], $summary->transports($member, [$report->id]));
        self::assertSame(0, $notices->count($member));
        self::assertSame([], $notices->unreadKeys($member, ['economy:'.$report->id]));
        self::assertFalse($notices->consult($member, 'transport', $report->id, new \DateTimeImmutable()));
    }

    public function testAdvancedFiltersRunBeforePaginationAndKeepTheReturnContext(): void
    {
        $service=self::getContainer()->get(ReportArchiveService::class);$member=$this->player->currentPlayerUniverse;
        $criteria=new \App\Service\Search\SearchCriteria(['participant'=>'ArchiveOwner','from'=>'2026-11-12','until'=>'2026-11-12']);
        self::assertCount(3,$service->page($member,'all',false,1,$criteria)['entries']);
        self::assertSame([],$service->page($member,'all',false,1,new \App\Service\Search\SearchCriteria(['participant'=>'ArchiveForeign']))['entries']);
        $planet=$this->home($this->recipient);
        // Le filtre accepte les noms partiels. Un nom distinct évite de retrouver aussi le port du transport.
        $planet->name = 'Cible du test des archives';
        $this->em->flush();
        $target=new \App\Service\Search\SearchCriteria(['planet'=>$planet->name]);
        self::assertCount(2,$service->page($member,'all',false,1,$target)['entries']);
        $crawler=$this->client->request('GET','/rapports',['type'=>'combat','participant'=>'ArchiveOwner','from'=>'2026-11-12','until'=>'2026-11-12']);self::assertResponseIsSuccessful();
        $url=$crawler->filter('a.report-row')->first()->attr('href');self::assertStringContainsString('participant=ArchiveOwner',$url);
        $this->client->request('GET',$url);self::assertResponseIsSuccessful();self::assertSelectorExists('a.report-back[href*="participant=ArchiveOwner"]');
    }

    public function testCombatListReadsSummariesWithoutHydratingRoundsAndKeepsDetailedProof(): void
    {
        $id = (int) substr($this->keys[0], strlen('combat:'));
        $log = $this->em->find(CombatLog::class, $id);
        $log->rounds = [['proof'=>str_repeat('synthetic-round-', 1000)]];
        $log->snapshot = ['participants'=>[['name'=>'Alpha'], ['name'=>'Beta']]];
        $spy = $this->em->getRepository(EspionageReport::class)->findAll()[0];
        $spy->snapshot = ['proof'=>str_repeat('synthetic-intelligence-', 1000)];
        $transport = $this->em->getRepository(TransportReport::class)->findAll()[0];
        $transport->snapshot['proof'] = str_repeat('synthetic-cargo-', 1000);
        $this->em->flush();
        $memberId = $this->player->currentPlayerUniverse->id;
        $this->em->clear();
        $member = $this->em->find(\App\Entity\PlayerUniverse::class, $memberId);
        $holder = self::getContainer()->get('doctrine.debug_data_holder');
        $holder->reset();
        $entries = self::getContainer()->get(ReportArchiveService::class)->page($member, 'combat', false, 1)['entries'];
        self::assertSame(2, $entries[0]['report']['participantCount']);
        self::assertSame('attacker_wins', $entries[0]['report']['outcome']);
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap()[CombatLog::class] ?? []);
        foreach ($holder->getData()['default'] ?? [] as $query) self::assertStringNotContainsString('.rounds', $query['sql']);
        $proof = self::getContainer()->get(\App\Service\Combat\CombatReportReader::class)->findVisible($member, $id);
        self::assertSame(str_repeat('synthetic-round-', 1000), $proof->rounds[0]['proof']);
        self::assertSame([], self::getContainer()->get(\App\Repository\ReportSummaryRepository::class)->combats($this->outsider->currentPlayerUniverse, [$id]));
        self::assertSame([], self::getContainer()->get(\App\Repository\ReportSummaryRepository::class)->combats($this->foreign->currentPlayerUniverse, [$id]));
        $entries = self::getContainer()->get(ReportArchiveService::class)->page($member, 'all', false, 1)['entries'];
        self::assertCount(3, $entries);
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap()[EspionageReport::class] ?? []);
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap()[TransportReport::class] ?? []);
        self::assertSame('ArchiveOwner', $entries[0]['report']['snapshot']['sender']);
        self::assertArrayNotHasKey('proof', $entries[0]['report']['snapshot']);
        self::assertArrayNotHasKey('snapshot', $entries[1]['report']);
        self::assertSame(str_repeat('synthetic-intelligence-', 1000), $this->em->getRepository(EspionageReport::class)->findAll()[0]->snapshot['proof']);
        self::assertSame(str_repeat('synthetic-cargo-', 1000), $this->em->getRepository(TransportReport::class)->findAll()[0]->snapshot['proof']);
    }

    public function testGlobalOrderPersonalArchiveRestoreAndProofPreservation(): void
    {
        $service=self::getContainer()->get(ReportArchiveService::class);$member=$this->player->currentPlayerUniverse;
        self::assertSame(array_reverse($this->keys),array_column($service->page($member,'all',false,1)['entries'],'key'));
        $service->change($member,$this->keys,true);
        self::assertSame([],$service->page($member,'all',false,1)['entries']);
        self::assertCount(3,$service->page($member,'all',true,1)['entries']);
        self::assertCount(2,$service->page($this->recipient->currentPlayerUniverse,'all',false,1)['entries']);
        self::assertSame([],$service->page($this->recipient->currentPlayerUniverse,'all',true,1)['entries']);
        $date=$this->em->getRepository(ReportArchive::class)->findOneBy(['playerUniverse'=>$member,'kind'=>'combat'])->archivedAt;
        $service->change($member,$this->keys,true);
        self::assertEquals($date,$this->em->getRepository(ReportArchive::class)->findOneBy(['playerUniverse'=>$member,'kind'=>'combat'])->archivedAt);
        $service->change($member,[$this->keys[0]],false);$service->change($member,[$this->keys[0]],false);
        self::assertSame([$this->keys[0]],array_column($service->page($member,'combat',false,1)['entries'],'key'));
        self::assertSame(1,$this->em->getRepository(CombatLog::class)->count([]));
        self::assertSame(1,$this->em->getRepository(EspionageReport::class)->count([]));
        self::assertSame(1,$this->em->getRepository(TransportReport::class)->count([]));
        self::assertSame(['participants'=>[]],$this->em->getRepository(CombatLog::class)->findAll()[0]->snapshot);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenSelections')]
    public function testUnauthorizedOrInvalidBatchIsAtomic(string $scenario): void
    {
        $member=$this->player->currentPlayerUniverse;
        $foreign=$this->transport($this->foreign,$this->foreign,'2026-11-12T14:00:00Z');
        $outsider=$this->transport($this->outsider,$this->outsider,'2026-11-12T14:00:00Z');$this->em->flush();
        $keys=match($scenario) {
            'foreign'=>[$this->keys[0],'economy:'.$foreign->id],
            'outsider'=>[$this->keys[0],'economy:'.$outsider->id],
            'malformed'=>[$this->keys[0],'wrong:1'],
            'empty'=>[],
            'too_many'=>array_map(fn($i)=>'economy:'.$i,range(1,51)),
        };
        $connection=$this->em->getConnection();$this->expectException(\DomainException::class);
        try { self::getContainer()->get(ReportArchiveService::class)->change($member,$keys,true); }
        finally { self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM report_archive')); }
    }
    public static function forbiddenSelections(): array { return array_map(fn($v)=>[$v],['foreign','outsider','malformed','empty','too_many']); }

    public function testPaginationRunsAfterGlobalSortAndArchiveFilter(): void
    {
        for($i=0;$i<52;$i++) $this->transport($this->player,$this->recipient,'2026-11-13T10:00:00Z');
        $this->em->flush();$service=self::getContainer()->get(ReportArchiveService::class);$member=$this->player->currentPlayerUniverse;
        $first=$service->page($member,'all',false,1);$second=$service->page($member,'all',false,2);
        self::assertSame(55,$first['report_count']);self::assertCount(50,$first['entries']);self::assertCount(5,$second['entries']);self::assertSame(2,$first['pages']);
        self::assertSame('combat',end($second['entries'])['kind']);
        $service->change($member,array_column($first['entries'],'key'),true);
        $remaining=$service->page($member,'all',false,2);
        self::assertSame(1,$remaining['page']);self::assertSame(5,$remaining['report_count']);self::assertCount(5,$remaining['entries']);
        self::assertSame(50,$service->page($member,'economy',true,1)['report_count']);
    }

    public function testHttpArchiveRestoreCsrfAndArchivedReportAccess(): void
    {
        $crawler=$this->client->request('GET','/rapports');self::assertResponseIsSuccessful();
        self::assertSame(array_reverse($this->keys),$crawler->filter('input[name="selected[]"]')->each(fn($node)=>$node->attr('value')));
        $token=$crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST','/rapports/archives',['_token'=>$token,'operation'=>'archive','selected'=>[$this->keys[0]]]);self::assertResponseRedirects();
        $crawler=$this->client->request('GET','/rapports?view=archives&type=combat');self::assertResponseIsSuccessful();
        self::assertCount(1,$crawler->filter('.report-row'));
        $this->client->request('GET',$crawler->filter('.report-row')->attr('href'));self::assertResponseIsSuccessful();
        $crawler=$this->client->request('GET','/rapports?view=archives&type=combat');
        $this->client->submit($crawler->selectButton('Restaurer')->form());self::assertResponseRedirects('/rapports?type=combat&view=archives&page=1');
        $this->client->request('POST','/rapports/archives',['_token'=>'forged','operation'=>'archive','selected'=>$this->keys]);self::assertResponseStatusCodeSame(403);
        $this->client->request('GET','/rapports?type=economy');self::assertResponseIsSuccessful();
    }

    public function testRemovalHidesSingleOrSelectedArchivedReportsAndPreservesProofs(): void
    {
        [$combat, $spy, $transport] = array_map(static fn (string $key): int => (int) explode(':', $key)[1], $this->keys);
        $member = $this->player->currentPlayerUniverse;
        $recipient = $this->recipient->currentPlayerUniverse;
        $diplomatic = new \App\Entity\DiplomaticReport($member, $recipient, 'orbit', 'requested', 'Alpha:1:1', null, new \DateTimeImmutable('2026-11-12T14:00:00Z'));
        $this->em->persist($diplomatic);
        $this->em->persist(new \App\Entity\ReportNotice($member, $this->em->find(CombatLog::class, $combat)));
        $transportReport = $this->em->find(TransportReport::class, $transport);
        $fleetId = $transportReport->fleet->id;
        $this->em->persist(new \App\Entity\ReportNotice($member, $transportReport));
        $this->em->persist(new \App\Entity\ReportNotice($recipient, $transportReport));
        $this->em->flush();
        $diplomaticId = $diplomatic->id;
        $memberId = $member->id;
        $recipientId = $recipient->id;
        $keys = [...$this->keys, 'diplomatic:'.$diplomaticId];

        $crawler = $this->client->request('GET', '/rapports');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        self::assertCount(0, $crawler->filter('button[name="remove"]'));
        $this->client->request('POST', '/rapports/archives', ['_token' => $token, 'operation' => 'archive', 'selected' => $keys]);
        self::assertResponseRedirects();

        $this->client->request('GET', '/simulateur?report='.$spy);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#simulation-intel option[value="'.$spy.'"]');

        $crawler = $this->client->request('GET', '/rapports?view=archives');
        self::assertCount(4, $crawler->filter('.report-row'));
        self::assertCount(5, $crawler->filter('button[name="remove"]'));
        $this->client->submit($crawler->filter('button[name="remove"][value="'.$this->keys[1].'"]')->form());
        self::assertResponseRedirects();

        $crawler = $this->client->request('GET', '/rapports?view=archives');
        self::assertCount(3, $crawler->filter('.report-row'));
        self::assertCount(0, $crawler->filter('a.report-row[href*="/rapports/espionnage/"]'));
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/rapports/archives', [
            '_token' => $token, 'view' => 'archives', 'remove' => 'selection',
            'selected' => [$this->keys[0], $this->keys[2], 'diplomatic:'.$diplomaticId],
        ]);
        self::assertResponseRedirects();

        foreach (['/rapports', '/rapports?view=archives', '/rapports?view=archives&participant=ArchiveOwner'] as $url) {
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertCount(0, $crawler->filter('.report-row'));
        }
        foreach (["/rapports/combat/$combat", "/rapports/espionnage/$spy", "/rapports/transport/$transport", "/rapports/diplomatie/$diplomaticId"] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/simulateur?report='.$spy);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('body', 'Rapport inaccessible dans cet univers.');
        self::assertSelectorNotExists('#simulation-intel option[value="'.$spy.'"]');

        // Les identifiants et marqueurs de consultation restent présents en base.
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $connection = $em->getConnection();
        self::assertSame(4, (int) $connection->fetchOne('SELECT COUNT(*) FROM report_archive WHERE player_universe_id = ? AND archived_at IS NOT NULL AND removed_at IS NOT NULL', [$memberId]));
        foreach (['combat_log', 'espionage_report', 'transport_report', 'diplomatic_report'] as $table) {
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM '.$table));
        }
        $notices = self::getContainer()->get(\App\Repository\ReportNoticeRepository::class);
        $currentMember = $em->find(\App\Entity\PlayerUniverse::class, $memberId);
        $currentRecipient = $em->find(\App\Entity\PlayerUniverse::class, $recipientId);
        self::assertSame([], $notices->unreadKeys($currentMember, $keys));
        self::assertSame(0, $notices->count($currentMember));
        self::assertFalse($notices->consult($currentMember, 'transport', $transport, new \DateTimeImmutable()));
        self::assertSame(1, $notices->count($currentRecipient));

        $this->client->loginUser($this->recipient);
        $this->client->request('GET', "/rapports/transport/$transport");
        self::assertResponseIsSuccessful();
        $this->client->request('GET', "/rapports/combat/$combat");
        self::assertResponseIsSuccessful();

        $crawler = $this->client->request('GET', '/rapports?type=economy');
        $recipientToken = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/rapports/archives', ['_token' => $recipientToken, 'operation' => 'archive', 'single' => $this->keys[2]]);
        self::assertResponseRedirects();
        $this->client->request('POST', '/rapports/archives', ['_token' => $recipientToken, 'remove' => $this->keys[2]]);
        self::assertResponseRedirects();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $repository = self::getContainer()->get(\App\Repository\GameRepository::class);
        $currentRecipient = $em->find(\App\Entity\PlayerUniverse::class, $recipientId);
        // Sans perdre l’identité du destinataire qui autorise composition et fret,
        // la ligne ne propose plus de lien vers le rapport retiré.
        self::assertSame([$fleetId => ['id' => $transport, 'removed' => true]], $repository->incomingTransportReportStates($currentRecipient, [$fleetId]));
    }

    public function testRemovalSelectionRejectsActiveForeignAndAlreadyRemovedReports(): void
    {
        $foreign = $this->transport($this->foreign, $this->foreign, '2026-11-12T15:00:00Z');
        $this->em->flush();
        $foreignId = $foreign->id;
        $crawler = $this->client->request('GET', '/rapports');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/rapports/archives', ['_token' => $token, 'operation' => 'archive', 'selected' => [$this->keys[0]]]);
        self::assertResponseRedirects();

        foreach ([$this->keys[1], 'economy:'.$foreignId] as $unavailable) {
            $this->client->request('POST', '/rapports/archives', [
                '_token' => $token, 'view' => 'archives', 'remove' => 'selection', 'selected' => [$this->keys[0], $unavailable],
            ]);
            self::assertResponseRedirects();
            $crawler = $this->client->followRedirect();
            self::assertCount(1, $crawler->filter('.report-row'));
            self::assertSelectorTextContains('body', 'Aucun changement appliqué.');
        }
        $this->client->request('POST', '/rapports/archives', ['_token' => 'forged', 'remove' => $this->keys[0]]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/rapports/archives', ['_token' => $token, 'remove' => $this->keys[0]]);
        self::assertResponseRedirects();
        $this->client->request('POST', '/rapports/archives', ['_token' => $token, 'operation' => 'restore', 'single' => $this->keys[0]]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'Aucun changement appliqué.');
        $this->client->request('GET', '/rapports?type=combat');
        self::assertSelectorNotExists('.report-row');
    }

}
