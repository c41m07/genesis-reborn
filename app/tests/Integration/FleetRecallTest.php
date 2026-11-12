<?php
namespace App\Tests\Integration;

use App\Entity\{ActionReceipt,CombatEvent,CombatParticipant,Fleet,FleetShip,Guild,GuildMember,Planet,PlanetResource,Player,PlayerBoost,PlayerDiscovery,PlayerTechnology,SolarSystem,TransportReport,Universe};
use App\Service\Fleet\{FleetNavigation,FleetRecallService,FleetService};
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class FleetRecallTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Player $peer;
    private Planet $home;
    private Planet $target;
    private Planet $npc;
    private Planet $virgin;

    protected function setUp(): void
    {
        $this->client=self::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();$this->clock=new MockClock('2026-11-12T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        $this->player=$this->player('RecallAuthor');$this->peer=$this->player('RecallPeer');
        $m=$this->player->currentPlayerUniverse;
        $this->home=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$m]);
        $old=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->peer->currentPlayerUniverse]);$old->owner=null;$old->acquiredAt=null;
        $free=array_values(array_filter($this->em->getRepository(Planet::class)->findBy(['solarSystem'=>$this->home->solarSystem],['position'=>'ASC']),fn(Planet $p)=>$p->id!==$this->home->id));
        foreach(array_slice($free,0,3) as $p) { $p->owner=null;$p->acquiredAt=null;$p->npcPlanet=false; }
        $this->target=$free[0];$this->target->owner=$this->peer->currentPlayerUniverse;$this->target->npcPlanet=false;
        $this->npc=$free[1];$this->npc->npcPlanet=true;$this->virgin=$free[2];$this->virgin->npcPlanet=false;
        self::getContainer()->get(ResourceService::class)->initialize($this->target);
        $this->peer->preferences['transportReception']='all';
        $tech=new PlayerTechnology();$tech->playerUniverse=$m;$tech->slug='colonial_expansion';$tech->level=1;$this->em->persist($tech);
        $guild=new Guild();$guild->leader=$m;$guild->universe=$m->universe;$guild->name='Rappels';$guild->tag='RAP';$this->em->persist($guild);
        foreach([$m,$this->peer->currentPlayerUniverse] as $member) { $gm=new GuildMember();$gm->guild=$guild;$gm->playerUniverse=$member;$this->em->persist($gm); }
        $idle=new Fleet();$idle->homePlanet=$this->home;$idle->playerUniverse=$m;$this->em->persist($idle);
        foreach(['transport','fighter','probe','colony_ship'] as $slug) { $ship=new FleetShip();$ship->fleet=$idle;$ship->slug=$slug;$ship->quantity=5;$this->em->persist($ship); }
        $this->em->flush();$this->client->loginUser($this->player);
    }
    private function player(string $name): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function send(string $mission='transport'): Fleet
    {
        $target=match($mission) { 'attack','conquer'=>$this->npc->id,'colonize'=>$this->virgin->id,'explore'=>$this->home->solarSystem->id,default=>$this->target->id };
        $composition=match($mission) { 'explore','spy'=>['probe'=>1],'colonize'=>['colony_ship'=>1],'conquer'=>['colony_ship'=>1,'fighter'=>1],default=>['transport'=>1,'fighter'=>1] };
        self::getContainer()->get(FleetService::class)->send($this->player->currentPlayerUniverse,$this->home,$mission,$target,$composition,1,
            $mission==='transport'?['metal'=>100,'hydrogen'=>50]:[],str_repeat('a',32),$mission==='orbit'?1:0);
        return $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$this->player->currentPlayerUniverse,'status'=>'traveling']);
    }
    private function totalShips(): int { return (int)$this->em->getConnection()->fetchOne('SELECT SUM(quantity) FROM fleet_ship'); }
    private function state(int $id): array { return $this->em->getConnection()->fetchAssociative('SELECT status,arrival_at,departure_at,cargo,reserved_fuel,orbit_fuel,dispatch_token,return_planet_id,navigation FROM fleet WHERE id=?',[$id]); }

    #[\PHPUnit\Framework\Attributes\DataProvider('missions')]
    public function testEveryOutboundMissionCanReturnWithoutCompletingItsGoal(string $mission): void
    {
        $total=$this->totalShips();$f=$this->send($mission);$oldToken=$f->dispatchToken;$originalSeconds=$f->travelSeconds;
        $this->clock->sleep(max(1,(int)floor($originalSeconds/4)));
        $service=self::getContainer()->get(FleetRecallService::class);$m=$this->player->currentPlayerUniverse;$q=$service->preview($m,$f);
        $cargo=$f->cargo;$stock=$this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type',[$this->home->id]);
        self::assertTrue($service->recall($m,$f->id,null,$q['version'],str_repeat('b',32)));
        self::assertSame('returning',$f->status);self::assertSame($this->home->id,$f->returnPlanet->id);
        self::assertEqualsWithDelta(($cargo['hydrogen']??0)-$q['spent_now'],$f->cargo['hydrogen'],.000001);
        self::assertEqualsWithDelta($q['return_fuel'],$f->reservedFuel,.000001);
        self::assertSame($stock,$this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type',[$this->home->id]));
        self::assertFalse($service->recall($m,$f->id,null,$q['version'],str_repeat('b',32)));
        $newToken=$f->dispatchToken;self::getContainer()->get(FleetService::class)->arrive($f->id,$oldToken);self::assertSame('returning',$f->status);
        $this->clock->sleep($f->travelSeconds+1);self::getContainer()->get(FleetService::class)->arrive($f->id,$oldToken);self::assertSame('returning',$f->status);
        self::getContainer()->get(FleetService::class)->arrive($f->id,$newToken);
        self::assertSame('completed',$f->status);self::assertSame($total,$this->totalShips());
        self::getContainer()->get(FleetService::class)->arrive($f->id,$newToken);self::assertSame($total,$this->totalShips());
        self::assertSame($this->peer->currentPlayerUniverse->id,$this->target->owner->id);self::assertNull($this->virgin->owner);self::assertNull($this->npc->owner);
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM espionage_report'));
        if($mission==='transport') {
            $report=$this->em->getRepository(TransportReport::class)->findOneBy(['fleet'=>$f]);
            self::assertSame('refused',$report->status);self::assertSame('player_recall',$report->snapshot['refusal_reason']);
            self::assertSame([],$report->snapshot['delivered']);self::assertEqualsWithDelta(100,($report->snapshot['returned_deposited']['metal']??0)+($report->snapshot['return_overflow']['metal']??0),.000001);
        }
    }
    public static function missions(): array { return array_map(fn($m)=>[$m],['explore','colonize','transport','attack','conquer','defend','orbit','spy']); }

    public function testBoostedFlightKeepsActualProgressAndOldMessagesAreObsolete(): void
    {
        $f=$this->send();$m=$this->player->currentPlayerUniverse;
        foreach([1,2] as $n) { $boost=new PlayerBoost();$boost->playerUniverse=$m;$boost->sourceType='admin';$boost->sourceKey='recall-test-'.$n;$boost->earnedAt=$this->clock->now();$this->em->persist($boost); }
        $this->em->flush();$this->clock->sleep(max(1,(int)floor($f->travelSeconds/4)));
        $nav=self::getContainer()->get(FleetNavigation::class);$before=$nav->progress($f,$this->clock->now());$tokens=[$f->dispatchToken];
        $boosts=self::getContainer()->get(\App\Service\Boost\BoostUseService::class);
        $boosts->useOnFleet($m,$f->id,str_repeat('c',32));$tokens[]=$f->dispatchToken;
        self::assertEqualsWithDelta($before,$nav->progress($f,$this->clock->now()),.000001);
        $this->clock->sleep(1);$beforeSecond=$nav->progress($f,$this->clock->now());
        $boosts->useOnFleet($m,$f->id,str_repeat('d',32));$tokens[]=$f->dispatchToken;
        self::assertEqualsWithDelta($beforeSecond,$nav->progress($f,$this->clock->now()),.000001);
        $service=self::getContainer()->get(FleetRecallService::class);$q=$service->preview($m,$f);
        self::assertGreaterThan($before,$q['progress']);$service->recall($m,$f->id,null,$q['version'],str_repeat('b',32));
        foreach($tokens as $old) { self::getContainer()->get(FleetService::class)->arrive($f->id,$old);self::assertSame('returning',$f->status); }
        self::assertSame(2,$this->em->getRepository(PlayerBoost::class)->count(['status'=>'used']));
    }

    public function testOrbitRecallConsumesOnlyItsElapsedShareAndPendingOrbitReturns(): void
    {
        $f=$this->send('orbit');$this->clock->sleep($f->travelSeconds+1);
        self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        if($f->status==='orbit_pending') self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true);
        self::assertSame('orbiting',$f->status);
        $orbitFuel=$f->orbitFuel;$hydrogen=$f->cargo['hydrogen'];$this->clock->sleep(900);
        $recalls=self::getContainer()->get(FleetRecallService::class);$q=$recalls->preview($this->player->currentPlayerUniverse,$f);
        self::assertEqualsWithDelta($orbitFuel/4,$q['spent_now'],.000001);
        $recalls->recall($this->player->currentPlayerUniverse,$f->id,null,$q['version'],str_repeat('b',32));
        self::assertEqualsWithDelta($hydrogen-$orbitFuel/4,$f->cargo['hydrogen'],.000001);self::assertSame(0.0,$f->orbitFuel);
    }

    public function testArrivedPendingOrbitCanBeRecalledWithoutBurningOrbitFuel(): void
    {
        $f=$this->send('orbit');$this->clock->sleep($f->travelSeconds+1);
        self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        self::assertSame('orbit_pending',$f->status);$hydrogen=$f->cargo['hydrogen'];
        $service=self::getContainer()->get(FleetRecallService::class);$q=$service->preview($this->player->currentPlayerUniverse,$f);
        self::assertSame(0.0,$q['spent_now']);$service->recall($this->player->currentPlayerUniverse,$f->id,null,$q['version'],str_repeat('b',32));
        self::assertEqualsWithDelta($hydrogen,$f->cargo['hydrogen'],.000001);self::assertSame('returning',$f->status);
    }

    public function testPendingOrbitRoundingNeverCreatesFuelOrRefusesAnOtherwiseFundedReturn(): void
    {
        $f=$this->send('orbit');$this->clock->sleep($f->travelSeconds+1);
        self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        self::assertSame('orbit_pending',$f->status);
        $f->orbitFuel=0;$f->reservedFuel=$f->cargo['hydrogen']+5e-10;$this->em->flush();
        $service=self::getContainer()->get(FleetRecallService::class);
        $q=$service->preview($this->player->currentPlayerUniverse,$f);
        self::assertLessThanOrEqual($f->cargo['hydrogen'],$q['return_fuel']);
        $before=$f->cargo['hydrogen'];$ships=$this->totalShips();
        $service->recall($this->player->currentPlayerUniverse,$f->id,null,$q['version'],str_repeat('b',32));
        self::assertSame('returning',$f->status);self::assertLessThanOrEqual($f->cargo['hydrogen'],$f->reservedFuel);
        self::assertEqualsWithDelta($before,$f->cargo['hydrogen'],1e-12);self::assertSame($ships,$this->totalShips());
    }

    public function testLostOriginUsesChosenOwnedColonyAndPreservesFreight(): void
    {
        $f=$this->send();$oldHome=$this->home->id;$m=$this->player->currentPlayerUniverse;
        $this->home->owner=$this->peer->currentPlayerUniverse;$this->target->owner=$m;$this->em->flush();$this->clock->sleep(max(1,(int)floor($f->travelSeconds/2)));
        $service=self::getContainer()->get(FleetRecallService::class);$q=$service->preview($m,$f,$this->target->id);
        self::assertTrue($q['origin_lost']);self::assertEqualsWithDelta(50,$q['freight']['hydrogen'],.000001);
        $service->recall($m,$f->id,$this->target->id,$q['version'],str_repeat('b',32));self::assertSame($oldHome,$f->homePlanet->id);
        $this->clock->sleep($f->travelSeconds+1);self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        self::assertSame('completed',$f->status);$idle=$this->em->getRepository(Fleet::class)->findOneBy(['homePlanet'=>$this->target,'playerUniverse'=>$m,'status'=>'idle']);self::assertNotNull($idle);
        self::assertSame(1,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$idle,'slug'=>'transport'])->quantity);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('refusals')]
    public function testRefusalNeverChangesFleetOrRecordsReceipt(string $scenario): void
    {
        $f=$this->send();$m=$this->player->currentPlayerUniverse;$return=null;
        if(in_array($scenario,['returning','occupying','attacking','defending'],true)) $f->status=$scenario;
        if($scenario==='expired') $this->clock->sleep($f->travelSeconds+1);
        if($scenario==='legacy') { $f->navigation=null;$f->arrivalAt=$f->arrivalAt->modify('-1 second'); }
        if($scenario==='lost') $this->home->owner=$this->peer->currentPlayerUniverse;
        if($scenario==='foreign_fleet') $m=$this->peer->currentPlayerUniverse;
        if($scenario==='combat') {
            $e=new CombatEvent();$e->planet=$this->target;$e->startedAt=$this->clock->now();$e->resolveAt=$this->clock->now()->modify('+10 minutes');$e->seed=1;$this->em->persist($e);
            $cp=new CombatParticipant();$cp->combatEvent=$e;$cp->fleet=$f;$cp->playerUniverse=$this->player->currentPlayerUniverse;$cp->role='attacker';$cp->arrivalAt=$e->startedAt;$this->em->persist($cp);
        }
        if($scenario==='fuel') {
            $this->home->owner=$this->peer->currentPlayerUniverse;$this->virgin->owner=$this->player->currentPlayerUniverse;
            $f->reservedFuel=.000001;
            $return=$this->virgin->id;$this->clock->sleep(1);
        }
        $this->em->flush();$before=$this->state($f->id);$connection=$this->em->getConnection();$version=$scenario==='version'?'stale':hash('sha256',$f->dispatchToken);
        $this->expectException(\DomainException::class);
        try { self::getContainer()->get(FleetRecallService::class)->recall($m,$f->id,$return,$version,str_repeat('b',32)); }
        finally {
            self::assertSame($before,$connection->fetchAssociative('SELECT status,arrival_at,departure_at,cargo,reserved_fuel,orbit_fuel,dispatch_token,return_planet_id,navigation FROM fleet WHERE id=?',[$f->id]));
            self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM action_receipt WHERE request_key=?',[str_repeat('b',32)]));
        }
    }
    public static function refusals(): array { return array_map(fn($s)=>[$s],['returning','occupying','attacking','defending','expired','legacy','lost','foreign_fleet','combat','fuel','version']); }

    public function testUnboostedLocalLegacyFlightCanBeReconstructed(): void
    {
        $f=$this->send();$f->navigation=null;$this->em->flush();$this->clock->sleep(1);
        $service=self::getContainer()->get(FleetRecallService::class);$q=$service->preview($this->player->currentPlayerUniverse,$f);
        self::assertGreaterThan(0,$q['progress']);
        $service->recall($this->player->currentPlayerUniverse,$f->id,null,$q['version'],str_repeat('b',32));
        self::assertSame('returning',$f->status);self::assertSame(1,$f->navigation['version']);
    }

    public function testLegacyBoostWithoutNavigationIsRejectedWithoutMutation(): void
    {
        $f=$this->send();$f->navigation=null;$this->clock->sleep(1);
        $boost=new PlayerBoost();$boost->playerUniverse=$this->player->currentPlayerUniverse;$boost->sourceType='admin';$boost->sourceKey='legacy-recall';
        $boost->status='used';$boost->earnedAt=$f->departureAt;$boost->usedAt=$this->clock->now();$boost->operationType='fleet';$boost->operationId=$f->id;$this->em->persist($boost);$this->em->flush();
        $before=$this->state($f->id);$this->expectException(\DomainException::class);
        try { self::getContainer()->get(FleetRecallService::class)->preview($this->player->currentPlayerUniverse,$f); }
        finally { self::assertSame($before,$this->state($f->id)); }
    }

    public function testDistantFallbackRefusesInsufficientFuelWithoutUsingPlanetStocks(): void
    {
        $f=$this->send();$m=$this->player->currentPlayerUniverse;$fallback=null;
        foreach($this->em->getRepository(Planet::class)->findAll() as $planet) if($planet->solarSystem->galaxy->id===$this->home->solarSystem->galaxy->id && $planet->solarSystem->id!==$this->home->solarSystem->id) { $fallback=$planet;break; }
        self::assertNotNull($fallback);$fallback->owner=$m;$fallback->solarSystem->coordX=$this->home->solarSystem->coordX+1000;
        $this->home->owner=$this->peer->currentPlayerUniverse;$this->em->flush();$this->clock->sleep(1);
        $connection=$this->em->getConnection();$state=$this->state($f->id);$stocks=$connection->fetchAllAssociative('SELECT planet_id,resource_type,current_stock FROM planet_resource ORDER BY planet_id,resource_type');
        $this->expectException(\DomainException::class);
        try { self::getContainer()->get(FleetRecallService::class)->recall($m,$f->id,$fallback->id,hash('sha256',$f->dispatchToken),str_repeat('b',32)); }
        finally {
            self::assertSame($state,$connection->fetchAssociative('SELECT status,arrival_at,departure_at,cargo,reserved_fuel,orbit_fuel,dispatch_token,return_planet_id,navigation FROM fleet WHERE id=?',[$f->id]));
            self::assertSame($stocks,$connection->fetchAllAssociative('SELECT planet_id,resource_type,current_stock FROM planet_resource ORDER BY planet_id,resource_type'));
        }
    }

    public function testHttpRecallIsPrivateCsrfProtectedAndConfirmed(): void
    {
        $f=$this->send();$this->clock->sleep(1);$url='/flottes/'.$f->id.'/rappel';$crawler=$this->client->request('GET',$url);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1','Rappeler la flotte');$form=$crawler->selectButton('Confirmer le rappel')->form();$this->client->submit($form);self::assertResponseRedirects();
        $this->client->request('POST',$url,['_token'=>'forged']);self::assertResponseStatusCodeSame(403);
        $this->client->loginUser($this->peer);$this->client->request('GET',$url);self::assertResponseStatusCodeSame(404);
    }
}
