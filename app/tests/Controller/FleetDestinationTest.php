<?php
namespace App\Tests\Controller;

use App\Entity\{AllianceContract,Fleet,FleetShip,Planet,Player,PlayerDiscovery,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Fleet\DestinationCatalogue;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FleetDestinationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Player $player;
    private Planet $home;
    private Planet $own;
    private Planet $ally;
    private Planet $enemy;
    private Planet $neutral;
    private Planet $hidden;
    private Planet $foreign;
    private Planet $npc;
    private Fleet $idle;

    protected function setUp(): void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        static::getContainer()->get(UniverseImporter::class)->import();
        [$this->player,$this->home]=$this->player('DestOwner');
        [$ally,$this->ally]=$this->player('DestAlly');[$enemy,$this->enemy]=$this->player('DestEnemy');
        [, $this->neutral]=$this->player('DestNeutral');
        // Une ancienne planète PNJ peut conserver son drapeau après capture par un joueur neutre.
        $this->neutral->npcPlanet=true;[$hidden,$oldHidden]=$this->player('SecretUnseen');
        [, $this->foreign]=$this->player('ForeignGalaxy','turbo-1');
        foreach([[$ally,'accepted'],[$enemy,'enemies']] as [$other,$status]) {
            $contract=new AllianceContract();$contract->playerA=$this->player->currentPlayerUniverse;$contract->playerB=$other->currentPlayerUniverse;
            $contract->proposer=$this->player->currentPlayerUniverse;$contract->status=$status;$this->em->persist($contract);
        }
        $repo=static::getContainer()->get(GameRepository::class);$member=$this->player->currentPlayerUniverse;
        foreach($repo->planets($member->universe,null,true) as $planet) if(!$planet->npcPlanet) {
            $this->own=$planet;$planet->name='Colonie connue';$planet->acquireBy($member,new \DateTimeImmutable());break;
        }
        foreach($repo->planets($member->universe,null,true) as $planet) if($planet->npcPlanet) {$this->npc=$planet;break;}
        foreach([$this->own,$this->ally,$this->enemy,$this->neutral,$this->npc] as $planet) $this->discover($planet);
        $known=array_column($repo->all(PlayerDiscovery::class,['playerUniverse'=>$member]),'solarSystem');
        $known=array_map(static fn($system)=>$system->id,$known);
        foreach($repo->planets($member->universe,null,true) as $planet) if(!$planet->npcPlanet && !in_array($planet->solarSystem->id,$known,true)) {
            $oldHidden->owner=null;$oldHidden->acquiredAt=null;$planet->acquireBy($hidden->currentPlayerUniverse,new \DateTimeImmutable());$this->hidden=$planet;break;
        }
        $this->idle=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'homePlanet'=>$this->home,'status'=>'idle'])??new Fleet();
        $this->idle->playerUniverse=$member;$this->idle->homePlanet=$this->home;$this->em->persist($this->idle);
        foreach(['probe','fighter','transport'] as $slug) {
            $ship=$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$this->idle,'slug'=>$slug,'tier'=>'basic'])??new FleetShip();
            $ship->fleet=$this->idle;$ship->slug=$slug;$ship->quantity=10;$this->em->persist($ship);
        }
        $this->em->flush();$this->client->loginUser($this->player);
    }

    private function player(string $name,string $slug='omega-1'): array
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        static::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        return [$player,$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse])];
    }

    private function discover(Planet $planet): void
    {
        $criteria=['playerUniverse'=>$this->player->currentPlayerUniverse,'solarSystem'=>$planet->solarSystem];
        if($this->em->getRepository(PlayerDiscovery::class)->findOneBy($criteria)) return;
        $discovery=new PlayerDiscovery();$discovery->playerUniverse=$criteria['playerUniverse'];$discovery->solarSystem=$planet->solarSystem;$this->em->persist($discovery);$this->em->flush();
    }

    public function testGroupsAndSuggestionsExposeOnlyKnownPlanetsOfActiveUniverse(): void
    {
        $member=$this->player->currentPlayerUniverse;$repo=static::getContainer()->get(GameRepository::class);
        foreach($repo->planets($member->universe,null,true) as $planet) if(!$planet->npcPlanet
            && $this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$member,'solarSystem'=>$planet->solarSystem])) {
            // L’anomalie est limitée à ce test de l’API, qui doit la filtrer avant toute donnée.
            $planet->owner=$this->foreign->owner;$planet->name='Relation étrangère';$this->em->flush();break;
        }
        $this->client->request('GET','/flottes/destinations?mission=explore');
        self::assertResponseIsSuccessful();$data=json_decode($this->client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);
        $friendly=array_column($data['groups']['friendly'],'id');$enemies=array_column($data['groups']['enemies'],'id');
        self::assertContains($this->own->id,$friendly);self::assertContains($this->ally->id,$friendly);
        self::assertContains($this->enemy->id,$enemies);self::assertContains($this->npc->id,$enemies);
        self::assertNotContains($this->neutral->id,[...$friendly,...$enemies]);self::assertNotContains($this->hidden->id,[...$friendly,...$enemies]);
        self::assertStringContainsString('no-store',$this->client->getResponse()->headers->get('Cache-Control'));
        foreach(['SecretUnseen','ForeignGalaxy','%%'] as $query) {
            $this->client->request('GET','/flottes/destinations',['mission'=>'explore','q'=>$query,'universe'=>$this->foreign->solarSystem->galaxy->universe->id]);
            self::assertResponseIsSuccessful();$rows=json_decode($this->client->getResponse()->getContent(),true)['suggestions'];self::assertSame([],$rows);
        }
        $this->client->request('GET','/flottes/destinations',['mission'=>'explore','q'=>'destneutral']);
        $rows=json_decode($this->client->getResponse()->getContent(),true)['suggestions'];
        self::assertSame([$this->neutral->id],array_column($rows,'id'));
        $this->client->request('GET','/flottes/destinations',['mission'=>'explore','q'=>'D']);self::assertSame([],json_decode($this->client->getResponse()->getContent(),true)['suggestions']);
    }

    public function testModesAreRevalidatedServerSideAndCustomExplorationRemainsPossible(): void
    {
        $member=$this->player->currentPlayerUniverse;$catalogue=static::getContainer()->get(DestinationCatalogue::class);
        self::assertSame($this->own->solarSystem->id,$catalogue->resolve($member,'explore','friendly','',$this->own->id)->id);
        self::assertSame($this->npc->id,$catalogue->resolve($member,'attack','enemies','',$this->npc->id)->id);
        self::assertSame($this->hidden->solarSystem->id,$catalogue->resolve($member,'explore','custom',$this->hidden->solarSystem->galaxy->coordinateCode().':'.$this->hidden->solarSystem->number())->id);
        $this->client->request('GET','/flottes');
        $token=$this->client->getCrawler()->filter('form[action="/flottes/envoyer"] input[name="_token"]')->first()->attr('value');
        $base=['_token'=>$token,'origin'=>$this->home->id,'mission'=>'explore','ships'=>['probe'=>['basic'=>1]],'ratio'=>1];
        $url=static::getContainer()->get('router')->generate('app_fleet_preview');
        foreach([['friendly',$this->neutral],['friendly',$this->enemy],['enemies',$this->ally],['enemies',$this->hidden],['enemies',$this->foreign]] as [$mode,$planet]) {
            $this->client->request('POST',$url,$base+['destination_mode'=>$mode,$mode.'_destination'=>$planet->id]);
            self::assertResponseStatusCodeSame(422);self::assertStringNotContainsString('SecretUnseen',$this->client->getResponse()->getContent());
        }
        $this->client->request('POST',$url,$base+['destination_mode'=>'invalid']);self::assertResponseStatusCodeSame(422);
        $this->client->request('POST',$url,array_replace($base,['mission'=>'attack','coordinates'=>$this->hidden->coordinates()]));
        self::assertResponseStatusCodeSame(422);self::assertStringContainsString('Explorez',$this->client->getResponse()->getContent());
        self::assertSame(0,$this->em->getRepository(Fleet::class)->count(['status'=>'traveling']));
    }

    public function testDefaultModePrefillAndPnjAttackChoicesKeepMissionRules(): void
    {
        $this->client->request('GET','/flottes');self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="destination_mode"][value="friendly"][checked]');
        $this->client->request('GET','/flottes',['mission'=>'attack','planet'=>$this->npc->id]);self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="destination_mode"][value="enemies"][checked]');
        self::assertSelectorExists('select[name="enemies_destination"] option[value="'.$this->npc->id.'"][selected]');
        $this->client->request('GET','/flottes',['mission'=>'attack','planet'=>$this->hidden->id]);self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/flottes/destinations',['mission'=>'invalid']);self::assertResponseStatusCodeSame(422);
        $this->client->getCookieJar()->clear();static::getContainer()->get('security.token_storage')->setToken(null);
        $this->client->request('GET','/flottes/destinations');self::assertResponseRedirects('/connexion');
    }
}
