<?php
namespace App\Tests\Controller;

use App\Entity\{AllianceContract,Fleet,FleetShip,Guild,GuildMember,Planet,PlanetDefense,Player,Universe};
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

    public function testLocalMapLegsUseFrozenDurationsAndExcludeHyperspace(): void
    {
        [$owner, $home] = $this->player('LocalMapOwner');
        [$viewer, $destination] = $this->player('LocalMapViewer');
        $home = $this->hiddenOrigin($viewer, $owner, $destination);
        $fleet = $this->fleet($owner, $home, $destination, 'transport');
        $origin = [$home->solarSystem->id, 1, 0, 0, $home->position];
        $target = [$destination->solarSystem->id, 1, 0, 0, $destination->position];
        $exit = $origin;
        $entry = $target;
        $exit[4] = $entry[4] = 10;
        $fleet->navigation = ['seconds' => 100, 'legs' => [
            ['from' => $origin, 'to' => $exit, 'seconds' => 20, 'hyper' => false],
            ['from' => $exit, 'to' => $entry, 'seconds' => 50, 'hyper' => true],
            ['from' => $entry, 'to' => $target, 'seconds' => 30, 'hyper' => false],
        ]];
        $this->em->flush();
        $legs = $this->row($this->map($owner, $home), $fleet)['localLegs'];
        self::assertCount(2, $legs);
        self::assertEquals(0.2, $legs[0]['end']);
        self::assertSame([['systemId' => $destination->solarSystem->id, 'from' => 10,
            'to' => $destination->position, 'start' => 0.7, 'end' => 1]], $this->row($this->map($viewer, $destination), $fleet)['localLegs']);
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

    public function testGuildTrafficIsMapOnlyAndRevokedAfterLeavingGuild(): void
    {
        [$owner, $home] = $this->player('GuildFleetOwner');
        [$viewer, $viewerHome] = $this->player('GuildFleetViewer');
        [$ally] = $this->player('GuildFleetAlly');
        [$target, $destination] = $this->player('GuildFleetTarget');

        $guild = new Guild();
        $guild->universe = $owner->currentPlayerUniverse->universe;
        $guild->leader = $owner->currentPlayerUniverse;
        $guild->name = 'Veilleurs du Nexus';
        $this->em->persist($guild);
        $this->joinGuild($owner, $guild);
        $membership = $this->joinGuild($viewer, $guild);
        $contract = new AllianceContract();
        $contract->playerA = $owner->currentPlayerUniverse;
        $contract->playerB = $ally->currentPlayerUniverse;
        $contract->proposer = $owner->currentPlayerUniverse;
        $contract->status = 'accepted';
        $this->em->persist($contract);
        $this->em->flush();

        foreach ([$viewer, $ally] as $observer) {
            $this->reveal($observer, $home);
            $this->reveal($observer, $destination);
        }
        $known = [];
        foreach (['explore', 'spy', 'colonize', 'transport', 'attack', 'conquer', 'defend', 'orbit', 'station'] as $mission) {
            $known[$mission] = $this->fleet($owner, $home, $destination, $mission);
        }
        $return = $this->fleet($owner, $home, $destination, 'spy', 'returning');
        $hiddenTarget = $this->hiddenOrigin($viewer, $target, $destination);
        $unknown = $this->fleet($owner, $home, $hiddenTarget, 'spy');
        $known['spy']->cargo = ['hydrogen' => 987654321];
        $this->ship($known['spy'], 'probe', 'intermediate', 29);

        $map = $this->map($viewer, $home);
        self::assertTrue($map['guildFilterAvailable']);
        foreach ([...array_values($known), $return] as $fleet) {
            $row = $this->row($map, $fleet);
            self::assertTrue($row['guildFlight']);
            self::assertSame($owner->username, $row['ownerName']);
            self::assertSame($home->name, $row['originName']);
            self::assertSame($destination->name, $row['destinationName']);
            self::assertSame($fleet->arrivalAt->format(DATE_ATOM), $row['arrivalAt']);
            foreach (['ships', 'cargo', 'composition', 'shipCount', 'fleetPoints'] as $privateKey) {
                self::assertArrayNotHasKey($privateKey, $row);
            }
        }
        self::assertNotContains($unknown->id, array_column($map['flights'], 'id'));
        self::assertSelectorTextContains('[data-guild-flight][data-flight-id="'.$return->id.'"]', $destination->name.' → '.$home->name);
        self::assertSelectorTextContains('[data-guild-flight][data-flight-id="'.$known['spy']->id.'"]', 'Espionnage');
        self::assertStringNotContainsString('987654321', $this->client->getResponse()->getContent());

        $galaxyId = $home->solarSystem->galaxy->id;
        $live = $this->surveillance($viewer, $galaxyId);
        self::assertTrue($this->row($live, $known['spy'])['guildFlight']);
        self::assertStringContainsString('data-guild-flight', $live['fleetLog']);
        self::assertStringNotContainsString('data-guild-flight', $live['html']);
        $withoutMap = $this->surveillance($viewer);
        self::assertArrayNotHasKey('flights', $withoutMap);
        self::assertArrayNotHasKey('guildFilterAvailable', $withoutMap);
        foreach (['/flottes', '/planetes/'.$viewerHome->id] as $path) {
            $this->client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorNotExists('[data-guild-flight]');
        }

        $alliedMap = $this->map($ally, $home);
        self::assertFalse($alliedMap['guildFilterAvailable']);
        self::assertNotContains($known['spy']->id, array_column($alliedMap['flights'], 'id'));
        self::assertArrayNotHasKey('guildFlight', $this->row($alliedMap, $known['transport']));
        self::assertNotContains($known['spy']->id, array_column($this->map($target, $home)['flights'], 'id'));

        $this->em->remove($this->em->getReference(GuildMember::class, $membership->id));
        $this->em->flush();
        $live = $this->surveillance($viewer, $galaxyId);
        self::assertFalse($live['guildFilterAvailable']);
        self::assertNotContains($known['spy']->id, array_column($live['flights'], 'id'));
        self::assertStringNotContainsString('data-guild-flight', $live['fleetLog']);
    }

    private function joinGuild(Player $player, Guild $guild): GuildMember
    {
        $membership = new GuildMember();
        $membership->guild = $guild;
        $membership->playerUniverse = $player->currentPlayerUniverse;
        $this->em->persist($membership);

        return $membership;
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
        $fleet->arrivalAt=$this->clock->now()->modify('+30 minutes 1 second');$fleet->cargo=['hydrogen'=>987654321];
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
        self::assertSame([], $row['localLegs']);
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
        $fleet->arrivalAt=$this->clock->now()->modify('+105 minutes 1 second');$this->ship($fleet,'fighter','basic',1);$this->em->flush();
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
        self::assertEquals(95, $benefits[2]['before']);
        self::assertEquals(100, $benefits[2]['after']);
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


    private static function assertIncomingPanelCount(string $html, string $expected): void
    {
        $panel = new \Symfony\Component\DomCrawler\Crawler($html);
        $count = $panel->filter('#incoming-attacks-heading > .badge');
        self::assertCount(1, $count);
        self::assertSame($expected, $count->text());
    }

    public function testIncomingPanelIncludesNeutralTransportsAndAlliedReinforcementsAndOrbit(): void
    {
        [$viewer,$target]=$this->player('IncomingViewer');[$neutral,$home]=$this->player('IncomingNeutral');[$ally,$alliedHome]=$this->player('IncomingAlly');
        $viewer->preferences['transportReception']='all';
        $contract=new AllianceContract();$contract->playerA=$viewer->currentPlayerUniverse;$contract->playerB=$ally->currentPlayerUniverse;
        $contract->proposer=$viewer->currentPlayerUniverse;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        $fleets=[$this->fleet($neutral,$home,$target,'transport'),$this->fleet($ally,$alliedHome,$target,'defend'),$this->fleet($neutral,$home,$target,'orbit')];
        foreach($fleets as $fleet)$this->ship($fleet,'transport','basic',2);
        $data=$this->surveillance($viewer);
        self::assertIncomingPanelCount($data['html'], '3');
        foreach($fleets as $fleet)self::assertStringContainsString('data-incoming-attack="'.$fleet->id.'"',$data['html']);
        self::assertStringContainsString('Transport',$data['html']);self::assertStringContainsString('Défense',$data['html']);self::assertStringContainsString('Mise en orbite',$data['html']);
        self::assertStringNotContainsString('Consulter le récapitulatif du transport', $data['html']);
        self::assertStringContainsString('Arrivée dans',$data['html']);self::assertStringNotContainsString('Impact dans',$data['html']);
        self::assertStringNotContainsString('has-hostile',$data['html']);
        $this->client->request('GET','/planetes/'.$target->id);self::assertSelectorTextContains('#incoming-attacks-heading','Flottes entrantes 3');
        self::assertSelectorCount(1,'.incoming-attack-panel');
        // La vue générale validée place les arrivées avant les vols propres.
        self::assertSelectorExists('.dashboard-secondary > .incoming-attacks .panel.flight-operations');
        self::assertSelectorExists('.dashboard-secondary > .incoming-attacks + [data-live-region="active-flights"]');
    }

    public function testOnlyProvenCurrentRecipientsCanSeeTheExplicitDepartureCompositionAndFreight(): void
    {
        [$viewer, $target] = $this->player('DetailViewer');
        [$sender, $home] = $this->player('DetailSender');
        [$successor] = $this->player('DetailSuccessor');
        [$enemy, $enemyHome] = $this->player('DetailEnemy');
        [$olderHost] = $this->player('DetailOldHost');
        $viewer->preferences['transportReception'] = 'all';
        $this->reveal($viewer, $home);
        $this->reveal($viewer, $enemyHome);
        $contract = new AllianceContract();
        $contract->playerA = $viewer->currentPlayerUniverse;
        $contract->playerB = $enemy->currentPlayerUniverse;
        $contract->proposer = $viewer->currentPlayerUniverse;
        $contract->status = 'enemies';
        $this->em->persist($contract);

        $traces = static::getContainer()->get(\App\Service\Admin\FleetMissionTraceService::class);
        $transports = static::getContainer()->get(\App\Service\Fleet\TransportService::class);
        $orbits = static::getContainer()->get(\App\Service\Fleet\OrbitRequestState::class);
        $depart = function (Player $owner, Planet $origin, string $mission, array $freight) use ($target, $traces): Fleet {
            $fleet = $this->fleet($owner, $origin, $target, $mission);
            $this->ship($fleet, 'fighter', 'basic', 2);
            $fleet->reservedFuel = 987654321;
            $fleet->cargo = ['hydrogen' => ($freight['hydrogen'] ?? 0) + $fleet->reservedFuel, 'metal' => 54321876];
            $fleet->navigation = ['marker' => 'NAVIGATION_NEVER_DISCLOSED'];
            $traces->recordDeparture($fleet, ['fighter' => ['basic' => 2]], $freight);
            $this->em->flush();

            return $fleet;
        };

        $transport = $depart($sender, $home, 'transport', ['metal' => 123, 'hydrogen' => 45]);
        $transports->recordDeparture($transport, ['metal' => 123, 'hydrogen' => 45]);
        $defense = $depart($sender, $home, 'defend', []);
        $orbit = $depart($sender, $home, 'orbit', []);
        $orbits->open($orbit, false);
        $noTrace = $this->fleet($sender, $home, $target, 'defend');
        $occupation = $depart($sender, $home, 'defend', ['metal' => 45678]);
        $occupation->orbitDurationSeconds = 3600;
        $unverifiedOrbit = $depart($sender, $home, 'orbit', ['metal' => 87654]);
        $enemyTransport = $depart($enemy, $enemyHome, 'transport', ['metal' => 92817]);
        $transports->recordDeparture($enemyTransport, ['metal' => 92817]);
        $otherMissions = [
            $depart($sender, $home, 'attack', ['metal' => 90210]),
            $depart($sender, $home, 'conquer', ['metal' => 90210]),
            $depart($sender, $home, 'spy', ['metal' => 90210]),
        ];
        $unknownOrigin = $this->hiddenOrigin($viewer, $sender, $target);
        $fromUnknown = $depart($sender, $unknownOrigin, 'defend', ['metal' => 81624]);
        $returning = $depart($sender, $home, 'defend', ['metal' => 98761]);
        $returning->status = 'returning';
        $target->owner = $olderHost->currentPlayerUniverse;
        $formerHost = $depart($sender, $home, 'defend', ['metal' => 71583]);
        $target->owner = $viewer->currentPlayerUniverse;
        $this->em->flush();

        $viewDetails = static function (array $payload, Fleet $fleet): \Symfony\Component\DomCrawler\Crawler {
            return (new \Symfony\Component\DomCrawler\Crawler($payload['html']))
                ->filter('[data-incoming-attack="'.$fleet->id.'"] [data-incoming-detail]');
        };
        $payload = $this->surveillance($viewer);
        foreach ([$transport, $defense, $orbit] as $fleet) {
            $detail = $viewDetails($payload, $fleet);
            self::assertCount(1, $detail);
            self::assertStringContainsString('Basique', $detail->text());
            self::assertStringContainsString('Composition et fret', $detail->text());
        }
        self::assertStringContainsString('123', $viewDetails($payload, $transport)->text());
        self::assertStringContainsString('45', $viewDetails($payload, $transport)->text());
        self::assertStringContainsString('Aucun fret transporté.', $viewDetails($payload, $defense)->text());
        self::assertStringContainsString('Aucun fret transporté.', $viewDetails($payload, $orbit)->text());
        foreach ([$noTrace, $occupation, $unverifiedOrbit, $enemyTransport, $fromUnknown, $returning, $formerHost, ...$otherMissions] as $fleet) {
            self::assertCount(0, $viewDetails($payload, $fleet));
        }
        foreach (['987654321', '54321876', 'NAVIGATION_NEVER_DISCLOSED'] as $privateValue) {
            self::assertStringNotContainsString($privateValue, $payload['html']);
        }
        // La page initiale et l’actualisation utilisent la même décision.
        $this->client->request('GET', '/flottes');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#incoming-fleet-details-'.$transport->id);
        self::assertSelectorExists('[data-incoming-attack="'.$transport->id.'"] a[href^="/rapports/transport/"]');
        $mapPayload = $this->surveillance($viewer, $target->solarSystem->galaxy->id);
        self::assertCount(0, $viewDetails($mapPayload, $transport));
        self::assertStringNotContainsString('incomingDetail', json_encode($mapPayload['flights'], JSON_THROW_ON_ERROR));

        // Ni un tiers ni le nouveau propriétaire ne reçoivent un détail existant.
        foreach ([$transport, $defense, $orbit] as $fleet) {
            self::assertCount(0, $viewDetails($this->surveillance($successor), $fleet));
        }
        $manager = static::getContainer()->get(EntityManagerInterface::class);
        $target = $manager->find(Planet::class, $target->id);
        $successor = $manager->find(Player::class, $successor->id);
        $target->owner = $successor->currentPlayerUniverse;
        $manager->flush();
        $successorPayload = $this->surveillance($successor);
        self::assertStringContainsString('data-incoming-attack="'.$transport->id.'"', $successorPayload['html']);
        foreach ([$transport, $defense, $orbit] as $fleet) {
            self::assertCount(0, $viewDetails($successorPayload, $fleet));
            self::assertCount(0, $viewDetails($this->surveillance($viewer), $fleet));
        }
    }

    public function testIncomingPanelExcludesOwnFlightsReturnsSpiesAndAlliedThirdPartyRoutes(): void
    {
        [$viewer,$target]=$this->player('ScopeViewer');[$sender,$home]=$this->player('ScopeSender');[$third,$elsewhere]=$this->player('ScopeThird');
        $contract=new AllianceContract();$contract->playerA=$viewer->currentPlayerUniverse;$contract->playerB=$sender->currentPlayerUniverse;
        $contract->proposer=$viewer->currentPlayerUniverse;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        $this->reveal($viewer,$home);$this->reveal($viewer,$elsewhere);
        $own=$this->fleet($viewer,$target,$target,'transport');
        $return=$this->fleet($sender,$home,$target,'transport','returning');
        $spy=$this->fleet($sender,$home,$target,'spy');$this->ship($spy,'probe','basic',1);
        $thirdParty=$this->fleet($sender,$home,$elsewhere,'transport');
        $data=$this->surveillance($viewer);
        foreach([$own,$return,$spy,$thirdParty] as $fleet)self::assertStringNotContainsString('data-incoming-attack="'.$fleet->id.'"',$data['html']);
        self::assertIncomingPanelCount($data['html'], '0');
        self::assertStringContainsString('Aucune flotte entrante visible vers vos colonies.',$data['html']);
    }

    public function testIncomingNonHostileFlightKeepsCompositionFreightAndUnknownOriginPrivate(): void
    {
        [$viewer,$target]=$this->player('PrivacyViewer');[$sender]=$this->player('PrivacySender');
        $home=$this->hiddenOrigin($viewer,$sender,$target);
        $fleet=$this->fleet($sender,$home,$target,'transport');$this->ship($fleet,'transport','superior',2);
        $fleet->cargo=['metal'=>987654321];$fleet->navigation=['private'=>'CONFIDENTIAL_INCOMING'];$this->em->flush();
        $data=$this->surveillance($viewer);self::assertStringContainsString('data-incoming-attack="'.$fleet->id.'"',$data['html']);
        self::assertStringContainsString('Origine non découverte',$data['html']);
        foreach(['ORIGINE_CONFIDENTIELLE','CONFIDENTIAL_INCOMING','987654321','Cargo interstellaire'] as $secret)self::assertStringNotContainsString($secret,json_encode($data));
        $rows=static::getContainer()->get(\App\Service\Fleet\FleetVisibility::class)->incoming($viewer->currentPlayerUniverse);
        self::assertSame(2,$rows[0]['shipCount']);self::assertNull($rows[0]['ships']);
        self::assertArrayNotHasKey('cargo',$rows[0]);self::assertArrayNotHasKey('composition',$rows[0]);
    }

    public function testDetectedThreatStaysFirstAndLegacyOrbitHasNoFalseCountdown(): void
    {
        [$viewer,$target]=$this->player('OrderViewer');[$sender,$home]=$this->player('OrderSender');
        $transport=$this->fleet($sender,$home,$target,'transport');$transport->arrivalAt=$this->clock->now()->modify('+1 minute');
        $attack=$this->fleet($sender,$home,$target,'attack');$attack->arrivalAt=$this->clock->now()->modify('+4 minutes');$attack->departureAt=$this->clock->now()->modify('-2 hours');
        $pending=$this->fleet($sender,$home,$target,'orbit','orbit_pending');$pending->arrivalAt=null;
        foreach([$transport,$attack,$pending] as $fleet)$this->ship($fleet,'fighter','basic',1);$this->em->flush();
        $data=$this->surveillance($viewer);
        $rows=static::getContainer()->get(\App\Service\Fleet\FleetVisibility::class)->incoming($viewer->currentPlayerUniverse);
        self::assertSame([$attack->id,$transport->id,$pending->id],array_column($rows,'id'));
        self::assertStringContainsString('has-hostile',$data['html']);self::assertStringContainsString('1 attaque détectée',$data['html']);
        self::assertStringContainsString('Demande d’orbite à traiter',$data['html']);
        $crawler=new \Symfony\Component\DomCrawler\Crawler($data['html']);
        self::assertCount(0,$crawler->filter('[data-incoming-attack="'.$pending->id.'"] time'));
    }

    public function testIncomingPanelRejectsForeignUniverseOriginsAndTargets(): void
    {
        [$viewer,$target]=$this->player('BorderViewer');[$sender,$home]=$this->player('BorderSender');[$foreign,$foreignHome]=$this->player('BorderForeign','turbo-1');
        $a=$this->fleet($foreign,$foreignHome,$target,'transport');
        $b=$this->fleet($sender,$foreignHome,$target,'transport');
        $foreignHome->owner=$viewer->currentPlayerUniverse;
        $c=$this->fleet($sender,$home,$foreignHome,'transport');$this->em->flush();
        $data=$this->surveillance($viewer);
        foreach([$a,$b,$c] as $fleet)self::assertStringNotContainsString('data-incoming-attack="'.$fleet->id.'"',$data['html']);
    }

    public function testExistingAttackFromAnEmpireWithoutPlayerRemainsDetectable(): void
    {
        [$viewer,$target]=$this->player('ForgottenViewer');[$sender,$home]=$this->player('ForgottenSender');
        $fleet=$this->fleet($sender,$home,$target,'attack');$fleet->arrivalAt=$this->clock->now()->modify('+4 minutes');$fleet->departureAt=$this->clock->now()->modify('-2 hours');$this->ship($fleet,'fighter','basic',1);
        $sender->currentPlayerUniverse->detachPlayer($this->clock->now());$this->em->flush();
        self::assertStringContainsString('data-incoming-attack="'.$fleet->id.'"',$this->surveillance($viewer)['html']);
    }

}
