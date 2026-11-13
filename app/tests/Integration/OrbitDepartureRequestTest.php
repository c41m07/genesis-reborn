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

final class OrbitDepartureRequestTest extends WebTestCase
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
        $this->player=$this->player('OrbitAuthor');$this->peer=$this->player('OrbitPeer');
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

    private function request(): array { return self::getContainer()->get(\App\Service\Fleet\OrbitRequestState::class)->read($this->em->getRepository(Fleet::class)->findOneBy(['mission'=>'orbit','status'=>'traveling'])); }
    private function ally(): void
    {
        $guild=new Guild();$guild->leader=$this->player->currentPlayerUniverse;$guild->universe=$guild->leader->universe;$guild->name='Orbites';$guild->tag='ORB';$this->em->persist($guild);
        foreach([$guild->leader,$this->peer->currentPlayerUniverse] as $m) { $gm=new GuildMember();$gm->guild=$guild;$gm->playerUniverse=$m;$this->em->persist($gm); }$this->em->flush();
    }
    public function testNeutralRequestExistsAtDepartureAndAcceptanceDoesNotTeleportFleet(): void
    {
        $f=$this->send('orbit');$token=$f->dispatchToken;$arrival=$f->arrivalAt;
        self::assertSame('pending',$this->request()['status']);
        self::assertSame($arrival->format(DATE_ATOM),$this->request()['expires_at']);
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM notification WHERE recipient_id=?',[$this->peer->currentPlayerUniverse->id]));
        self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$this->request()['key']);
        self::assertSame('traveling',$f->status);self::assertSame($token,$f->dispatchToken);self::assertEquals($arrival,$f->arrivalAt);
        $this->clock->sleep($f->travelSeconds+1);self::getContainer()->get(FleetService::class)->arrive($f->id,$token);
        self::assertSame('orbiting',$f->status);self::assertSame('arrived',$f->navigation['orbit_request']['status']);
    }
    public function testSilenceAtArrivalReturnsWithoutOrbitFuelConsumptionOrDuplicateShips(): void
    {
        $f=$this->send('orbit');$ships=$this->totalShips();$hydrogen=$f->cargo['hydrogen'];$oneWay=($f->reservedFuel-$f->orbitFuel)/2;$token=$f->dispatchToken;
        $this->clock->sleep($f->travelSeconds+1);$service=self::getContainer()->get(FleetService::class);$service->arrive($f->id,$token);
        self::assertSame('returning',$f->status);self::assertSame('expired',$f->navigation['orbit_request']['status']);
        self::assertEqualsWithDelta($hydrogen-$oneWay,$f->cargo['hydrogen'],.000001);self::assertSame(0.0,$f->orbitFuel);
        $service->arrive($f->id,$token);self::assertSame($ships,$this->totalShips());
        $this->clock->sleep($f->travelSeconds+1);$service->arrive($f->id,$f->dispatchToken);self::assertSame($ships,$this->totalShips());
    }
    public function testRefusalUsesPartialReturnAndKeepsClosedDecision(): void
    {
        $f=$this->send('orbit');$token=$f->dispatchToken;$key=$this->request()['key'];$this->clock->sleep(max(1,(int)floor($f->travelSeconds/4)));
        $q=self::getContainer()->get(FleetRecallService::class)->preview($this->player->currentPlayerUniverse,$f);$cargo=$f->cargo;
        self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,false,$key);
        self::assertSame('returning',$f->status);self::assertSame('refused',$f->navigation['orbit_request']['status']);self::assertSame($q['seconds'],$f->travelSeconds);
        self::assertEqualsWithDelta($cargo['hydrogen']-$q['spent_now'],$f->cargo['hydrogen'],.000001);
        self::getContainer()->get(FleetService::class)->arrive($f->id,$token);self::assertSame('returning',$f->status);
        $before=$this->state($f->id);
        try { self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$key);self::fail('Réponse périmée acceptée.'); }
        catch(\DomainException) { self::assertSame($before,$this->state($f->id)); }
    }
    public function testAlliedRequestIsAutomaticButLosingAllianceInvalidatesItAtArrival(): void
    {
        $this->ally();$f=$this->send('orbit');self::assertSame('accepted_auto',$this->request()['status']);
        foreach($this->em->getRepository(GuildMember::class)->findAll() as $member) $this->em->remove($member);$this->em->flush();
        $this->clock->sleep($f->travelSeconds+1);self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        self::assertSame('returning',$f->status);self::assertSame('invalid',$f->navigation['orbit_request']['status']);
    }
    public function testSenderWithdrawalClosesRequestAndStaleResponseCannotAcceptIt(): void
    {
        $f=$this->send('orbit');$key=$this->request()['key'];$this->clock->sleep(1);
        $recall=self::getContainer()->get(FleetRecallService::class);$m=$this->player->currentPlayerUniverse;$q=$recall->preview($m,$f);
        $recall->recall($m,$f->id,null,$q['version'],str_repeat('b',32));
        self::assertSame('withdrawn',$f->navigation['orbit_request']['status']);
        $this->expectException(\DomainException::class);self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$key);
    }
    public function testBoostChangesDeadlineButKeepsDecisionKeyAndOldArrivalIsObsolete(): void
    {
        $f=$this->send('orbit');$key=$this->request()['key'];$oldToken=$f->dispatchToken;
        self::getContainer()->get(FleetService::class)->reschedulePlayerBoost($f,$f->departureAt->modify('+10 seconds'));$this->em->flush();
        self::assertSame($key,$this->request()['key']);self::assertSame($f->arrivalAt->format(DATE_ATOM),$this->request()['expires_at']);
        $this->clock->sleep(11);self::getContainer()->get(FleetService::class)->arrive($f->id,$oldToken);self::assertSame('traveling',$f->status);
        self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);self::assertSame('returning',$f->status);
    }
    public function testLegacyDepartureKeepsArrivalRequestWithoutInventingDeadline(): void
    {
        $this->ally();$f=$this->send('orbit');unset($f->navigation['orbit_request']);$this->em->flush();
        $this->clock->sleep($f->travelSeconds+1);self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        self::assertSame('orbit_pending',$f->status);self::assertNull($f->arrivalAt);self::assertArrayNotHasKey('orbit_request',$f->navigation);
        self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true);self::assertSame('orbiting',$f->status);
    }
    public function testAcceptedNeutralOrbitJoinsDefenseAtArrivalOnly(): void
    {
        $f=$this->send('orbit');$key=$this->request()['key'];$service=self::getContainer()->get(FleetService::class);
        $service->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$key);
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM combat_participant'));
        $event=new CombatEvent();$event->planet=$this->target;$event->startedAt=$f->arrivalAt;$event->resolveAt=$f->arrivalAt->modify('+10 minutes');$event->seed=1;$event->dispatchToken='orbit-test';$this->em->persist($event);$this->em->flush();
        $this->clock->sleep($f->travelSeconds+1);$service->arrive($f->id,$f->dispatchToken);
        self::assertSame('defending',$f->status);
        $participant=$this->em->getRepository(CombatParticipant::class)->findOneBy(['fleet'=>$f]);self::assertSame('defender',$participant->role);
    }

    public function testChangedOwnerCannotReuseFormerOwnersConsent(): void
    {
        $f=$this->send('orbit');$key=$this->request()['key'];$service=self::getContainer()->get(FleetService::class);
        $service->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$key);
        $this->target->owner=null;$this->em->flush();
        $this->clock->sleep($f->travelSeconds+1);$service->arrive($f->id,$f->dispatchToken);
        self::assertSame('returning',$f->status);self::assertSame('invalid',$f->navigation['orbit_request']['status']);
    }

    public function testNpcWorldsAreRefusedButCapturedPlayerColoniesRemainEligible(): void
    {
        $policy=self::getContainer()->get(\App\Service\Fleet\OrbitPolicy::class);$m=$this->player->currentPlayerUniverse;
        self::assertFalse($policy->canRequest($m,$this->npc));self::assertFalse($policy->canRequest($m,$this->virgin));self::assertFalse($policy->canRequest($m,$this->home));
        $this->target->npcPlanet=true;self::assertTrue($policy->canRequest($m,$this->target));
    }

    public function testRequestViewIsPrivateAndHidesUnknownOriginIncludingSurveillance(): void
    {
        $f=$this->send('orbit');$host=$this->peer->currentPlayerUniverse;
        foreach($this->em->getRepository(PlayerDiscovery::class)->findBy(['playerUniverse'=>$host,'solarSystem'=>$this->home->solarSystem]) as $d) $this->em->remove($d);
        $this->em->flush();$view=self::getContainer()->get(\App\Service\Fleet\OrbitRequestView::class);
        $rows=$view->forMember($host);self::assertCount(1,$rows);self::assertNull($rows[0]['origin']);self::assertTrue($rows[0]['can_respond']);
        $other=$this->player('OrbitStranger');self::assertSame([],$view->forMember($other->currentPlayerUniverse));
        $this->client->loginUser($this->peer);
        $this->client->request('GET','/flottes/surveillance?orbits=1');self::assertResponseIsSuccessful();
        $payload=json_decode($this->client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);
        self::assertStringContainsString('système non découvert',$payload['orbitHtml']);
        self::assertStringNotContainsString($this->home->coordinates(),$payload['orbitHtml']);
        self::assertStringNotContainsString('reservedFuel',$payload['orbitHtml']);
    }

    public function testHttpRejectsMissingCsrfAndForeignDecisionWithoutStateChange(): void
    {
        $f=$this->send('orbit');$key=$this->request()['key'];$before=$this->state($f->id);
        $this->client->request('POST','/flottes/orbite/'.$f->id.'/repondre',['decision'=>'accept','request_key'=>$key]);self::assertResponseStatusCodeSame(403);
        self::assertSame($before,$this->state($f->id));
        $this->expectException(\DomainException::class);self::getContainer()->get(FleetService::class)->decideOrbit($this->player->currentPlayerUniverse,$f->id,true,$key);
    }

    public function testHostileRelationRejectsDepartureAndInvalidatesAcceptedArrival(): void
    {
        $f=$this->send('orbit');$service=self::getContainer()->get(FleetService::class);
        $service->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$this->request()['key']);
        $contract=new \App\Entity\AllianceContract();$contract->playerA=$this->player->currentPlayerUniverse;$contract->playerB=$this->peer->currentPlayerUniverse;
        if($contract->playerA->id>$contract->playerB->id) [$contract->playerA,$contract->playerB]=[$contract->playerB,$contract->playerA];
        $contract->proposer=$contract->playerA;$contract->status='enemies';$this->em->persist($contract);$this->em->flush();
        self::assertFalse(self::getContainer()->get(\App\Service\Fleet\OrbitPolicy::class)->canRequest($this->player->currentPlayerUniverse,$this->target));
        $this->clock->sleep($f->travelSeconds+1);$service->arrive($f->id,$f->dispatchToken);
        self::assertSame('returning',$f->status);self::assertSame('invalid',$f->navigation['orbit_request']['status']);
    }
    public function testMissingDecisionKeyIsRefused(): void
    {
        $f=$this->send('orbit');$before=$this->state($f->id);
        try { self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true);self::fail('Clé absente acceptée.'); }
        catch(\DomainException) { self::assertSame($before,$this->state($f->id)); }
    }

    public function testOtherUniverseCannotReadOrAnswerRequests(): void
    {
        $f=$this->send('orbit');$key=$this->request()['key'];
        $other=new Universe();$other->slug='orbit-other';$other->name='Autre univers';$this->em->persist($other);
        $foreign=new \App\Entity\PlayerUniverse();$foreign->universe=$other;$foreign->player=$this->peer;$foreign->joinedAt=$this->clock->now();$this->em->persist($foreign);$this->em->flush();
        self::assertSame([],self::getContainer()->get(\App\Service\Fleet\OrbitRequestView::class)->forMember($foreign));
        self::assertFalse(self::getContainer()->get(\App\Service\Fleet\OrbitPolicy::class)->canRequest($foreign,$this->target));
        $this->expectException(\DomainException::class);self::getContainer()->get(FleetService::class)->decideOrbit($foreign,$f->id,true,$key);
    }

    public function testCapturedNpcColonyUsesItsCurrentOwnerForOrbitConsent(): void
    {
        $this->target->npcPlanet=true;$this->em->flush();
        $f=$this->send('orbit');self::assertSame('pending',$this->request()['status']);
        self::getContainer()->get(FleetService::class)->decideOrbit($this->peer->currentPlayerUniverse,$f->id,true,$this->request()['key']);
        $this->clock->sleep($f->travelSeconds+1);self::getContainer()->get(FleetService::class)->arrive($f->id,$f->dispatchToken);
        self::assertSame('orbiting',$f->status);
    }

}
