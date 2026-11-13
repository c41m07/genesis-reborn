<?php
namespace App\Tests\Integration;

use App\Entity\{CombatEvent,CombatLog,EmpireReconstruction,Fleet,FleetShip,Galaxy,OutboxMessage,Planet,PlanetBuilding,PlanetDefense,PlanetOccupation,PlanetResource,Player,PlayerTechnology,PlayerUniverse,QueueItem,SolarSystem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Combat\{CombatEventService,OccupationCaptureService,OccupationPolicy,OccupationReadiness};
use App\Service\Player\{EmpireConversionService,EmpireReconstructionScheduler,EmpireReconstructionService};
use App\Service\Queue\{OperationCalculator,QueueService};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class EmpireReconstructionTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Planet $planet;
    private PlayerUniverse $member;
    private array $environment;

    protected function setUp(): void
    {
        $name='FORGOTTEN_EMPIRES_ENABLED';$this->environment=[$_SERVER[$name]??null,$_ENV[$name]??null,getenv($name)];
        $_SERVER[$name]=$_ENV[$name]='1';putenv($name.'=1');
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-11T12:00:00Z');self::getContainer()->set('clock',$this->clock);
        $universe=new Universe();$universe->slug='omega-1';$universe->name='Test';
        $galaxy=new Galaxy();$galaxy->universe=$universe;$galaxy->name='Test';
        $system=new SolarSystem();$system->galaxy=$galaxy;$system->name='Test';$system->coordX=0;$system->coordY=0;
        foreach([$universe,$galaxy,$system] as $row) $this->em->persist($row);$this->em->flush();
        $this->member=$this->member('former',$universe);
        $this->planet=new Planet();$this->planet->solarSystem=$system;$this->planet->position=1;$this->planet->name='Former';$this->planet->owner=$this->member;
        $this->em->persist($this->planet);$this->em->flush();
        self::getContainer()->get(ResourceService::class)->initialize($this->planet);$this->em->flush();
        $this->stocks(10000000);
    }

    protected function tearDown(): void
    {
        parent::tearDown();$name='FORGOTTEN_EMPIRES_ENABLED';
        foreach(['_SERVER','_ENV'] as $index=>$global) {
            if($this->environment[$index]===null) unset($GLOBALS[$global][$name]);else $GLOBALS[$global][$name]=$this->environment[$index];
        }
        putenv($this->environment[2]===false?$name:$name.'='.$this->environment[2]);
    }

    public function testConversionRecordsEveryModelAndTierIncludingReturningShipsButNotPaidOrders(): void
    {
        $ships=[];$defenses=[];$catalog=self::getContainer()->get(GameCatalog::class);
        foreach($catalog->all('ships') as $slug=>$definition) foreach(GameCatalog::TIERS as $tier) $ships[]=$this->ship($slug,2,$tier);
        foreach($catalog->all('defenses') as $slug=>$definition) foreach(GameCatalog::TIERS as $tier) $defenses[]=$this->defense($slug,3,$tier);
        $return=$this->ship('fighter',7);$return->fleet->status='returning';$return->fleet->arrivalAt=$this->clock->now()->modify('+1 hour');
        $order=$this->paidOrder('fighter',11,3600);$this->em->flush();
        $this->member->lastActivityAt=$this->clock->now()->modify('-31 days');$this->em->flush();
        self::assertSame('converted',self::getContainer()->get(EmpireConversionService::class)->convert($this->member)['status']);
        $state=$this->state();
        self::assertSame(9,$state->reference['ships']['fighter']['basic']);
        foreach($ships as $ship) if($ship->slug!=='fighter'||$ship->tier!=='basic') self::assertSame(2,$state->reference['ships'][$ship->slug][$ship->tier]);
        foreach($defenses as $defense) self::assertSame(3,$state->reference['defenses'][$defense->slug][$defense->tier]);
        self::assertSame(11,$order->quantity);self::assertNull($state->nextAt);
        self::assertSame('already_converted',self::getContainer()->get(EmpireConversionService::class)->convert($this->member)['status']);
        self::assertSame(1,$this->em->getRepository(EmpireReconstruction::class)->count([]));
    }

    public function testDelayRealCostsRealDurationRanksAndRepeatedMessages(): void
    {
        $ship=$this->ship('fighter',2,'superior');$this->technology('naval_engineering',10);
        $yard=new PlanetBuilding();$yard->planet=$this->planet;$yard->slug='shipyard';$yard->level=10;$this->em->persist($yard);
        $state=$this->convert();$ship->quantity=0;$this->em->flush();$this->afterCombat();
        $oldToken=$state->dispatchToken;
        self::assertSame('early',$this->rebuild());self::assertSame('stale',$this->service()->rebuild($state->id,$oldToken));
        $this->clock->sleep(299);self::assertSame('early',$this->rebuild());self::assertNull($state->queueItem);
        $this->clock->sleep(1);$stocks=self::getContainer()->get(ResourceService::class)->snapshot($this->planet);$plan=$this->plan('ships','fighter','superior');$token=$state->dispatchToken;
        self::assertSame('started',$this->rebuild());$item=$state->queueItem;
        self::assertSame('superior',$item->tier);self::assertSame(1,$item->quantity);
        self::assertSame($plan['costs'],$item->paidCosts);self::assertSame($plan['duration'],$item->durationSeconds);
        foreach($plan['costs'] as $resource=>$cost) self::assertEqualsWithDelta($stocks[$resource]['stock']-$cost,self::getContainer()->get(ResourceService::class)->snapshot($this->planet)[$resource]['stock'],0.00001);
        self::assertSame('stale',$this->service()->rebuild($state->id,$token));
        $this->finish();self::assertSame(1,$this->units('ships','fighter','superior'));
        self::assertSame('started',$this->rebuild());$this->finish();
        self::assertSame('complete',$this->rebuild());self::assertSame(2,$this->units('ships','fighter','superior'));
        self::assertSame(0,$this->units('ships','fighter','basic'));
    }

    public function testCheapestAffordableUnitsWinAcrossShipsAndDefensesAndStocksAreRetried(): void
    {
        $probe=$this->ship('probe',2);$fighter=$this->ship('fighter',1);$turret=$this->defense('ground_turret_anti_light',1);
        $this->convert();$probe->quantity=$fighter->quantity=$turret->quantity=0;$this->em->flush();$this->afterCombat();$this->clock->sleep(300);
        $this->stocks(0);self::assertSame('resources_missing',$this->rebuild());self::assertNull($this->state()->queueItem);
        $this->stocks(10000000);$this->clock->sleep(60);
        self::assertSame('started',$this->rebuild());self::assertSame('probe',$this->state()->queueItem->itemSlug);$this->finish();
        self::assertSame('started',$this->rebuild());self::assertSame('probe',$this->state()->queueItem->itemSlug);$this->finish();
        self::assertSame('started',$this->rebuild());self::assertSame('ground_turret_anti_light',$this->state()->queueItem->itemSlug);$this->finish();
        self::assertSame('started',$this->rebuild());self::assertSame('fighter',$this->state()->queueItem->itemSlug);$this->finish();
        self::assertSame('complete',$this->rebuild());
    }

    public function testReconstructionWaitsForElectricityEvenWhenOtherResourcesAreAvailable(): void
    {
        $ship=$this->ship('fighter',1);$this->convert();$ship->quantity=0;$this->em->flush();$this->afterCombat();$this->clock->sleep(300);
        $this->stocks(10000000);
        $electricity=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->planet,'resourceType'=>'electricity']);$electricity->currentStock=0;$this->em->flush();
        self::assertSame('resources_missing',$this->rebuild());self::assertNull($this->state()->queueItem);
    }

    public function testUnaffordableCheapestModelDoesNotBlockAnotherModel(): void
    {
        // La sonde coûte moins en valeur mais exige ici du nano indisponible.
        $this->member->universe->gameSettings=['catalog_overrides'=>['ships'=>[
            'probe'=>['cost_profiles'=>['basic'=>['metal'=>0,'nano'=>100,'antimatter'=>0,'hydrogen'=>0]]],
            'fighter'=>['cost_profiles'=>['basic'=>['metal'=>1000,'nano'=>0,'antimatter'=>0,'hydrogen'=>0]]],
        ]]];$this->em->flush();
        $probe=$this->ship('probe',1);$fighter=$this->ship('fighter',1);$this->convert();$probe->quantity=$fighter->quantity=0;
        $this->em->flush();$this->afterCombat();$this->clock->sleep(300);$this->stocks(0);
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->planet,'resourceType'=>'metal']);$metal->currentStock=1000;
        $electricity=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->planet,'resourceType'=>'electricity']);$electricity->currentStock=100;
        $this->em->flush();
        self::assertSame('started',$this->rebuild());self::assertSame('fighter',$this->state()->queueItem->itemSlug);
    }

    public function testReturningShipsAndOriginalPaidOrdersDoNotCreateDuplicatesOrRaiseReference(): void
    {
        $ship=$this->ship('fighter',2);$ship->fleet->status='returning';$ship->fleet->arrivalAt=$this->clock->now()->modify('+1 day');
        $this->paidOrder('fighter',3,60);$state=$this->convert();$this->afterCombat();$this->clock->sleep(300);
        self::assertSame('complete',$this->rebuild());self::assertSame(2,$state->reference['ships']['fighter']['basic']);
        self::assertSame(5,$this->units('ships','fighter'));
        $ship->quantity=0;$this->em->flush();$this->afterCombat();$this->clock->sleep(300);
        self::assertSame('complete',$this->rebuild());self::assertSame(3,$this->units('ships','fighter'));
    }

    public function testOriginalShipyardOrderKeepsItsPlaceAndItsPayment(): void
    {
        $ship=$this->ship('fighter',1);$order=$this->paidOrder('probe',1,3600);$this->convert();
        $ship->quantity=0;$this->em->flush();$this->afterCombat();$this->clock->sleep(300);
        self::assertSame('shipyard_busy',$this->rebuild());self::assertNull($this->state()->queueItem);
        self::assertSame($order,$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->planet]));
        $this->clock->sleep(3300);self::assertSame('started',$this->rebuild());self::assertSame(1,$this->units('ships','probe'));
    }

    public function testNewCombatPausesPaidWorkAndResumesOnlyAfterNewCooldown(): void
    {
        $ship=$this->ship('fighter',1);$state=$this->convert();$ship->quantity=0;$this->em->flush();
        $this->afterCombat();$this->clock->sleep(300);self::assertSame('started',$this->rebuild());
        $item=$state->queueItem;$originalDuration=$item->durationSeconds;$token=$item->dispatchToken;
        $this->clock->sleep(30);$combat=$this->attack();
        self::assertNull($item->startedAt);self::assertSame($originalDuration-30,$item->durationSeconds);
        $this->clock->sleep(600);self::getContainer()->get(QueueService::class)->complete($item->id,$token);
        self::assertSame(0,$this->units('ships','fighter'));
        self::getContainer()->get(CombatEventService::class)->resolve($combat->id,$combat->dispatchToken);
        self::assertSame('resolved',$combat->status);self::assertNotNull($state->nextAt);
        $this->clock->sleep(299);self::assertSame('early',$this->rebuild());self::assertNull($item->startedAt);
        $this->clock->sleep(1);$before=self::getContainer()->get(ResourceService::class)->snapshot($this->planet);
        self::assertSame('resumed',$this->rebuild());
        foreach(self::getContainer()->get(ResourceService::class)->snapshot($this->planet) as $slug=>$row) self::assertEqualsWithDelta($before[$slug]['stock'],$row['stock'],0.00001);
        $this->finish();self::assertSame(1,$this->units('ships','fighter'));
        self::assertSame('complete',$this->rebuild());self::assertSame($originalDuration-30,$item->durationSeconds);
    }

    public function testPartialDefenseRepairIsDeductedBeforeReconstruction(): void
    {
        $defense=$this->defense('ground_turret_anti_light',10);$this->technology('automated_defense_repair',25,'superior');
        $this->convert();$combat=$this->attack();$this->clock->sleep(600);
        self::getContainer()->get(CombatEventService::class)->resolve($combat->id,$combat->dispatchToken);
        $log=$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$combat]);
        $restored=$log->snapshot['defense_rebuilt']['ground_turret_anti_light:basic']??0;
        self::assertGreaterThan(0,$restored);self::assertSame($restored,$defense->quantity);
        $this->clock->sleep(300);$this->stocks(10000000);$rebuilt=0;
        while($this->rebuild()==='started') { $this->finish();++$rebuilt;self::assertLessThanOrEqual(10,$defense->quantity); }
        self::assertSame(10-$restored,$rebuilt);self::assertSame(10,$defense->quantity);
    }

    public function testReconstructionContinuesUnderOccupationAndBlocksCaptureWithoutResettingTime(): void
    {
        $ship=$this->ship('fighter',1);$this->convert();$ship->quantity=0;$this->em->flush();
        $occupation=$this->occupation();$captureAt=$occupation->captureAt;
        $this->afterCombat();$this->clock->sleep(300);self::assertSame('started',$this->rebuild());$this->finish();
        self::assertSame('hostile_forces',self::getContainer()->get(OccupationReadiness::class)->inspect($occupation)['decision']);
        self::assertEquals($captureAt,$occupation->captureAt);
        self::assertSame(0,$this->em->getRepository(Fleet::class)->count(['playerUniverse'=>$this->member,'status'=>'traveling']));
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count(['playerUniverse'=>$this->member,'queueType'=>'research']));
    }

    public function testCaptureCancelsPausedReconstructionWithoutRefundAndInvalidatesMessages(): void
    {
        $ship=$this->ship('fighter',1);$state=$this->convert();$ship->quantity=0;$this->em->flush();
        $this->afterCombat();$this->clock->sleep(300);self::assertSame('started',$this->rebuild());$item=$state->queueItem;$id=$item->id;$queueToken=$item->dispatchToken;
        $this->transaction(fn()=> $this->scheduler()->pauseLocked($this->planet,$this->clock->now()));
        $occupation=$this->occupation();$this->afterCombat();$token=$state->dispatchToken;
        $this->clock->sleep(86400);$stocks=self::getContainer()->get(ResourceService::class)->snapshot($this->planet);
        self::assertSame('captured',self::getContainer()->get(OccupationCaptureService::class)->capture($occupation->id,$occupation->dispatchToken)['status']);
        self::assertNull($this->em->find(QueueItem::class,$id));self::assertNull($state->queueItem);self::assertNull($state->nextAt);
        self::assertSame('stale',$this->service()->rebuild($state->id,$token));
        self::getContainer()->get(QueueService::class)->complete($id,$queueToken);
        foreach(self::getContainer()->get(ResourceService::class)->snapshot($this->planet) as $slug=>$row) self::assertEqualsWithDelta($stocks[$slug]['stock'],$row['stock'],0.00001);
        self::assertSame(0,$this->units('ships','fighter'));
    }

    public function testANewForgottenOwnerGetsItsOwnReferenceAfterCapture(): void
    {
        $ship=$this->ship('fighter',2);$former=$this->member;$old=$this->convert();$ship->quantity=0;$this->em->flush();
        $this->transaction(fn()=> $this->scheduler()->stopLocked($this->planet));
        $this->member=$this->member('newOwner',$former->universe);$this->planet->owner=$this->member;$this->em->flush();
        $this->ship('probe',3,'superior');
        $this->transaction(function(): void { $this->scheduler()->captureReferenceLocked($this->member);$this->member->detachPlayer($this->clock->now()); });
        $current=$this->scheduler()->forPlanet($this->planet);
        self::assertNotSame($old->id,$current->id);self::assertSame(2,$old->reference['ships']['fighter']['basic']);
        self::assertSame(['ships'=>['probe'=>['superior'=>3]]],$current->reference);
        self::assertSame(2,$this->em->getRepository(EmpireReconstruction::class)->count(['planet'=>$this->planet]));
        self::assertNull($old->nextAt);
    }

    public function testClassicNpcsAndExistingForgottenEmpiresHaveNoReference(): void
    {
        $this->member->detachPlayer($this->clock->now());$this->em->flush();$this->afterCombat();
        self::assertNull($this->scheduler()->forPlanet($this->planet));
        $this->planet->owner=null;$this->planet->npcPlanet=true;$this->em->flush();$this->afterCombat();
        self::assertNull($this->scheduler()->forPlanet($this->planet));
        self::assertSame(0,$this->em->getRepository(EmpireReconstruction::class)->count([]));
        self::assertSame(0,$this->em->getRepository(OutboxMessage::class)->count(['kind'=>'reconstruction']));
    }

    public function testClosedUniverseAndDisabledFeatureDoNotSpendResources(): void
    {
        $ship=$this->ship('fighter',1);$state=$this->convert();$ship->quantity=0;$this->em->flush();$this->afterCombat();$this->clock->sleep(300);
        $service=new EmpireReconstructionService(self::getContainer()->get(GameManager::class),self::getContainer()->get(GameRepository::class),$this->scheduler(),
            self::getContainer()->get(QueueService::class),self::getContainer()->get(ResourceService::class),self::getContainer()->get(GameCatalog::class),
            self::getContainer()->get(OperationCalculator::class),$this->clock,false);
        self::assertSame('disabled',$service->rebuild($state->id,$state->dispatchToken));
        $this->member->universe->status='closed';$this->em->flush();self::assertSame('universe_closed',$this->rebuild());
        self::assertNull($state->queueItem);
    }

    public function testRelayDeliversReconstructionToTheHandlerAndReplayDoesNotSpendTwice(): void
    {
        $ship=$this->ship('fighter',1);$state=$this->convert();$ship->quantity=0;$this->em->flush();$this->afterCombat();
        $id=$state->id;$token=$state->dispatchToken;
        $this->clock->sleep(300);
        self::getContainer()->get(\App\Service\Notification\OutboxRelay::class)->run();
        $this->member=$this->em->find(PlayerUniverse::class,$this->member->id);$this->planet=$this->em->find(Planet::class,$this->planet->id);
        $messages=self::getContainer()->get('messenger.transport.async')->getSent();
        $messages=array_values(array_filter($messages,static fn($envelope): bool=>$envelope->getMessage() instanceof \App\Message\EmpireReconstructionDue));
        self::assertCount(1,$messages);$message=$messages[0]->getMessage();self::assertSame($id,$message->reconstructionId);self::assertSame($token,$message->dispatchToken);
        $handler=self::getContainer()->get(\App\MessageHandler\EmpireReconstructionDueHandler::class);
        $handler($message);$stocks=self::getContainer()->get(ResourceService::class)->snapshot($this->planet);$handler($message);
        self::assertSame(1,$this->em->getRepository(QueueItem::class)->count(['planet'=>$this->planet]));
        foreach(self::getContainer()->get(ResourceService::class)->snapshot($this->planet) as $slug=>$row) self::assertEqualsWithDelta($stocks[$slug]['stock'],$row['stock'],0.00001);
    }

    public function testPendingCombatPreventsReconstructionEvenForADeliveredMessage(): void
    {
        $ship=$this->ship('fighter',1);$state=$this->convert();$ship->quantity=0;$this->em->flush();$this->afterCombat();$this->clock->sleep(300);
        $combat=new CombatEvent();$combat->planet=$this->planet;$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now()->modify('+600 seconds');$combat->seed=1;
        $this->em->persist($combat);$this->em->flush();
        $stocks=self::getContainer()->get(ResourceService::class)->snapshot($this->planet);
        self::assertSame('combat_pending',$this->rebuild());self::assertNull($state->queueItem);
        foreach(self::getContainer()->get(ResourceService::class)->snapshot($this->planet) as $slug=>$row) self::assertEqualsWithDelta($stocks[$slug]['stock'],$row['stock'],0.00001);
    }

    public function testMigrationCreatesOnlyTheReferenceTableAndRefusesToLoseReferences(): void
    {
        $connection=$this->em->getConnection();$connection->executeStatement('DROP TABLE empire_reconstruction');
        require_once dirname(__DIR__,2).'/migrations/Version20261111173000.php';
        $migration=new \DoctrineMigrations\Version20261111173000($connection,new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $connection->executeStatement($query->getStatement());
        self::assertSame([], (new SchemaTool($this->em))->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
        $this->convert();$down=new \DoctrineMigrations\Version20261111173000($connection,new \Psr\Log\NullLogger());
        $this->expectException(\Doctrine\Migrations\Exception\IrreversibleMigration::class);$down->down(new \Doctrine\DBAL\Schema\Schema());
    }

    private function member(string $name,Universe $universe): PlayerUniverse
    {
        $player=new Player();$player->email=$name.'@example.test';$player->username=$name;$player->password='unused';$player->emailVerifiedAt=$this->clock->now();
        $member=new PlayerUniverse();$member->player=$player;$member->universe=$universe;$member->joinedAt=$this->clock->now();$member->lastActivityAt=$this->clock->now();$member->catalogUnlocks=[];
        $this->em->persist($player);$this->em->persist($member);$this->em->flush();return $member;
    }
    private function ship(string $slug,int $quantity,string $tier='basic'): FleetShip
    {
        $fleet=new Fleet();$fleet->playerUniverse=$this->member;$fleet->homePlanet=$this->planet;$this->em->persist($fleet);
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->tier=$tier;$ship->quantity=$quantity;$this->em->persist($ship);$this->em->flush();return $ship;
    }
    private function defense(string $slug,int $quantity,string $tier='basic'): PlanetDefense
    {
        $row=new PlanetDefense();$row->planet=$this->planet;$row->slug=$slug;$row->tier=$tier;$row->quantity=$quantity;
        $this->em->persist($row);$this->em->flush();return $row;
    }
    private function technology(string $slug,int $level,string $tier='basic'): void
    {
        $row=new PlayerTechnology();$row->playerUniverse=$this->member;$row->slug=$slug;$row->tier=$tier;$row->level=$level;$this->em->persist($row);$this->em->flush();
    }
    private function convert(): EmpireReconstruction
    {
        $this->transaction(function(): void { $this->scheduler()->captureReferenceLocked($this->member);$this->member->detachPlayer($this->clock->now()); });
        return $this->state();
    }
    private function state(): EmpireReconstruction { return $this->em->getRepository(EmpireReconstruction::class)->findOneBy(['planet'=>$this->planet]); }
    private function scheduler(): EmpireReconstructionScheduler { return self::getContainer()->get(EmpireReconstructionScheduler::class); }
    private function service(): EmpireReconstructionService { return self::getContainer()->get(EmpireReconstructionService::class); }
    private function transaction(callable $action): mixed { return self::getContainer()->get(GameManager::class)->transaction($action); }
    private function afterCombat(): void { $this->transaction(fn()=> $this->scheduler()->afterCombatLocked($this->planet)); }
    private function rebuild(): string { $state=$this->state();return $this->service()->rebuild($state->id,$state->dispatchToken); }
    private function stocks(float $amount): void
    {
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$this->planet]) as $row) { $row->currentStock=$amount;$row->lastUpdatedAt=$this->clock->now(); }
        $this->em->flush();
    }
    private function finish(): void
    {
        $item=$this->state()->queueItem;$this->clock->sleep(max(0,$item->finishAt()->getTimestamp()-$this->clock->now()->getTimestamp()));
        self::getContainer()->get(QueueService::class)->complete($item->id,$item->dispatchToken);
    }
    private function units(string $group,string $slug,string $tier='basic'): int { return $this->scheduler()->inventory($this->planet,$this->member)[$group][$slug][$tier]??0; }
    private function plan(string $group,string $slug,string $tier): array
    {
        $catalog=self::getContainer()->get(GameCatalog::class);
        return self::getContainer()->get(OperationCalculator::class)->calculate($catalog->getTierForUniverse($this->member->universe,$group,$slug,$tier),$group,1,1,
            self::getContainer()->get(GameRepository::class)->levelsByTier(PlanetBuilding::class,['planet'=>$this->planet]),$catalog->forUniverse($this->member->universe),
            self::getContainer()->get(GameRepository::class)->levelsByTier(PlayerTechnology::class,['playerUniverse'=>$this->member]));
    }
    private function paidOrder(string $slug,int $quantity,int $duration): QueueItem
    {
        $item=new QueueItem();$item->planet=$this->planet;$item->playerUniverse=$this->member;$item->queueType='shipyard';$item->itemSlug=$slug;$item->quantity=$quantity;
        $item->durationSeconds=$duration;$item->queuedAt=$this->clock->now();$item->startedAt=$item->queuedAt;$item->dispatchToken='original-paid-order';
        $this->em->persist($item);$this->em->flush();return $item;
    }
    private function attack(): CombatEvent
    {
        $attacker=$this->member('attacker',$this->member->universe);
        $home=new Planet();$home->solarSystem=$this->planet->solarSystem;$home->position=2;$home->name='Attacker';$home->owner=$attacker;$this->em->persist($home);$this->em->flush();
        self::getContainer()->get(ResourceService::class)->initialize($home);
        $fleet=new Fleet();$fleet->playerUniverse=$attacker;$fleet->homePlanet=$home;$fleet->destination=$this->planet;$fleet->status='traveling';$fleet->mission='attack';
        $fleet->departureAt=$this->clock->now()->modify('-60 seconds');$fleet->arrivalAt=$this->clock->now();$fleet->travelSeconds=60;$fleet->dispatchToken='attacker';
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='battleship';$ship->quantity=100;
        $this->em->persist($fleet);$this->em->persist($ship);$this->em->flush();
        self::assertTrue($this->transaction(fn()=> self::getContainer()->get(CombatEventService::class)->joinAttack($fleet)));
        $combat=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->planet,'status'=>'pending']);$combat->seed=123;$this->em->flush();return $combat;
    }
    private function occupation(): PlanetOccupation
    {
        $holder=$this->member('holder',$this->member->universe);
        $home=new Planet();$home->solarSystem=$this->planet->solarSystem;$home->position=3;$home->name='Holder';$home->owner=$holder;$this->em->persist($home);$this->em->flush();
        self::getContainer()->get(ResourceService::class)->initialize($home);
        $tech=new PlayerTechnology();$tech->playerUniverse=$holder;$tech->slug='colonial_expansion';$tech->level=1;$this->em->persist($tech);
        $fleet=new Fleet();$fleet->playerUniverse=$holder;$fleet->homePlanet=$home;$fleet->destination=$this->planet;$fleet->status='occupying';$fleet->mission='conquer';
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='colony_ship';$ship->quantity=1;
        $combat=new CombatEvent();$combat->planet=$this->planet;$combat->status='resolved';$combat->startedAt=$this->clock->now();$combat->resolveAt=$this->clock->now();$combat->seed=42;
        foreach([$fleet,$ship,$combat] as $row) $this->em->persist($row);$this->em->flush();
        $occupation=new PlanetOccupation();$occupation->planet=$this->planet;
        $event=self::getContainer()->get(OccupationPolicy::class)->begin($occupation,$combat,$fleet,true,true);
        $this->em->persist($occupation);$this->em->persist($event);$this->em->flush();return $occupation;
    }
}
