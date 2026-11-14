<?php
namespace App\Tests\Integration;

use App\Entity\{Fleet,FleetShip,Planet,PlanetResource,Player,PlayerDiscovery,Universe};
use App\Service\Colony\ResourceService;
use App\Service\Fleet\{FleetHold,FleetRecallService,FleetService,FleetStationing};
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class FleetStationingTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Player $peer;
    private Planet $home;
    private Planet $target;
    private Planet $fallback;
    private Fleet $idle;

    protected function setUp(): void
    {
        $this->client=self::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();$this->clock=new MockClock('2026-11-14T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        $this->player=$this->player('StationOwner');$this->peer=$this->player('StationPeer');$m=$this->player->currentPlayerUniverse;
        $this->home=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$m]);
        $others=array_values(array_filter($this->em->getRepository(Planet::class)->findBy(['solarSystem'=>$this->home->solarSystem],['position'=>'ASC']),fn($planet)=>$planet->id!==$this->home->id));
        $this->target=$others[0];$this->fallback=$others[1];
        foreach([$this->target,$this->fallback] as $planet) {$planet->owner=$m;$planet->npcPlanet=false;self::getContainer()->get(ResourceService::class)->initialize($planet);}
        $this->idle=$this->em->getRepository(Fleet::class)->findOneBy(['homePlanet'=>$this->home,'playerUniverse'=>$m,'status'=>'idle'])??new Fleet();
        $this->idle->homePlanet=$this->home;$this->idle->playerUniverse=$m;$this->em->persist($this->idle);
        $ship=new FleetShip();$ship->fleet=$this->idle;$ship->slug='transport';$ship->quantity=4;$this->em->persist($ship);
        $this->em->flush();$this->client->loginUser($this->player);
    }

    private function player(string $name): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword=bin2hex(random_bytes(16)).'aA1!';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }

    private function send(array $cargo=[]): Fleet
    {
        self::getContainer()->get(FleetService::class)->send($this->player->currentPlayerUniverse,$this->home,'station',$this->target->id,['transport'=>2],1,$cargo,str_repeat('a',32));
        return $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$this->player->currentPlayerUniverse,'status'=>'traveling']);
    }
    private function arrive(Fleet $fleet): void
    {
        $this->clock->sleep(max(0,$fleet->arrivalAt->getTimestamp()-$this->clock->now()->getTimestamp())+1);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id,$fleet->dispatchToken);
    }
    private function stock(Planet $planet): ?Fleet {return $this->em->getRepository(Fleet::class)->findOneBy(['homePlanet'=>$planet,'playerUniverse'=>$this->player->currentPlayerUniverse,'status'=>'idle']);}

    public function testArrivalMovesBaseOnceAndKeepsFuelAndFreightOverflow(): void
    {
        $fleet=$this->send(['metal'=>100,'hydrogen'=>50]);$token=$fleet->dispatchToken;$oneWay=$fleet->reservedFuel/2;
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$this->target]) as $row) {$row->currentStock=1e8;$row->lastUpdatedAt=$this->clock->now();}
        $this->em->flush();self::assertNull($this->stock($this->target));$this->arrive($fleet);
        self::assertSame('completed',$fleet->status);self::assertNull($fleet->arrivalAt);$idle=$this->stock($this->target);
        self::assertNotNull($idle);self::assertEqualsWithDelta($oneWay,$idle->storedFuel,1e-8);
        self::assertEqualsWithDelta(100,$idle->cargo['metal'],1e-8);self::assertEqualsWithDelta(50,$idle->cargo['hydrogen'],1e-8);
        self::assertSame(2,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$idle,'slug'=>'transport'])->quantity);
        self::getContainer()->get(FleetService::class)->arrive($fleet->id,$token);
        self::assertEqualsWithDelta($oneWay,$idle->storedFuel,1e-8);self::assertSame(4,(int)$this->em->getConnection()->fetchOne('SELECT SUM(quantity) FROM fleet_ship'));
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM transport_report'));
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM player_boost'));
    }

    public function testStoredFuelHasPriorityAndPhysicalCargoIncludesIt(): void
    {
        $this->idle->storedFuel=10;$this->em->flush();$service=self::getContainer()->get(FleetService::class);
        $q=$service->quote($this->player->currentPlayerUniverse,$this->home,'station',$this->target->id,['transport'=>2],1);
        self::assertEqualsWithDelta(min(10,$q['total_fuel']),$q['stored_fuel_used'],1e-8);
        self::assertEqualsWithDelta($q['total_fuel']-$q['stored_fuel_used'],$q['planet_fuel'],1e-8);
        $fleet=$this->send();self::assertEqualsWithDelta($q['total_fuel'],$fleet->cargo['hydrogen'],1e-8);
        self::assertEqualsWithDelta(10-$q['stored_fuel_used'],$this->idle->storedFuel,1e-8);
    }

    public function testArrivalRoundingCannotCreateOnboardFuel(): void
    {
        $fleet=$this->send();$hydrogen=$fleet->cargo['hydrogen'];$leg=$fleet->navigation['station']['leg_fuel'];
        $fleet->reservedFuel=$hydrogen+5e-10;$this->em->flush();$this->arrive($fleet);
        self::assertSame('completed',$fleet->status);
        self::assertEqualsWithDelta($hydrogen-$leg,$this->stock($this->target)->storedFuel,1e-12);
    }

    public function testRepeatedWaitingMessagesCannotNotifyOrConsumeAgain(): void
    {
        $fleet=$this->send();$token=$fleet->dispatchToken;
        foreach([$this->home,$this->target,$this->fallback] as $planet) $planet->owner=$this->peer->currentPlayerUniverse;
        $this->em->flush();$this->arrive($fleet);$before=$fleet->cargo;$reserve=$fleet->reservedFuel;
        for($i=0;$i<3;$i++) self::getContainer()->get(FleetService::class)->arrive($fleet->id,$token);
        self::assertSame('station_waiting',$fleet->status);self::assertSame($before,$fleet->cargo);self::assertSame($reserve,$fleet->reservedFuel);
        $rows=self::getContainer()->get(\App\Service\Fleet\FleetVisibility::class)->forMap($this->player->currentPlayerUniverse,$this->target->solarSystem->galaxy);
        self::assertCount(1,$rows);self::assertSame($fleet->id,$rows[0]['id']);self::assertSame('Stationnement en attente',$rows[0]['statusLabel']);
        self::assertNull($rows[0]['arrivalAt']);self::assertNotNull($rows[0]['ships']);
        $notices=$this->em->getRepository(\App\Entity\Notification::class)->findBy(['recipient'=>$this->player->currentPlayerUniverse]);
        self::assertCount(1,array_filter($notices,static fn($notice)=>str_starts_with($notice->message,'Stationnement en attente')));
    }

    public function testRecallPreservesUnusedReserveAndNeverInstallsAtOriginalDestination(): void
    {
        $fleet=$this->send(['hydrogen'=>30]);$this->clock->sleep(1);$service=self::getContainer()->get(FleetRecallService::class);$member=$this->player->currentPlayerUniverse;
        $q=$service->preview($member,$fleet);$original=$fleet->reservedFuel;
        $service->recall($member,$fleet->id,null,$q['version'],str_repeat('b',32));
        self::assertEqualsWithDelta($original-$q['spent_now'],$fleet->reservedFuel,1e-8);$this->arrive($fleet);
        self::assertNull($this->stock($this->target));self::assertSame('completed',$fleet->status);
        self::assertEqualsWithDelta($q['unused_fuel'],$this->idle->storedFuel,1e-8);
    }

    public function testLostTargetFallsBackToOriginalHomeAndRechecksOwnershipOnReturn(): void
    {
        $fleet=$this->send();$old=$fleet->dispatchToken;$this->target->owner=$this->peer->currentPlayerUniverse;$this->em->flush();$this->arrive($fleet);
        self::assertSame('returning',$fleet->status);self::assertSame($this->home->id,$fleet->returnPlanet->id);
        $this->home->owner=$this->peer->currentPlayerUniverse;$this->fallback->owner=$this->peer->currentPlayerUniverse;$this->em->flush();$this->arrive($fleet);
        self::assertSame('station_waiting',$fleet->status);self::assertNull($fleet->arrivalAt);
        $cargo=$fleet->cargo;self::getContainer()->get(FleetService::class)->arrive($fleet->id,$old);self::assertSame($cargo,$fleet->cargo);
    }

    public function testNoHomeUsesShortestReachableOwnedColony(): void
    {
        $fleet=$this->send();$this->home->owner=$this->peer->currentPlayerUniverse;$this->target->owner=$this->peer->currentPlayerUniverse;
        // Le repli voisin est atteignable avec la réserve du trajet aller.
        $this->fallback->position=$this->target->position;$this->fallback->solarSystem=$this->target->solarSystem;
        // Garder des positions distinctes (contrainte de carte).
        $this->fallback->position=$this->target->position===1?2:1;
        $this->em->flush();$this->arrive($fleet);
        self::assertSame('returning',$fleet->status);self::assertSame($this->fallback->id,$fleet->returnPlanet->id);$this->arrive($fleet);
        self::assertSame('completed',$fleet->status);self::assertNotNull($this->stock($this->fallback));
    }

    private function wait(): Fleet
    {
        $fleet=$this->send(['hydrogen'=>100]);$this->home->owner=$this->peer->currentPlayerUniverse;$this->target->owner=$this->peer->currentPlayerUniverse;$this->fallback->owner=$this->peer->currentPlayerUniverse;
        $this->em->flush();$this->arrive($fleet);self::assertSame('station_waiting',$fleet->status);return $fleet;
    }

    public function testWaitingCanResumeOnceWithoutLosingOrUsingFreightFuel(): void
    {
        $fleet=$this->wait();$fuel=$fleet->reservedFuel;$cargo=$fleet->cargo;
        $this->target->owner=$this->player->currentPlayerUniverse;$this->em->flush();$service=self::getContainer()->get(FleetStationing::class);$member=$this->player->currentPlayerUniverse;
        $q=$service->preview($member,$fleet->id,$this->target->id);self::assertSame(0,$q['seconds']);
        $service->resume($member,$fleet->id,$this->target->id,$q['version'],str_repeat('b',32));
        self::assertSame('completed',$fleet->status);self::assertEqualsWithDelta($fuel,$this->stock($this->target)->storedFuel,1e-8);
        $service->resume($member,$fleet->id,$this->target->id,$q['version'],str_repeat('b',32));
        self::assertEqualsWithDelta($fuel,$this->stock($this->target)->storedFuel,1e-8);
        self::assertGreaterThanOrEqual(100,$cargo['hydrogen']);
    }

    public function testManualUnloadingPreservesOverflowAndIsIdempotent(): void
    {
        $this->idle->storedFuel=100;$this->idle->cargo=['metal'=>500];$this->em->flush();$service=self::getContainer()->get(FleetHold::class);$member=$this->player->currentPlayerUniverse;
        $before=$this->idle->storedFuel;$delivered=$service->unload($member,$this->home->id,'fuel',str_repeat('b',32));
        self::assertEqualsWithDelta($before,($delivered['hydrogen']??0)+$this->idle->storedFuel,1e-8);self::assertSame(['metal'=>500],$this->idle->cargo);
        self::assertSame([],$service->unload($member,$this->home->id,'fuel',str_repeat('b',32)));
    }

    public function testDepartureCannotStrandFreightWithoutShips(): void
    {
        $this->idle->cargo=['metal'=>1];$this->em->flush();$this->expectException(\DomainException::class);
        self::getContainer()->get(FleetService::class)->quote($this->player->currentPlayerUniverse,$this->home,'station',$this->target->id,['transport'=>4],1);
    }

    public function testOwnOrbitIsAutomaticallyAcceptedAndReturnsNormally(): void
    {
        $member=$this->player->currentPlayerUniverse;self::getContainer()->get(FleetService::class)->send($member,$this->home,'orbit',$this->target->id,['transport'=>1],1,[],str_repeat('a',32),1);
        $fleet=$this->em->getRepository(Fleet::class)->findOneBy(['status'=>'traveling']);$this->arrive($fleet);self::assertSame('orbiting',$fleet->status);
        $this->arrive($fleet);self::assertSame('returning',$fleet->status);$this->arrive($fleet);self::assertSame('completed',$fleet->status);
    }

    public function testWaitingPageSurvivesLostOriginAndIsPrivateAndCsrfProtected(): void
    {
        $fleet=$this->wait();$url='/flottes/'.$fleet->id.'/reprendre';$this->client->request('GET','/flottes');self::assertResponseIsSuccessful();
        self::assertSelectorExists('a[href="'.$url.'"]');$this->client->request('GET',$url);self::assertResponseIsSuccessful();
        $this->client->request('POST',$url,['_token'=>'invalid']);self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->peer);$this->client->request('GET',$url);self::assertResponseStatusCodeSame(404);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('resumeRefusals')]
    public function testResumeRefusalsLeaveFleetResourcesAndReceiptsUntouched(string $scenario): void
    {
        $fleet=$this->wait();$member=$this->player->currentPlayerUniverse;$target=$this->home;
        $target->owner=$member;
        if($scenario==='foreign_owner') $target->owner=$this->peer->currentPlayerUniverse;
        if($scenario==='foreign_member') $member=$this->peer->currentPlayerUniverse;
        if($scenario==='fuel') {$fleet->cargo['hydrogen']=100;$fleet->reservedFuel=0;}
        if($scenario==='unexplored') foreach($this->em->getRepository(PlayerDiscovery::class)->findBy(['playerUniverse'=>$member,'solarSystem'=>$target->solarSystem]) as $row) $this->em->remove($row);
        if($scenario==='universe') {
            $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);
            self::getContainer()->get(UniverseService::class)->select($this->peer,$universe->id);$member=$this->peer->currentPlayerUniverse;
        }
        if($scenario==='locked') $member->player->lockedAt=$this->clock->now();
        $this->em->flush();$connection=$this->em->getConnection();
        $before=$connection->fetchAssociative('SELECT status,cargo,reserved_fuel,dispatch_token,navigation FROM fleet WHERE id=?',[$fleet->id]);
        $stocks=$connection->fetchAllAssociative('SELECT planet_id,resource_type,current_stock FROM planet_resource ORDER BY planet_id,resource_type');
        $service=self::getContainer()->get(FleetStationing::class);
        $this->expectException(\DomainException::class);
        try {$service->resume($member,$fleet->id,$target->id,'obsolete',str_repeat('c',32));}
        finally {
            self::assertSame($before,$connection->fetchAssociative('SELECT status,cargo,reserved_fuel,dispatch_token,navigation FROM fleet WHERE id=?',[$fleet->id]));
            self::assertSame($stocks,$connection->fetchAllAssociative('SELECT planet_id,resource_type,current_stock FROM planet_resource ORDER BY planet_id,resource_type'));
            self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM action_receipt WHERE request_key=?',[str_repeat('c',32)]));
        }
    }

    public static function resumeRefusals(): array {return array_map(static fn($scenario)=>[$scenario],['foreign_owner','foreign_member','universe','unexplored','fuel','locked','version']);}

    public function testForeignUnloadingNeverMovesResourceStocks(): void
    {
        $this->idle->storedFuel=100;$this->em->flush();$connection=$this->em->getConnection();
        $before=$connection->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=?',[$this->home->id]);
        $this->expectException(\DomainException::class);
        try {self::getContainer()->get(FleetHold::class)->unload($this->peer->currentPlayerUniverse,$this->home->id,'fuel',str_repeat('b',32));}
        finally {
            self::assertSame($before,$connection->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=?',[$this->home->id]));
            self::assertEqualsWithDelta(100,(float)$connection->fetchOne('SELECT stored_fuel FROM fleet WHERE id=?',[$this->idle->id]),1e-8);
        }
    }
}
