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
        $report->createdAt=new \DateTimeImmutable($date);$report->snapshot=['sender'=>$sender->username,'recipient'=>$recipient->username,'destination'=>'Alpha:1:1'];$this->em->persist($report);return $report;
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
}
