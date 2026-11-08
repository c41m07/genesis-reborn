<?php
namespace App\Tests\Controller;

use App\Entity\{Player,PlayerUniverse,Universe};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class EmpireConversionAccessTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private PlayerUniverse $membership;
    private array $environment;

    protected function setUp(): void
    {
        $name='FORGOTTEN_EMPIRES_ENABLED';$this->environment=[$_SERVER[$name]??null,$_ENV[$name]??null,getenv($name)];
        $_SERVER[$name]=$_ENV[$name]='1';putenv($name.'=1');
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T22:59:59Z');static::getContainer()->set('clock',$this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        $data=new RegistrationData();$data->email='due-access@example.test';$data->username='DueAccess';$data->plainPassword='A-test-password-2026';
        $this->player=static::getContainer()->get(RegistrationService::class)->register($data);$this->player->emailVerifiedAt=$this->clock->now();$this->em->flush();
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($this->player,$universe->id);$this->membership=$this->player->currentPlayerUniverse;
        $this->membership->lastActivityAt=$this->clock->now()->modify('-30 days');$this->em->flush();$this->client->loginUser($this->player);
    }
    protected function tearDown(): void
    {
        parent::tearDown();$name='FORGOTTEN_EMPIRES_ENABLED';
        foreach(['_SERVER','_ENV'] as $index=>$global) {
            if($this->environment[$index]===null) unset($GLOBALS[$global][$name]);else $GLOBALS[$global][$name]=$this->environment[$index];
        }
        putenv($this->environment[2]===false?$name:$name.'='.$this->environment[2]);
    }
    public function testVisitBeforeMidnightKeepsAccessAndRestartsInactivity(): void
    {
        $this->client->request('GET','/planetes');self::assertResponseIsSuccessful();$this->em->refresh($this->membership);
        self::assertEquals($this->clock->now(),$this->membership->lastActivityAt);
        self::assertNotNull(static::getContainer()->get(\App\Service\Colony\GameShell::class)->get($this->player)['membership']);
    }
    public function testUniverseSelectionReusesTheEmpireUntilActualConversion(): void
    {
        $crawler=$this->client->request('GET','/univers');self::assertResponseIsSuccessful();
        $form=$crawler->filter('form[action="/univers/'.$this->membership->universe->id.'/rejoindre"]')->form();
        $this->client->submit($form);self::assertResponseRedirects('/');
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM player_universe'));
    }
    public function testDelayedMidnightJobDoesNotBlockAVisitBeforeActualConversion(): void
    {
        $this->clock->sleep(1);
        self::assertTrue(static::getContainer()->get(\App\Service\Player\EmpireActivityService::class)->snapshot($this->membership)['conversion_due']);
        $this->client->request('GET','/planetes');self::assertResponseIsSuccessful();$this->em->refresh($this->membership);
        self::assertEquals($this->clock->now(),$this->membership->lastActivityAt);
        self::assertSame(0,static::getContainer()->get(\App\Service\Player\EmpireConversionBatch::class)->run(true)['converted']);
    }
    public function testUnknownActivityStillStartsOnFirstLegitimateVisit(): void
    {
        $this->membership->lastActivityAt=null;$this->em->flush();
        $this->client->request('GET','/planetes');self::assertResponseIsSuccessful();$this->em->refresh($this->membership);
        self::assertEquals($this->clock->now(),$this->membership->lastActivityAt);
    }

    public function testActualMidnightConversionRevokesAccess(): void
    {
        $this->clock->sleep(1);self::assertSame(1,static::getContainer()->get(\App\Service\Player\EmpireConversionBatch::class)->run(true)['converted']);
        $this->client->request('GET','/planetes');self::assertResponseRedirects('/univers');
        self::assertTrue($this->em->find(PlayerUniverse::class,$this->membership->id)->isForgotten());
    }

    public function testCombatDeferredAtMidnightDoesNotBlockTheOwnersReturn(): void
    {
        $planet=$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$this->membership]);
        $combat=new \App\Entity\CombatEvent();$combat->planet=$planet;$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now()->modify('+10 minutes');$combat->seed=42;
        $this->em->persist($combat);$this->em->flush();$this->clock->sleep(1);
        self::assertSame(1,static::getContainer()->get(\App\Service\Player\EmpireConversionBatch::class)->run(true)['deferred']);
        $this->client->request('GET','/planetes');self::assertResponseIsSuccessful();$this->em->refresh($this->membership);
        self::assertEquals($this->clock->now(),$this->membership->lastActivityAt);self::assertTrue($this->membership->isPlayerControlled());
    }
}
