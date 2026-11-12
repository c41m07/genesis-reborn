<?php
namespace App\Tests\Integration;

use App\Entity\{AdminAuditLog,CombatEvent,Fleet,FleetShip,Planet,PlanetBuilding,PlanetDefense,PlanetOccupation,PlanetResource,Player,PlayerDiscovery,PlayerTechnology,PlayerUniverse,QueueItem,Universe};
use App\Service\Colony\{ColonyAbandonmentService,ResourceService};
use App\Service\Fleet\FleetService;
use App\Service\Player\RegistrationService;
use App\Service\Queue\QueueService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Uid\Uuid;

final class ColonyAbandonmentTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Player $peer;
    private PlayerUniverse $member;
    private Planet $home;
    private Planet $colony;
    private Planet $peerPlanet;

    protected function setUp(): void
    {
        $this->client=self::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $tool=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();$this->clock=new MockClock('2026-11-12T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        $this->player=$this->player('AbandonAuthor');$this->peer=$this->player('AbandonPeer');$this->member=$this->player->currentPlayerUniverse;
        $this->home=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->member]);
        $this->peerPlanet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->peer->currentPlayerUniverse]);$this->peerPlanet->name='Origine confidentielle adverse';
        $this->colony=$this->em->createQueryBuilder()->select('p')->from(Planet::class,'p')->join('p.solarSystem','s')
            ->where('s.galaxy = :galaxy')->andWhere('p.owner IS NULL')->andWhere('p.npcPlanet = false')
            ->setParameter('galaxy',$this->home->solarSystem->galaxy)->orderBy('p.id','ASC')->setMaxResults(1)->getQuery()->getSingleResult();
        $this->colony->acquireBy($this->member,$this->clock->now()->modify('+1 second'));
        self::getContainer()->get(ResourceService::class)->initialize($this->colony);
        if(!$this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$this->member,'solarSystem'=>$this->colony->solarSystem])) {
            $d=new PlayerDiscovery();$d->playerUniverse=$this->member;$d->solarSystem=$this->colony->solarSystem;$this->em->persist($d);
        }
        $building=new PlanetBuilding();$building->planet=$this->home;$building->slug='mine_metal';$building->level=2;$this->em->persist($building);
        $defense=new PlanetDefense();$defense->planet=$this->home;$defense->slug=array_key_first(self::getContainer()->get(\App\Service\Catalog\GameCatalog::class)->all('defenses'));$defense->quantity=3;$this->em->persist($defense);
        foreach(['tactical_intelligence'=>2,'colonial_expansion'=>1] as $slug=>$level) {
            $technology=new PlayerTechnology();$technology->playerUniverse=$this->member;$technology->slug=$slug;$technology->level=$level;$this->em->persist($technology);
        }
        $idle=new Fleet();$idle->homePlanet=$this->home;$idle->playerUniverse=$this->member;$this->em->persist($idle);
        $ship=new FleetShip();$ship->fleet=$idle;$ship->slug='fighter';$ship->quantity=2;$this->em->persist($ship);
        $this->em->flush();$this->client->loginUser($this->player);
    }

    private function player(string $name): Player
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);self::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function service(): ColonyAbandonmentService { return self::getContainer()->get(ColonyAbandonmentService::class); }
    private function confirm(?array $preview=null,string $name=''): bool
    {
        $preview??=$this->service()->preview($this->member,$this->home);
        return $this->service()->abandon($this->member,$this->home,$name!==''?$name:$this->home->name,str_repeat('a',32),$preview['version']);
    }
    private function stocks(Planet $p): array { return $this->em->getConnection()->fetchAllAssociative('SELECT resource_type,current_stock FROM planet_resource WHERE planet_id=? ORDER BY resource_type',[$p->id]); }
    private function auditCount(): int { return (int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM admin_audit_log WHERE action='player_colony_abandoned'"); }
    private function queue(Planet $planet,string $type='building',?QueueItem $depends=null): QueueItem
    {
        $q=new QueueItem();$q->planet=$planet;$q->playerUniverse=$this->member;$q->queueType=$type;
        $q->itemSlug=$type==='research'?'tactical_intelligence':($type==='shipyard'?'fighter':'mine_metal');$q->itemLevel=$type==='shipyard'?null:3;
        $q->durationSeconds=3600;$q->startedAt=$depends===null?$this->clock->now():null;$q->queuedAt=$this->clock->now();
        $q->dispatchToken=Uuid::v4()->toRfc4122();$q->paidCosts=['metal'=>500,'hydrogen'=>100];$q->dependsOn=$depends;$this->em->persist($q);$this->em->flush();return $q;
    }

    public function testCapturedColonyBecomesVirginWithAuditedLossesAndUnchangedOtherColony(): void
    {
        $this->home->npcPlanet=true;$this->home->npcGarrisonInitialized=true;
        foreach(['building','research','shipyard'] as $type) $this->queue($this->home,$type);
        $history=new CombatEvent();$history->planet=$this->home;$history->status='resolved';$history->startedAt=$this->clock->now();$history->resolveAt=$this->clock->now();$history->seed=1;$this->em->persist($history);$this->em->flush();
        $stocks=$this->stocks($this->colony);$preview=$this->service()->preview($this->member,$this->home);self::assertSame([],$preview['blockers']);
        self::assertCount(3,$preview['orders']);self::assertTrue($this->confirm($preview));
        self::assertNull($this->home->owner);self::assertNull($this->home->acquiredAt);self::assertFalse($this->home->npcPlanet);self::assertFalse($this->home->npcGarrisonInitialized);
        foreach(['planet_resource','planet_building','planet_defense','queue_item'] as $table) self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM '.$table.' WHERE planet_id=?',[$this->home->id]));
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM fleet_ship s JOIN fleet f ON f.id=s.fleet_id WHERE f.home_planet_id=?',[$this->home->id]));
        self::assertSame($stocks,$this->stocks($this->colony));self::assertSame(2,$this->em->getRepository(PlayerTechnology::class)->findOneBy(['playerUniverse'=>$this->member,'slug'=>'tactical_intelligence'])->level);
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM combat_event WHERE id=?',[$history->id]));
        self::assertSame(1,$this->auditCount());$audit=$this->em->getRepository(AdminAuditLog::class)->findOneBy(['action'=>'player_colony_abandoned']);
        self::assertTrue($audit->details['planet']['was_npc']);self::assertSame($this->home->id,$audit->details['planet']['id']);self::assertCount(3,$audit->details['cancelled_orders']);self::assertSame(2,$audit->details['losses']['ships'][0]['quantity']);
        self::assertSame($preview['version'],$audit->details['confirmed_version']);self::assertArrayNotHasKey('request_key',$audit->details);
    }

    public function testLastPlanetIsRefusedWithoutAnyLoss(): void
    {
        $this->colony->owner=$this->peer->currentPlayerUniverse;$this->em->flush();$before=$this->stocks($this->home);
        $preview=$this->service()->preview($this->member,$this->home);self::assertStringContainsString('au moins une planète',implode(' ',$preview['blockers']));
        try { $this->confirm($preview);self::fail('Dernière planète abandonnée.'); }
        catch(\DomainException) { self::assertSame($before,$this->stocks($this->home));self::assertSame(0,$this->auditCount()); }
    }

    public function testWrongTypedNameNeverDestroysAnything(): void
    {
        $before=$this->stocks($this->home);
        try { $this->confirm(null,'Nom incorrect');self::fail('Nom incorrect accepté.'); }
        catch(\DomainException) { self::assertSame($before,$this->stocks($this->home));self::assertSame(0,$this->auditCount()); }
    }

    public function testMissingVersionIsRefused(): void
    {
        $this->expectException(\DomainException::class);
        $this->service()->abandon($this->member,$this->home,$this->home->name,str_repeat('a',32),'');
    }

    public function testChangedShipsRequireFreshLossReview(): void
    {
        $preview=$this->service()->preview($this->member,$this->home);
        $ship=$this->em->getRepository(FleetShip::class)->findOneBy(['slug'=>'fighter']);$ship->quantity=4;$this->em->flush();
        try { $this->confirm($preview);self::fail('Bilan périmé accepté.'); }
        catch(\DomainException $e) { self::assertStringContainsString('Relisez le bilan',$e->getMessage());self::assertSame(4,(int)$this->em->getConnection()->fetchOne('SELECT quantity FROM fleet_ship WHERE id=?',[$ship->id]));self::assertSame(0,$this->auditCount()); }
    }

    public static function activeMissions(): iterable
    {
        foreach(['traveling','returning','orbit_pending','orbiting','attacking','defending','occupying'] as $status) yield $status=>[$status];
        yield 'sonde entrante invisible'=>['spy'];
        yield 'colonie de repli'=>['fallback'];
        yield 'aller depuis cette colonie'=>['outbound'];
    }

    #[DataProvider('activeMissions')]
    public function testAnyLinkedOperationIncludingHiddenSpyPreventsAbandonment(string $status): void
    {
        $preview=$this->service()->preview($this->member,$this->home);
        $fleet=new Fleet();$fleet->playerUniverse=$status==='outbound'?$this->member:$this->peer->currentPlayerUniverse;$fleet->homePlanet=$status==='outbound'?$this->home:$this->peerPlanet;
        $fleet->destination=in_array($status,['fallback','outbound'],true)?$this->peerPlanet:$this->home;$fleet->returnPlanet=$status==='fallback'?$this->home:null;
        $fleet->status=match($status){'spy'=>'traveling','fallback'=>'returning','outbound'=>'traveling',default=>$status};$fleet->mission=$status==='spy'?'spy':'orbit';
        $this->em->persist($fleet);$this->em->flush();$current=$this->service()->preview($this->member,$this->home);
        self::assertSame(['Une opération en cours empêche actuellement l’abandon de cette colonie.'],$current['blockers']);
        self::assertStringNotContainsString($this->peerPlanet->name,json_encode($current));
        $this->expectException(\DomainException::class);$this->confirm($preview);
    }

    public function testPendingCombatPreventsAbandonmentWithoutActiveFleet(): void
    {
        $event=new CombatEvent();$event->planet=$this->home;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now()->modify('+10 minutes');$event->seed=1;$this->em->persist($event);$this->em->flush();
        self::assertNotEmpty($this->service()->preview($this->member,$this->home)['blockers']);
        $this->expectException(\DomainException::class);$this->confirm();
    }

    public function testExternalDependentResearchBlocksButDoesNotCancelAnyQueue(): void
    {
        $root=$this->queue($this->home,'research');$dependent=$this->queue($this->colony,'research',$root);
        $preview=$this->service()->preview($this->member,$this->home);self::assertSame($dependent->id,$preview['dependencies'][0]['id']);
        try { $this->confirm($preview);self::fail('Dépendance externe ignorée.'); }
        catch(\DomainException) { self::assertSame(2,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM queue_item'));self::assertSame(0,$this->auditCount()); }
    }

    public function testUnrelatedOtherResearchSurvivesAndStartsNormally(): void
    {
        $this->queue($this->home,'research');$other=$this->queue($this->colony,'research');$other->startedAt=null;$this->em->flush();
        self::assertTrue($this->confirm());$this->em->refresh($other);self::assertNotNull($other->startedAt);self::assertSame($this->colony->id,$other->planet->id);
    }

    public function testOldQueueMessagesCannotResurrectAssetsAndOwnRecolonizationGetsNoNewKit(): void
    {
        $q=$this->queue($this->home,'shipyard');$id=$q->id;$token=$q->dispatchToken;$this->confirm();
        self::getContainer()->get(QueueService::class)->complete($id,$token);self::assertSame([],$this->stocks($this->home));
        $idle=new Fleet();$idle->playerUniverse=$this->member;$idle->homePlanet=$this->colony;$this->em->persist($idle);
        $ship=new FleetShip();$ship->fleet=$idle;$ship->slug='colony_ship';$ship->quantity=1;$this->em->persist($ship);$this->em->flush();
        $fleetService=self::getContainer()->get(FleetService::class);
        $fleetService->send($this->member,$this->colony,'colonize',$this->home->id,['colony_ship'=>1],0.1,[],str_repeat('b',32));
        $f=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$this->member,'status'=>'traveling']);
        $this->clock->sleep($f->travelSeconds+1);$fleetService->arrive($f->id,$f->dispatchToken);
        self::assertSame($this->member->id,$this->home->owner->id);
        foreach($this->stocks($this->home) as $row) self::assertEqualsWithDelta(0,$row['current_stock'],.000001);
        self::assertSame(0,$ship->quantity);
        self::getContainer()->get(QueueService::class)->complete($id,$token);
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM planet_building WHERE planet_id=?',[$this->home->id]));
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM fleet_ship s JOIN fleet f ON f.id=s.fleet_id WHERE f.home_planet_id=? AND s.slug='fighter'",[$this->home->id]));
    }

    public function testRepeatedConfirmationCannotRepeatLossOrAudit(): void
    {
        $preview=$this->service()->preview($this->member,$this->home);$this->confirm($preview);
        try { $this->confirm($preview);self::fail('Abandon répété accepté.'); }
        catch(\DomainException) { self::assertSame(1,$this->auditCount());self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM planet WHERE owner_id=?',[$this->member->id])); }
    }

    public function testForeignPlanetIsNotAccessibleAndMissingCsrfIsRejected(): void
    {
        $this->client->request('GET','/planetes/'.$this->peerPlanet->id.'/abandonner');self::assertResponseStatusCodeSame(404);
        $this->client->request('POST','/planetes/'.$this->home->id.'/abandonner',['membership'=>$this->member->id,'name'=>$this->home->name]);self::assertResponseStatusCodeSame(403);
        self::assertSame(0,$this->auditCount());
    }

    public function testHttpConfirmationUpdatesSelectedColonyAndPreservesUniverse(): void
    {
        $crawler=$this->client->request('GET','/planetes/'.$this->home->id.'/abandonner');self::assertResponseIsSuccessful();
        $form=$crawler->selectButton('Abandonner définitivement '.$this->home->name)->form(['name'=>$this->home->name]);
        $this->client->submit($form);self::assertResponseRedirects('/planetes#planet-'.$this->colony->id,303);
        $this->client->followRedirect();self::assertResponseIsSuccessful();self::assertSelectorTextContains('.flash','Colonie abandonnée.');
        self::assertSame($this->colony->id,$this->client->getRequest()->getSession()->get('selected_planet_'.$this->member->id));
    }
    public function testActiveOccupationBlocksEvenWithoutAnActiveFleetStatus(): void
    {
        $event=new CombatEvent();$event->planet=$this->home;$event->status='resolved';$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now();$event->seed=1;$this->em->persist($event);
        $fleet=new Fleet();$fleet->homePlanet=$this->peerPlanet;$fleet->playerUniverse=$this->peer->currentPlayerUniverse;$fleet->status='completed';$this->em->persist($fleet);
        $occupation=new PlanetOccupation();$occupation->planet=$this->home;$occupation->occupant=$this->peer->currentPlayerUniverse;$occupation->fleet=$fleet;$occupation->originCombat=$event;
        $occupation->startedAt=$this->clock->now();$occupation->captureAt=$this->clock->now()->modify('+24 hours');$occupation->dispatchToken=Uuid::v4()->toRfc4122();$this->em->persist($occupation);$this->em->flush();
        self::assertNotEmpty($this->service()->preview($this->member,$this->home)['blockers']);
        $this->expectException(\DomainException::class);$this->confirm();
    }

    public function testForeignIdleShipsAreNeitherDestroyedNorExposed(): void
    {
        $fleet=new Fleet();$fleet->homePlanet=$this->home;$fleet->playerUniverse=$this->peer->currentPlayerUniverse;$this->em->persist($fleet);
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=7;$this->em->persist($ship);$this->em->flush();
        $preview=$this->service()->preview($this->member,$this->home);self::assertNotEmpty($preview['blockers']);
        self::assertSame(2,$preview['losses']['ships'][0]['quantity']);
        try { $this->confirm($preview);self::fail('Présence étrangère ignorée.'); }
        catch(\DomainException) { self::assertSame(7,(int)$this->em->getConnection()->fetchOne('SELECT quantity FROM fleet_ship WHERE id=?',[$ship->id])); }
    }

    public function testResourceProductionDoesNotInvalidateAnOtherwiseIdenticalConfirmation(): void
    {
        $resource=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'metal']);$resource->currentStock=1000;$this->em->flush();
        $preview=$this->service()->preview($this->member,$this->home);$metal=array_column($preview['losses']['resources'],null,'slug')['metal']['quantity'];
        $this->clock->sleep(60);self::assertTrue($this->confirm($preview));
        $audit=$this->em->getRepository(AdminAuditLog::class)->findOneBy(['action'=>'player_colony_abandoned']);
        $rows=array_column($audit->details['losses']['resources'],null,'slug');self::assertGreaterThan($metal,$rows['metal']['quantity']);
    }

    public function testNewQueueRequiresUpdatedConfirmationWithoutCancellation(): void
    {
        $preview=$this->service()->preview($this->member,$this->home);$q=$this->queue($this->home);
        try { $this->confirm($preview);self::fail('Nouvelle commande ignorée.'); }
        catch(\DomainException $e) { self::assertStringContainsString('Relisez le bilan',$e->getMessage());self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM queue_item WHERE id=?',[$q->id]));self::assertSame(0,$this->auditCount()); }
    }

    public function testUniverseChangeInvalidatesPreviouslyOpenedHttpForm(): void
    {
        $crawler=$this->client->request('GET','/planetes/'.$this->home->id.'/abandonner');
        $values=$crawler->filter('.abandon-confirmation')->form()->getValues();$values['name']=$this->home->name;
        $other=new Universe();$other->slug='abandon-other';$other->name='Autre univers';$this->em->persist($other);
        $membership=new PlayerUniverse();$membership->player=$this->player;$membership->universe=$other;$membership->joinedAt=$this->clock->now();$this->em->persist($membership);
        $this->player->currentPlayerUniverse=$membership;$this->em->flush();
        $this->client->request('POST','/planetes/'.$this->home->id.'/abandonner',$values);self::assertResponseStatusCodeSame(403);
        self::assertSame($this->member->id,(int)$this->em->getConnection()->fetchOne('SELECT owner_id FROM planet WHERE id=?',[$this->home->id]));self::assertSame(0,$this->auditCount());
    }

    public function testForeignPlanetIsAlsoRejectedByTheDomainService(): void
    {
        $this->expectException(\DomainException::class);
        $this->service()->abandon($this->member,$this->peerPlanet,$this->peerPlanet->name,str_repeat('a',32),'forged');
    }

    public function testSecondAbandonCannotRemoveTheLastRemainingColony(): void
    {
        $this->confirm();$preview=$this->service()->preview($this->member,$this->colony);
        try { $this->service()->abandon($this->member,$this->colony,$this->colony->name,str_repeat('b',32),$preview['version']);self::fail('Dernière colonie perdue.'); }
        catch(\DomainException) { self::assertSame(1,$this->auditCount());self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM planet WHERE owner_id=?',[$this->member->id])); }
    }

    public function testReconstructionScheduleIsStoppedAndKeptForHistory(): void
    {
        $q=$this->queue($this->home);
        $state=new \App\Entity\EmpireReconstruction();$state->planet=$this->home;$state->membership=$this->member;$state->reference=['buildings'=>['mine_metal'=>['basic'=>2]]];
        $state->queueItem=$q;$state->nextAt=$this->clock->now()->modify('+1 hour');$state->dispatchToken=Uuid::v4()->toRfc4122();$old=$state->dispatchToken;
        $this->em->persist($state);$this->em->flush();self::assertTrue($this->confirm());
        self::assertNull($state->nextAt);self::assertNull($state->queueItem);self::assertNotSame($old,$state->dispatchToken);
        self::assertSame(['buildings'=>['mine_metal'=>['basic'=>2]]],$state->reference);
    }

    public function testLockedAccountCannotAbandonEvenThroughTheDomainService(): void
    {
        $preview=$this->service()->preview($this->member,$this->home);$this->player->lockedAt=$this->clock->now();$this->em->flush();
        try { $this->confirm($preview);self::fail('Compte verrouillé autorisé.'); }
        catch(\DomainException) { self::assertSame(0,$this->auditCount());self::assertSame($this->member->id,(int)$this->em->getConnection()->fetchOne('SELECT owner_id FROM planet WHERE id=?',[$this->home->id])); }
    }

    public function testDifferentOwnerStillReceivesTheNormalColonizationKit(): void
    {
        $this->confirm();$peer=$this->peer->currentPlayerUniverse;
        // L’origine de test reste dans la même galaxie : les voyages intergalactiques sont interdits.
        $this->peerPlanet->owner=null;$this->peerPlanet->acquiredAt=null;$this->em->flush();
        $this->peerPlanet=$this->em->createQueryBuilder()->select('p')->from(Planet::class,'p')->join('p.solarSystem','s')
            ->where('s.galaxy = :galaxy')->andWhere('p.owner IS NULL')->andWhere('p.npcPlanet = false')->andWhere('p.id != :target')
            ->setParameter('galaxy',$this->home->solarSystem->galaxy)->setParameter('target',$this->home->id)
            ->orderBy('p.id','ASC')->setMaxResults(1)->getQuery()->getSingleResult();
        $this->peerPlanet->acquireBy($peer,$this->clock->now());self::getContainer()->get(ResourceService::class)->initialize($this->peerPlanet);$this->em->flush();
        $d=new PlayerDiscovery();$d->playerUniverse=$peer;$d->solarSystem=$this->home->solarSystem;
        if(!$this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$peer,'solarSystem'=>$d->solarSystem])) $this->em->persist($d);
        $tech=new PlayerTechnology();$tech->playerUniverse=$peer;$tech->slug='colonial_expansion';$tech->level=1;$this->em->persist($tech);
        $idle=new Fleet();$idle->playerUniverse=$peer;$idle->homePlanet=$this->peerPlanet;$this->em->persist($idle);
        $ship=new FleetShip();$ship->fleet=$idle;$ship->slug='colony_ship';$ship->quantity=1;$this->em->persist($ship);$this->em->flush();
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$this->peerPlanet]) as $resource) $resource->currentStock=150000;$this->em->flush();
        $service=self::getContainer()->get(FleetService::class);
        $service->send($peer,$this->peerPlanet,'colonize',$this->home->id,['colony_ship'=>1],0.1,[],str_repeat('c',32));
        $fleet=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$peer,'status'=>'traveling']);
        $this->clock->sleep($fleet->travelSeconds+1);$service->arrive($fleet->id,$fleet->dispatchToken);
        $kit=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class)->rules()['colony_initial_stock'];
        foreach($this->stocks($this->home) as $row) self::assertEqualsWithDelta($kit[$row['resource_type']],$row['current_stock'],.000001);
        self::assertSame($peer->id,$this->home->owner->id);self::assertSame(0,$ship->quantity);
    }

}
