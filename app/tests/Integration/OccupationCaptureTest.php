<?php
namespace App\Tests\Integration;

use App\Entity\{CombatEvent,CombatLog,Fleet,FleetShip,Galaxy,OccupationEvent,Planet,PlanetBuilding,PlanetOccupation,Player,PlayerBoost,PlayerTechnology,PlayerUniverse,QueueItem,SolarSystem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ResourceService;
use App\Service\Combat\{OccupationCaptureService,OccupationPolicy,OccupationReadiness,OccupationService};
use App\Service\Fleet\FleetService;
use App\Service\Queue\QueueService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class OccupationCaptureTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PlanetOccupation $occupation;
    private PlayerUniverse $former;
    private FleetShip $colony;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        $universe=new Universe();$universe->slug='omega-1';$universe->name='Test';
        $galaxy=new Galaxy();$galaxy->universe=$universe;$galaxy->name='Test';$system=new SolarSystem();$system->galaxy=$galaxy;$system->name='Test';$system->coordX=0;$system->coordY=0;
        foreach([$universe,$galaxy,$system] as $row) $this->em->persist($row);$this->em->flush();
        $member=$this->member('holder',$universe);$this->former=$this->member('former',$universe);$this->former->detachPlayer($this->clock->now());
        $home=new Planet();$home->solarSystem=$system;$home->name='Home';$home->position=1;$home->owner=$member;
        $planet=new Planet();$planet->solarSystem=$system;$planet->name='Former';$planet->position=2;$planet->owner=$this->former;
        $research=new PlayerTechnology();$research->playerUniverse=$member;$research->slug='colonial_expansion';$research->level=1;
        foreach([$home,$planet,$research] as $row) $this->em->persist($row);$this->em->flush();
        self::getContainer()->get(ResourceService::class)->initialize($home);self::getContainer()->get(ResourceService::class)->initialize($planet);
        $fleet=new Fleet();$fleet->playerUniverse=$member;$fleet->homePlanet=$home;$fleet->destination=$planet;$fleet->status='occupying';$fleet->mission='conquer';
        $fleet->travelSeconds=60;$fleet->cargo=['hydrogen'=>500];$fleet->reservedFuel=500;$fleet->orbitFuel=480;$this->em->persist($fleet);
        $this->colony=new FleetShip();$this->colony->fleet=$fleet;$this->colony->slug='colony_ship';$this->colony->quantity=1;
        $fighter=new FleetShip();$fighter->fleet=$fleet;$fighter->slug='fighter';$fighter->quantity=2;
        $combat=new CombatEvent();$combat->planet=$planet;$combat->status='resolved';$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now();$combat->seed=42;
        foreach([$this->colony,$fighter,$combat] as $row) $this->em->persist($row);$this->em->flush();
        $this->occupation=new PlanetOccupation();$this->occupation->planet=$planet;
        $event=self::getContainer()->get(OccupationPolicy::class)->begin($this->occupation,$combat,$fleet,true,true);
        $fleet->departureAt=$this->occupation->startedAt;$fleet->arrivalAt=$this->occupation->captureAt;$fleet->dispatchToken=$this->occupation->dispatchToken;
        $this->em->persist($this->occupation);$this->em->persist($event);$this->em->flush();
    }

    public function testCapturePreservesStocksBuildingsAndOwnTechnologyWithoutGivingOldUnlocks(): void
    {
        $planet=$this->occupation->planet;$member=$this->occupation->occupant;
        $building=new PlanetBuilding();$building->planet=$planet;$building->slug='fortress';$building->tier='superior';$building->level=1;
        $technology=new PlayerTechnology();$technology->playerUniverse=$this->former;$technology->slug='weapon_calibration';$technology->level=7;
        $key=\App\Service\Requirement\CatalogAccess::key($planet,'buildings','fortress','superior');$this->former->catalogUnlocks[$key]=true;
        $report=new CombatLog();$report->combatEvent=$this->occupation->originCombat;$report->outcome='attacker_wins';$report->resolvedAt=$this->clock->now();
        $report->snapshot=['capture'=>['status'=>'occupying','occupation_id'=>$this->occupation->id],'losses'=>[]];
        foreach([$building,$technology,$report] as $row) $this->em->persist($row);$this->em->flush();
        $this->clock->sleep(86400);$stocks=self::getContainer()->get(ResourceService::class)->snapshot($planet);$token=$this->occupation->dispatchToken;
        self::assertSame('captured',$this->capture()->capture($this->occupation->id,$token)['status']);
        self::assertSame($member,$planet->owner);self::assertSame(1,$building->level);self::assertSame(0,$this->colony->quantity);
        self::assertSame(7,$technology->level);self::assertSame(0,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$member,'slug'=>'weapon_calibration']));
        foreach(self::getContainer()->get(ResourceService::class)->snapshot($planet) as $slug=>$row) self::assertEqualsWithDelta($stocks[$slug]['stock'],$row['stock'],0.000001);
        self::getContainer()->get(\App\Service\Requirement\CatalogAccess::class)->captureLocked($member);
        self::assertArrayNotHasKey($key,$member->catalogUnlocks);self::assertArrayHasKey($key,$this->former->catalogUnlocks);
        self::assertSame('captured',$report->snapshot['capture']['status']);self::assertSame([],$report->snapshot['losses']);
        self::assertSame('returning',$this->occupation->fleet->status);self::assertEquals(20,$this->occupation->fleet->reservedFuel);
        self::assertSame(1,$this->em->getRepository(PlayerBoost::class)->count(['playerUniverse'=>$member,'sourceType'=>'risk_mission']));
        self::assertSame('already_finished',$this->capture()->capture($this->occupation->id,$token)['status']);
        self::assertSame(0,$this->colony->quantity);self::assertSame(2,$this->em->getRepository(OccupationEvent::class)->count([]));
    }

    public function testRemainingOrdersAreCancelledWithoutRefundOrTransferAndOldTokenDoesNothing(): void
    {
        $order=$this->queue('building','mine_metal',172800);$order->paidCosts=['metal'=>1000];$this->em->flush();$id=$order->id;$oldToken=$order->dispatchToken;
        $this->clock->sleep(86400);$stocks=self::getContainer()->get(ResourceService::class)->snapshot($this->occupation->planet);
        $this->capture()->capture($this->occupation->id,$this->occupation->dispatchToken);
        self::assertNull($this->em->find(QueueItem::class,$id));self::assertNull($this->occupation->planet->buildingQueueStartedAt);
        $journal=$this->em->getRepository(OccupationEvent::class)->findOneBy(['action'=>'captured']);
        self::assertSame($id,$journal->snapshot['capture']['cancelled_orders'][0]['id']);
        foreach(self::getContainer()->get(ResourceService::class)->snapshot($this->occupation->planet) as $slug=>$row) self::assertEqualsWithDelta($stocks[$slug]['stock'],$row['stock'],0.000001);
        self::getContainer()->get(QueueService::class)->complete($id,$oldToken);
        self::assertSame(0,$this->em->getRepository(PlanetBuilding::class)->count(['planet'=>$this->occupation->planet,'slug'=>'mine_metal','level'=>2]));
    }

    public function testPaidDefenseFinishingAtDeadlineBlocksCaptureUntilDefeated(): void
    {
        $slug=array_key_first(self::getContainer()->get(\App\Service\Catalog\GameCatalog::class)->all('defenses'));
        $this->queue('shipyard',$slug,86400);$this->clock->sleep(86400);$token=$this->occupation->dispatchToken;
        self::assertSame('hostile_forces',$this->capture()->capture($this->occupation->id,$token)['status']);
        self::assertSame($this->former,$this->occupation->planet->owner);self::assertSame(1,$this->colony->quantity);
        $defense=$this->em->getRepository(\App\Entity\PlanetDefense::class)->findOneBy(['planet'=>$this->occupation->planet,'slug'=>$slug]);
        self::assertSame(1,$defense->quantity);$defense->quantity=0;$this->em->flush();
        self::assertSame('captured',$this->capture()->capture($this->occupation->id,$token)['status']);
    }

    public function testEnemyReturnMustArriveAndBeDefeatedBeforeCapture(): void
    {
        $fleet=new Fleet();$fleet->playerUniverse=$this->former;$fleet->homePlanet=$this->occupation->planet;$fleet->destination=$this->occupation->fleet->homePlanet;$fleet->status='returning';
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='fighter';$ship->quantity=1;$this->em->persist($fleet);$this->em->persist($ship);$this->em->flush();
        $this->clock->sleep(86400);$token=$this->occupation->dispatchToken;
        self::assertSame('hostile_returns',$this->capture()->capture($this->occupation->id,$token)['status']);
        $fleet->status='idle';$this->em->flush();self::assertSame('hostile_forces',$this->capture()->capture($this->occupation->id,$token)['status']);
        $ship->quantity=0;$this->em->flush();self::assertSame('captured',$this->capture()->capture($this->occupation->id,$token)['status']);
    }

    public function testPendingCombatAndEarlyOrStaleMessagesCannotCapture(): void
    {
        $token=$this->occupation->dispatchToken;self::assertSame('waiting',$this->capture()->capture($this->occupation->id,$token)['status']);
        self::assertSame('stale',$this->capture()->capture($this->occupation->id,'old-message')['status']);$this->clock->sleep(86400);
        $event=new CombatEvent();$event->planet=$this->occupation->planet;$event->startedAt=$this->clock->now();$event->resolveAt=$this->clock->now();$event->seed=42;$this->em->persist($event);$this->em->flush();
        self::assertSame('combat_pending',$this->capture()->capture($this->occupation->id,$token)['status']);self::assertSame(1,$this->colony->quantity);
        $event->status='resolved';$this->em->flush();self::assertSame('captured',$this->capture()->capture($this->occupation->id,$token)['status']);
    }

    public function testDestroyedColonizerFailsOccupationAndRefundsUnusedStationFuel(): void
    {
        $this->colony->quantity=0;$this->em->flush();$this->clock->sleep(43200);
        $result=$this->capture()->capture($this->occupation->id,$this->occupation->dispatchToken);
        self::assertSame('failed',$result['status']);self::assertSame('colony_ship_destroyed',$result['reason']);
        self::assertSame($this->former,$this->occupation->planet->owner);self::assertSame('returning',$this->occupation->fleet->status);
        self::assertEquals(260,$this->occupation->fleet->cargo['hydrogen']);self::assertEquals(20,$this->occupation->fleet->reservedFuel);
    }

    public function testDownstreamFailureRollsBackOwnerShipOrdersRewardAndJournal(): void
    {
        $order=$this->queue('building','mine_metal',172800);$this->occupation->fleet->mission='idle';$this->em->flush();$this->clock->sleep(86400);
        $connection=$this->em->getConnection();$former=$this->former->id;$planet=$this->occupation->planet->id;$ship=$this->colony->id;$orderId=$order->id;
        try { $this->capture()->capture($this->occupation->id,$this->occupation->dispatchToken);self::fail('La mission invalide doit annuler la transaction.'); }
        catch(\DomainException $error) { self::assertStringContainsString('mission',$error->getMessage()); }
        self::assertSame($former,(int)$connection->fetchOne('SELECT owner_id FROM planet WHERE id = ?',[$planet]));
        self::assertSame(1,(int)$connection->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?',[$ship]));
        self::assertSame(1,(int)$connection->fetchOne('SELECT COUNT(*) FROM queue_item WHERE id = ?',[$orderId]));
        self::assertSame('active',$connection->fetchOne('SELECT status FROM planet_occupation WHERE id = ?',[$this->occupation->id]));
        self::assertSame(1,(int)$connection->fetchOne('SELECT COUNT(*) FROM occupation_event'));self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM player_boost'));
    }

    public function testDisabledCaptureDoesNotChangeOwnership(): void
    {
        $this->expectException(\DomainException::class);$this->capture(false)->capture($this->occupation->id,$this->occupation->dispatchToken);
    }

    public function testCancellingCapturedPlanetAlsoRemovesDependentResearchElsewhere(): void
    {
        $head=$this->queue('research','weapon_calibration',172800);$head->itemLevel=1;
        $planet=new Planet();$planet->solarSystem=$this->occupation->planet->solarSystem;$planet->position=3;$planet->name='Other';$planet->owner=$this->former;$this->em->persist($planet);$this->em->flush();
        self::getContainer()->get(ResourceService::class)->initialize($planet);
        $dependent=new QueueItem();$dependent->planet=$planet;$dependent->playerUniverse=$this->former;$dependent->queueType='research';$dependent->itemSlug='weapon_calibration';$dependent->itemLevel=2;
        $dependent->durationSeconds=60;$dependent->queuedAt=$this->clock->now();$dependent->dispatchToken='dependent-research';$dependent->dependsOn=$head;$this->em->persist($dependent);$this->em->flush();
        $ids=[$head->id,$dependent->id];$this->clock->sleep(86400);
        self::assertSame('captured',$this->capture()->capture($this->occupation->id,$this->occupation->dispatchToken)['status']);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count([]));self::assertNull($planet->researchQueueStartedAt);
        $event=$this->em->getRepository(OccupationEvent::class)->findOneBy(['action'=>'captured']);
        self::assertEqualsCanonicalizing($ids,array_column($event->snapshot['capture']['cancelled_orders'],'id'));
        self::assertSame($this->former,$planet->owner);
    }

    public function testColonyShipAloneCanBeConsumedWithoutSchedulingAnEmptyReturn(): void
    {
        $fighter=$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$this->occupation->fleet,'slug'=>'fighter']);$fighter->quantity=0;$this->em->flush();$this->clock->sleep(86400);
        self::assertSame('captured',$this->capture()->capture($this->occupation->id,$this->occupation->dispatchToken)['status']);
        self::assertSame(0,$this->colony->quantity);self::assertSame('completed',$this->occupation->fleet->status);
        self::assertNull($this->occupation->fleet->arrivalAt);self::assertSame([],$this->occupation->fleet->cargo);
    }

    private function capture(bool $enabled=true): OccupationCaptureService
    {
        $readiness=new OccupationReadiness(self::getContainer()->get(GameRepository::class),self::getContainer()->get(OccupationPolicy::class),self::getContainer()->get(OccupationService::class),self::getContainer()->get(\App\Service\Player\RelationResolver::class));
        return new OccupationCaptureService(self::getContainer()->get(GameManager::class),self::getContainer()->get(GameRepository::class),$readiness,
            self::getContainer()->get(OccupationPolicy::class),self::getContainer()->get(QueueService::class),self::getContainer()->get(ResourceService::class),
            self::getContainer()->get(FleetService::class),self::getContainer()->get(\App\Service\Boost\BoostGrantService::class),self::getContainer()->get(\App\Service\Notification\NotificationService::class),
            self::getContainer()->get(\App\Service\Universe\PlanetNameGenerator::class),$this->clock,$enabled);
    }
    private function member(string $name,Universe $universe): PlayerUniverse
    {
        $player=new Player();$player->email=$name.'@example.test';$player->username=$name;$player->password='unused';
        $member=new PlayerUniverse();$member->player=$player;$member->universe=$universe;$member->joinedAt=$this->clock->now();$member->lastActivityAt=$this->clock->now();$member->catalogUnlocks=[];
        $this->em->persist($player);$this->em->persist($member);$this->em->flush();return $member;
    }
    private function queue(string $type,string $slug,int $duration): QueueItem
    {
        $item=new QueueItem();$item->planet=$this->occupation->planet;$item->playerUniverse=$this->former;$item->queueType=$type;$item->itemSlug=$slug;$item->itemLevel=$type==='building'?2:null;
        $item->durationSeconds=$duration;$item->queuedAt=$this->occupation->startedAt;$item->startedAt=$item->queuedAt;$item->dispatchToken='capture-order-'.$type;
        $field=$type.'QueueStartedAt';$item->planet->$field=$item->startedAt;$this->em->persist($item);$this->em->flush();return $item;
    }
}
