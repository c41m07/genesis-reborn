<?php
namespace App\Tests\Integration;

use App\Entity\{CombatEvent,CombatLog,Fleet,FleetShip,OccupationEvent,Planet,PlanetOccupation,PlanetResource,Player,PlayerDiscovery,PlayerTechnology,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Combat\{CombatEventService,OccupationService};
use App\Service\Fleet\FleetService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class OccupationStartTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Planet $origin;
    private Planet $target;
    private array $environment;

    protected function setUp(): void
    {
        $name='FORGOTTEN_EMPIRES_ENABLED';$this->environment=[$_SERVER[$name]??null,$_ENV[$name]??null,getenv($name)];
        $_SERVER[$name]=$_ENV[$name]='1';putenv($name.'=1');
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $this->clock=new MockClock('2026-11-08T12:00:00Z');static::getContainer()->set('clock',$this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        $this->player=$this->player('occupant');$this->origin=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->player->currentPlayerUniverse]);
        $research=new PlayerTechnology();$research->playerUniverse=$this->player->currentPlayerUniverse;$research->slug='colonial_expansion';$research->level=1;$this->em->persist($research);$this->em->flush();
        $this->target=$this->npc();
    }
    protected function tearDown(): void
    {
        parent::tearDown();$name='FORGOTTEN_EMPIRES_ENABLED';
        foreach(['_SERVER','_ENV'] as $index=>$global) {
            if($this->environment[$index]===null) unset($GLOBALS[$global][$name]);else $GLOBALS[$global][$name]=$this->environment[$index];
        }
        putenv($this->environment[2]===false?$name:$name.'='.$this->environment[2]);
    }

    public function testClassicNpcAttackWinsWithoutCaptureOccupationOrLootEvenWithColonyShip(): void
    {
        $fleet=$this->fleet($this->target);$fleet->mission='attack';$fleet->orbitFuel=0;$this->em->flush();
        $colony=$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship']);
        $log=$this->win($fleet);
        self::assertNull($this->target->owner);self::assertNull($this->target->acquiredAt);
        self::assertSame(0,$this->em->getRepository(PlanetOccupation::class)->count([]));
        self::assertSame(1,$colony->quantity);self::assertSame('returning',$fleet->status);
        self::assertNull($log->snapshot['capture']);self::assertSame([],$log->snapshot['loot']['total']);
        self::assertNotEmpty(array_filter($log->snapshot['participants'],static fn(array $p):bool=>($p['pve_npc']??false)===true));
        $event=$log->combatEvent;$reports=$this->em->getRepository(CombatLog::class)->count([]);
        static::getContainer()->get(CombatEventService::class)->resolve($event->id,$event->dispatchToken);
        self::assertSame($reports,$this->em->getRepository(CombatLog::class)->count([]));
        self::assertSame(1,$colony->quantity);self::assertNull($this->target->owner);
    }

    public function testNpcAttackSeedsGarrisonOnceAndArrivalReplayDoesNotDuplicateParticipants(): void
    {
        $target=null;
        foreach(static::getContainer()->get(GameRepository::class)->planets($this->player->currentPlayerUniverse->universe,null,true) as $candidate) if(!$candidate->npcPlanet) {$target=$candidate;break;}
        self::assertNotNull($target);$target->npcPlanet=true;$target->conquestLevel=1;
        self::assertFalse($target->npcGarrisonInitialized);
        $fleet=$this->fleet($target);$fleet->mission='attack';$fleet->orbitFuel=0;$this->em->flush();
        $token=$fleet->dispatchToken;$service=static::getContainer()->get(FleetService::class);
        $service->arrive($fleet->id,$token);
        self::assertSame('attacking',$fleet->status);self::assertTrue($target->npcGarrisonInitialized);
        $ships=$this->em->getRepository(\App\Entity\PlanetNpcShip::class)->findBy(['planet'=>$target]);
        self::assertNotEmpty($ships);$quantities=array_column($ships,'quantity','id');
        $service->arrive($fleet->id,$token);
        self::assertSame($quantities,array_column($this->em->getRepository(\App\Entity\PlanetNpcShip::class)->findBy(['planet'=>$target]),'quantity','id'));
        self::assertSame(1,$this->em->getRepository(\App\Entity\CombatParticipant::class)->count(['fleet'=>$fleet]));
        self::assertSame(1,$this->em->getRepository(CombatEvent::class)->count(['planet'=>$target]));
    }

    public function testVictoryStartsOccupationWithoutCaptureConsumptionLootOrImmediateReward(): void
    {
        $fleet=$this->fleet($this->target);$colony=$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship']);
        $oldToken=$fleet->dispatchToken;$log=$this->win($fleet);
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        self::assertSame('occupying',$log->snapshot['capture']['status']);self::assertNotNull($occupation);
        self::assertNull($this->target->owner);self::assertSame(1,$colony->quantity);
        self::assertSame('occupying',$fleet->status);self::assertNotSame($oldToken,$fleet->dispatchToken);
        self::assertSame($occupation->dispatchToken,$fleet->dispatchToken);
        self::assertSame(86400,$occupation->captureAt->getTimestamp()-$this->clock->now()->getTimestamp());
        self::assertEquals(480,$fleet->orbitFuel);self::assertEquals(500,$fleet->reservedFuel);self::assertEquals(500,$fleet->cargo['hydrogen']);
        self::assertSame(0,$log->snapshot['losses'][$fleet->id.':colony_ship']??0);
        self::assertSame(1,static::getContainer()->get(OccupationService::class)->reservedSlots($this->player->currentPlayerUniverse));
        self::assertSame(0,$this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['playerUniverse'=>$this->player->currentPlayerUniverse,'sourceType'=>'risk_mission']));
        $combat=$log->combatEvent;static::getContainer()->get(CombatEventService::class)->resolve($combat->id,$combat->dispatchToken);
        self::assertSame(1,$this->em->getRepository(OccupationEvent::class)->count([]));
        static::getContainer()->get(FleetService::class)->arrive($fleet->id,$oldToken);
        self::assertSame('occupying',$fleet->status);
    }

    public function testReportAndFleetPageShowOccupationWithoutClaimingPlanetIsCaptured(): void
    {
        $fleet=$this->fleet($this->target);$log=$this->win($fleet);$this->client->loginUser($this->player);
        $this->client->request('GET','/rapports/combat/'.$log->id);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.mission-report','La planète n’est pas encore acquise');
        self::assertSelectorExists('time[datetime="'.$log->snapshot['capture']['capture_at'].'"]');
        $this->client->request('GET','/flottes');self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-active-fleet="'.$fleet->id.'"]','OCCUPATION JcE');
        self::assertSelectorTextContains('[data-active-fleet="'.$fleet->id.'"]','la planète ne vous appartient pas encore');
    }

    public function testQuotePrepaysExactlyTwentyFourHoursOfStationFuel(): void
    {
        $this->idleShips(['colony_ship'=>1,'transport'=>1]);$this->discover($this->target);
        $quote=static::getContainer()->get(FleetService::class)->quote($this->player->currentPlayerUniverse,$this->origin,'conquer',$this->target->id,['colony_ship'=>1,'transport'=>1],1);
        $catalog=static::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        $hourly=0;foreach(['colony_ship','transport'] as $slug) $hourly+=$catalog->getTierForUniverse($this->player->currentPlayerUniverse->universe,'ships',$slug,'basic')['fuel_orbit'];
        self::assertEqualsWithDelta(24*$hourly,$quote['orbit_fuel'],0.000001);
        self::assertEqualsWithDelta($quote['one_way_fuel']*2+$quote['orbit_fuel'],$quote['total_fuel'],0.000001);
    }

    public function testReservedSlotAlsoBlocksAColonizationDeparture(): void
    {
        $this->win($this->fleet($this->target));$free=null;
        foreach($this->em->getRepository(Planet::class)->findBy(['owner'=>null,'npcPlanet'=>false]) as $planet) {
            if($planet->solarSystem->galaxy->id===$this->origin->solarSystem->galaxy->id) { $free=$planet;break; }
        }
        self::assertNotNull($free);$this->discover($free);
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Limite de colonies');
        static::getContainer()->get(FleetService::class)->quote($this->player->currentPlayerUniverse,$this->origin,'colonize',$free->id,['colony_ship'=>1],1);
    }

    public function testSecondWinningFleetCannotOverbookCapacityAndKeepsUnusedStationFuel(): void
    {
        $this->win($this->fleet($this->target));$target=$this->npc([$this->target->id]);$fleet=$this->fleet($target);$log=$this->win($fleet);
        self::assertSame('failed',$log->snapshot['capture']['status']);self::assertSame('planet_limit',$log->snapshot['capture']['reason']);
        self::assertNull($target->owner);self::assertSame('returning',$fleet->status);
        self::assertEquals(0,$fleet->orbitFuel);self::assertEquals(20,$fleet->reservedFuel);self::assertEquals(500,$fleet->cargo['hydrogen']);
        self::assertSame(1,$this->em->getRepository(PlanetOccupation::class)->count(['status'=>'active']));
    }

    public function testForgottenEmpireKeepsItsStocksResearchAndIdentityDuringOccupation(): void
    {
        $defender=$this->player('former');$old=$defender->currentPlayerUniverse;
        $target=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$old]);
        $technology=new PlayerTechnology();$technology->playerUniverse=$old;$technology->slug='weapon_calibration';$technology->level=7;$this->em->persist($technology);
        $old->detachPlayer($this->clock->now());$this->em->flush();
        $before=$this->em->getConnection()->fetchAllAssociative('SELECT id, resource_type, current_stock FROM planet_resource WHERE planet_id = ? ORDER BY id',[$target->id]);
        $log=$this->win($this->fleet($target));
        self::assertSame('occupying',$log->snapshot['capture']['status']);self::assertSame($old,$target->owner);self::assertSame(7,$technology->level);
        self::assertSame($before,$this->em->getConnection()->fetchAllAssociative('SELECT id, resource_type, current_stock FROM planet_resource WHERE planet_id = ? ORDER BY id',[$target->id]));
        self::assertSame(0,$this->em->getRepository(\App\Entity\PlanetNpcShip::class)->count(['planet'=>$target]));
    }

    public function testOccupationPreventsVacationsAndCannotBeAcceleratedAsAFlight(): void
    {
        $fleet=$this->fleet($this->target);$this->win($fleet);
        self::assertTrue(static::getContainer()->get(GameRepository::class)->hasVacationFleetBlocker($this->player->currentPlayerUniverse));
        $this->expectException(\DomainException::class);
        static::getContainer()->get(FleetService::class)->reschedulePlayerBoost($fleet,$this->clock->now()->modify('+1 second'));
    }

    public function testWinningWithoutColonyShipDoesNotReserveAndReturnsUnusedStationFuel(): void
    {
        $fleet=$this->fleet($this->target);$ship=$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship']);
        $ship->quantity=0;$this->em->flush();$log=$this->win($fleet);
        self::assertSame('failed',$log->snapshot['capture']['status']);self::assertSame('colony_ship_destroyed',$log->snapshot['capture']['reason']);
        self::assertSame('returning',$fleet->status);self::assertEquals(20,$fleet->reservedFuel);self::assertEquals(500,$fleet->cargo['hydrogen']);
        self::assertSame(0,$this->em->getRepository(PlanetOccupation::class)->count([]));
    }

    public function testConversionCancelsOccupationAndReturnsTheFleetWithoutLosingTheShip(): void
    {
        $fleet=$this->fleet($this->target);$this->win($fleet);$membership=$this->player->currentPlayerUniverse;
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['fleet'=>$fleet]);
        $membership->lastActivityAt=$this->clock->now()->modify('-31 days');$this->em->flush();
        $service=new \App\Service\Player\EmpireConversionService(static::getContainer()->get(GameManager::class),static::getContainer()->get(GameRepository::class),
            static::getContainer()->get(\App\Service\Colony\ActionGuard::class),static::getContainer()->get(\App\Service\Player\EmpireActivityService::class),
            static::getContainer()->get(FleetService::class),static::getContainer()->get(\App\Service\Queue\QueueService::class),$this->clock,
            static::getContainer()->get(OccupationService::class),true,static::getContainer()->get(\App\Service\Player\GuildProjectService::class),static::getContainer()->get(\App\Service\Player\EmpireReconstructionScheduler::class));
        self::assertSame('converted',$service->convert($membership)['status']);
        self::assertSame('failed',$occupation->status);self::assertSame('occupant_unavailable',$occupation->endReason);
        self::assertSame('returning',$fleet->status);self::assertEquals(20,$fleet->reservedFuel);self::assertEquals(500,$fleet->cargo['hydrogen']);
        self::assertSame(1,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship'])->quantity);
        self::assertSame(0,static::getContainer()->get(GameRepository::class)->occupationReservations($membership));
        $log=$this->em->getRepository(\App\Entity\EmpireConversion::class)->findOneBy(['membership'=>$membership]);
        self::assertSame([$occupation->id],$log->effects['cancelled_occupation_ids']);
    }

    public function testDisablingAfterBattleStartCannotTurnOccupationMissionIntoInstantCapture(): void
    {
        $fleet=$this->fleet($this->target);$fleet->orbitDurationSeconds=86400;
        $combat=static::getContainer()->get(CombatEventService::class);
        static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($fleet));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $eventId=$event->id;$fleetId=$fleet->id;$targetId=$this->target->id;
        self::ensureKernelShutdown();$_SERVER['FORGOTTEN_EMPIRES_ENABLED']=$_ENV['FORGOTTEN_EMPIRES_ENABLED']='0';putenv('FORGOTTEN_EMPIRES_ENABLED=0');
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        static::getContainer()->set('clock',$this->clock);$this->em=static::getContainer()->get(EntityManagerInterface::class);
        $this->clock->sleep(600);$event=$this->em->find(CombatEvent::class,$eventId);
        static::getContainer()->get(CombatEventService::class)->resolve($eventId,$event->dispatchToken);
        $log=$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$event]);$fleet=$this->em->find(Fleet::class,$fleetId);
        self::assertSame('occupation_disabled',$log->snapshot['capture']['reason']);
        self::assertNull($this->em->find(Planet::class,$targetId)->owner);self::assertSame('returning',$fleet->status);
        self::assertEquals(20,$fleet->reservedFuel);self::assertEquals(500,$fleet->cargo['hydrogen']);
        self::assertSame(0,$this->em->getRepository(PlanetOccupation::class)->count([]));
    }

    public function testReinforcementsKeepDeadlineAndAlliesReturnWithUnusedFuelAfterCapture(): void
    {
        $this->win($this->fleet($this->target));$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $deadline=$occupation->captureAt;$token=$occupation->dispatchToken;$holder=$occupation->occupant;
        $ally=$this->player('ally');$contract=new \App\Entity\AllianceContract();$contract->playerA=$holder;$contract->playerB=$ally->currentPlayerUniverse;
        $contract->proposer=$holder;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        $service=static::getContainer()->get(OccupationService::class);$combat=static::getContainer()->get(CombatEventService::class);
        self::assertTrue($combat->canDefend($holder,$this->target));self::assertTrue($combat->canDefend($ally->currentPlayerUniverse,$this->target));
        self::assertFalse($combat->canConquer($ally->currentPlayerUniverse,$this->target));
        $reinforcement=$this->fleet($this->target);$reinforcement->playerUniverse=$ally->currentPlayerUniverse;
        $reinforcement->homePlanet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$ally->currentPlayerUniverse]);
        $reinforcement->mission='defend';$reinforcement->orbitDurationSeconds=86400;
        $service->recordReinforcementDeparture($reinforcement);$this->em->flush();$this->clock->sleep(3600);
        self::assertTrue(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->arriveDefender($reinforcement)));
        self::assertTrue(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->arriveDefender($reinforcement)));
        self::assertSame('occupying',$reinforcement->status);self::assertEquals($deadline,$occupation->captureAt);self::assertSame($token,$occupation->dispatchToken);
        self::assertSame(0,$service->reservedSlots($ally->currentPlayerUniverse));
        $this->clock->sleep(23*3600);
        $result=$this->captureService()->capture($occupation->id,$token);
        self::assertSame('captured',$result['status']);self::assertSame('returning',$reinforcement->status);
        self::assertEqualsWithDelta(40,$reinforcement->cargo['hydrogen'],0.00001);self::assertEquals(20,$reinforcement->reservedFuel);
        self::assertSame(1,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$reinforcement,'slug'=>'colony_ship'])->quantity);
    }

    public function testReinforcementQuoteUsesRemainingTimeWithCombatWindowMinimum(): void
    {
        $this->win($this->fleet($this->target));$this->idleShips(['transport'=>1]);$this->discover($this->target);
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $this->clock->sleep(12*3600);$service=static::getContainer()->get(FleetService::class);
        $quote=$service->quote($occupation->occupant,$this->origin,'defend',$this->target->id,['transport'=>1],1);
        $catalog=static::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        $hourly=$catalog->getTierForUniverse($occupation->occupant->universe,'ships','transport','basic')['fuel_orbit'];
        self::assertEqualsWithDelta($hourly*12,$quote['orbit_fuel'],0.00001);
        $this->clock->sleep(12*3600);$quote=$service->quote($occupation->occupant,$this->origin,'defend',$this->target->id,['transport'=>1],1);
        self::assertEqualsWithDelta($hourly*$catalog->rules()['combat_window_seconds']/3600,$quote['orbit_fuel'],0.00001);
    }

    public function testStaleReinforcementCannotJoinAReplacementOccupation(): void
    {
        $this->win($this->fleet($this->target));$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $service=static::getContainer()->get(OccupationService::class);$reinforcement=$this->fleet($this->target);$reinforcement->mission='defend';
        $service->recordReinforcementDeparture($reinforcement);$this->em->flush();self::assertTrue($service->acceptsReinforcement($reinforcement));
        $occupation->dispatchToken=\Symfony\Component\Uid\Uuid::v4()->toRfc4122();$this->em->flush();
        self::assertFalse(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>static::getContainer()->get(CombatEventService::class)->arriveDefender($reinforcement)));
        self::assertSame('traveling',$reinforcement->status);
    }

    public function testReinforcementJoinsOpenBattleAndRemainsWithHolderAfterVictory(): void
    {
        $held=$this->fleet($this->target);$this->win($held);$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $deadline=$occupation->captureAt;$rival=$this->rivalFleet(['colony_ship'=>1]);$combat=static::getContainer()->get(CombatEventService::class);
        static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival));
        $support=$this->fleet($this->target);$support->mission='defend';$support->orbitDurationSeconds=86400;
        static::getContainer()->get(OccupationService::class)->recordReinforcementDeparture($support);$this->em->flush();
        self::assertTrue(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->arriveDefender($support)));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame('occupying',$support->status);self::assertEquals($deadline,$occupation->captureAt);
        $entries=array_values(array_filter($event->combatSnapshot['participants'],static fn(array $entry): bool=>$entry['fleet_id']===$support->id));
        self::assertCount(1,$entries);self::assertSame('occupant-'.$occupation->occupant->id,$entries[0]['camp']);
    }

    public function testAlliedColonyShipCannotReplaceTheHoldersDestroyedColonyShip(): void
    {
        $held=$this->fleet($this->target);$this->win($held);$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $ally=$this->player('support');$contract=new \App\Entity\AllianceContract();$contract->playerA=$occupation->occupant;$contract->playerB=$ally->currentPlayerUniverse;
        $contract->proposer=$occupation->occupant;$contract->status='accepted';$this->em->persist($contract);$this->em->flush();
        $support=$this->fleet($this->target);$support->playerUniverse=$ally->currentPlayerUniverse;$support->mission='defend';$support->orbitDurationSeconds=86400;
        static::getContainer()->get(OccupationService::class)->recordReinforcementDeparture($support);$this->em->flush();
        $combat=static::getContainer()->get(CombatEventService::class);static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->arriveDefender($support));
        $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$held,'slug'=>'colony_ship'])->quantity=0;$this->em->flush();
        $rival=$this->rivalFleet(['colony_ship'=>1]);static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame('failed',$occupation->status);self::assertSame('colony_ship_destroyed',$occupation->endReason);self::assertSame('returning',$support->status);
        self::assertSame(1,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$support,'slug'=>'colony_ship'])->quantity);
    }

    public function testHolderVictoryKeepsTimerAndRivalCanContestDespiteBeginnerProtection(): void
    {
        $held=$this->fleet($this->target);$this->win($held);$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $deadline=$occupation->captureAt;$token=$occupation->dispatchToken;$rival=$this->rivalFleet(['colony_ship'=>1]);
        $combat=static::getContainer()->get(CombatEventService::class);
        self::assertTrue($combat->canAttack($rival->playerUniverse,$this->target));self::assertTrue($combat->canConquer($rival->playerUniverse,$this->target));
        self::assertFalse($combat->canDefend($rival->playerUniverse,$this->target));
        self::assertTrue(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival)));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame('resolved',$event->status);self::assertSame('active',$occupation->status);self::assertSame('occupying',$held->status);
        self::assertEquals($deadline,$occupation->captureAt);self::assertSame($token,$occupation->dispatchToken);
        self::assertSame(1,$this->em->getRepository(OccupationEvent::class)->count(['occupation'=>$occupation,'action'=>'retained']));
    }

    public function testRivalConquestReplacesHolderAndStartsFullNewDay(): void
    {
        $held=$this->fleet($this->target);$this->win($held);$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $oldHolder=$occupation->occupant;$oldToken=$occupation->dispatchToken;$this->clock->sleep(3600);
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$held]) as $ship) if($ship->slug!=='colony_ship') $ship->quantity=0;
        $this->em->flush();$rival=$this->rivalFleet(['battleship'=>20,'colony_ship'=>1]);$log=$this->win($rival);
        self::assertSame('occupying',$log->snapshot['capture']['status']);self::assertSame($rival->playerUniverse,$occupation->occupant);
        self::assertSame('occupying',$rival->status);self::assertNotSame('occupying',$held->status);self::assertNotSame($oldToken,$occupation->dispatchToken);
        self::assertSame(86400,$occupation->captureAt->getTimestamp()-$this->clock->now()->getTimestamp());
        self::assertSame(0,static::getContainer()->get(OccupationService::class)->reservedSlots($oldHolder));
        self::assertSame(1,$this->em->getRepository(OccupationEvent::class)->count(['occupation'=>$occupation,'action'=>'replaced']));
    }

    public function testAttackOnlyVictoryEndsOccupationWithoutGivingPlanetOrSlot(): void
    {
        $held=$this->fleet($this->target);$this->win($held);$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$held]) as $ship) if($ship->slug!=='colony_ship') $ship->quantity=0;
        $rival=$this->rivalFleet(['battleship'=>20]);$rival->mission='attack';$this->em->flush();$log=$this->win($rival);
        self::assertSame('failed',$log->snapshot['capture']['status']);self::assertSame('failed',$occupation->status);
        self::assertNull($this->target->owner);self::assertSame('returning',$rival->status);
        self::assertSame(0,$this->em->getRepository(PlanetOccupation::class)->count(['status'=>'active']));
    }

    public function testReturnedNpcAndHolderBelongToDifferentCamps(): void
    {
        $held=$this->fleet($this->target);$this->win($held);$old=$this->player('forgotten');$member=$old->currentPlayerUniverse;
        $member->detachPlayer($this->clock->now());$this->target->owner=$member;
        $research=new PlayerTechnology();$research->playerUniverse=$member;$research->slug='weapon_calibration';$research->level=7;$this->em->persist($research);
        $npc=$this->fleet($this->target);$npc->playerUniverse=$member;$npc->homePlanet=$this->target;$npc->status='idle';$npc->destination=null;
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$npc]) as $ship) $ship->quantity=$ship->slug==='fighter'?1:0;
        $rival=$this->rivalFleet(['colony_ship'=>1]);$this->em->flush();$combat=static::getContainer()->get(CombatEventService::class);
        self::assertTrue(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival)));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        $entries=$event->combatSnapshot['participants'];$byFleet=[];foreach($entries as $entry) if($entry['fleet_id']!==null) $byFleet[$entry['fleet_id']]=$entry;
        self::assertSame('occupant-'.$held->playerUniverse->id,$byFleet[$held->id]['camp']);self::assertSame('npc-owner-'.$member->id,$byFleet[$npc->id]['camp']);
        self::assertNotSame($byFleet[$npc->id]['camp'],$byFleet[$rival->id]['camp']);
        self::assertSame(7,$byFleet[$npc->id]['technology_levels']['weapon_calibration']['basic']);
    }

    public function testDeploymentMigrationRefusesToEraseExistingHistory(): void
    {
        $this->win($this->fleet($this->target));$reinforcement=$this->fleet($this->target);$reinforcement->mission='defend';
        static::getContainer()->get(OccupationService::class)->recordReinforcementDeparture($reinforcement);$this->em->flush();
        require_once dirname(__DIR__,2).'/migrations/Version20261108040000.php';
        $migration=new \DoctrineMigrations\Version20261108040000($this->em->getConnection(),new \Psr\Log\NullLogger());
        $this->expectException(\Doctrine\Migrations\Exception\AbortMigration::class);$migration->down(new \Doctrine\DBAL\Schema\Schema());
    }

    public function testDueMessageTravelsThroughRelayAndCapturesOnlyOnceAfterFullDay(): void
    {
        $fleet=$this->fleet($this->target);$this->win($fleet);$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $id=$occupation->id;$planetId=$this->target->id;$memberId=$occupation->occupant->id;$token=$occupation->dispatchToken;$deadline=$occupation->captureAt;
        $outbox=$this->em->getRepository(\App\Entity\OutboxMessage::class)->findOneBy(['kind'=>'occupation','targetId'=>$id]);
        self::assertNotNull($outbox);self::assertEquals($deadline,$outbox->dueAt);
        static::getContainer()->get(\App\Service\Notification\OutboxRelay::class)->run();
        $messages=array_values(array_filter(static::getContainer()->get('messenger.transport.async')->getSent(),static fn($envelope): bool=>$envelope->getMessage() instanceof \App\Message\OccupationDue));
        self::assertCount(1,$messages);self::assertSame(86400000,$messages[0]->last(\Symfony\Component\Messenger\Stamp\DelayStamp::class)->getDelay());
        $message=$messages[0]->getMessage();self::assertSame($token,$message->dispatchToken);
        $handler=static::getContainer()->get(\App\MessageHandler\OccupationDueHandler::class);$handler($message);$handler($message);
        self::assertSame(1,$this->em->getRepository(\App\Entity\OutboxMessage::class)->count(['kind'=>'occupation','targetId'=>$id]));
        self::assertNull($this->em->find(Planet::class,$planetId)->owner);
        $this->clock->sleep(86400);$handler($message);$handler($message);
        self::assertSame($memberId,$this->em->find(Planet::class,$planetId)->owner->id);
        self::assertSame(1,$this->em->getRepository(OccupationEvent::class)->count(['action'=>'captured']));
        self::assertSame(1,$this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['sourceType'=>'risk_mission']));
    }

    public function testDueMessageWaitsForBattleAndRetainedVictoryRequeuesWithoutResettingDeadline(): void
    {
        $this->win($this->fleet($this->target));$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $id=$occupation->id;$token=$occupation->dispatchToken;$deadline=$occupation->captureAt;
        $pending=$this->em->getRepository(\App\Entity\OutboxMessage::class)->findOneBy(['kind'=>'occupation','targetId'=>$id]);$this->em->remove($pending);$this->em->flush();
        $this->clock->sleep(86400);$rival=$this->rivalFleet(['colony_ship'=>1]);$combat=static::getContainer()->get(CombatEventService::class);
        static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival));
        $handler=static::getContainer()->get(\App\MessageHandler\OccupationDueHandler::class);$message=new \App\Message\OccupationDue($id,$token);$handler($message);
        self::assertNull($this->target->owner);self::assertSame('active',$occupation->status);
        self::assertSame(0,$this->em->getRepository(\App\Entity\OutboxMessage::class)->count(['kind'=>'occupation','targetId'=>$id]));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        $retry=$this->em->getRepository(\App\Entity\OutboxMessage::class)->findOneBy(['kind'=>'occupation','targetId'=>$id]);
        self::assertNotNull($retry);self::assertSame($token,$retry->token);self::assertEquals($this->clock->now(),$retry->dueAt);
        self::assertEquals($deadline,$occupation->captureAt);$handler($message);self::assertSame('captured',$occupation->status);
    }

    public function testResumeCommandPreviewsWithoutWritesThenSchedulesOnceAndSupportsCursor(): void
    {
        $this->win($this->fleet($this->target));$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $outbox=$this->em->getRepository(\App\Entity\OutboxMessage::class)->findOneBy(['kind'=>'occupation','targetId'=>$occupation->id]);$this->em->remove($outbox);$this->em->flush();
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\OccupationResumeCommand(static::getContainer()->get(\App\Service\Combat\OccupationScheduler::class)));
        self::assertSame(0,$tester->execute(['--limit'=>'1']));self::assertStringContainsString('Prévisualisation sans écriture',$tester->getDisplay());
        self::assertStringContainsString('--after-id='.$occupation->id,$tester->getDisplay());
        self::assertSame(0,$this->em->getRepository(\App\Entity\OutboxMessage::class)->count(['kind'=>'occupation']));
        self::assertSame(0,$tester->execute(['--apply'=>true]));self::assertSame(0,$tester->execute(['--apply'=>true]));
        self::assertSame(1,$this->em->getRepository(\App\Entity\OutboxMessage::class)->count(['kind'=>'occupation']));
        self::assertSame(0,$tester->execute(['--after-id'=>(string)$occupation->id]));self::assertStringContainsString('Occupations examinées : 0',$tester->getDisplay());
        self::assertSame(2,$tester->execute(['--limit'=>'501']));self::assertSame(2,$tester->execute(['--after-id'=>'-1']));
        self::assertNull($this->target->owner);
    }

    public function testSchedulerRejectsStaleClaimAndSkipsClosedUniverse(): void
    {
        $this->win($this->fleet($this->target));$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $scheduler=static::getContainer()->get(\App\Service\Combat\OccupationScheduler::class);
        self::assertSame('stale',$scheduler->enqueue($occupation->id,'old-claim'));
        $occupation->occupant->universe->status='closed';$this->em->flush();
        self::assertSame('universe_closed',$scheduler->enqueue($occupation->id,$occupation->dispatchToken));
        $preview=$scheduler->resume();self::assertSame('universe_closed',$preview['occupations'][0]['status']);
        $message=new \App\Message\OccupationDue($occupation->id,$occupation->dispatchToken);$this->clock->sleep(86400);
        static::getContainer()->get(\App\MessageHandler\OccupationDueHandler::class)($message);
        self::assertNull($this->target->owner);self::assertSame('active',$occupation->status);
    }

    public function testDisabledSchedulerAndHandlerNeverCaptureOrEnqueue(): void
    {
        $this->win($this->fleet($this->target));$occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $scheduler=new \App\Service\Combat\OccupationScheduler(static::getContainer()->get(GameManager::class),static::getContainer()->get(GameRepository::class),$this->clock,false);
        $count=$this->em->getRepository(\App\Entity\OutboxMessage::class)->count(['kind'=>'occupation']);
        self::assertSame('disabled',$scheduler->enqueue($occupation->id,$occupation->dispatchToken));
        self::assertCount(1,$scheduler->resume()['occupations']);
        $tester=new \Symfony\Component\Console\Tester\CommandTester(new \App\Command\OccupationResumeCommand($scheduler));
        self::assertSame(1,$tester->execute(['--apply'=>true]));self::assertStringContainsString('désactivées',$tester->getDisplay());
        $this->clock->sleep(86400);$handler=new \App\MessageHandler\OccupationDueHandler($this->captureService(),$scheduler);
        $handler(new \App\Message\OccupationDue($occupation->id,$occupation->dispatchToken));
        self::assertNull($this->target->owner);self::assertSame('active',$occupation->status);
        self::assertSame($count,$this->em->getRepository(\App\Entity\OutboxMessage::class)->count(['kind'=>'occupation']));
    }

    public function testNpcVictoryDoesNotRewardTheDefeatedOccupationReinforcement(): void
    {
        $held=$this->fleet($this->target);$this->win($held);
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$held]) as $ship) $ship->quantity=$ship->slug==='colony_ship'?1:0;
        $support=$this->fleet($this->target);$support->mission='defend';$support->orbitDurationSeconds=86400;
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$support]) as $ship) $ship->quantity=$ship->slug==='colony_ship'?1:0;
        static::getContainer()->get(OccupationService::class)->recordReinforcementDeparture($support);$this->em->flush();
        $combat=static::getContainer()->get(CombatEventService::class);static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->arriveDefender($support));
        $npc=new \App\Entity\PlanetNpcShip();$npc->planet=$this->target;$npc->slug='battleship';$npc->quantity=20;$this->em->persist($npc);$this->em->flush();
        $rival=$this->rivalFleet(['colony_ship'=>1]);static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        $log=$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$event]);self::assertSame('defender_wins',$log->outcome);
        self::assertSame('failed',$log->snapshot['capture']['status']);
        self::assertSame(0,$this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['sourceType'=>'risk_mission']));
    }

    public function testOldReportShowsTakeoverWithoutRewritingTheBattleSnapshot(): void
    {
        $held=$this->fleet($this->target);$original=$this->win($held);$before=$original->snapshot;
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$held]) as $ship) if($ship->slug!=='colony_ship') $ship->quantity=0;
        $this->em->flush();$rival=$this->rivalFleet(['battleship'=>20,'colony_ship'=>1]);$this->win($rival);
        $this->client->loginUser($this->player);$this->client->request('GET','/rapports/combat/'.$original->id);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.mission-report','Votre occupation a été remplacée');
        self::assertSelectorNotExists('time[datetime="'.$before['capture']['capture_at'].'"]');
        self::assertSame($before,$this->em->find(CombatLog::class,$original->id)->snapshot);
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $this->clock->sleep(86400);self::assertSame('captured',$this->captureService()->capture($occupation->id,$occupation->dispatchToken)['status']);
        $this->client->request('GET','/connexion');
        $this->client->loginUser($this->em->find(\App\Entity\Player::class,$this->player->id));
        $this->client->request('GET','/rapports/combat/'.$original->id);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.mission-report','Votre occupation a été remplacée');
        self::assertSame($before,$this->em->find(CombatLog::class,$original->id)->snapshot);
    }

    public function testFailedOccupationReportNoLongerShowsAnActiveDeadline(): void
    {
        $held=$this->fleet($this->target);$log=$this->win($held);$before=$log->snapshot;
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$held,'slug'=>'colony_ship'])->quantity=0;$this->em->flush();
        self::assertSame('failed',$this->captureService()->capture($occupation->id,$occupation->dispatchToken)['status']);
        $this->client->loginUser($this->player);$this->client->request('GET','/rapports/combat/'.$log->id);self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('time[datetime="'.$before['capture']['capture_at'].'"]');
        self::assertSame($before,$this->em->find(CombatLog::class,$log->id)->snapshot);
    }

    public function testRetainedBattleReportShowsFinalCaptureAndTheCorrectVictoriousCamp(): void
    {
        $this->win($this->fleet($this->target));$rival=$this->rivalFleet(['colony_ship'=>1]);$combat=static::getContainer()->get(CombatEventService::class);
        static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        $log=$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$event]);$before=$log->snapshot;
        $occupation=$this->em->getRepository(PlanetOccupation::class)->findOneBy(['planet'=>$this->target]);
        $this->clock->sleep($occupation->captureAt->getTimestamp()-$this->clock->now()->getTimestamp());
        self::assertSame('captured',$this->captureService()->capture($occupation->id,$occupation->dispatchToken)['status']);
        $this->client->loginUser($this->player);$this->client->request('GET','/rapports/combat/'.$log->id);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#combat-result','Victoire de votre camp');self::assertSelectorTextContains('.mission-report','Vous avez conquis cette planète PNJ');
        self::assertSelectorNotExists('time[datetime="'.$before['capture']['capture_at'].'"]');
        self::assertSame($before,$this->em->find(CombatLog::class,$log->id)->snapshot);
    }

    public function testDisablingDuringContestationResolvesCombatWithoutStartingAReplacementOccupation(): void
    {
        $held=$this->fleet($this->target);$this->win($held);
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$held]) as $ship) if($ship->slug!=='colony_ship') $ship->quantity=0;
        $this->em->flush();$rival=$this->rivalFleet(['battleship'=>20,'colony_ship'=>1]);$rival->orbitDurationSeconds=86400;
        $combat=static::getContainer()->get(CombatEventService::class);static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$combat->joinAttack($rival));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$this->target,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $eventId=$event->id;$fleetId=$rival->id;$planetId=$this->target->id;
        self::ensureKernelShutdown();$_SERVER['FORGOTTEN_EMPIRES_ENABLED']=$_ENV['FORGOTTEN_EMPIRES_ENABLED']='0';putenv('FORGOTTEN_EMPIRES_ENABLED=0');
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        static::getContainer()->set('clock',$this->clock);$this->em=static::getContainer()->get(EntityManagerInterface::class);
        $this->clock->sleep(600);$event=$this->em->find(CombatEvent::class,$eventId);
        static::getContainer()->get(CombatEventService::class)->resolve($eventId,$event->dispatchToken);
        self::assertSame('resolved',$event->status);self::assertSame('returning',$this->em->find(Fleet::class,$fleetId)->status);
        self::assertNull($this->em->find(Planet::class,$planetId)->owner);
        self::assertSame(0,$this->em->getRepository(PlanetOccupation::class)->count(['status'=>'active']));
        self::assertSame(0,$this->em->getRepository(OccupationEvent::class)->count(['action'=>'replaced']));
        self::assertEquals(0,$this->em->find(Fleet::class,$fleetId)->orbitFuel);
    }

    private function captureService(): \App\Service\Combat\OccupationCaptureService
    {
        $container=static::getContainer();
        $readiness=new \App\Service\Combat\OccupationReadiness($container->get(GameRepository::class),$container->get(\App\Service\Combat\OccupationPolicy::class),$container->get(OccupationService::class),$container->get(\App\Service\Player\RelationResolver::class));
        return new \App\Service\Combat\OccupationCaptureService($container->get(GameManager::class),$container->get(GameRepository::class),$readiness,
            $container->get(\App\Service\Combat\OccupationPolicy::class),$container->get(\App\Service\Queue\QueueService::class),$container->get(\App\Service\Colony\ResourceService::class),
            $container->get(FleetService::class),$container->get(\App\Service\Boost\BoostGrantService::class),$container->get(\App\Service\Notification\NotificationService::class),
            $container->get(\App\Service\Universe\PlanetNameGenerator::class),$this->clock,true);
    }

    private function rivalFleet(array $ships): Fleet
    {
        $player=$this->player('rival');$member=$player->currentPlayerUniverse;
        $technology=new PlayerTechnology();$technology->playerUniverse=$member;$technology->slug='colonial_expansion';$technology->level=1;$this->em->persist($technology);
        $fleet=$this->fleet($this->target);$fleet->playerUniverse=$member;$fleet->homePlanet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$member]);
        foreach($this->em->getRepository(FleetShip::class)->findBy(['fleet'=>$fleet]) as $ship) $ship->quantity=$ships[$ship->slug]??0;
        $this->em->flush();return $fleet;
    }

    private function win(Fleet $fleet): CombatLog
    {
        $service=static::getContainer()->get(CombatEventService::class);
        self::assertTrue(static::getContainer()->get(GameManager::class)->transaction(fn(): bool=>$service->joinAttack($fleet)));
        $event=$this->em->getRepository(CombatEvent::class)->findOneBy(['planet'=>$fleet->destination,'status'=>'pending']);$event->seed=123;$this->em->flush();
        $this->clock->sleep(600);$service->resolve($event->id,$event->dispatchToken);
        $log=$this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent'=>$event]);self::assertNotNull($log);self::assertSame('attacker_wins',$log->outcome);return $log;
    }
    private function player(string $name): Player
    {
        $data=new RegistrationData();$data->email=$name.'@example.test';$data->username=$name;$data->plainPassword='A-test-password-2026';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);$player->emailVerifiedAt=$this->clock->now();$this->em->flush();
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($player,$universe->id);return $player;
    }
    private function npc(array $exclude=[]): Planet
    {
        foreach($this->em->getRepository(Planet::class)->findBy(['owner'=>null,'npcPlanet'=>true]) as $target) {
            if(!in_array($target->id,$exclude,true) && $target->solarSystem->galaxy->id===$this->origin->solarSystem->galaxy->id) {
                $target->conquestLevel=1;static::getContainer()->get(\App\Service\Colony\ResourceService::class)->initialize($target);$this->em->flush();return $target;
            }
        }
        throw new \RuntimeException('Cible PNJ de test absente.');
    }
    private function fleet(Planet $target): Fleet
    {
        $fleet=new Fleet();$fleet->playerUniverse=$this->player->currentPlayerUniverse;$fleet->homePlanet=$this->origin;$fleet->destination=$target;$fleet->destinationSystem=$target->solarSystem;
        $fleet->mission='conquer';$fleet->status='traveling';$fleet->travelSeconds=60;$fleet->reservedFuel=500;$fleet->orbitFuel=480;$fleet->cargo=['hydrogen'=>500];
        $fleet->departureAt=$this->clock->now()->modify('-60 seconds');$fleet->arrivalAt=$this->clock->now();$fleet->dispatchToken='occupation-start-'.bin2hex(random_bytes(6));$this->em->persist($fleet);
        foreach(['fighter'=>12,'battleship'=>1,'colony_ship'=>1] as $slug=>$quantity) { $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->quantity=$quantity;$this->em->persist($ship); }
        $this->em->flush();return $fleet;
    }
    private function idleShips(array $ships): void
    {
        $fleet=new Fleet();$fleet->playerUniverse=$this->player->currentPlayerUniverse;$fleet->homePlanet=$this->origin;$this->em->persist($fleet);
        foreach($ships as $slug=>$quantity) { $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug=$slug;$ship->quantity=$quantity;$this->em->persist($ship); }
        $this->em->flush();
    }
    private function discover(Planet $planet): void
    {
        $criteria=['playerUniverse'=>$this->player->currentPlayerUniverse,'solarSystem'=>$planet->solarSystem];
        if($this->em->getRepository(PlayerDiscovery::class)->findOneBy($criteria)) return;
        $discovery=new PlayerDiscovery();$discovery->playerUniverse=$this->player->currentPlayerUniverse;$discovery->solarSystem=$planet->solarSystem;$this->em->persist($discovery);$this->em->flush();
    }
}
