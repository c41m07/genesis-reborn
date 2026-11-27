<?php
namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Planet, PlanetBuilding, PlanetResource, Player, PlayerTechnology, QueueItem, Universe};
use App\Service\Colony\{EconomicUnitCapacity, ResourceService};
use App\Service\Fleet\{FleetRecallService, FleetService};
use App\Service\Player\RegistrationService;
use App\Service\Queue\QueueService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class EconomicUnitTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private Planet $home;
    private Planet $target;

    protected function setUp(): void
    {
        self::bootKernel();$this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $tool = new SchemaTool($this->em);$metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->clock = new MockClock('2026-11-15T12:00:00Z');self::getContainer()->set('clock', $this->clock);
        $data = new RegistrationData();$data->email = 'civil@example.test';$data->username = 'CivilOwner';$data->plainPassword = bin2hex(random_bytes(16)).'aA1!';
        $this->player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        $universe->gameSettings['multipliers'] = ['building_time'=>1,'research_time'=>1,'ship_build_time'=>1,'resource_production'=>1,'travel_time'=>1];
        self::getContainer()->get(UniverseService::class)->select($this->player, $universe->id);
        $membership = $this->player->currentPlayerUniverse;
        $this->home = $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$membership]);
        $this->target = $this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$this->home->solarSystem,'owner'=>null]);
        $this->target->owner = $membership;$this->target->npcPlanet = false;
        self::getContainer()->get(ResourceService::class)->initialize($this->target);
        foreach ([$this->home,$this->target] as $planet) {
            foreach (['mine_metal'=>2,'mine_nano'=>2,'mine_hydrogen'=>5,'power_plant'=>5,'shipyard'=>2,'lab'=>2] as $slug=>$level) $this->building($planet, $slug, $level);
            foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $row) {$row->currentStock = 10000;$row->lastUpdatedAt = $this->clock->now();}
        }
        foreach (['engineering_foundations'=>2,'vector_propulsion'=>1] as $slug=>$level) {
            $technology = new PlayerTechnology();$technology->playerUniverse = $membership;$technology->slug = $slug;$technology->level = $level;$this->em->persist($technology);
        }
        $this->em->flush();
    }

    private function building(Planet $planet, string $slug, int $level, string $tier = 'basic'): void
    {
        $row = $this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$planet,'slug'=>$slug,'tier'=>$tier]) ?? new PlanetBuilding();
        $row->planet = $planet;$row->slug = $slug;$row->tier = $tier;$row->level = $level;$this->em->persist($row);
    }

    private function units(Planet $planet, string $slug, int $quantity, string $tier = 'basic'): FleetShip
    {
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['homePlanet'=>$planet,'playerUniverse'=>$planet->owner,'status'=>'idle']) ?? new Fleet();
        $fleet->homePlanet = $planet;$fleet->playerUniverse = $planet->owner;$this->em->persist($fleet);$this->em->flush();
        $ship = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>$slug,'tier'=>$tier]) ?? new FleetShip();
        $ship->fleet = $fleet;$ship->slug = $slug;$ship->tier = $tier;$ship->quantity = $quantity;$this->em->persist($ship);$this->em->flush();return $ship;
    }

    private function state(Planet $planet): array
    {
        $levels = self::getContainer()->get(\App\Repository\GameRepository::class)->levelsByTier(PlanetBuilding::class, ['planet'=>$planet]);
        return self::getContainer()->get(EconomicUnitCapacity::class)->state($planet, $levels);
    }

    public function testRankedCapacityKeepsExcessUnitsAndCivilProductionHasNoElectricUpkeep(): void
    {
        $this->units($this->home, 'hydrogen_collector', 7);
        $this->units($this->home, 'hydrogen_collector', 2, 'superior');
        $this->units($this->home, 'solar_satellite', 12);
        $this->building($this->home, 'power_plant', 0);$this->em->flush();
        $states = $this->state($this->home);
        self::assertSame(5, $states['hydrogen_collector']['basic']['active']);
        self::assertSame(2, $states['hydrogen_collector']['basic']['inactive']);
        self::assertSame(0, $states['hydrogen_collector']['superior']['active']);
        self::assertSame(12, $states['solar_satellite']['basic']['inactive']);
        $energy = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'electricity']);$energy->currentStock = 0;
        $hydrogen = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'hydrogen']);$hydrogen->currentStock = 0;$this->em->flush();
        $resources = self::getContainer()->get(ResourceService::class);$snapshot = $resources->snapshot($this->home, true);
        $modifiers = array_column($resources->positionProductionModifiers($this->home), 'percent', 'label');
        $factor = 1 + $modifiers['Hydrogène']/100;
        self::assertEqualsWithDelta(4500*$factor, $snapshot['hydrogen']['sources']['hydrogen_collector']*3600, 1e-6);
        self::assertEqualsWithDelta(0, $snapshot['hydrogen']['productionInputs']['electricity']['actual'], 1e-8);
        $this->clock->sleep(600);
        self::assertEqualsWithDelta($snapshot['hydrogen']['rate']*600, $resources->snapshot($this->home)['hydrogen']['stock'], 1e-6);
        self::assertSame(7, $this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$this->em->getRepository(Fleet::class)->findOneBy(['homePlanet'=>$this->home,'status'=>'idle']),'slug'=>'hydrogen_collector','tier'=>'basic'])->quantity);
        $resources->materialize($this->home);$this->building($this->home,'mine_hydrogen',1,'superior');$this->building($this->home,'power_plant',1,'superior');$this->em->flush();
        $states = $this->state($this->home);self::assertSame(1,$states['hydrogen_collector']['superior']['active']);
        self::assertSame(1,$states['hydrogen_collector']['superior']['inactive']);self::assertSame(2,$states['solar_satellite']['superior']['capacity']);

    }

    public function testSatelliteQueueReservesCapacityAndCancellationReleasesIt(): void
    {
        $queue = self::getContainer()->get(QueueService::class);$member = $this->player->currentPlayerUniverse;
        self::assertSame(10, $queue->maximum($member, $this->home, 'ships', 'solar_satellite', 'basic'));
        self::assertSame(8, $queue->add($member, $this->home, 'ships', 'solar_satellite', 8, str_repeat('a',32)));
        self::assertSame(2, $queue->maximum($member, $this->home, 'ships', 'solar_satellite', 'basic'));
        self::assertSame(2, $queue->add($member, $this->home, 'ships', 'solar_satellite', 3, str_repeat('b',32)));
        self::assertSame(10, $this->state($this->home)['solar_satellite']['basic']['reserved']);
        self::assertSame(0, $queue->maximum($member, $this->home, 'ships', 'solar_satellite', 'basic'));
        self::assertStringContainsString('place', $queue->constructionPreview($member,$this->home,'ships','solar_satellite','basic',1)['reason']);
        $first = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->home,'itemSlug'=>'solar_satellite'], ['id'=>'ASC']);
        $queue->cancel($member, $first->id, str_repeat('c',32));
        self::assertSame(2, $this->state($this->home)['solar_satellite']['basic']['reserved']);
        self::assertSame(8, $queue->maximum($member, $this->home, 'ships', 'solar_satellite', 'basic'));
    }

    public function testDelayedCompletionCreditsOnlyProductionAfterItsPaidFinish(): void
    {
        $member = $this->player->currentPlayerUniverse;$queue = self::getContainer()->get(QueueService::class);
        $queue->add($member, $this->home, 'ships', 'solar_satellite', 2, str_repeat('a',32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->home,'itemSlug'=>'solar_satellite']);
        $energy = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'electricity']);
        $energy->currentStock = 0;$this->em->flush();$finish = $item->finishAt();$token = $item->dispatchToken;$id = $item->id;
        $this->clock->sleep($item->durationSeconds+600);$queue->synchronize($member);
        self::assertEquals($finish, $energy->lastUpdatedAt);
        $snapshot = self::getContainer()->get(ResourceService::class)->snapshot($this->home, true);
        $baseline = $energy->currentStock;
        self::assertGreaterThan(0, $snapshot['electricity']['sources']['solar_satellite']);
        self::assertEqualsWithDelta($baseline+$snapshot['electricity']['rate']*600, $snapshot['electricity']['stock'], 1e-6);
        $queue->complete($id,$token);
        self::assertSame(2, $this->state($this->home)['solar_satellite']['basic']['present']);
        self::assertEqualsWithDelta($snapshot['electricity']['stock'], self::getContainer()->get(ResourceService::class)->snapshot($this->home)['electricity']['stock'], 1e-6);
    }

    public function testDepartureReservesDestinationAndRecallReleasesItWithoutDoubleProduction(): void
    {
        $this->units($this->home,'hydrogen_collector',3);
        $service = self::getContainer()->get(FleetService::class);$member = $this->player->currentPlayerUniverse;
        $service->send($member,$this->home,'station',$this->target->id,['hydrogen_collector'=>2],1,[],str_repeat('a',32));
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'status'=>'traveling']);
        self::assertSame(1, $this->state($this->home)['hydrogen_collector']['basic']['active']);
        self::assertSame(2, $this->state($this->target)['hydrogen_collector']['basic']['reserved']);
        self::assertSame(0, $this->state($this->target)['hydrogen_collector']['basic']['active']);
        $this->clock->sleep(60);$recall = self::getContainer()->get(FleetRecallService::class);$quote = $recall->preview($member,$fleet);
        $recall->recall($member,$fleet->id,null,$quote['version'],str_repeat('b',32));
        self::assertSame(0, $this->state($this->target)['hydrogen_collector']['basic']['reserved']);
        self::assertSame(2, $this->state($this->home)['hydrogen_collector']['basic']['reserved']);
        $token = $fleet->dispatchToken;$this->clock->sleep($fleet->travelSeconds+1);$service->arrive($fleet->id,$token);$service->arrive($fleet->id,$token);
        self::assertSame(3, $this->state($this->home)['hydrogen_collector']['basic']['active']);
        self::assertSame(0, $this->state($this->home)['hydrogen_collector']['basic']['reserved']);
        self::assertSame('completed', $fleet->status);
    }
    public function testDestinationLossReleasesItsReservationAndKeepsCollectorsOnFallback(): void
    {
        $this->units($this->home,'hydrogen_collector',3);
        $member = $this->player->currentPlayerUniverse;$service = self::getContainer()->get(FleetService::class);
        $service->send($member,$this->home,'station',$this->target->id,['hydrogen_collector'=>2],1,[],str_repeat('a',32));
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'status'=>'traveling']);
        self::getContainer()->get(ResourceService::class)->materialize($this->target);
        $this->target->owner = null;$this->em->flush();
        self::assertSame(0,$this->state($this->target)['hydrogen_collector']['basic']['reserved']);
        $this->clock->sleep($fleet->travelSeconds+1);$service->arrive($fleet->id,$fleet->dispatchToken);
        self::assertSame('returning',$fleet->status);self::assertSame($this->home->id,$fleet->returnPlanet->id);
        self::assertSame(2,$this->state($this->home)['hydrogen_collector']['basic']['reserved']);
        $this->clock->sleep($fleet->travelSeconds+1);$service->arrive($fleet->id,$fleet->dispatchToken);
        self::assertSame(3,$this->state($this->home)['hydrogen_collector']['basic']['active']);
        self::assertSame(0,$this->state($this->home)['hydrogen_collector']['basic']['reserved']);
    }

    public function testCollectorReservationPreventsNewConstructionFromTakingItsPlace(): void
    {
        $this->units($this->home,'hydrogen_collector',2);$this->units($this->target,'hydrogen_collector',3);
        $member = $this->player->currentPlayerUniverse;$resources = self::getContainer()->get(ResourceService::class);
        $hydrogen = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->target,'resourceType'=>'hydrogen']);$hydrogen->currentStock = 0;$this->em->flush();
        $service = self::getContainer()->get(FleetService::class);
        $service->send($member,$this->home,'station',$this->target->id,['hydrogen_collector'=>2],.1,[],str_repeat('a',32));
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'status'=>'traveling']);
        $queue = self::getContainer()->get(QueueService::class);$queue->add($member,$this->target,'ships','hydrogen_collector',2,str_repeat('b',32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->target,'itemSlug'=>'hydrogen_collector']);
        self::assertLessThan($fleet->travelSeconds,$item->durationSeconds+60);
        $this->clock->sleep($item->durationSeconds+60);$queue->synchronize($member);
        $state = $this->state($this->target)['hydrogen_collector']['basic'];
        self::assertSame(3,$state['active']);self::assertSame(2,$state['inactive']);self::assertSame(2,$state['reserved']);
        $before = $resources->snapshot($this->target)['hydrogen']['stock'];
        $recall = self::getContainer()->get(FleetRecallService::class);$quote = $recall->preview($member,$fleet);
        $recall->recall($member,$fleet->id,null,$quote['version'],str_repeat('c',32));
        self::assertSame(5,$this->state($this->target)['hydrogen_collector']['basic']['active']);
        self::assertEqualsWithDelta($before,$hydrogen->currentStock,1e-6,'La libération ne produit pas rétroactivement.');
    }

    public function testCivilProductionUsesUniverseEventsVacationsAndStorageBoundaries(): void
    {
        $this->units($this->home,'hydrogen_collector',1);
        $member = $this->player->currentPlayerUniverse;$member->universe->gameSettings['multipliers']['resource_production'] = 2;
        $hydrogen = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'hydrogen']);$hydrogen->currentStock = 0;
        $energy = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'electricity']);$energy->currentStock = 5000;
        $event = new \App\Entity\GameEvent();$event->name = 'Civil production';$event->type = 'production_bonus';$event->status = 'active';$event->universe = $member->universe;
        $event->startsAt = $this->clock->now()->modify('+100 seconds');$event->endsAt = $this->clock->now()->modify('+200 seconds');$event->parameters = ['percentage'=>100];$this->em->persist($event);
        $vacation = new \App\Entity\VacationPeriod();$vacation->membership = $member;$vacation->startedAt = $this->clock->now()->modify('+150 seconds');$vacation->scheduledEndAt = $this->clock->now()->modify('+250 seconds');$this->em->persist($vacation);$this->em->flush();
        $resources = self::getContainer()->get(ResourceService::class);$initial = $resources->snapshot($this->home,true);
        $modifiers = array_column($resources->positionProductionModifiers($this->home),'percent','label');
        self::assertEqualsWithDelta(1800*(1+$modifiers['Hydrogène']/100),$initial['hydrogen']['sources']['hydrogen_collector']*3600,1e-6);
        $this->clock->sleep(170);self::assertEquals(0,$resources->snapshot($this->home,true)['hydrogen']['gross']);
        $this->clock->sleep(230);
        // 100 secondes normales, 50 doublées, 100 en vacances, puis 150 normales.
        self::assertEqualsWithDelta($initial['hydrogen']['rate']*350,$resources->snapshot($this->home)['hydrogen']['stock'],1e-6);
        $resources->materialize($this->home);$hydrogen->currentStock = 10123;$this->em->flush();$this->clock->sleep(3600);
        self::assertSame(10123.0,$resources->snapshot($this->home)['hydrogen']['stock'],'Le surplus existant reste conservé sans nouveau crédit au-delà du plafond.');
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenMissions')]
    public function testSatelliteAndCollectorForbiddenMissionsNeverSpendResources(string $slug,string $mission): void
    {
        $this->units($this->home,$slug,1);
        $service = self::getContainer()->get(FleetService::class);$member = $this->player->currentPlayerUniverse;
        $before = $this->em->getConnection()->fetchAllAssociative('SELECT id,current_stock FROM planet_resource ORDER BY id');
        try {$service->send($member,$this->home,$mission,$this->target->id,[$slug=>1],1,[],str_repeat('d',32));self::fail('Mission interdite acceptée.');}
        catch (\DomainException $exception) {self::assertStringContainsString('ne peut pas participer',$exception->getMessage());}
        self::assertSame($before,$this->em->getConnection()->fetchAllAssociative('SELECT id,current_stock FROM planet_resource ORDER BY id'));
        self::assertSame(0,$this->em->getRepository(Fleet::class)->count(['status'=>'traveling']));
    }

    public static function forbiddenMissions(): array
    {
        return [['solar_satellite','station'],['hydrogen_collector','transport']];
    }

    public function testGarrisonProducesUntilCombatLossesAreAppliedAtClosing(): void
    {
        $this->units($this->home,'hydrogen_collector',1);$this->units($this->home,'solar_satellite',1);$this->units($this->home,'transport',1);
        [$event,$combat] = $this->battle();
        self::assertSame(1,$this->state($this->home)['hydrogen_collector']['basic']['active']);
        self::assertSame(1,$this->state($this->home)['solar_satellite']['basic']['active']);
        $this->clock->sleep(60);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame('pending',$event->status);self::assertSame(1,$this->state($this->home)['hydrogen_collector']['basic']['active']);
        $this->clock->sleep(600);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame('resolved',$event->status);
        $log = $this->em->getRepository(\App\Entity\CombatLog::class)->findOneBy(['combatEvent'=>$event]);
        $lost = 0;foreach ($log->snapshot['losses'] as $key=>$quantity) if(str_contains($key,':hydrogen_collector') || str_contains($key,':solar_satellite')) $lost += $quantity;
        self::assertGreaterThan(0,$lost);self::assertSame(2-$lost,$this->state($this->home)['hydrogen_collector']['basic']['present']+$this->state($this->home)['solar_satellite']['basic']['present']);
        $rows = $this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$this->home]);foreach ($rows as $row) self::assertEquals($this->clock->now(),$row->lastUpdatedAt);
        self::assertGreaterThan(0,self::getContainer()->get(QueueService::class)->maximum($this->player->currentPlayerUniverse,$this->home,'ships','solar_satellite','basic'));
    }

    public function testCivilUnitsAppearInDefenderSimulationAndCannotBeImportedAsAttackers(): void
    {
        $this->units($this->home,'hydrogen_collector',2);$this->units($this->home,'solar_satellite',2);$this->units($this->home,'fighter',1);
        $simulation = self::getContainer()->get(\App\Service\Combat\PlayerCombatSimulation::class);$member = $this->player->currentPlayerUniverse;
        $own = $simulation->own($member,$this->home->id);
        self::assertSame(['fighter'=>['basic'=>1]],$own['ships']);
        $forces = ['attacker'=>['ships'=>['fighter'=>['basic'=>1]]],
            'defender'=>['ships'=>['hydrogen_collector'=>['superior'=>2],'solar_satellite'=>['intermediate'=>2]]]];
        $result = $simulation->run($member,$forces,42);
        foreach ($result['rounds'] as $round) foreach ($round['shots'] as $shot) self::assertStringStartsWith('attacker:',$shot['attacker']);
        self::assertNotEmpty($result['survivors']);
        foreach (['hydrogen_collector','solar_satellite'] as $slug) {
            try {$simulation->run($member,['attacker'=>['ships'=>[$slug=>['basic'=>1]]]],42);self::fail('Unité civile offensive acceptée.');}
            catch (\DomainException $exception) {self::assertStringContainsString('uniquement à la défense',$exception->getMessage());}
        }
    }

    public function testPricePreviewAndPaidQueueMatchForAllSixDiscountedProfiles(): void
    {
        foreach (['basic','intermediate','superior'] as $tier) {
            foreach (['shipyard'=>25,'power_plant'=>5,'mine_hydrogen'=>5] as $slug=>$level) $this->building($this->home,$slug,$level,$tier);
            foreach (['engineering_foundations'=>2,'vector_propulsion'=>1,'naval_engineering'=>25] as $slug=>$level) {
                $technology = $this->em->getRepository(PlayerTechnology::class)->findOneBy(['playerUniverse'=>$this->player->currentPlayerUniverse,'slug'=>$slug,'tier'=>$tier]) ?? new PlayerTechnology();
                $technology->playerUniverse = $this->player->currentPlayerUniverse;$technology->slug = $slug;$technology->tier = $tier;$technology->level = $level;$this->em->persist($technology);
            }
        }
        $this->em->flush();$queue = self::getContainer()->get(QueueService::class);$member = $this->player->currentPlayerUniverse;$index = 0;
        foreach (['hydrogen_collector','solar_satellite'] as $slug) foreach (['basic','intermediate','superior'] as $tier) {
            $preview = $queue->constructionPreview($member,$this->home,'ships',$slug,$tier,2);
            self::assertSame(2,$preview['quantity']);self::assertSame('', $preview['reason']);
            self::assertSame(2,$queue->add($member,$this->home,'ships',$slug,2,str_repeat(dechex(++$index),32),$tier));
            $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->home,'itemSlug'=>$slug,'tier'=>$tier]);
            self::assertSame($preview['costs'],$item->paidCosts);self::assertSame($preview['duration'],$item->durationSeconds);
            self::assertSame(100*(int)ceil($item->durationSeconds/60),$item->paidCosts['electricity']);
        }
    }

    public function testShipyardDeliveryJoinsLocalDefenseOnceWithItsWholeFleetAndStoredFuel(): void
    {
        $this->units($this->home,'transport',5);[$event,$combat] = $this->battle();
        $ship = $this->units($this->home,'fighter',1);$local = $ship->fleet;$local->storedFuel = 100;$local->cargo = ['nano'=>17];$this->em->flush();
        $queue = self::getContainer()->get(QueueService::class);$member = $this->player->currentPlayerUniverse;
        $queue->add($member,$this->home,'ships','solar_satellite',1,str_repeat('a',32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->home,'itemSlug'=>'solar_satellite']);$id = $item->id;$token = $item->dispatchToken;
        $this->clock->sleep(60);$combat->resolve($event->id,$event->dispatchToken);self::assertSame('pending',$event->status);
        $this->clock->sleep($item->durationSeconds-60+1);$queue->synchronize($member);
        self::assertSame('defending',$local->status);self::assertNull($local->departureAt);self::assertSame(100.0,$local->storedFuel);self::assertSame(['nano'=>17],$local->cargo);
        $participant = $this->em->getRepository(\App\Entity\CombatParticipant::class)->findOneBy(['combatEvent'=>$event,'fleet'=>$local]);
        self::assertNotNull($participant);self::assertSame(3,$participant->arrivalRound);
        self::assertSame(1,$this->state($this->home)['solar_satellite']['basic']['active']);
        $queue->complete($id,$token);
        self::assertSame(1,$this->em->getRepository(\App\Entity\CombatParticipant::class)->count(['combatEvent'=>$event,'fleet'=>$local]));
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne("SELECT SUM(quantity) FROM fleet_ship WHERE slug = 'solar_satellite'"));
    }

    public function testStationingDeliveryJoinsLocalDefenseWithoutLosingOnboardReserve(): void
    {
        $this->units($this->home,'transport',5);[$event] = $this->battle();
        $this->units($this->target,'hydrogen_collector',1);$this->units($this->target,'fighter',1);
        $member = $this->player->currentPlayerUniverse;$member->universe->gameSettings['multipliers']['travel_time'] = .001;$this->em->flush();
        $service = self::getContainer()->get(FleetService::class);
        $service->send($member,$this->target,'station',$this->home->id,['hydrogen_collector'=>1,'fighter'=>1],1,[],str_repeat('a',32));
        $fleet = $this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'status'=>'traveling']);
        $this->clock->sleep($fleet->travelSeconds);$service->arrive($fleet->id,$fleet->dispatchToken);
        self::assertSame('completed',$fleet->status);
        $localCollector = $this->em->getRepository(FleetShip::class)->findOneBy(['slug'=>'hydrogen_collector','quantity'=>1], ['id'=>'DESC']);
        $reinforcement = $localCollector?->fleet;
        self::assertSame('defending', $reinforcement?->status);
        self::assertSame($this->home, $reinforcement->homePlanet);
        self::assertNotNull($reinforcement);self::assertEqualsWithDelta(0,$reinforcement->storedFuel,1e-8);self::assertNull($reinforcement->departureAt);
        $participant = $this->em->getRepository(\App\Entity\CombatParticipant::class)->findOneBy(['combatEvent'=>$event,'fleet'=>$reinforcement]);
        self::assertSame(1,$participant->arrivalRound);self::assertSame(1,$this->state($this->home)['hydrogen_collector']['basic']['active']);
        self::assertSame(0,$this->state($this->home)['hydrogen_collector']['basic']['reserved']);
    }

    public function testLateAttackKeepsAlreadyCompletedUnitsInInitialGarrison(): void
    {
        $this->units($this->home,'transport',5);$member = $this->player->currentPlayerUniverse;
        $queue = self::getContainer()->get(QueueService::class);$queue->add($member,$this->home,'ships','solar_satellite',1,str_repeat('a',32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->home,'itemSlug'=>'solar_satellite']);
        $this->clock->sleep($item->durationSeconds+120);[$event] = $this->battle(120);$queue->synchronize($member);
        $defenders = $this->em->getRepository(\App\Entity\CombatParticipant::class)->findBy(['combatEvent'=>$event,'role'=>'defender']);
        self::assertCount(2,$defenders);foreach($defenders as $defender) self::assertSame(1,$defender->arrivalRound);
        self::assertSame(1,$this->state($this->home)['solar_satellite']['basic']['active']);
    }

    public function testLateLocalDeliveryNeverRewritesResolvedRounds(): void
    {
        $this->units($this->home,'transport',5);[$event,$combat] = $this->battle();
        $queue = self::getContainer()->get(QueueService::class);$queue->add($this->player->currentPlayerUniverse,$this->home,'ships','solar_satellite',1,str_repeat('a',32));
        $this->clock->sleep(240);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame('pending',$event->status);self::assertSame(4,$event->currentRound);$history = $event->combatSnapshot['round_history'];
        $queue->synchronize($this->player->currentPlayerUniverse);
        $defenders = $this->em->getRepository(\App\Entity\CombatParticipant::class)->findBy(['combatEvent'=>$event,'role'=>'defender'],['id'=>'DESC']);
        self::assertSame(5,$defenders[0]->arrivalRound);
        $this->clock->sleep(60);$combat->resolve($event->id,$event->dispatchToken);
        self::assertSame($history,array_slice($event->combatSnapshot['round_history'],0,4));
    }

    public function testAdministrativeGrantStartsCivilProductionNowAndKeepsExcessInactive(): void
    {
        $this->player->roles = ['ROLE_ADMIN'];$this->units($this->home,'hydrogen_collector',1);
        $hydrogen = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'hydrogen']);
        $hydrogen->currentStock = 0;$this->em->flush();
        $resources = self::getContainer()->get(ResourceService::class);$this->clock->sleep(60);
        $expected = $resources->snapshot($this->home)['hydrogen']['stock'];
        self::assertLessThan(9000,$expected,'Le stockage doit laisser visible un éventuel crédit rétroactif.');
        $service = self::getContainer()->get(\App\Service\Admin\AdminInterventionService::class);
        self::assertTrue($service->ships($this->player,$this->player->id,$this->home,'hydrogen_collector','basic','7','Correction civile',str_repeat('a',32)));
        self::assertEqualsWithDelta($expected,$resources->snapshot($this->home)['hydrogen']['stock'],1e-6);
        $state = $this->state($this->home)['hydrogen_collector']['basic'];
        self::assertSame(8,$state['present']);self::assertSame(5,$state['active']);self::assertSame(3,$state['inactive']);
        $rate = $resources->snapshot($this->home)['hydrogen']['rate'];$this->clock->sleep(10);
        self::assertEqualsWithDelta($expected+10*$rate,$resources->snapshot($this->home)['hydrogen']['stock'],1e-6);
        self::assertFalse($service->ships($this->player,$this->player->id,$this->home,'hydrogen_collector','basic','7','Correction civile',str_repeat('a',32)));
        self::assertSame(8,$this->state($this->home)['hydrogen_collector']['basic']['present']);
        self::assertSame(1,$this->em->getRepository(\App\Entity\AdminAuditLog::class)->count(['action'=>'admin_ships_grant']));
    }

    public function testAdministrativeCivilGrantSettlesPaidDeliveriesBeforeMaterializingProduction(): void
    {
        $this->player->roles = ['ROLE_ADMIN'];
        $electricity = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$this->home,'resourceType'=>'electricity']);
        $electricity->currentStock = 5000;$this->em->flush();
        $queues = self::getContainer()->get(QueueService::class);$member = $this->player->currentPlayerUniverse;
        $queues->add($member,$this->home,'ships','solar_satellite',1,str_repeat('c',32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->home,'itemSlug'=>'solar_satellite']);
        $this->clock->sleep($item->durationSeconds+60);
        $resources = self::getContainer()->get(ResourceService::class);$baseline = $resources->snapshot($this->home)['electricity']['stock'];
        self::assertLessThan(9000,$baseline,'Le plafond ne doit pas masquer le crédit après livraison.');
        self::getContainer()->get(\App\Service\Admin\AdminInterventionService::class)->ships($this->player,$this->player->id,$this->home,'hydrogen_collector','basic','1','Correction après livraison',str_repeat('d',32));
        $after = $resources->snapshot($this->home,true)['electricity'];
        $expected = $baseline+60*$after['sources']['solar_satellite'];
        self::assertEqualsWithDelta($expected,$after['stock'],1e-6);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count(['planet'=>$this->home]));
        self::assertSame(1,$this->state($this->home)['solar_satellite']['basic']['active']);
        $queues->synchronize($member);
        self::assertEqualsWithDelta($expected,$resources->snapshot($this->home)['electricity']['stock'],1e-6);
        self::assertEquals($this->clock->now(),$electricity->lastUpdatedAt);
    }

    public function testAdministrativeDeliveryJoinsTheNextUnresolvedRoundWithTheWholeLocalFleet(): void
    {
        $this->player->roles = ['ROLE_ADMIN'];$this->units($this->home,'transport',5);[$event,$combat] = $this->battle();
        $this->clock->sleep(60);$combat->resolve($event->id,$event->dispatchToken);self::assertSame('pending',$event->status);
        $local = $this->units($this->home,'fighter',1)->fleet;$local->storedFuel = 75;$this->em->flush();
        $service = self::getContainer()->get(\App\Service\Admin\AdminInterventionService::class);
        self::assertTrue($service->ships($this->player,$this->player->id,$this->home,'solar_satellite','basic','2','Livraison civile',str_repeat('b',32)));
        $participant = $this->em->getRepository(\App\Entity\CombatParticipant::class)->findOneBy(['combatEvent'=>$event,'fleet'=>$local]);
        self::assertNotNull($participant);self::assertSame(2,$participant->arrivalRound);
        self::assertSame('defending',$local->status);self::assertNull($local->departureAt);self::assertSame(75.0,$local->storedFuel);
        self::assertSame(2,$this->state($this->home)['solar_satellite']['basic']['active']);
        self::assertFalse($service->ships($this->player,$this->player->id,$this->home,'solar_satellite','basic','2','Livraison civile',str_repeat('b',32)));
        self::assertSame(1,$this->em->getRepository(\App\Entity\CombatParticipant::class)->count(['combatEvent'=>$event,'fleet'=>$local]));
    }

    /** @return array{\App\Entity\CombatEvent, \App\Service\Combat\CombatEventService} */
    private function battle(int $arrivalDelay = 0): array
    {
        $this->player->currentPlayerUniverse->joinedAt = $this->clock->now()->modify('-30 days');$this->building($this->home,'mine_metal',5);
        $data = new RegistrationData();$data->email = 'civil-enemy@example.test';$data->username = 'CivilEnemy';$data->plainPassword = bin2hex(random_bytes(16)).'aA1!';
        $enemy = self::getContainer()->get(RegistrationService::class)->register($data);self::getContainer()->get(UniverseService::class)->select($enemy,$this->player->currentPlayerUniverse->universe->id);
        $enemy->currentPlayerUniverse->joinedAt = $this->clock->now()->modify('-30 days');
        $origin = $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$enemy->currentPlayerUniverse]);$this->building($origin,'mine_metal',5);
        $fleet = new Fleet();$fleet->playerUniverse = $enemy->currentPlayerUniverse;$fleet->homePlanet = $origin;$fleet->destination = $this->home;$fleet->destinationSystem = $this->home->solarSystem;
        $fleet->status = 'traveling';$fleet->mission = 'attack';$fleet->travelSeconds = 60;$fleet->reservedFuel = 20;$fleet->cargo = ['hydrogen'=>20];
        $fleet->arrivalAt = $this->clock->now()->modify('-'.$arrivalDelay.' seconds');$fleet->departureAt = $fleet->arrivalAt->modify('-60 seconds');$fleet->dispatchToken = 'civil-attack';$this->em->persist($fleet);
        $ship = new FleetShip();$ship->fleet = $fleet;$ship->slug = 'fighter';$ship->quantity = 3;$this->em->persist($ship);$this->em->flush();
        $combat = self::getContainer()->get(\App\Service\Combat\CombatEventService::class);self::assertTrue(self::getContainer()->get(\App\Manager\GameManager::class)->transaction(fn() => $combat->joinAttack($fleet)));
        $event = $this->em->getRepository(\App\Entity\CombatEvent::class)->findOneBy(['planet'=>$this->home,'status'=>'pending']);$event->seed = 42;$this->em->flush();
        return [$event,$combat];
    }

    public function testConcurrentSatelliteOrdersCannotReserveTheLastPlaceTwice(): void
    {
        $this->units($this->home,'solar_satellite',9);
        $codes = $this->race(['action'=>'add','group'=>'ships','slug'=>'solar_satellite']);sort($codes);
        self::assertSame([0,10], $codes);
        $this->em->clear();
        self::assertSame(1, $this->state($this->home)['solar_satellite']['basic']['reserved']);
        self::assertSame(1, (int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM queue_item WHERE item_slug = 'solar_satellite'"));
    }

    public function testConcurrentTransfersCannotReserveTheLastCollectorPlaceTwice(): void
    {
        $this->units($this->home,'hydrogen_collector',2);$this->building($this->target,'mine_hydrogen',1);$this->em->flush();
        $codes = $this->race(['action'=>'station','targetId'=>$this->target->id]);sort($codes);
        self::assertSame([0,10], $codes);$this->em->clear();
        self::assertSame(1, $this->state($this->target)['hydrogen_collector']['basic']['reserved']);
        self::assertSame(1, $this->state($this->home)['hydrogen_collector']['basic']['present']);
        self::assertSame(1, (int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM fleet WHERE mission = 'station' AND status = 'traveling'"));
    }

    /** Deux connexions attendent réellement le même verrou avant le départ commun. */
    private function race(array $action): array
    {
        $directory = sys_get_temp_dir().'/genesis-civil-race-'.bin2hex(random_bytes(8));mkdir($directory,0700);
        $connection = $this->em->getConnection();$processes = [];$connection->beginTransaction();
        $connection->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE',[$this->player->currentPlayerUniverse->id]);
        try {
            foreach (['a','b'] as $index=>$key) {
                $input = $action+['membershipId'=>$this->player->currentPlayerUniverse->id,'planetId'=>$this->home->id,
                    'now'=>$this->clock->now()->format(DATE_ATOM),'key'=>str_repeat($key,32),
                    'ready'=>$directory.'/'.$index,'barrier'=>$directory.'/go'];
                $process = new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/concurrent_queue.php',json_encode($input,JSON_THROW_ON_ERROR)]);
                $process->setTimeout(30);$process->start();$processes[] = $process;
            }
            $deadline = microtime(true)+15;
            do { $ready = glob($directory.'/[0-9]') ?: [];if (count($ready)===2) break;usleep(10000); } while(microtime(true)<$deadline);
            self::assertCount(2,$ready);
            $ids = array_map(static fn(string $file): int => (int)file_get_contents($file),$ready);touch($directory.'/go');
            do {
                $blocked = 0;
                foreach ($connection->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int)$row['Id'],$ids,true) && str_contains(strtoupper($row['Info'] ?? ''),'FOR UPDATE')) ++$blocked;
                }
                if ($blocked===2) break;usleep(10000);
            } while(microtime(true)<$deadline);
            self::assertSame(2,$blocked,'Les deux demandes doivent attendre simultanément.');$connection->commit();
            return array_map(static function($process): int {
                $code = $process->wait();self::assertContains($code,[0,10],$process->getErrorOutput().$process->getOutput());return $code;
            },$processes);
        } finally {
            if ($connection->isTransactionActive()) $connection->rollBack();
            foreach ($processes as $process) if ($process->isRunning()) $process->stop();
        }
    }

}
