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
    private \Symfony\Component\Clock\MockClock $clock;

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
        $this->clock=new \Symfony\Component\Clock\MockClock();static::getContainer()->set('clock',$this->clock);
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
        $fleet->departureAt=$this->clock->now()->modify('-1 minute');$fleet->arrivalAt=$this->clock->now()->modify('+1 hour');
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
        $fleet->departureAt=$this->clock->now()->modify('-2 hours');$fleet->arrivalAt=$this->clock->now()->modify('+4 minutes');
        $this->reveal($ally,$home);$this->reveal($ally,$destination);
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


    private function reveal(Player $viewer,Planet $planet): void
    {
        $m=$viewer->currentPlayerUniverse;
        if($this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$m,'solarSystem'=>$planet->solarSystem])) return;
        $discovery=new \App\Entity\PlayerDiscovery();$discovery->playerUniverse=$this->em->getReference(\App\Entity\PlayerUniverse::class,$m->id);$discovery->solarSystem=$this->em->getReference(\App\Entity\SolarSystem::class,$planet->solarSystem->id);
        $this->em->persist($discovery);$this->em->flush();
    }

    private function surveillance(Player $viewer,?int $galaxy=null): array
    {
        $this->client->loginUser($viewer);
        $this->client->request('GET','/flottes/surveillance'.($galaxy?'?galaxy='.$galaxy:''));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store',$this->client->getResponse()->headers->get('Cache-Control'));
        return json_decode($this->client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);
    }

    private function hiddenOrigin(Player $viewer,Player $owner,Planet $target): Planet
    {
        $known=array_map(fn($d)=>$d->solarSystem->id,$this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findBy(['playerUniverse'=>$viewer->currentPlayerUniverse]));
        foreach($this->em->getRepository(Planet::class)->findAll() as $planet)
            if($planet->solarSystem->galaxy->id===$target->solarSystem->galaxy->id && !in_array($planet->solarSystem->id,$known,true)) {
                $planet->owner=$owner->currentPlayerUniverse;$planet->name='ORIGINE_CONFIDENTIELLE';$this->em->flush();return $planet;
            }
        self::fail('Une origine non découverte doit être disponible dans la fixture.');
    }

    public function testUndetectedAttackIsAbsentFromHtmlJsonAndOwnerKeepsFullTracking(): void
    {
        [$defender,$target]=$this->player('HiddenDefender');[$attacker,$home]=$this->player('HiddenAttacker');
        $fleet=$this->fleet($attacker,$home,$target,'attack');$this->ship($fleet,'fighter','basic',4);
        $data=$this->surveillance($defender,$target->solarSystem->galaxy->id);
        self::assertStringNotContainsString('data-incoming-attack=', $data['html']);
        self::assertNotContains($fleet->id,array_column($data['flights'],'id'));
        self::assertNotContains($fleet->id,array_column($this->map($defender,$target)['flights'],'id'));
        self::assertStringContainsString('fighter',$this->row($this->map($attacker,$home),$fleet)['ships']);
        $this->client->loginUser($defender);$this->client->request('GET','/planetes/'.$target->id);
        self::assertSelectorNotExists('[data-incoming-attack]');
    }

    public function testExactThresholdAndUnknownOriginNeverLeakThroughServerPayload(): void
    {
        [$defender,$target]=$this->player('ThresholdDefender');[$attacker]=$this->player('ThresholdAttacker');
        $home=$this->hiddenOrigin($defender,$attacker,$target);
        $fleet=$this->fleet($attacker,$home,$target,'attack');$fleet->departureAt=$this->clock->now()->modify('-2 hours');
        $fleet->arrivalAt=$this->clock->now()->modify('+5 minutes 1 second');$fleet->cargo=['hydrogen'=>987654321];
        $fleet->navigation=['active'=>['status'=>'traveling','progress'=>.5,'at'=>$this->clock->now()->getTimestamp()],'private'=>'SECRET_NAVIGATION'];
        $this->ship($fleet,'fighter','basic',3);$this->em->flush();
        self::assertNotContains($fleet->id,array_column($this->surveillance($defender,$target->solarSystem->galaxy->id)['flights'],'id'));
        $this->clock->sleep(1);
        $data=$this->surveillance($defender,$target->solarSystem->galaxy->id);
        self::assertStringContainsString('data-incoming-attack="'.$fleet->id.'"',$data['html']);
        self::assertStringContainsString($target->name,$data['html']);
        self::assertStringContainsString($attacker->username,$data['html']);
        $row=$this->row($data,$fleet);
        foreach(['originSystemId','originGalaxy','originX','originY','originCoordinates','departureAt','progressBase','progressAt','ships'] as $field) self::assertNull($row[$field],$field);
        foreach(['ORIGINE_CONFIDENTIELLE','SECRET_NAVIGATION','987654321','fighter'] as $secret) self::assertStringNotContainsString($secret,json_encode($data));
        self::assertSame(3,$row['shipCount']);self::assertSame('Origine non découverte',$row['originName']);
        $this->reveal($defender,$home);
        $row=$this->row($this->surveillance($defender,$target->solarSystem->galaxy->id),$fleet);
        self::assertSame($home->name,$row['originName']);self::assertSame($home->coordinates(),$row['originCoordinates']);
        self::assertNull($row['departureAt']);
    }

    public function testSimpleRankSumAndUniverseCapsDoNotChangeSpyBonuses(): void
    {
        [$defender,$target]=$this->player('RankDefender');[$attacker,$home]=$this->player('RankAttacker');
        foreach(['basic'=>5,'intermediate'=>5,'superior'=>5] as $tier=>$level) {
            $technology=new \App\Entity\PlayerTechnology();$technology->playerUniverse=$defender->currentPlayerUniverse;
            $technology->slug='tactical_intelligence';$technology->tier=$tier;$technology->level=$level;$this->em->persist($technology);
        }
        $this->em->flush();
        $detection=static::getContainer()->get(\App\Service\Fleet\AttackDetection::class);
        self::assertSame(15,$detection->level($defender->currentPlayerUniverse));
        self::assertSame(['basic'=>5,'intermediate'=>5,'superior'=>5],$detection->maxLevels($defender->currentPlayerUniverse->universe));
        $fleet=$this->fleet($attacker,$home,$target,'attack');$fleet->departureAt=$this->clock->now()->modify('-4 hours');
        $fleet->arrivalAt=$this->clock->now()->modify('+80 minutes 1 second');$this->ship($fleet,'fighter','basic',1);$this->em->flush();
        self::assertStringNotContainsString('data-incoming-attack=',$this->surveillance($defender)['html']);
        $this->clock->sleep(1);self::assertStringContainsString('data-incoming-attack=',$this->surveillance($defender)['html']);
        $universe=$this->em->find(Universe::class,$defender->currentPlayerUniverse->universe->id);
        $universe->gameSettings['catalog_overrides']['technologies']['tactical_intelligence']['effect']['tier_max_levels']=['basic'=>2,'intermediate'=>3,'superior'=>4];
        self::assertSame(['basic'=>2,'intermediate'=>3,'superior'=>4],$detection->maxLevels($universe));
        $catalog=static::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        $benefits=static::getContainer()->get(\App\Service\Colony\CatalogueInsights::class)->benefits(
            $catalog->getTierForUniverse($universe,'technologies','tactical_intelligence','superior'),'technologies',4,$target,[],['tactical_intelligence'=>['basic'=>5,'intermediate'=>5,'superior'=>3]]);
        self::assertSame(16,$benefits[0]['before']);self::assertSame(18,$benefits[0]['after']);
        self::assertSame(-16,$benefits[1]['before']);self::assertSame(-18,$benefits[1]['after']);
        self::assertEquals(70,$benefits[2]['before']);self::assertEquals(75,$benefits[2]['after']);
    }

    public function testNonOffensiveRecipientsAlliesAndDiscoveryAreRechecked(): void
    {
        [$recipient,$target]=$this->player('Recipient');[$sender,$home]=$this->player('Sender');[$ally]=$this->player('KnownAlly');[$stranger]=$this->player('Unrelated');
        $home=$this->hiddenOrigin($recipient,$sender,$target);
        $fleet=$this->fleet($sender,$home,$target,'transport');$this->ship($fleet,'transport','basic',2);
        $contract=new AllianceContract();$contract->playerA=$sender->currentPlayerUniverse;$contract->playerB=$ally->currentPlayerUniverse;
        $contract->proposer=$sender->currentPlayerUniverse;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        self::assertSame('Origine non découverte',$this->row($this->map($recipient,$target),$fleet)['originName']);
        self::assertNotContains($fleet->id,array_column($this->map($stranger,$target)['flights'],'id'));
        self::assertNotContains($fleet->id,array_column($this->map($ally,$target)['flights'],'id'));
        $this->reveal($ally,$home);$this->reveal($ally,$target);
        self::assertSame(2,$this->row($this->map($ally,$target),$fleet)['shipCount']);
        $contract=$this->em->find(AllianceContract::class,$contract->id);$contract->status='revoked';$this->em->flush();
        self::assertNotContains($fleet->id,array_column($this->map($ally,$target)['flights'],'id'));
    }

    public function testBoostedDeadlineReturnSpyAndRepeatedReadsDoNotMutateAnything(): void
    {
        [$defender,$target]=$this->player('ReadDefender');[$attacker,$home]=$this->player('ReadAttacker');
        $fleet=$this->fleet($attacker,$home,$target,'attack');$this->ship($fleet,'fighter','basic',2);
        self::assertStringNotContainsString('data-incoming-attack=',$this->surveillance($defender)['html']);
        $fleet=$this->em->find(Fleet::class,$fleet->id);$fleet->arrivalAt=$this->clock->now()->modify('+30 seconds');$this->em->flush();
        $before=$this->em->getConnection()->fetchAssociative('SELECT last_activity_at FROM player_universe WHERE id=?',[$defender->currentPlayerUniverse->id]);
        $rows=$this->em->getConnection()->fetchAllAssociative('SELECT * FROM fleet ORDER BY id');
        for($i=0;$i<2;$i++) self::assertStringContainsString('data-incoming-attack=',$this->surveillance($defender)['html']);
        self::assertSame($before,$this->em->getConnection()->fetchAssociative('SELECT last_activity_at FROM player_universe WHERE id=?',[$defender->currentPlayerUniverse->id]));
        self::assertSame($rows,$this->em->getConnection()->fetchAllAssociative('SELECT * FROM fleet ORDER BY id'));
        $fleet=$this->em->find(Fleet::class,$fleet->id);$fleet->status='returning';$this->em->flush();self::assertStringNotContainsString('data-incoming-attack=',$this->surveillance($defender)['html']);
        $fleet=$this->em->find(Fleet::class,$fleet->id);$fleet->status='traveling';$fleet->mission='spy';$this->em->flush();
        $data=$this->surveillance($defender,$target->solarSystem->galaxy->id);
        self::assertSame([],$data['flights']);self::assertStringNotContainsString('data-incoming-attack=',$data['html']);
        self::assertStringContainsString('fighter',$this->row($this->map($attacker,$home),$fleet)['ships']);
    }

    public function testSurveillanceIsIsolatedToSessionUniverseAndProtectedForAnonymous(): void
    {
        [$viewer,$home]=$this->player('SessionViewer');[$other,$otherHome]=$this->player('SessionOther','turbo-1');
        $this->client->loginUser($viewer);$this->client->request('GET','/flottes/surveillance?galaxy='.$otherHome->solarSystem->galaxy->id);
        self::assertResponseStatusCodeSame(404);
        $data=$this->surveillance($viewer);self::assertSame($viewer->currentPlayerUniverse->id,$data['membership']);
        $this->client->getCookieJar()->clear();$this->client->request('GET','/flottes/surveillance');
        self::assertResponseRedirects('/connexion');
    }

}
