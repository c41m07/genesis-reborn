<?php
namespace App\Tests\Integration;

use App\Entity\{CombatEvent,CombatLog,EmpireConversion,EmpireInactivityWarning,Fleet,FleetShip,Planet,PlanetBuilding,PlanetOccupation,PlanetResource,Player,PlayerDiscovery,PlayerTechnology,QueueItem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Combat\CombatEventService;
use App\Service\Fleet\FleetService;
use App\Service\Player\{EmpireActivityService,EmpireConversionBatch,EmpireInactivityWarningService,PlayerProtection,RegistrationService,VacationEconomyService};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ForgottenEmpireJourneyTest extends WebTestCase
{
    private \Symfony\Bundle\FrameworkBundle\KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Planet $planet;
    private array $environment;
    private array $messages=[];

    protected function setUp(): void
    {
        $name='FORGOTTEN_EMPIRES_ENABLED';$this->environment=[$_SERVER[$name]??null,$_ENV[$name]??null,getenv($name)];
        $_SERVER[$name]=$_ENV[$name]='1';putenv($name.'=1');
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');static::getContainer()->set('clock',$this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();$this->player=$this->player('Journey');
        $this->planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->player->currentPlayerUniverse]);
        $this->player->currentPlayerUniverse->recoveryProtectedUntil=$this->clock->now()->modify('+45 days');$this->em->flush();
    }

    protected function tearDown(): void
    {
        parent::tearDown();$name='FORGOTTEN_EMPIRES_ENABLED';
        foreach(['_SERVER','_ENV'] as $index=>$global) {
            if($this->environment[$index]===null) unset($GLOBALS[$global][$name]);else $GLOBALS[$global][$name]=$this->environment[$index];
        }
        putenv($this->environment[2]===false?$name:$name.'='.$this->environment[2]);
    }

    public function testWarningsConversionRestartAndConquestOfTheOriginalEmpire(): void
    {
        $former=$this->player->currentPlayerUniverse;$universe=$former->universe;
        $research=new PlayerTechnology();$research->playerUniverse=$former;$research->slug='weapon_calibration';$research->level=7;$this->em->persist($research);
        $this->idle($this->player,$this->planet,['fighter'=>1]);$this->em->flush();
        $this->clock->sleep(14*86400);self::assertSame(1,$this->warnings()->run(true)['sent']);self::assertSame(0,$this->warnings()->run(true)['sent']);
        self::assertFalse(static::getContainer()->get(PlayerProtection::class)->status($former,0)['active']);
        $this->clock->sleep(13*86400);self::assertSame(1,$this->warnings()->run(true)['sent']);
        self::assertCount(2,$this->messages);self::assertStringContainsString('08/12/2026 à 23:00 UTC',$this->messages[0]->getTextBody());
        $this->clock->sleep(3*86400);static::getContainer()->get(EmpireActivityService::class)->assertCanControl($former);
        self::assertSame(0,static::getContainer()->get(EmpireConversionBatch::class)->run(true)['converted']);$this->clock->sleep(11*3600);
        self::assertSame(1,static::getContainer()->get(EmpireConversionBatch::class)->run(true)['converted']);
        $this->client->loginUser($this->player);$this->client->request('GET','/planetes');self::assertResponseRedirects('/univers');
        self::assertTrue($former->isForgotten());self::assertSame($former->id,$this->planet->owner->id);self::assertSame(7,$research->level);
        self::assertSame(0,$this->em->getRepository(\App\Entity\PlanetNpcShip::class)->count(['planet'=>$this->planet]));
        self::assertSame(0,$this->warnings()->run(true)['sent']);

        $crawler=$this->client->request('GET','/univers');$this->client->submit($crawler->filter('form[action="/univers/'.$universe->id.'/rejoindre"]')->form());
        self::assertResponseRedirects('/');$this->player=$this->em->find(Player::class,$this->player->id);$fresh=$this->player->currentPlayerUniverse;
        self::assertNotSame($former->id,$fresh->id);self::assertSame(1,$this->em->getRepository(Planet::class)->count(['owner'=>$fresh]));
        self::assertSame(0,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$fresh]));
        $this->client->request('GET','/planetes/'.$this->planet->id);self::assertResponseStatusCodeSame(404);

        $attacker=$this->player('Conqueror');$origin=$this->originInFormerSystem($attacker);$member=$attacker->currentPlayerUniverse;
        $expansion=new PlayerTechnology();$expansion->playerUniverse=$member;$expansion->slug='colonial_expansion';$expansion->level=1;$this->em->persist($expansion);
        $this->idle($attacker,$origin,['fighter'=>12,'battleship'=>4,'transport'=>5,'colony_ship'=>1]);
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$origin]) as $stock) $stock->currentStock=1000000;
        $this->em->flush();$this->client->loginUser($attacker);
        foreach(['/carte','/carte-v2'] as $route) {
            $this->client->request('GET',$route.'?system='.$this->planet->solarSystem->id.'&galaxy='.$this->planet->solarSystem->galaxy->id);self::assertResponseIsSuccessful();
            self::assertSelectorExists('a[href="/flottes?mission=conquer&planet='.$this->planet->id.'"]');
        }
        $crawler=$this->client->request('GET','/flottes?mission=conquer&planet='.$this->planet->id);self::assertResponseIsSuccessful();
        $form=$crawler->filter('.fleet-form form')->first()->form();$form['mission']='conquer';$form['coordinates']=$this->planet->coordinates();$form['ratio']='0.1';
        foreach(['fighter'=>12,'battleship'=>4,'transport'=>5,'colony_ship'=>1] as $slug=>$quantity) $form['ships['.$slug.']']=(string)$quantity;
        $this->client->submit($form);self::assertResponseRedirects('/flottes');
        $fleet=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'mission'=>'conquer','status'=>'traveling']);self::assertNotNull($fleet);
        $this->clock->sleep($fleet->travelSeconds);static::getContainer()->get(FleetService::class)->arrive($fleet->id,$fleet->dispatchToken);
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->planet,'status'=>'pending']);self::assertNotNull($event);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);static::getContainer()->get(CombatEventService::class)->resolve($event->id,$event->dispatchToken);
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->planet]);self::assertNotNull($occupation);self::assertSame($former->id,$this->planet->owner->id);
        $log=$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$event]);$this->client->request('GET','/rapports/combat/'.$log->id);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.mission-report','La planète n’est pas encore acquise');
        $entries=array_values(array_filter($log->snapshot['participants'],static fn(array $entry): bool=>($entry['player_universe_id']??null)===$former->id));
        self::assertSame(7,$entries[0]['technology_levels']['weapon_calibration']['basic']);
        // Un autre vaisseau au dépôt ne libère pas la place réservée par l'occupation.
        $depot=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'status'=>'idle']);
        $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$depot,'slug'=>'colony_ship'])->quantity=1;$this->em->flush();
        foreach(['/carte','/carte-v2'] as $route) {
            $this->client->request('GET',$route.'?system='.$this->planet->solarSystem->id.'&galaxy='.$this->planet->solarSystem->galaxy->id);self::assertResponseIsSuccessful();
            self::assertSelectorExists('a[href="/flottes?mission=defend&planet='.$this->planet->id.'"]');
            self::assertSelectorNotExists('a[href="/flottes?mission=conquer&planet='.$this->planet->id.'"]');
            self::assertSelectorNotExists('form.map-quick-mission input[name="mission"][value="colonize"]');
            self::assertSelectorExists('time[datetime="'.$occupation->captureAt->format(DATE_ATOM).'"]');
        }
        $this->player=$this->em->find(Player::class,$this->player->id);$fresh=$this->player->currentPlayerUniverse;
        $this->planet=$this->em->find(Planet::class,$this->planet->id);
        $discovery=new PlayerDiscovery();$discovery->playerUniverse=$fresh;$discovery->solarSystem=$this->planet->solarSystem;
        if($this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$fresh,'solarSystem'=>$this->planet->solarSystem])===null) $this->em->persist($discovery);
        $expansion=new PlayerTechnology();$expansion->playerUniverse=$fresh;$expansion->slug='colonial_expansion';$expansion->level=1;$this->em->persist($expansion);
        $newHome=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$fresh]);$this->idle($this->player,$newHome,['colony_ship'=>1,'transport'=>1]);$this->em->flush();
        $this->client->loginUser($this->player);
        foreach(['/carte','/carte-v2'] as $route) {
            $this->client->request('GET',$route.'?system='.$this->planet->solarSystem->id.'&galaxy='.$this->planet->solarSystem->galaxy->id);self::assertResponseIsSuccessful();
            self::assertSelectorExists('a[href="/flottes?mission=attack&planet='.$this->planet->id.'"]');
            self::assertSelectorExists('a[href="/flottes?mission=conquer&planet='.$this->planet->id.'"]');
            self::assertSelectorNotExists('a[href="/flottes?mission=defend&planet='.$this->planet->id.'"]');
        }
        $fresh=$this->em->find(\App\Entity\PlayerUniverse::class,$fresh->id);$member=$this->em->find(\App\Entity\PlayerUniverse::class,$member->id);
        $contract=new \App\Entity\AllianceContract();$contract->playerA=$fresh;$contract->playerB=$member;$contract->proposer=$fresh;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        foreach(['/carte','/carte-v2'] as $route) {
            $this->client->request('GET',$route.'?system='.$this->planet->solarSystem->id.'&galaxy='.$this->planet->solarSystem->galaxy->id);self::assertResponseIsSuccessful();
            self::assertSelectorExists('a[href="/flottes?mission=defend&planet='.$this->planet->id.'"]');
            self::assertSelectorNotExists('a[href="/flottes?mission=attack&planet='.$this->planet->id.'"]');
            self::assertSelectorNotExists('a[href="/flottes?mission=conquer&planet='.$this->planet->id.'"]');
        }
        $this->planet=$this->em->find(Planet::class,$this->planet->id);
        $message=new \App\Message\OccupationDue($occupation->id,$occupation->dispatchToken);$handler=static::getContainer()->get(\App\MessageHandler\OccupationDueHandler::class);
        $this->clock->sleep(86400-1);$handler($message);self::assertSame($former->id,$this->planet->owner->id);
        $this->clock->sleep(1);$handler($message);$handler($message);self::assertSame($member->id,$this->em->find(Planet::class,$this->planet->id)->owner->id);self::assertSame(7,$research->level);
        self::assertSame(0,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$member,'slug'=>'weapon_calibration']));
        self::assertSame(1,$this->em->getRepository(EmpireConversion::class)->count([]));self::assertSame(2,$this->em->getRepository(EmpireInactivityWarning::class)->count(['membership'=>$former]));
    }

    public function testReturnBeforeDayThirtyRestoresProtectionAndStartsANewWarningPeriod(): void
    {
        $member=$this->player->currentPlayerUniverse;$this->clock->sleep(14*86400);self::assertSame(1,$this->warnings()->run(true)['sent']);
        self::assertFalse(static::getContainer()->get(PlayerProtection::class)->status($member,0)['active']);
        $this->clock->sleep(15*86400);$this->client->loginUser($this->player);$this->client->request('GET','/planetes');self::assertResponseIsSuccessful();
        $this->em->refresh($member);self::assertEquals($this->clock->now(),$member->lastActivityAt);
        self::assertTrue(static::getContainer()->get(PlayerProtection::class)->status($member,0)['active']);
        $this->clock->sleep(86400);self::assertSame(0,static::getContainer()->get(EmpireConversionBatch::class)->run(true)['converted']);
        $this->clock->sleep(13*86400);self::assertSame(1,$this->warnings()->run(true)['sent']);
        self::assertSame(2,$this->em->getRepository(EmpireInactivityWarning::class)->count(['membership'=>$member,'thresholdDays'=>14]));
    }

    public function testMaximumVacationShiftsWarningsAndConversionWithoutProgressUnderProtection(): void
    {
        $member=$this->player->currentPlayerUniverse;$lastActivity=$member->lastActivityAt;$this->clock->sleep(13*86400);
        $queue=new QueueItem();$queue->playerUniverse=$member;$queue->planet=$this->planet;$queue->queueType='building';$queue->itemSlug='mine_metal';$queue->itemLevel=2;
        $queue->queuedAt=$this->clock->now();$queue->startedAt=$queue->queuedAt;$queue->durationSeconds=600;$queue->dispatchToken='journey-paid-building';$this->em->persist($queue);$this->em->flush();
        $resources=static::getContainer()->get(\App\Service\Colony\ResourceService::class);$before=$resources->snapshot($this->planet);
        $period=static::getContainer()->get(VacationEconomyService::class)->start($member);$this->clock->sleep(90*86400-1);
        self::assertSame(0,$this->warnings()->run(true)['sent']);self::assertSame(0,static::getContainer()->get(EmpireConversionBatch::class)->run(true)['converted']);
        $this->client->loginUser($this->player);$this->client->request('GET','/planetes');self::assertResponseIsSuccessful();$this->em->refresh($member);self::assertEquals($lastActivity,$member->lastActivityAt);
        foreach($resources->snapshot($this->planet) as $slug=>$stock) self::assertEqualsWithDelta($before[$slug]['stock'],$stock['stock'],0.00001);
        self::assertNotNull($this->em->find(QueueItem::class,$queue->id));$this->clock->sleep(1);self::assertFalse($period->isActiveAt($this->clock->now()));
        $this->clock->sleep(86400);self::assertSame(1,$this->warnings()->run(true)['sent']);
        self::assertStringContainsString(\App\Service\Player\EmpireConversionSchedule::atOrAfter($lastActivity->modify('+120 days'))->format('d/m/Y à H:i').' UTC',$this->messages[0]->getTextBody());
        $this->clock->sleep(13*86400);self::assertSame(1,$this->warnings()->run(true)['sent']);
        $this->clock->sleep(3*86400);self::assertSame(0,static::getContainer()->get(EmpireConversionBatch::class)->run(true)['converted']);$this->clock->sleep(11*3600);
        self::assertSame(1,static::getContainer()->get(EmpireConversionBatch::class)->run(true)['converted']);
        self::assertTrue($member->isForgotten());self::assertSame(2,$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$this->planet,'slug'=>'mine_metal'])->level);
    }

    private function warnings(): EmpireInactivityWarningService
    {
        $mailer=$this->createStub(MailerInterface::class);$mailer->method('send')->willReturnCallback(function(Email $message): void { $this->messages[]=$message; });
        return new EmpireInactivityWarningService(static::getContainer()->get(GameManager::class),static::getContainer()->get(GameRepository::class),$mailer,
            static::getContainer()->get('router'),$this->clock,new \Psr\Log\NullLogger(),static::getContainer()->get(EmpireActivityService::class),'https://game.example.test','game@example.test',true);
    }
    private function player(string $name): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);$player->emailVerifiedAt=$this->clock->now();$this->em->flush();
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);static::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function idle(Player $player,Planet $planet,array $ships): void
    {
        $fleet=new Fleet();$fleet->playerUniverse=$player->currentPlayerUniverse;$fleet->homePlanet=$planet;$this->em->persist($fleet);
        foreach($ships as $slug=>$quantity) { $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->quantity=$quantity;$this->em->persist($ship); }
        $this->em->flush();
    }
    private function originInFormerSystem(Player $player): Planet
    {
        // Le scénario reste déterministe sans voyage intergalactique : seul le placement du fixture change.
        $member=$player->currentPlayerUniverse;$original=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$member]);
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$this->planet->solarSystem,'owner'=>null,'npcPlanet'=>false]);self::assertNotNull($planet);
        $original->owner=null;$planet->owner=$member;static::getContainer()->get(\App\Service\Colony\ResourceService::class)->initialize($planet);
        $criteria=['playerUniverse'=>$member,'solarSystem'=>$planet->solarSystem];
        if($this->em->getRepository(PlayerDiscovery::class)->findOneBy($criteria)===null) { $discovery=new PlayerDiscovery();$discovery->playerUniverse=$member;$discovery->solarSystem=$planet->solarSystem;$this->em->persist($discovery); }
        $this->em->flush();return $planet;
    }
}
