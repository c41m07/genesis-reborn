<?php
namespace App\Tests\Controller;

use App\Entity\{Planet, Player, QueueItem, Universe, VacationPeriod};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class VacationPreferencesTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private array $previousEnvironment;

    protected function setUp(): void
    {
        $name='FORGOTTEN_EMPIRES_ENABLED';
        $this->previousEnvironment=[$_SERVER[$name]??null,$_ENV[$name]??null,getenv($name)];
        $_SERVER[$name]=$_ENV[$name]='1';
        putenv($name.'=1');
        $this->client=static::createClient(['environment'=>'test'],['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);
        $metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');
        static::getContainer()->set('clock',$this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        $data=new RegistrationData();
        $data->email='vacation-ui@example.test';
        $data->username='VacationUI';
        $data->plainPassword='A-test-password-2026';
        $this->player=static::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($this->player,$universe->id);
        $this->client->loginUser($this->player);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $name='FORGOTTEN_EMPIRES_ENABLED';
        foreach(['_SERVER','_ENV'] as $index=>$global) {
            if($this->previousEnvironment[$index]===null) unset($GLOBALS[$global][$name]);
            else $GLOBALS[$global][$name]=$this->previousEnvironment[$index];
        }
        putenv($this->previousEnvironment[2]===false?$name:$name.'='.$this->previousEnvironment[2]);
    }

    public function testActivationAndExitRespectMinimumDurationAndCooldown(): void
    {
        $crawler=$this->client->request('GET','/profil/preferences');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('label[for="vacation-confirm"]');
        $form=$crawler->selectButton('Activer le mode vacances dans cet univers')->form();
        $form['confirm']->tick();
        $this->client->submit($form);
        self::assertResponseRedirects('/profil/preferences#vacances');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#vacances','Vacances actives');
        self::assertEquals($this->clock->now(),$this->em->getRepository(VacationPeriod::class)->findOneBy([])->startedAt);
        self::assertSelectorExists('form[action$="/vacances/terminer"] button[disabled]');
        $this->clock->sleep(48*3600);
        $this->client->request('GET','/profil/preferences');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $this->client->loginUser($this->player);
        $crawler=$this->client->request('GET','/profil/preferences');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#vacances');
        self::assertSelectorExists('form[action$="/vacances/terminer"] button:not([disabled])');
        $this->client->submit($crawler->selectButton('Terminer mes vacances dans cet univers')->form());
        self::assertResponseRedirects('/profil/preferences#vacances');
        $this->client->followRedirect();
        self::assertSelectorTextContains('#vacances','Nouvelle activation possible');
        self::assertSelectorExists('form[action$="/vacances/activer"] button[disabled]');
        self::assertSame(1,$this->em->getRepository(VacationPeriod::class)->count([]));
    }

    public function testConfirmationIsRequiredOnServer(): void
    {
        $crawler=$this->client->request('GET','/profil/preferences');
        $this->client->submit($crawler->selectButton('Activer le mode vacances dans cet univers')->form());
        self::assertResponseRedirects('/profil/preferences#vacances');
        self::assertSame(0,$this->em->getRepository(VacationPeriod::class)->count([]));
        $this->client->followRedirect();
        self::assertSelectorTextContains('body','Confirmez avoir lu');
    }

    public function testTamperedMembershipIsRejected(): void
    {
        $crawler=$this->client->request('GET','/profil/preferences');
        $form=$crawler->selectButton('Activer le mode vacances dans cet univers')->form();
        $values=$form->getPhpValues();
        $values['membership_id']=(string)($this->player->currentPlayerUniverse->id+1000);
        $values['confirm']='1';
        $this->client->request('POST',$form->getUri(),$values);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0,$this->em->getRepository(VacationPeriod::class)->count([]));
    }

    public function testInvalidTokenIsRejected(): void
    {
        $this->client->request('POST','/profil/preferences/vacances/activer',[
            'membership_id'=>$this->player->currentPlayerUniverse->id,'confirm'=>'1','_token'=>'invalid',
        ]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(0,$this->em->getRepository(VacationPeriod::class)->count([]));
    }

    public function testForgedEarlyExitIsRejectedOnServer(): void
    {
        $crawler=$this->client->request('GET','/profil/preferences');
        $form=$crawler->selectButton('Activer le mode vacances dans cet univers')->form();
        $form['confirm']->tick();
        $this->client->submit($form);
        $crawler=$this->client->followRedirect();
        $form=$crawler->filter('form[action$="/vacances/terminer"]')->form();
        $this->client->request('POST',$form->getUri(),$form->getPhpValues());
        self::assertResponseRedirects('/profil/preferences#vacances');
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM vacation_period WHERE ended_at IS NOT NULL'));
    }

    public function testVacationControlsAreHiddenWithoutSelectedUniverse(): void
    {
        $this->player->currentPlayerUniverse=null;
        $this->em->flush();
        $this->client->request('GET','/profil/preferences');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#vacances');
        $this->client->request('POST','/profil/preferences/vacances/activer',['confirm'=>'1']);
        self::assertResponseStatusCodeSame(403);
    }

    public function testMovingFleetExplainsWhyActivationIsUnavailable(): void
    {
        $fleet=new \App\Entity\Fleet();
        $fleet->playerUniverse=$this->player->currentPlayerUniverse;
        $fleet->homePlanet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$fleet->playerUniverse]);
        $fleet->mission='transport';
        $fleet->status='returning';
        $this->em->persist($fleet);
        $this->em->flush();
        $this->client->request('GET','/profil/preferences');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#vacation-start-blocker','Toutes vos flottes doivent être rentrées');
        self::assertSelectorExists('form[action$="/vacances/activer"] button[disabled]');
        self::assertSelectorNotExists('#vacation-confirm');
    }

    public function testDisabledFeatureHidesControlsAndRejectsDirectActivation(): void
    {
        self::ensureKernelShutdown();
        $_SERVER['FORGOTTEN_EMPIRES_ENABLED']=$_ENV['FORGOTTEN_EMPIRES_ENABLED']='0';
        putenv('FORGOTTEN_EMPIRES_ENABLED=0');
        $this->client=static::createClient(['environment'=>'test'],['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->client->loginUser($this->player);
        $this->client->request('GET','/profil/preferences');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#vacances');
        $id=$this->player->currentPlayerUniverse->id;
        $request=$this->client->getRequest();
        $stack=static::getContainer()->get('request_stack');
        $stack->push($request);
        try {
            $token=static::getContainer()->get('security.csrf.token_manager')->getToken('vacation-start-'.$id)->getValue();
            $request->getSession()->save();
        } finally { $stack->pop(); }
        $this->client->request('POST','/profil/preferences/vacances/activer',['membership_id'=>$id,'confirm'=>'1','_token'=>$token]);
        self::assertResponseRedirects('/profil/preferences#vacances');
        $em=static::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(0,$em->getRepository(VacationPeriod::class)->count([]));
    }

    public function testPausedQueueHasStaticProgressAndNoMutationForms(): void
    {
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->player->currentPlayerUniverse]);
        $queue=new QueueItem();
        $queue->planet=$planet;
        $queue->playerUniverse=$this->player->currentPlayerUniverse;
        $queue->queueType='building';
        $queue->itemSlug='mine_metal';
        $queue->itemLevel=1;
        $queue->durationSeconds=600;
        $queue->startedAt=$this->clock->now()->modify('-120 seconds');
        $queue->queuedAt=$queue->startedAt;
        $queue->dispatchToken='vacation-ui-test';
        $planet->buildingQueueStartedAt=$queue->startedAt;
        $this->em->persist($queue);
        $this->em->flush();
        $crawler=$this->client->request('GET','/profil/preferences');
        $form=$crawler->selectButton('Activer le mode vacances dans cet univers')->form();
        $form['confirm']->tick();
        $this->client->submit($form);
        self::assertResponseRedirects('/profil/preferences#vacances');
        $crawler=$this->client->request('GET','/planetes/'.$planet->id.'?section=buildings');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-vacation-paused] [role="progressbar"][aria-valuenow="20"]');
        self::assertSelectorNotExists('[data-vacation-paused] [data-controller="progress"]');
        self::assertSelectorNotExists('[data-live-region="queues"] form');
        self::assertSelectorNotExists('.building-card form[action*="/construire"]');
        $this->clock->sleep(3600);
        $this->client->request('GET','/planetes/'.$planet->id.'?section=buildings');
        self::assertSelectorExists('[data-vacation-paused] [role="progressbar"][aria-valuenow="20"]');
    }
}
