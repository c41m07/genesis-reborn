<?php
namespace App\Tests\Controller;
use App\Entity\{Player,Universe,Planet,PlanetBuilding,Fleet,FleetShip,QueueItem};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
final class GameplayAccessTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    protected function setUp():void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost','HTTP_ORIGIN'=>'http://genesis-reborn.localhost']);
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool=new SchemaTool($this->em);$meta=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($meta);$tool->createSchema($meta);
        static::getContainer()->get(UniverseImporter::class)->import();
    }
    private function player(string $name):array
    {
        $d=new RegistrationData();$d->email=strtolower($name).'@example.test';$d->username=$name;$d->plainPassword='A-test-password-2026';$p=static::getContainer()->get(RegistrationService::class)->register($d);
        $u=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);static::getContainer()->get(UniverseService::class)->select($p,$u->id);
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$p->currentPlayerUniverse]);
        $f=new Fleet();$f->playerUniverse=$p->currentPlayerUniverse;$f->homePlanet=$planet;$this->em->persist($f);
        foreach(['probe','transport','colony_ship'] as $slug){$s=new FleetShip();$s->fleet=$f;$s->slug=$slug;$s->quantity=1;$this->em->persist($s);}$this->em->flush();return [$p,$planet];
    }
    public function testOtherPlayerPlanetResourcesAndActionsAreInaccessible():void
    {
        [$a,$own]=$this->player('Alpha');[$b,$foreign]=$this->player('Beta');$this->client->loginUser($a);
        foreach(['/planetes/'.$foreign->id,'/planetes/'.$foreign->id.'/ressources'] as $path){$this->client->request('GET',$path);self::assertResponseStatusCodeSame(404);}
        $this->client->request('POST','/planetes/'.$foreign->id.'/construire',['group'=>'buildings','slug'=>'mine_metal','_token'=>'forged']);self::assertResponseStatusCodeSame(404);
        $this->client->request('POST','/planetes/'.$own->id.'/construire',['group'=>'buildings','slug'=>'mine_metal','_token'=>'forged']);self::assertResponseStatusCodeSame(403);
    }
    public function testFleetPreviewIsServerAuthoritativeAndCannotUseForeignOrigin():void
    {
        [$a,$own]=$this->player('Alpha');[$b,$foreign]=$this->player('Beta');$this->client->loginUser($a);
        $crawler=$this->client->request('GET','/flottes');self::assertResponseIsSuccessful();$token=$crawler->filter('input[name="_token"]')->first()->attr('value');
        $payload=['_token'=>$token,'origin'=>$own->id,'mission'=>'explore','system'=>$own->solarSystem->id,'ratio'=>'0.5','ships'=>['probe'=>1],'cargo'=>[],'total_fuel'=>0,'seconds'=>0];
        $this->client->request('POST','/flottes/estimer',$payload);self::assertResponseIsSuccessful();$q=json_decode($this->client->getResponse()->getContent(),true);self::assertGreaterThan(0,$q['total_fuel']);self::assertGreaterThan(0,$q['seconds']);
        $payload['origin']=$foreign->id;$this->client->request('POST','/flottes/estimer',$payload);self::assertResponseStatusCodeSame(422);
        $payload['origin']=$own->id;$payload['ships']=['probe'=>999];$this->client->request('POST','/flottes/estimer',$payload);self::assertResponseStatusCodeSame(422);
        $this->client->request('POST','/flottes/estimer',['_token'=>'invalid']);self::assertResponseStatusCodeSame(403);
    }
    public function testNotificationCookieAuthorizesOnlyCurrentMembership():void
    {
        [$a]=$this->player('Alpha');[$b]=$this->player('Beta');$ownId=$a->currentPlayerUniverse->id;$foreignId=$b->currentPlayerUniverse->id;$this->client->loginUser($a);
        $this->client->request('GET','/notifications/abonnement');self::assertResponseIsSuccessful();$cookies=$this->client->getResponse()->headers->getCookies();$jwt=null;
        foreach($cookies as $cookie) if($cookie->getName()==='mercureAuthorization') $jwt=$cookie->getValue();
        self::assertNotNull($jwt);$part=explode('.',$jwt)[1];$claims=json_decode(base64_decode(strtr($part,'-_','+/')),true);
        self::assertSame(['https://genesis-reborn.localhost/players/'.$ownId.'/events'],$claims['mercure']['subscribe']);self::assertStringNotContainsString('/players/'.$foreignId.'/events',json_encode($claims));
    }
    public function testProfileRequiresCurrentPasswordThenLogsOutAfterSave():void
    {
        [$a]=$this->player('Alpha');$this->client->loginUser($a);$crawler=$this->client->request('GET','/profil');self::assertResponseIsSuccessful();
        $form=$crawler->selectButton('Enregistrer mon profil')->form();$this->client->submit($form,['profile[email]'=>'updated@example.test','profile[username]'=>'Updated','profile[currentPassword]'=>'wrong']);self::assertResponseStatusCodeSame(422);
        $crawler=$this->client->request('GET','/profil');$form=$crawler->selectButton('Enregistrer mon profil')->form();$this->client->submit($form,['profile[email]'=>'updated@example.test','profile[username]'=>'Updated','profile[currentPassword]'=>'A-test-password-2026']);self::assertResponseRedirects('/connexion');
        $this->client->request('GET','/profil');self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
    }
    public function testColonySelectorPreservesOwnSelectionAndRejectsForeignPlanet():void
    {
        [$a,$first]=$this->player('Alpha');[$b,$foreign]=$this->player('Beta');
        $second=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$first->solarSystem,'owner'=>null]);
        $second->owner=$a->currentPlayerUniverse;static::getContainer()->get(\App\Service\Colony\ResourceService::class)->initialize($second);$this->em->flush();
        $secondId=$second->id;$foreignId=$foreign->id;$this->client->loginUser($a);
        $crawler=$this->client->request('GET','/planetes/'.$secondId.'?section=technologies');self::assertResponseIsSuccessful();
        self::assertCount(2,$crawler->filter('#sidebar-colony option'));self::assertSame((string)$secondId,$crawler->filter('#sidebar-colony option[selected]')->attr('value'));
        self::assertCount(0,$crawler->filter('#sidebar-colony option[value="'.$foreignId.'"]'));
        self::assertSame('page',$crawler->filter('a[data-colony-section="technologies"]')->attr('aria-current'));
        $this->client->request('GET','/');self::assertResponseRedirects('/planetes/'.$secondId);
        $this->client->request('GET','/?colony='.$foreignId);self::assertResponseStatusCodeSame(404);
    }
    public function testSceneShowsQueueTotalAndOnlyOwnActiveFlightsAcrossColonies():void
    {
        [$a,$first]=$this->player('Alpha');[$b,$foreign]=$this->player('Beta');$m=$a->currentPlayerUniverse;
        $second=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$first->solarSystem,'owner'=>null]);
        $second->owner=$m;static::getContainer()->get(\App\Service\Colony\ResourceService::class)->initialize($second);
        $now=new \DateTimeImmutable();$start=$now->modify('-20 seconds');$first->buildingQueueStartedAt=$start;
        foreach([120,180] as $i=>$seconds){$q=new QueueItem();$q->planet=$first;$q->playerUniverse=$m;$q->queueType='building';$q->itemSlug='mine_metal';$q->itemLevel=$i+1;$q->durationSeconds=$seconds;$q->startedAt=$i===0?$start:null;$q->queuedAt=$start;$q->dispatchToken=\Symfony\Component\Uid\Uuid::v4()->toRfc4122();$this->em->persist($q);}
        $flight=new Fleet();$flight->playerUniverse=$m;$flight->homePlanet=$second;$flight->destination=$first;$flight->destinationSystem=$first->solarSystem;$flight->status='returning';$flight->mission='transport';$flight->departureAt=$start;$flight->arrivalAt=$now->modify('+90 seconds');$this->em->persist($flight);
        $enemy=new Fleet();$enemy->playerUniverse=$b->currentPlayerUniverse;$enemy->homePlanet=$foreign;$enemy->destinationSystem=$foreign->solarSystem;$enemy->status='traveling';$enemy->mission='explore';$enemy->departureAt=$start;$enemy->arrivalAt=$now->modify('+90 seconds');$this->em->persist($enemy);$this->em->flush();
        $flightId=$flight->id;$enemyId=$enemy->id;$this->client->loginUser($a);$crawler=$this->client->request('GET','/planetes/'.$first->id);self::assertResponseIsSuccessful();
        self::assertCount(3,$crawler->filter('.colony-banner [data-queue-type]'));self::assertSame('+1',$crawler->filter('[data-queue-type="building"] .queue-extra')->text());
        self::assertSame($start->modify('+300 seconds')->format('c'),$crawler->filter('[data-queue-type="building"] [data-progress-total-value]')->attr('data-progress-total-value'));
        self::assertCount(1,$crawler->filter('[data-active-fleet="'.$flightId.'"]'));self::assertCount(0,$crawler->filter('[data-active-fleet="'.$enemyId.'"]'));
        self::assertStringContainsString('RETOUR',$crawler->filter('[data-active-fleet="'.$flightId.'"]')->text());
    }

    public function testMapMissionPrefillChecksDiscoveriesOwnershipAndUniverse():void
    {
        [$a,$own]=$this->player('Alpha');[$b,$foreign]=$this->player('Beta');
        $free=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$own->solarSystem,'owner'=>null]);
        $unknown=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$this->em->getRepository(\App\Entity\SolarSystem::class)->findOneBy(['name'=>'Système 12','galaxy'=>$own->solarSystem->galaxy])]);
        $freeId=$free->id;$foreignId=$foreign->id;$unknownId=$unknown->id;$this->client->loginUser($a);
        $crawler=$this->client->request('GET','/flottes?mission=colonize&planet='.$freeId);self::assertResponseIsSuccessful();self::assertSame('colonize',$crawler->filter('select[name="mission"] option[selected]')->first()->attr('value'));self::assertSame((string)$freeId,$crawler->filter('select[name="planet"] option[selected]')->first()->attr('value'));
        $this->client->request('GET','/flottes?mission=transport&planet='.$foreignId);self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/flottes?mission=colonize&planet='.$unknownId);self::assertResponseStatusCodeSame(404);
    }

}
