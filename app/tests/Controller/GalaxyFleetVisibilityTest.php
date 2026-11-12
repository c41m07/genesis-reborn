<?php
namespace App\Tests\Controller;

use App\Entity\{AllianceContract,Fleet,FleetShip,Planet,PlanetDefense,Player,Universe};
use App\Service\Player\{LeaderboardService,RegistrationService};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GalaxyFleetVisibilityTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host']??'') !== 'database-test' || ($params['dbname']??'') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        static::getContainer()->get(UniverseImporter::class)->import();
    }

    private function player(string $name, string $slug='omega-1'): array
    {
        $data = new RegistrationData();
        $data->email = strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player = static::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        static::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        return [$player,$planet];
    }

    private function fleet(Player $owner, Planet $home, Planet $target, string $mission, string $status='traveling'): Fleet
    {
        $fleet = new Fleet();$fleet->playerUniverse=$owner->currentPlayerUniverse;$fleet->homePlanet=$home;$fleet->destination=$target;
        $fleet->destinationSystem=$target->solarSystem;$fleet->mission=$mission;$fleet->status=$status;
        $fleet->departureAt=new \DateTimeImmutable('-1 minute');$fleet->arrivalAt=new \DateTimeImmutable('+1 hour');
        $fleet->travelSeconds=3660;
        $this->em->persist($fleet);$this->em->flush();
        return $fleet;
    }

    private function ship(Fleet $fleet, string $slug, string $tier, int $quantity): FleetShip
    {
        $ship = new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->tier=$tier;$ship->quantity=$quantity;
        $this->em->persist($ship);$this->em->flush();return $ship;
    }

    private function map(Player $viewer, Planet $origin): array
    {
        $this->client->loginUser($viewer);
        $crawler = $this->client->request('GET','/carte-v2?galaxy='.$origin->solarSystem->galaxy->id);
        self::assertResponseIsSuccessful();
        return json_decode($crawler->filter('.galaxy-v2')->attr('data-galaxy-v2-map-value'),true,512,JSON_THROW_ON_ERROR);
    }

    private function row(array $map, Fleet $fleet): array
    {
        $rows = array_column($map['flights'],null,'id');
        self::assertArrayHasKey($fleet->id,$rows);
        return $rows[$fleet->id];
    }

    public function testSpyTrajectoriesArePrivateToOwnerEvenForTargetAndAllies(): void
    {
        [$owner,$home]=$this->player('ProbeOwner');[$target,$destination]=$this->player('ProbeTarget');[$ally]=$this->player('ProbeAlly');
        $contract = new AllianceContract();$contract->playerA=$owner->currentPlayerUniverse;$contract->playerB=$ally->currentPlayerUniverse;
        $contract->proposer=$owner->currentPlayerUniverse;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        $outbound=$this->fleet($owner,$home,$destination,'spy');$this->ship($outbound,'probe','basic',3);
        $returning=$this->fleet($owner,$home,$destination,'spy','returning');$this->ship($returning,'probe','intermediate',2);
        $ownMap=$this->map($owner,$home);
        self::assertStringContainsString('probe',$this->row($ownMap,$outbound)['ships']);
        self::assertStringContainsString('Intermédiaire',$this->row($ownMap,$returning)['ships']);
        foreach([$target,$ally] as $viewer) {
            $map=$this->map($viewer,$home);
            self::assertNotContains($outbound->id,array_column($map['flights'],'id'));
            self::assertNotContains($returning->id,array_column($map['flights'],'id'));
            self::assertStringNotContainsString('data-flight-id="'.$outbound->id.'"',$this->client->getResponse()->getContent());
        }
    }

    public function testForeignFleetHasOnlyCountsAndPointsUsingUniverseRanksWithoutDefenses(): void
    {
        [$owner,$home]=$this->player('FleetOwner');[$target,$destination]=$this->player('FleetTarget');[$stranger]=$this->player('FleetStranger');
        [$ally]=$this->player('FleetAlly');
        $contract=new AllianceContract();$contract->playerA=$owner->currentPlayerUniverse;$contract->playerB=$ally->currentPlayerUniverse;
        $contract->proposer=$owner->currentPlayerUniverse;$contract->status='accepted';$this->em->persist($contract);
        $universe=$owner->currentPlayerUniverse->universe;
        $universe->gameSettings['catalog_overrides']['ships']['fighter']['cost_profiles']=[
            'basic'=>['metal'=>101,'nano'=>0,'antimatter'=>0,'hydrogen'=>0],
            'intermediate'=>['metal'=>199,'nano'=>0,'antimatter'=>0,'hydrogen'=>0],
        ];
        $this->em->flush();
        $fleet=$this->fleet($owner,$home,$destination,'attack');
        $ships=[$this->ship($fleet,'fighter','basic',3),$this->ship($fleet,'fighter','intermediate',2),$this->ship($fleet,'transport','basic',0)];
        $defense=new PlanetDefense();$defense->planet=$home;$defense->slug='ground_turret_anti_light';$defense->quantity=100;
        $this->em->persist($defense);$this->em->flush();
        $scores=static::getContainer()->get(LeaderboardService::class);
        self::assertSame(7,$scores->fleetPoints($fleet,$ships));
        self::assertSame(0,$scores->fleetPoints($fleet,[]));
        $live=array_values(array_filter($scores->forUniverse($owner->currentPlayerUniverse),static fn(array $row):bool=>$row['membership']->id===$owner->currentPlayerUniverse->id))[0];
        self::assertGreaterThan(7,$live['fleet_points']);
        $row=$this->row($this->map($target,$home),$fleet);
        self::assertFalse($row['ownedByCurrentPlayer']);
        self::assertSame(5,$row['shipCount']);self::assertSame(7,$row['fleetPoints']);self::assertNull($row['ships']);
        self::assertArrayNotHasKey('cargo',$row);self::assertArrayNotHasKey('composition',$row);
        self::assertSelectorTextContains('[data-flight-id="'.$fleet->id.'"]','5 vaisseaux');
        self::assertSelectorTextContains('[data-flight-id="'.$fleet->id.'"]','7 points');
        self::assertStringNotContainsString('fighter',$this->client->getCrawler()->filter('[data-flight-id="'.$fleet->id.'"]')->text());
        $own=$this->row($this->map($owner,$home),$fleet);
        self::assertTrue($own['ownedByCurrentPlayer']);
        self::assertStringContainsString('3 × fighter (Basique)',$own['ships']);
        self::assertStringContainsString('2 × fighter (Intermédiaire)',$own['ships']);
        self::assertSame(7,$own['fleetPoints']);
        $allied=$this->row($this->map($ally,$home),$fleet);
        self::assertSame(5,$allied['shipCount']);self::assertSame(7,$allied['fleetPoints']);self::assertNull($allied['ships']);
        self::assertNotContains($fleet->id,array_column($this->map($stranger,$home)['flights'],'id'));
    }

    public function testDiscoveryAndUniverseStillRestrictMapData(): void
    {
        [$viewer,$home]=$this->player('MapViewer');[$outsider,$otherHome]=$this->player('OtherUniverse','turbo-1');
        // Une destination locale ne doit pas contourner le filtre d’univers du propriétaire.
        $foreign=$this->fleet($outsider,$otherHome,$home,'transport');$this->ship($foreign,'transport','basic',1);
        $map=$this->map($viewer,$home);
        self::assertNotContains($foreign->id,array_column($map['flights'],'id'));
        $unknown=array_filter($map['systems'],static fn(array $system):bool=>!$system['known']);
        self::assertNotEmpty($unknown);
        foreach($unknown as $system) self::assertSame([],$system['planets']);
        $this->client->request('GET','/carte-v2?galaxy='.$otherHome->solarSystem->galaxy->id);
        self::assertResponseStatusCodeSame(404);
    }
    public function testUnknownFallbackColonyIsNeverExposedByARecalledFleet(): void
    {
        [$viewer,$target]=$this->player('RecallViewer');[$owner,$home]=$this->player('RecallOwner');
        $known=array_map(fn($d)=>$d->solarSystem->id,$this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findBy(['playerUniverse'=>$viewer->currentPlayerUniverse]));
        $fallback=null;
        foreach($this->em->getRepository(Planet::class)->findAll() as $planet) if($planet->solarSystem->galaxy->id===$target->solarSystem->galaxy->id
            && !in_array($planet->solarSystem->id,$known,true)) { $fallback=$planet;break; }
        self::assertNotNull($fallback);$fallback->owner=$owner->currentPlayerUniverse;$fallback->name='REPLI_CONFIDENTIEL';
        $fleet=$this->fleet($owner,$home,$target,'transport','returning');$fleet->returnPlanet=$fallback;$fleet->navigation=['recall'=>['progress'=>.5]];
        $this->ship($fleet,'transport','basic',2);$this->em->flush();
        $data=$this->map($viewer,$target);$row=array_values(array_filter($data['flights'],fn($f)=>$f['id']===$fleet->id))[0];
        self::assertSame(['hidden'=>true],$row['recallRoute']);self::assertStringNotContainsString('REPLI_CONFIDENTIEL',json_encode($row));
        $data=$this->map($owner,$home);$row=array_values(array_filter($data['flights'],fn($f)=>$f['id']===$fleet->id))[0];
        self::assertSame('REPLI_CONFIDENTIEL',$row['recallRoute']['returnName']);self::assertSame($fallback->solarSystem->id,$row['recallRoute']['returnSystemId']);
    }

}
