<?php
namespace App\Tests\Controller;

use App\Entity\{Player,PlayerUniverse,Universe,Planet,Fleet,FleetShip,FleetComposition,SocialMessage,EspionageReport};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Service\Fleet\FleetCompositionService;
use App\Service\Combat\{PlayerCombatSimulation,CombatSimulator,CombatUnitFactory};
use App\Service\Search\{MessageSearch,SearchCriteria};
use App\Service\Catalog\GameCatalog;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class StrategicToolsTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private Player $owner;
    private Player $other;
    private Player $foreign;

    protected function setUp(): void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        static::getContainer()->get(UniverseImporter::class)->import();
        $this->owner=$this->player('StrategyOwner');$this->other=$this->player('StrategyOther');$this->foreign=$this->player('StrategyForeign','turbo-1');
    }
    private function player(string $name,string $universe='omega-1'): Player
    {
        $data=new RegistrationData();$data->username=$name;$data->email=strtolower($name).'@example.test';$data->plainPassword='Strategic-test-2026!';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);
        $target=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>$universe]);static::getContainer()->get(UniverseService::class)->select($player,$target->id);return $player;
    }
    private function compositions(): FleetCompositionService { return static::getContainer()->get(FleetCompositionService::class); }
    private function home(Player $player): Planet { return $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]); }
    private function create(string $key='aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'): FleetComposition
    {
        return $this->compositions()->save($this->owner->currentPlayerUniverse,0,'Escorte',['fighter'=>['basic'=>3,'superior'=>1]],0,$key);
    }
    public function testCompositionCreationIsIdempotentAndVersioned(): void
    {
        $first=$this->create();$id=$first->id;$version=$first->version;
        $same=$this->compositions()->save($this->owner->currentPlayerUniverse,0,'Autre',['probe'=>['basic'=>99]],0,$first->requestKey);
        self::assertSame($id,$same->id);self::assertSame('Escorte',$same->name);self::assertCount(1,$this->compositions()->all($this->owner->currentPlayerUniverse));
        $this->compositions()->save($this->owner->currentPlayerUniverse,$id,'Renommée',['fighter'=>['basic'=>2]],$version,$first->requestKey);
        self::assertGreaterThan($version,$first->version);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('a changé');
        $this->compositions()->save($this->owner->currentPlayerUniverse,$id,'Périmée',['fighter'=>['basic'=>9]],$version,$first->requestKey);
    }
    public function testCompositionLimitIsTwentyPerEmpire(): void
    {
        for($i=0;$i<20;$i++) $this->create(str_pad(dechex($i),32,'0',STR_PAD_LEFT));
        self::assertCount(20,$this->compositions()->all($this->owner->currentPlayerUniverse));
        $other=$this->compositions()->save($this->other->currentPlayerUniverse,0,'Autre empire',['probe'=>['basic'=>1]],0,str_repeat('f',32));self::assertNotNull($other->id);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('20 compositions');$this->create(str_repeat('b',32));
    }
    public function testCompositionPermissionsAndUniverseIsolationOnHttp(): void
    {
        $composition=$this->create();$id=$composition->id;
        foreach([$this->other,$this->foreign] as $player) {
            $this->client->loginUser($player);$this->client->request('GET','/flottes/compositions?edit='.$id);self::assertResponseStatusCodeSame(404);
            $this->client->request('GET','/flottes/compositions/'.$id.'/charger');self::assertResponseStatusCodeSame(404);
        }
        $this->client->loginUser($this->owner);$this->client->request('POST','/flottes/compositions',['_token'=>'invalid','id'=>$id,'action'=>'delete']);self::assertResponseStatusCodeSame(403);
    }
    public function testLoadingDisclosesMissingShipsAndNeverSendsFleet(): void
    {
        $composition=$this->create();$home=$this->home($this->owner);$fleet=new Fleet();$fleet->playerUniverse=$this->owner->currentPlayerUniverse;$fleet->homePlanet=$home;$this->em->persist($fleet);
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=2;$this->em->persist($ship);$this->em->flush();
        $plan=$this->compositions()->plan($this->owner->currentPlayerUniverse,$composition,$home);
        self::assertTrue($plan['missing']);self::assertSame(1,$plan['rows'][0]['missing']);self::assertSame(['fighter'=>['basic'=>2]],$plan['available']);
        $this->client->loginUser($this->owner);$this->client->request('GET','/flottes/compositions/'.$composition->id.'/charger?origin='.$home->id);self::assertResponseIsSuccessful();self::assertSelectorTextContains('main','Accepter les quantités disponibles');
        $this->client->request('GET','/flottes?composition='.$composition->id.'&origin='.$home->id.'&confirmation=stale&adjust=1');self::assertResponseRedirects('/flottes/compositions/'.$composition->id.'/charger?origin='.$home->id);
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM fleet WHERE status='traveling'"));
    }
    public function testSimulationMatchesRealEngineAndDoesNotWriteGameState(): void
    {
        $owner=$this->owner->currentPlayerUniverse;$service=static::getContainer()->get(PlayerCombatSimulation::class);$factory=static::getContainer()->get(CombatUnitFactory::class);$catalog=static::getContainer()->get(GameCatalog::class);
        $forces=['attacker'=>['ships'=>['fighter'=>['basic'=>8]],'research'=>['weapons_engineering'=>['basic'=>2]]],'defender'=>['ships'=>['fighter'=>['intermediate'=>2]],'defenses'=>['ground_turret_anti_light'=>['basic'=>1]],'fortress'=>['basic'=>3]]];
        $before=$this->em->getConnection()->fetchAllAssociative('SELECT * FROM planet_resource ORDER BY id');
        $actual=$service->run($owner,$forces,12345);self::assertSame($actual,$service->run($owner,$forces,12345));
        $rules=$catalog->rules();$defense=$factory->defense($owner->universe,'ground_turret_anti_light','basic',[],['basic'=>3])+['quantity'=>1,'tier'=>'basic','camp'=>'defender','id'=>'ground_turret_anti_light-basic'];
        $expected=static::getContainer()->get(CombatSimulator::class)->simulate([
            ['id'=>'attacker','role'=>'attacker','camp'=>'attacker','arrival_round'=>1,'units'=>$factory->ships($owner->universe,$forces['attacker']['ships'],$forces['attacker']['research'])],
            ['id'=>'defender','role'=>'defender','camp'=>'defender','arrival_round'=>1,'units'=>$factory->ships($owner->universe,$forces['defender']['ships'],[])],
        ],[$defense],$catalog->all('combat_modifiers')['modifiers'],$rules['max_combat_rounds'],12345,null,$rules['shield_recharge_percent_per_round'],$rules['restore_survivor_shields_after_combat']);
        self::assertSame($expected,$actual);
        $batch=$service->runBatch($owner,$forces,12345);self::assertCount(10,$batch['trials']);
        self::assertSame($before,$this->em->getConnection()->fetchAllAssociative('SELECT * FROM planet_resource ORDER BY id'));
        foreach(['fleet','combat_event','combat_log','queue_item'] as $table) self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table));
    }
    public function testSimulationRejectsOversizedAndMalformedForces(): void
    {
        $service=static::getContainer()->get(PlayerCombatSimulation::class);
        foreach([['attacker'=>['ships'=>'bad']],['attacker'=>['ships'=>['fighter'=>['basic'=>501]]]],['attacker'=>['ships'=>['fighter'=>['unknown'=>1]]]],['attacker'=>['ships'=>['fighter'=>['basic'=>'1.5']]]]] as $forces) {
            try {$service->run($this->owner->currentPlayerUniverse,$forces,42);self::fail('Entrée invalide acceptée.');} catch(\DomainException $e) {self::assertNotEmpty($e->getMessage());}
        }
    }
    public function testIntelUsesOnlyOwnedSnapshotsIncludingLegacyRanks(): void
    {
        $report=new EspionageReport();$report->sender=$this->owner->currentPlayerUniverse;$report->target=$this->home($this->other);$report->createdAt=new \DateTimeImmutable('-4 days');$report->snapshot=['fleets'=>['fighter:superior'=>['quantity'=>4]],'research'=>['weapons_engineering'=>['level'=>2]],'buildings'=>['fortress'=>['level'=>3]]];$this->em->persist($report);$this->em->flush();
        $service=static::getContainer()->get(PlayerCombatSimulation::class);$intel=$service->intel($this->owner->currentPlayerUniverse,$report->id);
        self::assertSame(4,$intel['forces']['ships']['fighter']['superior']);self::assertSame(2,$intel['forces']['research']['weapons_engineering']['basic']);self::assertSame(3,$intel['forces']['fortress']['basic']);self::assertArrayNotHasKey('defenses',$intel['forces']);
        $this->expectException(\DomainException::class);$service->intel($this->other->currentPlayerUniverse,$report->id);
    }
    public function testMessageSearchIsPaginatedScopedAndDoesNotMarkRead(): void
    {
        $owner=$this->owner->currentPlayerUniverse;$other=$this->other->currentPlayerUniverse;
        for($i=0;$i<130;$i++) {$m=new SocialMessage();$m->sender=$other;$m->content='Signal 100% _ numéro '.$i;$m->createdAt=new \DateTimeImmutable('2026-11-12T12:00:00Z');$this->em->persist($m);}
        $private=new SocialMessage();$private->sender=$other;$private->recipient=$owner;$private->content='Réponse confidentielle';$private->createdAt=new \DateTimeImmutable();$this->em->persist($private);
        $secret=new SocialMessage();$secret->sender=$other;$secret->recipient=$this->foreign->currentPlayerUniverse;$secret->content='Inter-univers interdit';$secret->createdAt=new \DateTimeImmutable();$this->em->persist($secret);
        $global=new SocialMessage();$global->sender=$this->foreign->currentPlayerUniverse;$global->content='Signal 100% _ étranger';$global->createdAt=new \DateTimeImmutable();$this->em->persist($global);$this->em->flush();
        $service=static::getContainer()->get(MessageSearch::class);$criteria=new SearchCriteria(['q'=>'100% _','from'=>'2026-11-12','until'=>'2026-11-12']);
        $page=$service->page($owner,$criteria,'global',0,1);self::assertSame(130,$page['count']);self::assertCount(50,$page['entries']);self::assertCount(30,$service->page($owner,$criteria,'global',0,3)['entries']);
        $prefs=$owner->player->preferences;$privatePage=$service->page($owner,new SearchCriteria([]),'private',0,1);self::assertSame([$private->id],array_map(fn($m)=>$m->id,$privatePage['entries']));
        self::assertNull($private->readAt);self::assertSame($prefs,$owner->player->preferences);
        self::assertSame(0,$service->page($owner,new SearchCriteria(['q'=>'Inter-univers']),'all',0,1)['count']);
    }
    public function testGuildSearchRequiresCurrentMembershipAndKeepsUnreadState(): void
    {
        $owner=$this->owner->currentPlayerUniverse;$other=$this->other->currentPlayerUniverse;
        $diplomacy=static::getContainer()->get(\App\Service\Player\DiplomacyService::class);$diplomacy->create($owner,'Chercheurs','CHERC');
        $guild=static::getContainer()->get(\App\Service\Player\RelationResolver::class)->guild($owner);$diplomacy->join($other,$guild->id);
        $message=new SocialMessage();$message->sender=$owner;$message->guild=$guild;$message->content='Journal collectif';$message->createdAt=new \DateTimeImmutable();$this->em->persist($message);$this->em->flush();
        $search=static::getContainer()->get(MessageSearch::class);$prefs=$other->player->preferences;
        self::assertSame(1,$search->page($other,new SearchCriteria(['q'=>'Journal']),'guild',0,1)['count']);self::assertSame($prefs,$other->player->preferences);
        $diplomacy->leave($other);self::assertSame(0,$search->page($other,new SearchCriteria(['q'=>'Journal']),'all',0,1)['count']);
    }

    public function testSimulationHttpRejectsCsrfAndDisplaysTenTrials(): void
    {
        $before=$this->em->getConnection()->fetchAllAssociative('SELECT * FROM planet_resource ORDER BY id');
        $this->client->loginUser($this->owner);$crawler=$this->client->request('GET','/simulateur');self::assertResponseIsSuccessful();
        $token=$crawler->filter('.simulation-form input[name="_token"]')->attr('value');
        $payload=['_token'=>$token,'seed'=>'42','assumptions'=>'1','forces'=>['attacker'=>['ships'=>['fighter'=>['basic'=>2]]],'defender'=>['ships'=>['fighter'=>['basic'=>1]]]]];
        $this->client->request('POST','/simulateur',$payload);self::assertResponseIsSuccessful();self::assertSelectorExists('#simulation-result');
        self::assertSelectorCount(10,'.simulation-trial');self::assertSelectorTextContains('#simulation-result-title','Estimation sur 10 combats');
        self::assertSelectorTextContains('#simulation-result','7 victoires donnent 70 %, sans garantir cette probabilité réelle');
        self::assertSame($before,$this->em->getConnection()->fetchAllAssociative('SELECT * FROM planet_resource ORDER BY id'));
        $payload['_token']='forged';$this->client->request('POST','/simulateur',$payload);self::assertResponseStatusCodeSame(403);
    }

    public function testIntelProvenanceDistinguishesUnknownAndModifiedValues(): void
    {
        $service=static::getContainer()->get(PlayerCombatSimulation::class);$intel=['forces'=>['ships'=>['fighter'=>['basic'=>2]],'fortress'=>[]]];
        $forces=['defender'=>['ships'=>['fighter'=>['basic'=>'2','superior'=>'0']],'fortress'=>['basic'=>'0']]];
        $sources=$service->provenance($this->owner->currentPlayerUniverse,$forces,$intel);
        self::assertSame('Rapport daté · valeurs reprises',$sources['ships']);self::assertSame('Rapport daté · valeurs reprises',$sources['fortress']);
        self::assertStringContainsString('Inconnu',$sources['defenses']);
        $forces['defender']['ships']['fighter']['basic']=3;
        self::assertSame('Hypothèse modifiée depuis le rapport',$service->provenance($this->owner->currentPlayerUniverse,$forces,$intel)['ships']);
    }

    public function testCompositionDeletionIsOwnedAndCannotProduceDoubleEffects(): void
    {
        $composition=$this->create();$id=$composition->id;$version=$composition->version;
        $this->client->loginUser($this->other);$crawler=$this->client->request('GET','/flottes/compositions');
        $token=$crawler->filter('main form input[name="_token"]')->first()->attr('value');
        $this->client->request('POST','/flottes/compositions',['_token'=>$token,'action'=>'delete','id'=>$id,'version'=>$version]);self::assertResponseStatusCodeSame(422);
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM fleet_composition'));
    }

    public function testCompositionDeletionCannotProduceDoubleEffects(): void
    {
        $composition=$this->create();$id=$composition->id;$version=$composition->version;
        $this->compositions()->delete($this->owner->currentPlayerUniverse,$id,$version);
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM fleet_composition'));
        $this->expectException(\DomainException::class);$this->compositions()->delete($this->owner->currentPlayerUniverse,$id,$version);
    }

    public function testSearchKeepsPagesBoundedOnLargeSyntheticHistory(): void
    {
        $owner=$this->owner->currentPlayerUniverse;$peer=$this->other->currentPlayerUniverse;$target=$this->home($this->other);$db=$this->em->getConnection();
        for($batch=0;$batch<10;$batch++) {
            $messages=[];$reports=[];
            for($i=0;$i<500;$i++) {
                $messages[]="({$peer->id},{$owner->id},'Historique synthétique','2026-11-12 12:00:00')";
                $reports[]="({$owner->id},{$target->id},'success','2026-11-12 12:00:00',1,1,5,0,0,0,'{}')";
            }
            $db->executeStatement('INSERT INTO social_message(sender_id,recipient_id,content,created_at) VALUES '.implode(',',$messages));
            $db->executeStatement('INSERT INTO espionage_report(sender_id,target_id,result,created_at,probes_sent,intel_categories,detection_chance,sender_espionage_level,target_espionage_level,target_counter_espionage_level,snapshot) VALUES '.implode(',',$reports));
        }
        $start=microtime(true);$messages=static::getContainer()->get(MessageSearch::class)->page($owner,new SearchCriteria(['q'=>'synthétique']),'private',0,100);
        self::assertSame(5000,$messages['count']);self::assertSame(100,$messages['pages']);self::assertCount(50,$messages['entries']);
        $reports=static::getContainer()->get(\App\Service\Combat\ReportArchiveService::class)->page($owner,'espionage',false,100,new SearchCriteria(['participant'=>'StrategyOwner','from'=>'2026-11-12']));
        self::assertSame(5000,$reports['report_count']);self::assertCount(50,$reports['entries']);self::assertSame(100,$reports['pages']);
        $plan=$db->fetchAllAssociative("EXPLAIN SELECT id FROM social_message WHERE recipient_id=? AND content LIKE '%synthétique%' ORDER BY created_at DESC,id DESC LIMIT 50 OFFSET 4950",[$owner->id]);
        self::assertNotEmpty($plan);fwrite(STDERR,sprintf("\nHistorique synthétique : 5 000 messages + 5 000 rapports, deux pages filtrées en %.3f s ; index message : %s.\n",microtime(true)-$start,$plan[0]['key']??'parcours'));
    }

    public function testDateFiltersRejectInvalidRangesAndRespectTimezone(): void
    {
        $criteria=new SearchCriteria(['from'=>'2026-03-29','until'=>'2026-03-29'],'Europe/Paris');self::assertSame('2026-03-28 23:00:00',$criteria->since);self::assertSame('2026-03-29 22:00:00',$criteria->before);
        foreach([['from'=>'2026-02-30'],['from'=>'2026-12-10','until'=>'2026-12-01']] as $invalid) {
            try {new SearchCriteria($invalid);self::fail('Date invalide acceptée.');} catch(\DomainException $e) {self::assertNotEmpty($e->getMessage());}
        }
    }
}
