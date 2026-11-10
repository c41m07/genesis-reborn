<?php
namespace App\Tests\Integration;
use App\Entity\{Player,PlayerUniverse,Universe,Planet,QueueItem,PlanetBuilding,PlanetResource,OutboxMessage,PlayerTechnology,Fleet,FleetShip,PlanetDefense,Notification};
use App\Service\Universe\{UniverseImporter,UniverseService,UniverseLifecycleService};
use App\Service\Notification\OutboxRelay;
use App\Message\UniverseClosureDue;
use App\MessageHandler\UniverseClosureDueHandler;
use App\Service\Player\RegistrationService;
use App\Service\Player\LeaderboardService;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\QueueService;
use App\Service\Colony\ResourceService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
final class GamePersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée : test refusé.');
        $tool=new SchemaTool($this->em);$meta=$this->em->getMetadataFactory()->getAllMetadata();$tool->dropSchema($meta);$tool->createSchema($meta);
        self::getContainer()->get(UniverseImporter::class)->import();
    }
    private function player(string $name='Commandant'): Player
    {
        $d=new RegistrationData();$d->email=strtolower($name).'@example.test';$d->username=$name;$d->plainPassword='A-test-password-2026';
        return self::getContainer()->get(RegistrationService::class)->register($d);
    }
    private function join(Player $p,string $slug='turbo-1'): Planet
    {
        $u=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);self::getContainer()->get(UniverseService::class)->select($p,$u->id);
        return $this->em->getRepository(Planet::class)->findOneBy(['owner'=>$p->currentPlayerUniverse]);
    }
    public function testImportIsIdempotentAndPreservesOwnership(): void
    {
        $p=$this->player();$planet=$this->join($p);$count=$this->em->getRepository(Planet::class)->count();
        self::assertSame(0,self::getContainer()->get(UniverseImporter::class)->import());
        self::assertSame($count,$this->em->getRepository(Planet::class)->count());self::assertSame($p->currentPlayerUniverse->id,$planet->owner->id);
    }
    public function testTemporaryUniverseClosesAsynchronouslyAndKeepsItsState(): void
    {
        $player=$this->player();$planet=$this->join($player);$membershipId=$player->currentPlayerUniverse->id;
        $universeId=$player->currentPlayerUniverse->universe->id;$planetId=$planet->id;
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$stock=$metal->currentStock;
        $lifecycle=self::getContainer()->get(UniverseLifecycleService::class);
        $lifecycle->setEndDate($universeId,new \DateTimeImmutable('+1 day'));
        $outbox=$this->em->getRepository(OutboxMessage::class)->findOneBy(['kind'=>'universe','targetId'=>$universeId]);
        self::assertNotNull($outbox);
        $relay=self::getContainer()->get(OutboxRelay::class);self::assertSame(1,$relay->run());
        $transport=self::getContainer()->get('messenger.transport.async');$sent=$transport->getSent();self::assertCount(1,$sent);
        $early=$sent[0]->getMessage();self::assertInstanceOf(UniverseClosureDue::class,$early);
        $handler=self::getContainer()->get(UniverseClosureDueHandler::class);$handler($early);
        self::assertSame('active',$this->em->find(Universe::class,$universeId)->status);

        $lifecycle->setEndDate($universeId,new \DateTimeImmutable('-1 hour'));
        $handler($early);
        self::assertSame('active',$this->em->find(Universe::class,$universeId)->status);
        self::assertSame(1,$relay->run());$sent=$transport->getSent();self::assertCount(2,$sent);
        $due=$sent[1]->getMessage();self::assertInstanceOf(UniverseClosureDue::class,$due);
        $handler($due);$handler($due);$this->em->clear();

        $closed=$this->em->find(Universe::class,$universeId);self::assertSame('closed',$closed->status);
        $preserved=$this->em->find(Planet::class,$planetId);self::assertSame($membershipId,$preserved->owner->id);
        self::assertEqualsWithDelta($stock,$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$preserved,'resourceType'=>'metal'])->currentStock,.000001);
    }
    public function testQueuedLevelDependenciesCancellationAndDuplicateRequest(): void
    {
        $p=$this->player();$planet=$this->join($p);$s=self::getContainer()->get(QueueService::class);$m=$p->currentPlayerUniverse;
        $s->add($m,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $stock=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal'])->currentStock;
        $s->add($m,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        self::assertSame(1,$this->em->getRepository(QueueItem::class)->count());
        self::assertSame($stock,$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal'])->currentStock);
        $s->add($m,$planet,'buildings','mine_metal',1,str_repeat('b',32));
        $s->add($m,$planet,'buildings','mine_nano',1,str_repeat('c',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);
        self::assertSame(2,$items[1]->itemLevel);self::assertSame($items[0],$items[1]->dependsOn);
        $s->cancel($m,$items[0]->id,str_repeat('d',32));
        $left=$this->em->getRepository(QueueItem::class)->findAll();self::assertCount(1,$left);self::assertSame('mine_nano',$left[0]->itemSlug);self::assertNotNull($left[0]->startedAt);
    }
    public function testCompletionIsIdempotentAndRejectsStaleOrEarlyMessages(): void
    {
        $p=$this->player();$planet=$this->join($p);$s=self::getContainer()->get(QueueService::class);
        $s->add($p->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy([]);$id=$item->id;$token=$item->dispatchToken;
        $s->complete($id,'stale-token');$s->complete($id,$token);self::assertSame(1,$this->em->getRepository(QueueItem::class)->count());
        $item->startedAt=new \DateTimeImmutable('-1 hour');$this->em->flush();$s->complete($id,$token);$s->complete($id,$token);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
        $b=$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$planet,'slug'=>'mine_metal']);self::assertSame(1,$b->level);
        self::assertGreaterThan(0,$this->em->getRepository(OutboxMessage::class)->count());
    }
    public function testForeignPlanetRejectedBeforeResourceConsumption(): void
    {
        $a=$this->player('Alpha');$planet=$this->join($a);$b=$this->player('Beta');$this->join($b);
        $this->expectException(\DomainException::class);
        self::getContainer()->get(QueueService::class)->add($b->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
    }
    public function testDirectQueueRequestCannotBuildLockedShip(): void
    {
        $player=$this->player();$planet=$this->join($player);$queue=self::getContainer()->get(QueueService::class);
        try {
            $queue->add($player->currentPlayerUniverse,$planet,'ships','cruiser',1,str_repeat('e',32));
            self::fail('Un vaisseau verrouillé ne doit pas pouvoir être commandé directement.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString('Déblocage initial',$exception->getMessage());
        }
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
    }
    public function testCrossUniverseOwnershipRejected(): void
    {
        $a=$this->player();$planet=$this->join($a,'omega-1');$this->join($a,'turbo-1');
        $this->expectException(\DomainException::class);
        self::getContainer()->get(QueueService::class)->add($a->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
    }
    public function testInsufficientResourcesRollBackQueueAndReceipt(): void
    {
        $p=$this->player();$planet=$this->join($p);
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $r) { $r->currentStock=0;$r->productionRate=0; }$this->em->flush();
        try { self::getContainer()->get(QueueService::class)->add($p->currentPlayerUniverse,$planet,'buildings','lab',1,str_repeat('a',32));self::fail('Ressources insuffisantes attendues.'); }
        catch(\DomainException $e) { self::assertStringContainsString('Ressources insuffisantes',$e->getMessage()); }
        $connection=$this->em->getConnection();self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM queue_item'));self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM action_receipt'));
    }
    private function ships(Planet $planet,array $composition): \App\Entity\Fleet
    {
        $f=new \App\Entity\Fleet();$f->homePlanet=$planet;$f->playerUniverse=$planet->owner;$this->em->persist($f);
        foreach($composition as $slug=>$quantity) { $s=new \App\Entity\FleetShip();$s->fleet=$f;$s->slug=$slug;$s->quantity=$quantity;$this->em->persist($s); }
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $r) $r->productionRate=0;
        $this->em->flush();return $f;
    }
    private function land(\App\Entity\Fleet $fleet,\App\Service\Fleet\FleetService $service): void
    {
        $fleet->arrivalAt=new \DateTimeImmutable('-1 second');$this->em->flush();$service->arrive($fleet->id,$fleet->dispatchToken);
    }
    public function testSoloProbeReservesFuelAndDiscoversWholeSystemExactlyOnce(): void
    {
        $clock = new \Symfony\Component\Clock\MockClock('2026-11-02T00:00:00Z');self::getContainer()->set('clock',$clock);
        $p=$this->player();$planet=$this->join($p);$this->ships($planet,['probe'=>1]);$m=$p->currentPlayerUniverse;
        $systems=$this->em->getRepository(\App\Entity\SolarSystem::class)->findBy(['galaxy'=>$planet->solarSystem->galaxy],['id'=>'ASC']);
        $unknownSystems=array_values(array_filter($systems,fn($system)=>!$this->em->getRepository(\App\Entity\PlayerDiscovery::class)->count(['playerUniverse'=>$m,'solarSystem'=>$system])));
        self::assertNotEmpty($unknownSystems);$target=$unknownSystems[0];
        $eventService=self::getContainer()->get(\App\Service\Boost\BoostEventService::class);$eventService->tick();
        $service=self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $quote=$service->quote($m,$planet,'explore',$target->id,['probe'=>1],.5);
        self::assertLessThanOrEqual($quote['fuel_capacity'],$quote['total_fuel']);self::assertSame(0,$quote['cargo_capacity']);
        $hydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'hydrogen']);$before=$hydrogen->currentStock;
        $service->send($m,$planet,'explore',$target->id,['probe'=>1],.5,[],str_repeat('e',32));
        $service->send($m,$planet,'explore',$target->id,['probe'=>1],.5,[],str_repeat('e',32));
        self::assertEqualsWithDelta($before-$quote['total_fuel'],$hydrogen->currentStock,.000001);
        $fleet=$this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['status'=>'traveling']);$old=$fleet->dispatchToken;
        $clock->sleep($fleet->travelSeconds + 1);$service->arrive($fleet->id,$fleet->dispatchToken);self::assertSame('completed',$fleet->status);
        $service->arrive($fleet->id,$old);
        self::assertSame(1,$this->em->getRepository(\App\Entity\PlayerDiscovery::class)->count(['playerUniverse'=>$m,'solarSystem'=>$target]));
        self::assertSame(1,$this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['playerUniverse'=>$m,'sourceType'=>'risk_mission']));
        $explorationEvent=$this->em->getRepository(\App\Entity\BoostEvent::class)->findOneBy(['universe'=>$m->universe,'templateKey'=>'exploration','status'=>'active']);
        self::assertSame(1,$this->em->getRepository(\App\Entity\BoostEventContribution::class)->count(['event'=>$explorationEvent,'playerUniverse'=>$m]));
        self::assertSame(0,$this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'probe'])->quantity);
        self::assertNull($fleet->arrivalAt);
    }
    public function testTransportDeliversFreightWithoutSpendingReservedReturnFuelTwice(): void
    {
        $clock=new \Symfony\Component\Clock\MockClock('2026-11-16T00:00:00Z');self::getContainer()->set('clock',$clock);
        $p=$this->player();$origin=$this->join($p);$this->ships($origin,['transport'=>1]);$m=$p->currentPlayerUniverse;
        $target=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$target->owner=$m;
        self::getContainer()->get(ResourceService::class)->initialize($target);$this->em->flush();
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$target]) as $r) $r->productionRate=0;$this->em->flush();
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$target]) as $r)$r->currentStock=1000;
        $this->em->flush();
        $resource=fn($planet,$slug)=>$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>$slug]);
        $targetMetal=$resource($target,'metal')->currentStock;$targetHydrogen=$resource($target,'hydrogen')->currentStock;
        $sourceHydrogen=$resource($origin,'hydrogen')->currentStock;
        self::getContainer()->get(\App\Service\Boost\BoostEventService::class)->tick();
        $service=self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $q=$service->quote($m,$origin,'transport',$target->id,['transport'=>1],.5,['metal'=>123,'hydrogen'=>67]);
        $service->send($m,$origin,'transport',$target->id,['transport'=>1],.5,['metal'=>123,'hydrogen'=>67],str_repeat('f',32));
        self::assertEqualsWithDelta($sourceHydrogen-$q['total_fuel']-67,$resource($origin,'hydrogen')->currentStock,.000001);
        $fleet=$this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['status'=>'traveling']);$old=$fleet->dispatchToken;
        $clock->sleep($fleet->travelSeconds + 1);$targetMetalBeforeArrival=self::getContainer()->get(ResourceService::class)->snapshot($target)['metal']['stock'];$targetHydrogenBeforeArrival=self::getContainer()->get(ResourceService::class)->snapshot($target)['hydrogen']['stock'];$service->arrive($fleet->id,$old);
        $transportEvent=$this->em->getRepository(\App\Entity\BoostEvent::class)->findOneBy(['universe'=>$m->universe,'templateKey'=>'transport','status'=>'active']);
        self::assertSame(1,$this->em->getRepository(\App\Entity\BoostEventContribution::class)->count(['event'=>$transportEvent,'playerUniverse'=>$m]));
        self::assertEqualsWithDelta($targetMetalBeforeArrival+123,$resource($target,'metal')->currentStock,.000001);
        self::assertEqualsWithDelta($targetHydrogenBeforeArrival+67,$resource($target,'hydrogen')->currentStock,.000001);
        self::assertEqualsWithDelta(190.0,$this->em->getRepository(\App\Entity\BoostEventContribution::class)->findOneBy(['event'=>$transportEvent,'playerUniverse'=>$m])->value,.000001);
        self::assertEqualsWithDelta($q['one_way_fuel'],$fleet->reservedFuel,.000001);
        $sourceBeforeLanding=self::getContainer()->get(ResourceService::class)->snapshot($origin)['hydrogen']['stock'];$returnHydrogen=max(0.0,($fleet->cargo['hydrogen']??0)-$fleet->reservedFuel);$this->land($fleet,$service);self::assertSame('completed',$fleet->status);
        self::assertEqualsWithDelta($sourceBeforeLanding+$returnHydrogen,$resource($origin,'hydrogen')->currentStock,.000001);
    }
    public function testDedicatedFuelTankDoesNotProvideFreightCapacity(): void
    {
        $p=$this->player();$origin=$this->join($p);$this->ships($origin,['probe'=>1]);$m=$p->currentPlayerUniverse;
        $target=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$target->owner=$m;$this->em->flush();
        $quote=self::getContainer()->get(\App\Service\Fleet\FleetService::class)->quote($m,$origin,'transport',$target->id,['probe'=>1],1,['metal'=>1]);
        self::assertSame(0.0, (float) $quote['freight_capacity']);
        self::assertSame([], $quote['freight']);
        self::assertTrue($quote['adjusted']);
    }
    public static function colonyShipKits(): array
    {
        return [
            'basic'=>[['basic'=>1],1.0,'basic'],
            'intermediate'=>[['intermediate'=>1],1.25,'intermediate'],
            'superior'=>[['superior'=>1],1.5625,'superior'],
            'mixed'=>[['basic'=>1,'superior'=>1],1.0,'basic'],
        ];
    }
    #[\PHPUnit\Framework\Attributes\DataProvider('colonyShipKits')]
    public function testSoloColonyShipCanColonizeKnownPlanet(array $ranks,float $factor,string $consumedTier): void
    {
        $p=$this->player();$origin=$this->join($p);$idle=$this->ships($origin,[]);foreach($ranks as $tier=>$quantity){$ship=new FleetShip();$ship->fleet=$idle;$ship->slug='colony_ship';$ship->tier=$tier;$ship->quantity=$quantity;$this->em->persist($ship);}$m=$p->currentPlayerUniverse;$colonial_expansion=new PlayerTechnology();$colonial_expansion->playerUniverse=$m;$colonial_expansion->slug='colonial_expansion';$colonial_expansion->level=1;$this->em->persist($colonial_expansion);$this->em->flush();
        $target=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$target->npcPlanet=false;
        $service=self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $service->send($m,$origin,'colonize',$target->id,['colony_ship'=>$ranks],1,[],str_repeat('a',32));
        $fleet=$this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['status'=>'traveling']);$this->land($fleet,$service);
        self::assertSame($m->id,$target->owner->id);
        $kit=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class)->rules()['colony_initial_stock'];
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$target]) as $resource) self::assertEquals($kit[$resource->resourceType]*$factor,$resource->currentStock);
        if(isset($ranks['superior']) && $consumedTier!=='superior') self::assertSame(1,$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship','tier'=>'superior'])->quantity);
        self::assertSame(5,$this->em->getRepository(PlanetResource::class)->count(['planet'=>$target]));
        self::assertSame(0,$this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship','tier'=>$consumedTier])->quantity);
    }

    private function clock(): \Symfony\Component\Clock\MockClock
    {
        $clock=new \Symfony\Component\Clock\MockClock('2026-10-21T12:00:00Z');
        self::getContainer()->set('clock',$clock);return $clock;
    }
    private function secondPlanet(Player $player): Planet
    {
        $origin=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$planet->owner=$player->currentPlayerUniverse;
        self::getContainer()->get(ResourceService::class)->initialize($planet);$this->em->flush();return $planet;
    }
    public function testDelayedMineCompletionAndSpendingUseActualEventChronology(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p);$q=self::getContainer()->get(QueueService::class);$r=self::getContainer()->get(ResourceService::class);
        $q->add($p->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$planet]);$duration=$item->durationSeconds;$completedId=$item->id;$completedToken=$item->dispatchToken;
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=10000;$old=$metal->productionRate;$this->em->flush();
        $clock->sleep(3600);
        // A spend before the worker returns must first catch up the already-completed mine.
        $q->add($p->currentPlayerUniverse,$planet,'buildings','mine_nano',1,str_repeat('b',32));
        $afterSpend=$r->snapshot($planet)['metal']['stock'];
        self::assertGreaterThan(9200,$afterSpend);
        self::assertSame(1,$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$planet,'slug'=>'mine_metal'])->level);
        $before=$r->snapshot($planet)['metal']['stock'];$q->complete($completedId,$completedToken);
        self::assertEqualsWithDelta($before,$r->snapshot($planet)['metal']['stock'],.000001);
    }
    public function testCatchupRespectsStorageCapBeforeStorageUpgrade(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p);$q=self::getContainer()->get(QueueService::class);$r=self::getContainer()->get(ResourceService::class);
        $q->add($p->currentPlayerUniverse,$planet,'buildings','storage_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$planet]);$duration=$item->durationSeconds;
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=10000;$rate=$metal->productionRate;$this->em->flush();
        $clock->sleep(3600);$q->synchronize($p->currentPlayerUniverse);
        self::assertEqualsWithDelta(10000+$rate*(3600-$duration),$r->snapshot($planet)['metal']['stock'],.000001);
        self::assertSame(110000.0,$r->snapshot($planet)['metal']['capacity']);
    }
    public function testResearchLevelsAndCancellationAreSharedAcrossColonies(): void
    {
        [$clock,$player,$one,$two,$service]=$this->researchColonies();$membership=$player->currentPlayerUniverse;
        $service->add($membership,$one,'technologies','engineering_foundations',1,str_repeat('a',32));
        $service->add($membership,$two,'technologies','engineering_foundations',1,str_repeat('b',32));
        $service->add($membership,$two,'technologies','tactical_intelligence',1,str_repeat('c',32));
        [$first,$dependent,$next]=$this->em->getRepository(QueueItem::class)->findBy(['playerUniverse'=>$membership],['id'=>'ASC']);
        self::assertSame(2,$dependent->itemLevel);self::assertSame($first,$dependent->dependsOn);self::assertNull($dependent->startedAt);self::assertNull($next->startedAt);
        $resources=self::getContainer()->get(ResourceService::class);
        $beforeOne=$resources->snapshot($one)['metal']['stock'];$beforeTwo=$resources->snapshot($two)['metal']['stock'];
        $refundOne=$first->paidCosts['metal']*.25;$refundTwo=$dependent->paidCosts['metal']*.25;$id=$first->id;$token=$first->dispatchToken;
        $service->cancel($membership,$id,str_repeat('d',32));
        self::assertSame(1,$this->em->getRepository(QueueItem::class)->count(['playerUniverse'=>$membership]));
        self::assertEquals($clock->now(),$next->startedAt);self::assertNull($one->researchQueueStartedAt);self::assertEquals($clock->now(),$two->researchQueueStartedAt);
        self::assertEqualsWithDelta($beforeOne+$refundOne,$resources->snapshot($one)['metal']['stock'],.000001);
        self::assertEqualsWithDelta($beforeTwo+$refundTwo,$resources->snapshot($two)['metal']['stock'],.000001);
        $service->complete($id,$token);
        self::assertSame(1,$this->em->getRepository(QueueItem::class)->count(['playerUniverse'=>$membership]));
    }

    public function testResearchFromTwoColoniesRunsSuccessively(): void
    {
        [$clock,$player,$one,$two,$service]=$this->researchColonies();$membership=$player->currentPlayerUniverse;
        $service->add($membership,$one,'technologies','engineering_foundations',1,str_repeat('a',32));
        $service->add($membership,$two,'technologies','tactical_intelligence',1,str_repeat('b',32));
        [$first,$second]=$this->em->getRepository(QueueItem::class)->findBy(['playerUniverse'=>$membership],['id'=>'ASC']);
        self::assertNotNull($first->startedAt);self::assertNull($second->startedAt);$finish=$first->finishAt();
        $clock->sleep($first->durationSeconds);$service->synchronize($membership);
        self::assertEquals($finish,$second->startedAt);self::assertSame(1,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$membership]));
        $clock->sleep(60);$service->synchronize($membership);
        self::assertSame(2,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$membership]));
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
    }

    public function testShipyardAndDefensesAreDeliveredExactlyOnce(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p);
        foreach(['shipyard','fortress'] as $slug) { $b=new PlanetBuilding();$b->planet=$planet;$b->slug=$slug;$b->level=1;$this->em->persist($b); }$this->em->flush();
        $q=self::getContainer()->get(QueueService::class);$q->add($p->currentPlayerUniverse,$planet,'ships','fighter',2,str_repeat('a',32));$q->add($p->currentPlayerUniverse,$planet,'defenses','ground_turret_anti_light',3,str_repeat('b',32));
        $clock->sleep(3600);$q->synchronize($p->currentPlayerUniverse);$q->synchronize($p->currentPlayerUniverse);
        self::assertSame(2,$this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['slug'=>'fighter'])->quantity);
        self::assertSame(3,$this->em->getRepository(\App\Entity\PlanetDefense::class)->findOneBy(['planet'=>$planet])->quantity);
    }
    public function testDefenseRanksPersistAndCompleteIndependently(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p);
        $fortress=new PlanetBuilding();$fortress->planet=$planet;$fortress->slug='fortress';$fortress->level=1;$this->em->persist($fortress);
        $advancedFortress=new PlanetBuilding();$advancedFortress->planet=$planet;$advancedFortress->slug='fortress';$advancedFortress->tier='intermediate';$advancedFortress->level=10;$this->em->persist($advancedFortress);
        $technology=new PlayerTechnology();$technology->playerUniverse=$p->currentPlayerUniverse;$technology->slug='weapon_calibration';$technology->tier='intermediate';$technology->level=10;$this->em->persist($technology);$this->em->flush();
        $queue=self::getContainer()->get(QueueService::class);$queue->add($p->currentPlayerUniverse,$planet,'defenses','ground_turret_anti_light',2,str_repeat('a',32),'basic');$queue->add($p->currentPlayerUniverse,$planet,'defenses','ground_turret_anti_light',3,str_repeat('b',32),'superior');
        $clock->sleep(3600);$queue->synchronize($p->currentPlayerUniverse);
        $rows=$this->em->getRepository(\App\Entity\PlanetDefense::class)->findBy(['planet'=>$planet,'slug'=>'ground_turret_anti_light'],['tier'=>'ASC']);
        self::assertCount(2,$rows);self::assertSame('basic',$rows[0]->tier);self::assertSame(2,$rows[0]->quantity);self::assertSame('superior',$rows[1]->tier);self::assertSame(3,$rows[1]->quantity);
    }

    public function testBoostRedispatchesOnlyHeadAndIgnoresOldMessage(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p,'omega-1');$q=self::getContainer()->get(QueueService::class);
        $q->add($p->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));$q->add($p->currentPlayerUniverse,$planet,'buildings','mine_nano',1,str_repeat('b',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);$head=$items[0];$next=$items[1];$token=$head->dispatchToken;$nextDuration=$next->durationSeconds;
        // Durée contrôlée pour tester le redispatch, indépendamment de l’équilibrage des mines.
        $head->durationSeconds=120;$next->durationSeconds=120;$nextDuration=120;$this->em->flush();
        $clock->sleep(30);$q->boost($p->currentPlayerUniverse,$head->id,2,str_repeat('c',32));$newToken=$head->dispatchToken;
        self::assertSame(75,$head->durationSeconds);self::assertNotSame($token,$newToken);self::assertSame($nextDuration,$next->durationSeconds);
        $q->boost($p->currentPlayerUniverse,$head->id,2,str_repeat('c',32));self::assertSame(75,$head->durationSeconds);
        $clock->sleep(70);$q->complete($head->id,$token);self::assertSame(2,$this->em->getRepository(QueueItem::class)->count());
        $q->complete($head->id,$newToken);self::assertSame(1,$this->em->getRepository(QueueItem::class)->count());self::assertSame($nextDuration,$next->durationSeconds);
    }
    public function testMercureFailureDoesNotBlockGameplayOutbox(): void
    {
        $p=$this->player();$planet=$this->join($p);$m=$p->currentPlayerUniverse;
        self::getContainer()->get(\App\Service\Notification\NotificationService::class)->enqueue($m,'Test privé');
        $q=self::getContainer()->get(QueueService::class);$q->add($m,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $publisher=new class implements \App\Integration\Mercure\PublisherInterface { public function publish(string $topic,array $data,string $id):void { throw new \RuntimeException('Hub indisponible'); } };
        $relay=new \App\Service\Notification\OutboxRelay(self::getContainer()->get(\App\Manager\GameManager::class),self::getContainer()->get(\App\Repository\GameRepository::class),self::getContainer()->get(\Symfony\Component\Messenger\MessageBusInterface::class),$publisher,self::getContainer()->get(\Psr\Clock\ClockInterface::class));
        self::assertSame(1,$relay->run(false));
        self::assertCount(1,self::getContainer()->get('messenger.transport.async')->getSent());
        try { $relay->run(true);self::fail('Panne attendue'); }catch(\RuntimeException $e){self::assertSame('Hub indisponible',$e->getMessage());}
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind='notification'"));
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind='queue'"));
    }

    public function testCombatRelayPublishesDelayedEventInsteadOfHandlingItImmediately(): void
    {
        $clock=$this->clock();$message=new OutboxMessage();$message->kind='combat';$message->targetId=987654;
        $message->token=str_repeat('b',32);$message->dueAt=$clock->now()->modify('+60 seconds');
        $this->em->persist($message);$this->em->flush();
        self::assertSame(1,self::getContainer()->get(OutboxRelay::class)->run());
        $sent=self::getContainer()->get('messenger.transport.async')->getSent();
        self::assertCount(1,$sent);
        self::assertInstanceOf(\App\Message\CombatEventDue::class,$sent[0]->getMessage());
        self::assertSame(987654,$sent[0]->getMessage()->combatEventId);
        self::assertSame(60000,$sent[0]->last(\Symfony\Component\Messenger\Stamp\DelayStamp::class)->getDelay());
    }

    public function testAdminListsTwentyOldestDeadlinesIncludingUnsupportedKinds(): void
    {
        $clock=$this->clock();$ids=[];
        for($index=0;$index<21;++$index) {
            $message=new OutboxMessage();$message->kind='diagnostic_test';$message->targetId=900000+$index;
            $message->token=bin2hex(random_bytes(16));$message->dueAt=new \DateTimeImmutable('2020-01-01T00:00:00Z');
            $this->em->persist($message);$this->em->flush();$ids[]=$message->id;
        }
        $snapshot=self::getContainer()->get(\App\Service\Admin\AdminOperationsService::class)->snapshot();
        self::assertTrue($snapshot['outbox_available']);
        self::assertSame(21,$snapshot['outbox']['other']);
        self::assertSame(array_slice($ids,0,20),array_column($snapshot['outbox_pending'],'id'));
        self::assertSame('UTC',$snapshot['outbox_pending'][0]['due_at']->getTimezone()->getName());
        self::assertSame($clock->now()->getTimestamp()-(new \DateTimeImmutable('2020-01-01T00:00:00Z'))->getTimestamp(),$snapshot['outbox_pending'][0]['overdue_seconds']);
    }

    public function testOutboxRestartsAfterPublishFailureAndDuplicateDeliveryIsHarmless(): void
    {
        $clock=$this->clock();$player=$this->player('RelayRestart');$planet=$this->join($player);
        self::getContainer()->get(QueueService::class)->add($player->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy([]);$itemId=$item->id;$planetId=$planet->id;
        $clock->sleep($item->durationSeconds+1);$restartAt=$clock->now();
        $bus=self::getContainer()->get(\Symfony\Component\Messenger\MessageBusInterface::class);
        $crashingBus=new class($bus) implements \Symfony\Component\Messenger\MessageBusInterface {
            public function __construct(private \Symfony\Component\Messenger\MessageBusInterface $inner) {}
            public function dispatch(object $message,array $stamps=[]): \Symfony\Component\Messenger\Envelope
            {
                $this->inner->dispatch($message,$stamps);
                throw new \RuntimeException('Interruption après publication');
            }
        };
        $relay=new OutboxRelay(self::getContainer()->get(\App\Manager\GameManager::class),self::getContainer()->get(\App\Repository\GameRepository::class),$crashingBus,self::getContainer()->get(\App\Integration\Mercure\PublisherInterface::class),$clock);
        try { $relay->run();self::fail('Interruption attendue'); } catch(\RuntimeException $error) { self::assertSame('Interruption après publication',$error->getMessage()); }
        self::assertFalse($this->em->isOpen());
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind='queue'"));
        $sent=self::getContainer()->get('messenger.transport.async')->getSent();self::assertCount(1,$sent);$first=$sent[0]->getMessage();
        self::ensureKernelShutdown();self::bootKernel();
        self::getContainer()->set('clock',new \Symfony\Component\Clock\MockClock($restartAt));
        $this->em=self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame(1,self::getContainer()->get(OutboxRelay::class)->run());
        $replayed=self::getContainer()->get('messenger.transport.async')->getSent();self::assertCount(1,$replayed);
        self::assertEquals($first,$replayed[0]->getMessage());
        self::assertSame(0,(int)$this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind='queue'"));
        $handler=self::getContainer()->get(\App\MessageHandler\QueueItemFinishedHandler::class);
        $handler($first);$handler($replayed[0]->getMessage());
        self::assertNull($this->em->find(QueueItem::class,$itemId));
        self::assertSame(1,$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$planetId,'slug'=>'mine_metal'])->level);
    }

    public function testPrivateMessagesStayInUniverseAndBecomeReadForRecipient(): void
    {
        $sender = $this->player('PrivateSender');
        $this->join($sender, 'omega-1');
        $recipient = $this->player('PrivateRecipient');
        $this->join($recipient, 'omega-1');
        $outsider = $this->player('PrivateOutsider');
        $this->join($outsider, 'turbo-1');

        $service = self::getContainer()->get(\App\Service\Player\SocialMessageService::class);
        $service->sendPrivate($sender->currentPlayerUniverse, $recipient->currentPlayerUniverse->id, 'Message de test');
        self::assertSame(1, $service->unreadPrivateCount($recipient->currentPlayerUniverse));
        self::assertSame(0, $service->unreadPrivateCount($sender->currentPlayerUniverse));

        $thread = $service->privateConversation($recipient->currentPlayerUniverse, $sender->currentPlayerUniverse->id);
        self::assertCount(1, $thread);
        self::assertSame('Message de test', $thread[0]->content);
        self::assertNotNull($thread[0]->readAt);
        self::assertSame(0, $service->unreadPrivateCount($recipient->currentPlayerUniverse));

        $this->expectException(\DomainException::class);
        $service->sendPrivate($sender->currentPlayerUniverse, $outsider->currentPlayerUniverse->id, 'Inter-univers interdit');
    }

    public function testGuildChatRequiresMembershipAndNotifiesOtherMembers(): void
    {
        $leader = $this->player('ChatLeader');
        $this->join($leader, 'omega-1');
        $member = $this->player('ChatMember');
        $this->join($member, 'omega-1');
        $outsider = $this->player('ChatOutsider');
        $this->join($outsider, 'omega-1');
        $diplomacy = self::getContainer()->get(\App\Service\Player\DiplomacyService::class);
        $relations = self::getContainer()->get(\App\Service\Player\RelationResolver::class);
        $diplomacy->create($leader->currentPlayerUniverse, 'Canal privé');
        $guild = $relations->guild($leader->currentPlayerUniverse);
        $diplomacy->join($member->currentPlayerUniverse, $guild->id);

        $messages = self::getContainer()->get(\App\Service\Player\SocialMessageService::class);
        $messages->sendGuild($member->currentPlayerUniverse, 'Message réservé aux membres');
        self::assertSame(1, $messages->unreadGuildCount($leader->currentPlayerUniverse));
        self::assertSame(0, $messages->unreadGuildCount($member->currentPlayerUniverse));
        $thread = $messages->guildConversation($leader->currentPlayerUniverse, $guild);
        self::assertSame(0, $messages->unreadGuildCount($leader->currentPlayerUniverse));
        self::assertCount(1, $thread);
        self::assertSame($member->currentPlayerUniverse->id, $thread[0]->sender->id);
        self::assertNotEmpty($this->em->getRepository(\App\Entity\Notification::class)->findBy(['recipient' => $leader->currentPlayerUniverse]));
        self::getContainer()->get(\App\Service\Admin\AdminService::class)->reportMessage($leader->currentPlayerUniverse, $thread[0]->id, 'Propos inappropriés');
        self::assertSame(1, $this->em->getRepository(\App\Entity\MessageReport::class)->count());

        $this->expectException(\DomainException::class);
        $messages->guildConversation($outsider->currentPlayerUniverse, $guild);
    }


    public function testPrivateMessageModerationRequiresReportAndAuditsRedaction(): void
    {
        $sender = $this->player('ReportSender');
        $this->join($sender, 'omega-1');
        $recipient = $this->player('ReportRecipient');
        $this->join($recipient, 'omega-1');
        $admin = $this->player('ReportAdmin');
        $admin->roles = ['ROLE_ADMIN'];
        $this->em->flush();

        $messages = self::getContainer()->get(\App\Service\Player\SocialMessageService::class);
        $messages->sendPrivate($sender->currentPlayerUniverse, $recipient->currentPlayerUniverse->id, 'Contenu signalé');
        $message = $this->em->getRepository(\App\Entity\SocialMessage::class)->findOneBy([]);
        $moderation = self::getContainer()->get(\App\Service\Admin\AdminService::class);
        $moderation->reportMessage($recipient->currentPlayerUniverse, $message->id, 'Propos abusifs');
        $report = $this->em->getRepository(\App\Entity\MessageReport::class)->findOneBy([]);
        self::assertSame('open', $report->status);
        $moderation->recordReportViews($admin, [$report]);
        self::assertSame('message_report_view', $this->em->getRepository(\App\Entity\AdminAuditLog::class)->findOneBy(['action' => 'message_report_view'])->action);
        $moderation->reviewReport($admin, $report->id, 'redact');
        self::assertSame('[Message masqué après modération]', $message->content);
        self::assertNotNull($message->moderatedAt);
        self::assertSame('actioned', $report->status);
        self::assertSame(2, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->count());
    }

    public function testAdministrationSuspensionAndUniverseScheduleAreAudited(): void
    {
        $admin = $this->player('ControlAdmin');
        $admin->roles = ['ROLE_ADMIN'];
        $target = $this->player('SuspendedPlayer');
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        $this->em->flush();
        $service = self::getContainer()->get(\App\Service\Admin\AdminService::class);
        $service->setPlayerSuspended($admin, $target->id, true);
        self::assertNotNull($target->lockedAt);
        try {
            self::getContainer()->get(\App\Security\PlayerUserChecker::class)->checkPreAuth($target);
            self::fail('Un compte suspendu doit refuser l’authentification.');
        } catch (\Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException) {
            self::assertTrue(true);
        }
        $service->setPlayerSuspended($admin, $target->id, false);
        self::assertNull($target->lockedAt);
        $openAt = new \DateTimeImmutable('+2 hours');
        $service->setUniverseSchedule($admin, $universe->id, $openAt, new \DateTimeImmutable('+1 day'));
        self::assertEquals($openAt, $universe->startsAt);
        self::assertNotNull($universe->endsAt);
        self::assertSame(3, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->count());
        $service->setUniverseSettings($admin, $universe->id, ['multipliers' => ['building_time' => '1.5', 'research_time' => '1', 'ship_build_time' => '1', 'resource_production' => '2', 'travel_time' => '1'], 'limits' => ['max_planets' => '8', 'max_fleet_size' => '150'], 'features' => ['guilds_enabled' => '1', 'planet_conquest' => '0']]);
        $effective = self::getContainer()->get(GameCatalog::class)->forUniverse($universe);
        self::assertEqualsWithDelta(1 / 1.5, $effective['multipliers']['building_time'], 0.000001);
        self::assertSame(8, $effective['limits']['max_planets']);
        self::assertFalse($effective['features']['planet_conquest']);
        self::assertSame(4, $this->em->getRepository(\App\Entity\AdminAuditLog::class)->count());
    }

    public function testDirectAllianceRequiresMutualConsentAndIsSymmetric(): void
    {
        $a=$this->player('Alpha');$this->join($a);$b=$this->player('Beta');$this->join($b);
        $service=self::getContainer()->get(\App\Service\Player\DiplomacyService::class);$relations=self::getContainer()->get(\App\Service\Player\RelationResolver::class);
        $ma=$a->currentPlayerUniverse;$mb=$b->currentPlayerUniverse;
        $service->alliance($ma,$mb->id,'propose');self::assertFalse($relations->allied($ma,$mb));
        $service->alliance($mb,$ma->id,'accept');self::assertTrue($relations->allied($ma,$mb));self::assertTrue($relations->allied($mb,$ma));
        $service->alliance($mb,$ma->id,'break');self::assertFalse($relations->allied($ma,$mb));
    }
    public function testGuildEnemiesBreakDirectContractsAndAllianceNeedsTwoLeaders(): void
    {
        $a=$this->player('Alpha');$this->join($a,'omega-1');$b=$this->player('Beta');$this->join($b,'omega-1');$c=$this->player('Gamma');$this->join($c,'omega-1');$d=$this->player('Delta');$this->join($d,'omega-1');
        $service=self::getContainer()->get(\App\Service\Player\DiplomacyService::class);$relations=self::getContainer()->get(\App\Service\Player\RelationResolver::class);
        $ma=$a->currentPlayerUniverse;$mb=$b->currentPlayerUniverse;$mc=$c->currentPlayerUniverse;$md=$d->currentPlayerUniverse;
        $service->create($ma,'Guilde Alpha');$service->create($mb,'Guilde Beta');$ga=$relations->guild($ma);$gb=$relations->guild($mb);$service->join($mc,$ga->id);$service->join($md,$gb->id);
        $service->relation($ma,$gb->id,'allies');self::assertFalse($relations->allied($mc,$md));
        $service->relation($mb,$ga->id,'allies');self::assertTrue($relations->allied($mc,$md));
        $service->alliance($ma,$mb->id,'propose');$service->alliance($mb,$ma->id,'accept');
        $service->relation($ma,$gb->id,'enemies');self::assertFalse($relations->allied($ma,$mb));self::assertNull($relations->contract($ma,$mb));self::assertSame('enemies',$relations->guildStatus($gb,$ga));self::assertTrue($relations->allied($ma,$mc));
    }
    public function testGuildLeadershipCanTransferThenGuildCanBeDissolved(): void
    {
        $a=$this->player('Alpha');$this->join($a,'omega-1');$b=$this->player('Beta');$this->join($b,'omega-1');
        $service=self::getContainer()->get(\App\Service\Player\DiplomacyService::class);$relations=self::getContainer()->get(\App\Service\Player\RelationResolver::class);
        $ma=$a->currentPlayerUniverse;$mb=$b->currentPlayerUniverse;$service->create($ma,'Guilde Alpha');$g=$relations->guild($ma);$service->join($mb,$g->id);
        $service->transfer($ma,$mb->id);self::assertSame($mb->id,$g->leader->id);$service->leave($ma);self::assertNull($relations->guild($ma));
        $service->disband($mb);self::assertSame(0,$this->em->getRepository(\App\Entity\Guild::class)->count());self::assertSame(0,$this->em->getRepository(\App\Entity\GuildMember::class)->count());
    }
    public function testProposerCannotAcceptOwnAlliance(): void
    {
        $a=$this->player('Alpha');$this->join($a);$b=$this->player('Beta');$this->join($b);$service=self::getContainer()->get(\App\Service\Player\DiplomacyService::class);
        $service->alliance($a->currentPlayerUniverse,$b->currentPlayerUniverse->id,'propose');$this->expectException(\DomainException::class);$service->alliance($a->currentPlayerUniverse,$b->currentPlayerUniverse->id,'accept');
    }
    public function testAllianceCannotCrossUniverses(): void
    {
        $a=$this->player('Alpha');$this->join($a);$b=$this->player('Beta');$this->join($b,'omega-1');$this->expectException(\DomainException::class);
        self::getContainer()->get(\App\Service\Player\DiplomacyService::class)->alliance($a->currentPlayerUniverse,$b->currentPlayerUniverse->id,'propose');
    }
    public function testGuildFeatureDisabledAndLeaderPermissionAreEnforced(): void
    {
        $a=$this->player('Alpha');$this->join($a);$this->expectException(\DomainException::class);self::getContainer()->get(\App\Service\Player\DiplomacyService::class)->create($a->currentPlayerUniverse,'Pas de guilde');
    }
    public function testNonLeaderCannotDeclareGuildEnemy(): void
    {
        $a=$this->player('Alpha');$this->join($a,'omega-1');$b=$this->player('Beta');$this->join($b,'omega-1');$c=$this->player('Gamma');$this->join($c,'omega-1');
        $service=self::getContainer()->get(\App\Service\Player\DiplomacyService::class);$relations=self::getContainer()->get(\App\Service\Player\RelationResolver::class);
        $service->create($a->currentPlayerUniverse,'Guilde Alpha');$service->create($b->currentPlayerUniverse,'Guilde Beta');$service->join($c->currentPlayerUniverse,$relations->guild($a->currentPlayerUniverse)->id);
        $this->expectException(\DomainException::class);$service->relation($c->currentPlayerUniverse,$relations->guild($b->currentPlayerUniverse)->id,'enemies');
    }
    public function testLeaderboardCountsOwnedAssetsAndRemovesPointsAfterFleetLoss(): void
    {
        $player = $this->player('Ranked');
        $planet = $this->join($player);
        $noScore = $this->player('NoScore');
        $this->join($noScore);
        $building = new PlanetBuilding();
        $building->planet = $planet;
        $building->slug = 'mine_metal';
        $building->level = 2;
        $this->em->persist($building);
        $technology = new PlayerTechnology();
        $technology->playerUniverse = $player->currentPlayerUniverse;
        $technology->slug = 'weapon_calibration';
        $technology->level = 1;
        $this->em->persist($technology);

        $fleet = new Fleet();
        $fleet->playerUniverse = $player->currentPlayerUniverse;
        $fleet->homePlanet = $planet;
        $fleet->status = 'idle';
        $fleet->mission = 'idle';
        $this->em->persist($fleet);
        $ship = new FleetShip();
        $ship->fleet = $fleet;
        $ship->slug = 'fighter';
        $ship->quantity = 3;
        $this->em->persist($ship);
        $defense = new PlanetDefense();
        $defense->planet = $planet;
        $defense->slug = 'ground_turret_anti_light';
        $defense->quantity = 2;
        $this->em->persist($defense);
        $this->em->flush();

        $catalog = self::getContainer()->get(GameCatalog::class);
        $buildingCost = array_sum($catalog->costs($catalog->get('buildings', 'mine_metal'), 1, 1, 'buildings'))
            + array_sum($catalog->costs($catalog->get('buildings', 'mine_metal'), 2, 1, 'buildings'));
        $researchCost = array_sum($catalog->costs($catalog->get('technologies', 'weapon_calibration'), 1, 1, 'technologies'));
        $fleetCost = array_sum($catalog->costs($catalog->get('ships', 'fighter'), 1, 3, 'ships'))
            + array_sum($catalog->costs($catalog->get('defenses', 'ground_turret_anti_light'), 1, 2, 'defenses'));
        $leaderboard = self::getContainer()->get(LeaderboardService::class);
        $rows = $leaderboard->forUniverse($player->currentPlayerUniverse);
        $own = array_values(array_filter($rows, static fn (array $row): bool => $row['membership']->id === $player->currentPlayerUniverse->id))[0];

        self::assertSame(intdiv($buildingCost, 100), $own['building_points']);
        self::assertSame(intdiv($researchCost, 100), $own['research_points']);
        self::assertSame(intdiv($fleetCost, 100), $own['fleet_points']);
        self::assertSame($own['building_points'] + $own['research_points'] + $own['fleet_points'], $own['total_points']);
        self::assertSame(5, $own['fleet_units']);
        self::assertSame(1, $own['planets']);
        self::assertSame(1, $own['ranks']['points']);

        $ship->quantity = 1;
        $this->em->flush();
        $afterLoss = array_values(array_filter($leaderboard->forUniverse($player->currentPlayerUniverse), static fn (array $row): bool => $row['membership']->id === $player->currentPlayerUniverse->id))[0];
        self::assertLessThan($own['fleet_points'], $afterLoss['fleet_points']);
    }

    public function testPowerPlantActuallyIncreasesEnergyProduction(): void
    {
        $clock=$this->clock();$a=$this->player();$planet=$this->join($a);$resources=self::getContainer()->get(ResourceService::class);$resources->consume($planet,['electricity'=>1000]);$before=$resources->snapshot($planet)['electricity']['rate'];
        $q=self::getContainer()->get(QueueService::class);$q->add($a->currentPlayerUniverse,$planet,'buildings','power_plant',1,str_repeat('a',32));$clock->sleep(60);$q->synchronize($a->currentPlayerUniverse);
        self::assertGreaterThan($before,$resources->snapshot($planet)['electricity']['rate']);
    }
    public function testShellColonyLimitFollowsResearchAndUniverseCap(): void
    {
        $player = $this->player('ColonyCounter');
        $planet = $this->join($player, 'omega-1');
        $shell = self::getContainer()->get(\App\Service\Colony\GameShell::class);
        self::assertSame(1, $shell->get($player, $planet)['colonyLimit']);
        $technology = new PlayerTechnology();
        $technology->playerUniverse = $player->currentPlayerUniverse;
        $technology->slug = 'colonial_expansion';
        $technology->level = 2;
        $this->em->persist($technology);
        $this->em->flush();
        self::assertSame(3, $shell->get($player, $planet)['colonyLimit']);
        $player->currentPlayerUniverse->universe->gameSettings['limits']['max_planets'] = 2;
        $this->em->flush();
        self::assertSame(2, $shell->get($player, $planet)['colonyLimit']);
    }

    public function testGuildLeaderboardAggregatesMembersAndComputesMedian(): void
    {
        $viewer = null;
        $rows = [];
        foreach (['Alpha' => [10, 40, 90], 'Beta' => [7, 10]] as $name => $totals) {
            $guild = new \App\Entity\Guild();
            $guild->name = $name;
            foreach ($totals as $index => $total) {
                $player = $this->player('GuildScore' . $name . $index);
                $this->join($player, 'omega-1');
                $membership = $player->currentPlayerUniverse;
                $viewer ??= $membership;
                if ($index === 0) {
                    $guild->leader = $membership;
                    $guild->universe = $membership->universe;
                    $this->em->persist($guild);
                }
                $member = new \App\Entity\GuildMember();
                $member->guild = $guild;
                $member->playerUniverse = $membership;
                $this->em->persist($member);
                $rows[] = ['membership' => $membership, 'building_points' => $total - 3, 'research_points' => 1, 'fleet_points' => 2, 'total_points' => $total];
            }
        }
        $this->em->flush();
        $standings = self::getContainer()->get(LeaderboardService::class)->guildsForUniverse($viewer, $rows);
        self::assertCount(2, $standings);
        self::assertSame('Alpha', $standings[0]['guild']->name);
        self::assertSame(1, $standings[0]['rank']);
        self::assertSame(3, $standings[0]['members']);
        self::assertSame(131, $standings[0]['building_points']);
        self::assertSame(3, $standings[0]['research_points']);
        self::assertSame(6, $standings[0]['fleet_points']);
        self::assertSame(140, $standings[0]['total_points']);
        self::assertEquals(40, $standings[0]['median_points']);
        self::assertSame(17, $standings[1]['total_points']);
        self::assertEquals(8.5, $standings[1]['median_points']);
    }

    public function testProfileUpdateHashesPasswordAndPreservesMembership(): void
    {
        $a=$this->player();$this->join($a);$membership=$a->currentPlayerUniverse->id;$data=new \App\Validator\DTO\ProfileData();$data->email='new@example.test';$data->username='Nouveau Commandant';$data->currentPassword='A-test-password-2026';$data->newPassword='New-test-password-2026';
        self::getContainer()->get(\App\Service\Player\ProfileService::class)->update($a,$data);
        self::assertSame($membership,$a->currentPlayerUniverse->id);self::assertSame('new@example.test',$a->email);self::assertNotSame($data->newPassword,$a->password);self::assertTrue(self::getContainer()->get(\Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface::class)->isPasswordValid($a,$data->newPassword));
    }

    private function concurrentProcesses(Player $p,Planet $planet,array $keys): array
    {
        $processes=[];
        // Les processus restent concurrents, mais leur temps métier n'avance pas pendant leur démarrage.
        $instant=(new \DateTimeImmutable())->format('Y-m-d H:i:s.uP');
        foreach($keys as $key) { $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/concurrent_action.php',(string)$p->currentPlayerUniverse->id,(string)$planet->id,$key,$instant]);$process->setTimeout(30);$process->start();$processes[]=$process; }
        return array_map(fn($process)=>$process->wait(),$processes);
    }
    public function testConcurrentDoubleSubmissionChargesAndCreatesOnlyOnce(): void
    {
        $p=$this->player();$planet=$this->join($p);$key=str_repeat('a',32);
        self::assertSame([0,0],$this->concurrentProcesses($p,$planet,[$key,$key]));
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM queue_item'));
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM action_receipt'));
    }
    public function testConcurrentSpendingCannotOverdrawResourceStock(): void
    {
        $p=$this->player();$planet=$this->join($p);$universe=$p->currentPlayerUniverse->universe;
        $catalog=self::getContainer()->get(GameCatalog::class);$price=$catalog->costs($catalog->getTierForUniverse($universe,'buildings','mine_metal','basic'),1,1,'buildings')['metal'];
        $available=$price+intdiv($price,2);$expectedRemaining=$available-$price;
        $universe->gameSettings['multipliers']['resource_production']=.01;
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=$available;$metal->productionRate=0;$this->em->flush();
        $codes=$this->concurrentProcesses($p,$planet,[str_repeat('a',32),str_repeat('b',32)]);sort($codes);self::assertSame([0,10],$codes);
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM queue_item'));
        $remaining=(float)$this->em->getConnection()->fetchOne('SELECT current_stock FROM planet_resource WHERE id=?',[$metal->id]);self::assertGreaterThanOrEqual($expectedRemaining,$remaining);self::assertLessThan($expectedRemaining+1,$remaining);
    }

    public function testCancellationRefundsPaidCostsAndDependentsExactlyOnce():void
    {
        $this->clock();$p=$this->player();$planet=$this->join($p,'omega-1');$m=$p->currentPlayerUniverse;$q=self::getContainer()->get(QueueService::class);
        $q->add($m,$planet,'buildings','mine_metal',1,str_repeat('a',32));$q->add($m,$planet,'buildings','mine_metal',1,str_repeat('b',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);$id=$items[0]->id;
        self::assertSame(['metal'=>480,'nano'=>160,'antimatter'=>0,'hydrogen'=>0],$items[0]->paidCosts);
        $paid=['metal'=>0,'nano'=>0,'antimatter'=>0,'hydrogen'=>0];foreach($items as $item)foreach($item->paidCosts as $resource=>$amount)$paid[$resource]+=$amount;
        $resources=[];$before=[];foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $resource){$resources[$resource->resourceType]=$resource;$before[$resource->resourceType]=$resource->currentStock;}
        // Modifier les nouveaux prix ne doit pas changer les coûts déjà payés.
        $catalog=self::getContainer()->get(GameCatalog::class);$field=new \ReflectionProperty($catalog,'data');$data=$field->getValue($catalog);$data['buildings']['mine_metal']['cost_profiles']['basic']['metal']=99999;$field->setValue($catalog,$data);
        $q->cancel($m,$id,str_repeat('c',32));foreach($paid as $resource=>$amount)self::assertEqualsWithDelta($before[$resource]+$amount*.25,$resources[$resource]->currentStock,.000001,$resource);
        $q->cancel($m,$id,str_repeat('d',32));foreach($paid as $resource=>$amount)self::assertEqualsWithDelta($before[$resource]+$amount*.25,$resources[$resource]->currentStock,.000001,$resource);self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
    }
    public function testCancellationRefundRespectsStorageCapacity():void
    {
        $this->clock();$p=$this->player();$planet=$this->join($p,'omega-1');$q=self::getContainer()->get(QueueService::class);$q->add($p->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy([]);$metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=9999;$this->em->flush();
        $q->cancel($p->currentPlayerUniverse,$item->id,str_repeat('b',32));self::assertSame(10000.0,$metal->currentStock);
    }
    public function testCatalogueSheetsUseDiscountedCostsAndSeparateDefenseStocksByRank():void
    {
        $player=$this->player();$planet=$this->join($player);
        foreach(['nanomaterial_synthesis','naval_engineering'] as $slug)foreach(GameCatalog::TIERS as $tier){
            $tech=new PlayerTechnology();$tech->playerUniverse=$player->currentPlayerUniverse;$tech->slug=$slug;$tech->tier=$tier;$tech->level=25;$this->em->persist($tech);
        }
        foreach(['basic'=>3,'intermediate'=>7] as $tier=>$quantity){
            $defense=new PlanetDefense();$defense->planet=$planet;$defense->slug='ground_turret_anti_light';$defense->tier=$tier;$defense->quantity=$quantity;$this->em->persist($defense);
        }
        $this->em->flush();$data=self::getContainer()->get(\App\Service\Colony\ColonyView::class)->data($player->currentPlayerUniverse,$planet);
        $catalog=self::getContainer()->get(GameCatalog::class);
        foreach($data['cards']['buildings'] as $card)if($card['definition']['slug']==='mine_metal'&&$card['tier']==='basic'){
            $base=$catalog->costs($card['definition'],1,1,'buildings');
            self::assertSame((int)ceil($base['metal']*.6),$card['costs']['metal']);
        }
        // These ranks can be locked: enable the catalogue preference before reading them.
        $player->preferences['showLockedItems']=true;$this->em->flush();$data=self::getContainer()->get(\App\Service\Colony\ColonyView::class)->data($player->currentPlayerUniverse,$planet);
        $quantities=[];foreach($data['cards']['defenses'] as $card)if($card['definition']['slug']==='ground_turret_anti_light')$quantities[$card['tier']]=$card['quantity'];
        self::assertSame(['basic'=>3,'intermediate'=>7,'superior'=>0],$quantities);
    }

    public function testResourceStripMetadataReflectsProtectionAndActualFactoryProductivity():void
    {
        $this->clock();$player=$this->player();$planet=$this->join($player);
        foreach(['mine_metal'=>100,'mine_nano'=>2,'mine_hydrogen'=>2,'mine_antimatter'=>10,'storage_metal'=>1] as $slug=>$level){
            $building=new PlanetBuilding();$building->planet=$planet;$building->slug=$slug;$building->level=$level;$this->em->persist($building);
        }
        $storage=new PlanetBuilding();$storage->planet=$planet;$storage->slug='storage_metal';$storage->tier='intermediate';$storage->level=2;$this->em->persist($storage);
        $antimatter=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'antimatter']);$antimatter->currentStock=1000;
        $this->em->flush();$service=self::getContainer()->get(ResourceService::class);
        $snapshot=$service->snapshot($planet);
        self::assertEqualsWithDelta(36000,$snapshot['metal']['protectedCapacity'],.000001);
        self::assertSame(100.0,$snapshot['metal']['productivity']);
        self::assertSame(100.0,$snapshot['antimatter']['productivity']);
        self::assertNull($snapshot['electricity']['productivity']);
        $electricity=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'electricity']);
        $electricity->currentStock=0;$this->em->flush();$snapshot=$service->snapshot($planet);
        self::assertLessThan(100,$snapshot['metal']['productivity']);
        self::assertEqualsWithDelta($snapshot['metal']['productivity'],$snapshot['nano']['productivity'],.000001);
        self::assertLessThan($service->rate($planet,'metal',0,['mine_metal'=>['basic'=>100]]),$snapshot['metal']['rate']);
        $power=new PlanetBuilding();$power->planet=$planet;$power->slug='power_plant';$power->level=100;$this->em->persist($power);
        $hydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'hydrogen']);$hydrogen->currentStock=0;
        $this->em->flush();$snapshot=$service->snapshot($planet);
        self::assertSame(100.0,$snapshot['metal']['productivity']);
        self::assertLessThan(100,$snapshot['antimatter']['productivity']);
    }

    public function testCatalogueBenefitsMatchActualProductionAndResearchDependencies():void
    {
        $p=$this->player();$planet=$this->join($p);$insights=self::getContainer()->get(\App\Service\Colony\CatalogueInsights::class);$catalog=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        self::assertArrayNotHasKey('mine_energy',$catalog->all('buildings'));
        $benefit=$insights->benefits($catalog->get('buildings','power_plant'),'buildings',1,$planet,[],[])[0];
        $resources=self::getContainer()->get(ResourceService::class);self::assertEqualsWithDelta($resources->rate($planet,'electricity',0,['power_plant'=>1])*3600,$benefit['after'],.000001);self::assertGreaterThan($benefit['before'],$benefit['after']);
        $unlocks=$insights->unlocks('technologies','engineering_foundations',5);self::assertContains('vector_propulsion',array_column($unlocks,'slug'));
        $tree=$insights->tree(['lab'=>4],['engineering_foundations'=>5,'naval_engineering'=>5]);$nodes=array_merge(...array_values($tree));$propulsion=array_values(array_filter($nodes,fn($n)=>$n['definition']['slug']==='vector_propulsion'))[0];self::assertTrue($propulsion['available']);
        $tree=$insights->tree([],['engineering_foundations'=>5,'naval_engineering'=>5]);$nodes=array_merge(...array_values($tree));$propulsion=array_values(array_filter($nodes,fn($n)=>$n['definition']['slug']==='vector_propulsion'))[0];self::assertFalse($propulsion['available']);
    }

    public function testTechnologyTreeIncludesEveryCategoryAndOrdersAllPrerequisites():void
    {
        $insights=self::getContainer()->get(\App\Service\Colony\CatalogueInsights::class);
        $tree=$insights->tree([],[]);$nodes=array_merge(...array_values($tree));
        $byKey=array_column($nodes,null,'key');
        self::assertEqualsCanonicalizing(['buildings','technologies','ships','defenses'],array_values(array_unique(array_column($nodes,'group'))));
        self::assertContains('buildings:lab-basic',$byKey['technologies:vector_propulsion-basic']['parents']);
        self::assertContains('buildings:shipyard-basic',$byKey['ships:cruiser-basic']['parents']);
        self::assertContains('buildings:shipyard-basic',$byKey['ships:fighter-basic']['parents']);
        foreach($byKey as $node)foreach($node['parents'] as $parent){
            self::assertArrayHasKey($parent,$byKey);
            self::assertLessThan($node['depth'],$byKey[$parent]['depth']);
        }
    }

    public function testSpyMissionConsumesAllProbesAndKeepsReportPrivate(): void
    {
        $clock = $this->clock();
        $sender = $this->player('SpySender');
        $origin = $this->join($sender);
        $targetOwner = $this->player('SpyTarget');
        $this->join($targetOwner);
        $target = null;
        foreach ($this->em->getRepository(\App\Entity\Planet::class)->findBy(['owner' => null, 'npcPlanet' => false]) as $candidate) {
            if ($candidate->solarSystem->galaxy->universe->id === $sender->currentPlayerUniverse->universe->id) { $target = $candidate; break; }
        }
        self::assertNotNull($target);
        $fleetStock = $this->ships($origin, ['probe' => 1]);
        $discovery = $this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'solarSystem' => $target->solarSystem]);
        if (!$discovery) { $discovery = new \App\Entity\PlayerDiscovery(); $discovery->playerUniverse = $sender->currentPlayerUniverse; $discovery->solarSystem = $target->solarSystem; $this->em->persist($discovery); }
        foreach ([['tactical_intelligence', 2], ['signature_masking', 5]] as [$slug, $level]) {
            $technology = new PlayerTechnology();
            $technology->playerUniverse = $sender->currentPlayerUniverse;
            $technology->slug = $slug;
            $technology->level = $level;
            $this->em->persist($technology);
        }
        foreach ($this->em->getRepository(\App\Entity\Fleet::class)->findBy(['homePlanet' => $target, 'status' => 'idle']) as $localFleet) {
            $localProbe = $this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet' => $localFleet, 'slug' => 'probe']);
            if ($localProbe) $localProbe->quantity = 0;
        }
        $this->em->flush();
        $hydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$origin,'resourceType'=>'hydrogen']);$hydrogen->currentStock=1000000;$this->em->flush();
        // Le succès doit être déterministe sans modifier les 5 % de détection du jeu.
        self::getContainer()->set(\Random\Randomizer::class, new \Random\Randomizer(new \Random\Engine\Mt19937(0)));
        $service = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $service->send($sender->currentPlayerUniverse, $origin, 'spy', $target->id, ['probe' => 1], 1, [], str_repeat('e', 32));
        $spyFleet = $this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'status' => 'traveling', 'mission' => 'spy']);
        self::assertNotNull($spyFleet);
        $clock->sleep($spyFleet->travelSeconds + 1);
        $service->arrive($spyFleet->id, $spyFleet->dispatchToken);

        $report = $this->em->getRepository(\App\Entity\EspionageReport::class)->findOneBy(['sender' => $sender->currentPlayerUniverse]);
        self::assertNotNull($report);
        self::assertSame('success', $report->result);
        self::assertSame(1, $this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['playerUniverse' => $sender->currentPlayerUniverse, 'sourceType' => 'risk_mission']));
        self::assertSame(5, $report->detectionChance);
        self::assertSame(1, $report->probesSent);
        self::assertSame(3, $report->intelCategories);
        self::assertSame(['resources', 'buildings', 'research'], array_keys($report->snapshot));
        self::assertSame(0, $this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet' => $spyFleet, 'slug' => 'probe'])->quantity);
        self::assertSame('completed', $spyFleet->status);
        $reader = self::getContainer()->get(\App\Service\Fleet\EspionageReportReader::class);
        self::assertSame($report->id, $reader->findVisible($sender->currentPlayerUniverse, $report->id)?->id);
        self::assertNull($reader->findVisible($targetOwner->currentPlayerUniverse, $report->id));
        self::assertSame('idle', $fleetStock->status);
    }

    public function testSpySnapshotUsesTheTargetUniverseCatalogueForEveryCategory(): void
    {
        $player = $this->player('SpySnapshot');
        $planet = $this->join($player);
        $universe = $planet->solarSystem->galaxy->universe;
        $entries = [
            'buildings' => 'mine_metal',
            'technologies' => 'tactical_intelligence',
            'ships' => 'fighter',
            'defenses' => 'ground_turret_anti_light',
        ];
        foreach ($entries as $group => $slug) {
            $universe->gameSettings['catalog_overrides'][$group][$slug]['tier_labels']['basic'] = 'Rapport ' . $group;
        }
        $building = new PlanetBuilding();
        $building->planet = $planet;
        $building->slug = 'mine_metal';
        $building->level = 1;
        $this->em->persist($building);
        $technology = new PlayerTechnology();
        $technology->playerUniverse = $player->currentPlayerUniverse;
        $technology->slug = 'tactical_intelligence';
        $technology->level = 1;
        $this->em->persist($technology);
        $this->ships($planet, ['fighter' => 2]);
        $defense = new PlanetDefense();
        $defense->planet = $planet;
        $defense->slug = 'ground_turret_anti_light';
        $defense->quantity = 3;
        $this->em->persist($defense);
        $this->em->flush();

        // Vérifie toutes les catégories sans dépendre du tirage de détection.
        $service = self::getContainer()->get(\App\Service\Fleet\EspionageService::class);
        $snapshot = new \ReflectionMethod($service, 'snapshot')->invoke($service, $planet);
        self::assertSame('Rapport buildings', $snapshot['buildings']['mine_metal']['basic']['label']);
        self::assertSame('Rapport technologies', $snapshot['research']['tactical_intelligence']['basic']['label']);
        self::assertSame('Rapport ships', $snapshot['fleets']['fighter:basic']['label']);
        self::assertSame(2, $snapshot['fleets']['fighter:basic']['quantity']);
        self::assertSame('Rapport defenses', $snapshot['defenses']['ground_turret_anti_light:basic']['label']);
        self::assertSame(3, $snapshot['defenses']['ground_turret_anti_light:basic']['quantity']);
    }

    public function testDetectedSpyMissionAlertsTargetAndStoresNoIntel(): void
    {
        $clock = $this->clock();
        $sender = $this->player('CaughtSpy');
        $origin = $this->join($sender);
        $targetOwner = $this->player('CounterSpy');
        $target = $this->join($targetOwner);
        $this->ships($origin, ['probe' => 2]);
        $originHydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$origin,'resourceType'=>'hydrogen']);$originHydrogen->currentStock=100000;$this->em->flush();
        $localFleet = $this->ships($target, ['probe' => 20]);
        $discovery = $this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'solarSystem' => $target->solarSystem]);
        if (!$discovery) { $discovery = new \App\Entity\PlayerDiscovery(); $discovery->playerUniverse = $sender->currentPlayerUniverse; $discovery->solarSystem = $target->solarSystem; $this->em->persist($discovery); }
        $counter = new PlayerTechnology();
        $counter->playerUniverse = $targetOwner->currentPlayerUniverse;
        $counter->slug = 'intelligence_countermeasures';
        $counter->level = 5;
        $this->em->persist($counter);
        $this->em->flush();
        $service = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $service->send($sender->currentPlayerUniverse, $origin, 'spy', $target->id, ['probe' => 2], 1, [], str_repeat('a', 32));
        $spyFleet = $this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'status' => 'traveling', 'mission' => 'spy']);
        self::assertNotNull($spyFleet);
        $clock->sleep($spyFleet->travelSeconds + 1);
        $service->arrive($spyFleet->id, $spyFleet->dispatchToken);

        $report = $this->em->getRepository(\App\Entity\EspionageReport::class)->findOneBy(['sender' => $sender->currentPlayerUniverse]);
        self::assertNotNull($report);
        self::assertContains($report->result, ['detected', 'success']);
        self::assertSame(95, $report->detectionChance);
        self::assertSame(2, $report->probesSent);
        if ($report->result === 'detected') {
            self::assertSame([], $report->snapshot);
            self::assertSame(0, $report->intelCategories);
        } else {
            self::assertGreaterThan(0, $report->intelCategories);
        }
        self::assertSame('completed', $spyFleet->status);
        self::assertSame(0, $this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet' => $spyFleet, 'slug' => 'probe'])->quantity);
        $alerts = $this->em->getRepository(\App\Entity\Notification::class)->findBy(['recipient' => $targetOwner->currentPlayerUniverse]);
        if ($report->result === 'detected') self::assertNotEmpty(array_filter($alerts, static fn ($notification): bool => str_contains($notification->message, 'Sondes d’espionnage détectées')));
        self::assertSame('idle', $localFleet->status);
    }


    public function testNpcSpyReportInitializesGarrisonAndUsesItsCounterTechnology(): void
    {
        $clock = $this->clock();
        $sender = $this->player('NpcSpy');
        $origin = $this->join($sender);
        $this->ships($origin, ['probe' => 2]);
        $originHydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$origin,'resourceType'=>'hydrogen']);$originHydrogen->currentStock=100000;$this->em->flush();
        $target = null;
        foreach ($this->em->getRepository(\App\Entity\Planet::class)->findBy(['owner' => null, 'npcPlanet' => true]) as $candidate) {
            if ($candidate->solarSystem->galaxy->universe->id === $sender->currentPlayerUniverse->universe->id) { $target = $candidate; break; }
        }
        self::assertNotNull($target);
        $target->conquestLevel = 5;
        $discovery = $this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'solarSystem' => $target->solarSystem]);
        if (!$discovery) { $discovery = new \App\Entity\PlayerDiscovery(); $discovery->playerUniverse = $sender->currentPlayerUniverse; $discovery->solarSystem = $target->solarSystem; $this->em->persist($discovery); }
        $this->em->flush();
        $service = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $service->send($sender->currentPlayerUniverse, $origin, 'spy', $target->id, ['probe' => 2], 1, [], str_repeat('b', 32));
        $fleet = $this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'status' => 'traveling', 'mission' => 'spy']);
        self::assertNotNull($fleet);
        $clock->sleep($fleet->travelSeconds + 1);
        $service->arrive($fleet->id, $fleet->dispatchToken);

        $report = $this->em->getRepository(\App\Entity\EspionageReport::class)->findOneBy(['sender' => $sender->currentPlayerUniverse]);
        self::assertNotNull($report);
        self::assertContains($report->result, ['detected', 'success']);
        self::assertSame(4, $report->targetCounterEspionageLevel);
        self::assertSame(94, $report->detectionChance);
        if ($report->result === 'detected') {
            self::assertSame([], $report->snapshot);
            self::assertSame(0, $report->intelCategories);
        } else {
            self::assertSame(0, $report->intelCategories);
            self::assertSame([], $report->snapshot);
        }
        self::assertTrue($target->npcGarrisonInitialized);
        self::assertGreaterThan(0, $this->em->getRepository(\App\Entity\PlanetNpcShip::class)->count(['planet' => $target]));
        self::assertSame(0, $this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet' => $fleet, 'slug' => 'probe'])->quantity);
    }


    public function testSpyQuoteRejectsOwnedTargetsAndAnyEscortShip(): void
    {
        $sender = $this->player('SpyRules');
        $origin = $this->join($sender);
        $ownedTarget = $this->secondPlanet($sender);
        $other = $this->player('SpyRulesTarget');
        $this->join($other);
        $foreignTarget = null;
        foreach ($this->em->getRepository(\App\Entity\Planet::class)->findBy(['owner' => null, 'npcPlanet' => false]) as $candidate) {
            if ($candidate->solarSystem->galaxy->universe->id === $other->currentPlayerUniverse->universe->id) { $foreignTarget = $candidate; break; }
        }
        self::assertNotNull($foreignTarget);
        $foreignTarget->owner = $other->currentPlayerUniverse;
        self::getContainer()->get(ResourceService::class)->initialize($foreignTarget);
        $this->ships($origin, ['probe' => 1, 'fighter' => 1]);
        $discovery = $this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'solarSystem' => $foreignTarget->solarSystem]);
        if (!$discovery) { $discovery = new \App\Entity\PlayerDiscovery(); $discovery->playerUniverse = $sender->currentPlayerUniverse; $discovery->solarSystem = $foreignTarget->solarSystem; $this->em->persist($discovery); }
        $this->em->flush();
        $service = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        try {
            $service->quote($sender->currentPlayerUniverse, $origin, 'spy', $ownedTarget->id, ['probe' => 1], 1);
            self::fail('L’espionnage d’une planète détenue par l’émetteur doit être refusé.');
        } catch (\DomainException) {}
        try {
            $service->quote($sender->currentPlayerUniverse, $origin, 'spy', $foreignTarget->id, ['probe' => 1, 'fighter' => 1], 1);
            self::fail('Une escorte ne doit pas être acceptée pour l’espionnage.');
        } catch (\DomainException) {}
    }
    public function testUniverseMetricsSeparateUniversesAndNeverMaterializeProgression(): void
    {
        $clock = $this->clock();
        $first = $this->player('MetricsOne');
        $planet = $this->join($first);
        $second = $this->player('MetricsTwo');
        $this->join($second);
        $membership = $first->currentPlayerUniverse;
        $membership->joinedAt = $clock->now()->modify('-1 hour');
        $membership->lastRecoveryProtectionAt = $clock->now()->modify('-10 minutes');
        $membership->recoveryProtectedUntil = $clock->now()->modify('+1 hour');
        $second->currentPlayerUniverse->joinedAt = $clock->now()->modify('-3 hours');
        $foreign = new PlayerUniverse();
        $foreign->player = $first;
        $foreign->universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        $foreign->joinedAt = $clock->now()->modify('-1 year');
        $foreign->recoveryProtectedUntil = $clock->now()->modify('+1 day');
        $this->em->persist($foreign);
        $foreignPlanet = self::getContainer()->get(\App\Repository\GameRepository::class)->planets($foreign->universe, null, true)[0];
        $foreignPlanet->owner = $foreign;
        $building = new PlanetBuilding();
        $building->planet = $planet;
        $building->slug = 'lab';
        $building->level = 2;
        $this->em->persist($building);
        $technology = new PlayerTechnology();
        $technology->playerUniverse = $membership;
        $technology->slug = 'engineering_foundations';
        $technology->level = 3;
        $this->em->persist($technology);
        $foreignBuilding = clone $building;
        $foreignBuilding->planet = $foreignPlanet;
        $foreignBuilding->level = 99;
        $this->em->persist($foreignBuilding);
        $foreignTechnology = clone $technology;
        $foreignTechnology->playerUniverse = $foreign;
        $foreignTechnology->level = 77;
        $this->em->persist($foreignTechnology);
        foreach ([['building', null, 60, 100], ['research', 30, 120, 100], ['shipyard', 90, 180, 60]] as $index => [$type, $started, $queued, $duration]) {
            $item = new QueueItem();
            $item->planet = $planet;
            $item->playerUniverse = $membership;
            $item->queueType = $type;
            $item->itemSlug = 'metrics-fixture';
            $item->durationSeconds = $duration;
            $item->queuedAt = $clock->now()->modify('-'.$queued.' seconds');
            $item->startedAt = $started === null ? null : $clock->now()->modify('-'.$started.' seconds');
            $item->dispatchToken = sprintf('00000000-0000-4000-8000-%012d', $index);
            $this->em->persist($item);
        }
        $foreignItem = clone $item;
        $foreignItem->planet = $foreignPlanet;
        $foreignItem->playerUniverse = $foreign;
        $foreignItem->startedAt = null;
        $foreignItem->queuedAt = $clock->now()->modify('-1 day');
        $foreignItem->dispatchToken = '00000000-0000-4000-8000-000000000099';
        $this->em->persist($foreignItem);
        $futureItem = clone $item;
        $futureItem->startedAt = null;
        $futureItem->queuedAt = $clock->now()->modify('+1 second');
        $futureItem->dispatchToken = '00000000-0000-4000-8000-000000000100';
        $this->em->persist($futureItem);
        foreach ([[$planet, 'defender_wins'], [$foreignPlanet, 'attacker_wins']] as [$combatPlanet, $outcome]) {
            $event = new \App\Entity\CombatEvent();
            $event->planet = $combatPlanet;
            $event->startedAt = $clock->now()->modify('-1 minute');
            $event->resolveAt = $clock->now();
            $event->seed = 1;
            $event->status = 'resolved';
            $log = new \App\Entity\CombatLog();
            $log->combatEvent = $event;
            $log->outcome = $outcome;
            $log->resolvedAt = $clock->now();
            $this->em->persist($event);
            $this->em->persist($log);
        }
        $metal = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet' => $planet, 'resourceType' => 'metal']);
        $metal->currentStock = 0;
        $metal->lastUpdatedAt = $clock->now();
        $this->em->flush();
        $connection = $this->em->getConnection();
        $tables = ['player_universe', 'queue_item', 'planet_resource', 'planet_building', 'player_technology', 'combat_log'];
        $before = [];
        foreach ($tables as $table) $before[$table] = $connection->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id');
        $service = self::getContainer()->get(\App\Service\Admin\UniverseMetricsService::class);
        $metrics = $service->snapshot($membership->universe);
        self::assertSame(2, $metrics['progression']['members']);
        self::assertEquals(7200, $metrics['progression']['age']['median']);
        self::assertSame(2, $metrics['progression']['buildingLevels']);
        self::assertSame(3, $metrics['progression']['researchLevels']);
        self::assertSame(4, $metrics['queues']['total']);
        self::assertSame(1, $metrics['queues']['invalidDates']);
        self::assertSame(60, $metrics['queues']['pending']['median']);
        self::assertSame(70, $metrics['queues']['remaining']['max']);
        self::assertSame(1, $metrics['queues']['overdue']);
        self::assertSame(1, $metrics['recovery']['protected']);
        self::assertSame(['defender_wins' => 1], $metrics['recovery']['outcomes']);
        self::assertSame(1, $metrics['resources']['rows']['metal']['empty']);
        self::assertSame(0, $metrics['resources']['rows']['electricity']['productivityObserved']);
        self::assertSame(1, $service->snapshot($foreign->universe)['progression']['members']);
        $this->em->flush();
        foreach ($tables as $table) self::assertSame($before[$table], $connection->fetchAllAssociative('SELECT * FROM '.$table.' ORDER BY id'), $table.' doit rester inchangée.');
    }

    public function testUniverseResourceMetricsBoundPagesAndDistinguishMissingData(): void
    {
        $clock = $this->clock();
        $player = $this->player('MetricsPages');
        $home = $this->join($player);
        $universe = $player->currentPlayerUniverse->universe;
        $planets = self::getContainer()->get(\App\Repository\GameRepository::class)->planets($universe, null, true);
        self::assertGreaterThanOrEqual(25, count($planets));
        foreach (array_slice($planets, 0, 25) as $planet) $planet->owner = $player->currentPlayerUniverse;
        $metal = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet' => $home, 'resourceType' => 'metal']);
        $metal->lastUpdatedAt = $clock->now()->modify('+1 second');
        $this->em->flush();
        $resourceCount = $this->em->getRepository(PlanetResource::class)->count();
        $service = self::getContainer()->get(\App\Service\Admin\UniverseMetricsService::class);
        $first = $service->snapshot($universe, -10)['resources'];
        $last = $service->snapshot($universe, PHP_INT_MAX)['resources'];
        self::assertSame(26, $first['total']);
        self::assertSame(1, $first['page']);
        self::assertSame(2, $last['page']);
        self::assertSame(0, $first['observed'] + $last['observed']);
        self::assertSame(26, $first['unavailable'] + $last['unavailable']);
        self::assertNull($first['rows']['metal']['stockPercent']);
        self::assertSame($resourceCount, $this->em->getRepository(PlanetResource::class)->count());
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $home]) as $resource) $resource->lastUpdatedAt = $clock->now()->modify('-1 day');
        $metal->currentStock = 1234;
        $mine = new PlanetBuilding();
        $mine->planet = $home;
        $mine->slug = 'mine_metal';
        $mine->level = 1;
        $this->em->persist($mine);
        $this->em->flush();
        $activePages = [$service->snapshot($universe, 1)['resources'], $service->snapshot($universe, 2)['resources']];
        $active = array_values(array_filter($activePages, static fn (array $page): bool => $page['observed'] === 1))[0];
        $universe->status = 'closed';
        $this->em->flush();
        $closedPages = [$service->snapshot($universe, 1)['resources'], $service->snapshot($universe, 2)['resources']];
        $closed = array_values(array_filter($closedPages, static fn (array $page): bool => $page['observed'] === 1))[0];
        $capacity = self::getContainer()->get(ResourceService::class)->capacity($home, 'metal', ['mine_metal' => ['basic' => 1]]);
        self::assertEqualsWithDelta(100 * 1234 / $capacity, $closed['rows']['metal']['stockPercent'], 1e-8);
        self::assertGreaterThan($closed['rows']['metal']['stockPercent'], $active['rows']['metal']['stockPercent']);
        self::assertSame(0, $closed['rows']['metal']['rateObserved']);
        self::assertSame(0, $closed['rows']['metal']['productivityObserved']);
    }

    public function testEmptyUniverseMetricsKeepUnknownDurationsDistinctFromZeroCounts(): void
    {
        $universe = new Universe();
        $universe->slug = 'metrics-empty';
        $universe->name = 'Univers vide de test';
        $this->em->persist($universe);
        $this->em->flush();
        $metrics = self::getContainer()->get(\App\Service\Admin\UniverseMetricsService::class)->snapshot($universe);
        self::assertSame(0, $metrics['progression']['members']);
        self::assertNull($metrics['progression']['age']['median']);
        self::assertSame(0, $metrics['queues']['total']);
        self::assertNull($metrics['queues']['pending']['median']);
        self::assertSame([], $metrics['recovery']['outcomes']);
        self::assertSame(0, $metrics['resources']['observed']);
    }


    public function testConstructionMaximumAndDirectPurchaseUseCurrentStocksBeyondOneHundred(): void
    {
        $clock=$this->clock();$player=$this->player('Maximum');$planet=$this->join($player);$membership=$player->currentPlayerUniverse;
        $catalog=self::getContainer()->get(GameCatalog::class);$buildings=[];$technologies=[];
        foreach($catalog->all('buildings') as $slug=>$definition) {
            $row=$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$planet,'slug'=>$slug,'tier'=>'basic'])??new PlanetBuilding();
            $row->planet=$planet;$row->slug=$slug;$row->level=25;$this->em->persist($row);$buildings[$slug]=['basic'=>25];
        }
        foreach($catalog->all('technologies') as $slug=>$definition) {
            $row=new PlayerTechnology();$row->playerUniverse=$membership;$row->slug=$slug;$row->level=25;$this->em->persist($row);$technologies[$slug]=['basic'=>25];
        }
        $definition=$catalog->getTierForUniverse($membership->universe,'ships','probe','basic');
        $plan=self::getContainer()->get(\App\Service\Queue\OperationCalculator::class)->calculate($definition,'ships',1,150,$buildings,$catalog->forUniverse($membership->universe),$technologies);
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $row) {
            $row->currentStock=$plan['costs'][$row->resourceType]??0;$row->productionRate=0;$row->lastUpdatedAt=$clock->now();
        }
        $this->em->flush();
        $service=self::getContainer()->get(QueueService::class);
        self::assertSame(150,$service->maximum($membership,$planet,'ships','probe','basic'));
        self::assertSame(150,$service->add($membership,$planet,'ships','probe',1000,str_repeat('e',32)));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$planet,'itemSlug'=>'probe']);
        self::assertSame(150,$item->quantity);
        self::assertSame($plan['costs'],$item->paidCosts);
        self::assertSame($plan['duration'],$item->durationSeconds);
        self::assertSame(0,$service->maximum($membership,$planet,'ships','probe','basic'));
        self::assertSame(0,$service->add($membership,$planet,'ships','probe',1000,str_repeat('e',32)));
        self::assertSame(1,$this->em->getRepository(QueueItem::class)->count(['planet'=>$planet]));
    }

    private function researchColonies(): array
    {
        $clock=$this->clock();$player=$this->player('CommonResearch');$one=$this->join($player);$two=$this->secondPlanet($player);
        foreach([$one,$two] as $planet) {$lab=new PlanetBuilding();$lab->planet=$planet;$lab->slug='lab';$lab->level=1;$this->em->persist($lab);}
        $this->em->flush();
        return [$clock,$player,$one,$two,self::getContainer()->get(QueueService::class)];
    }

    private function recordedResearch(Planet $planet,string $slug,int $level,int $duration,\DateTimeImmutable $at,bool $active,array $paid=['metal'=>123]): QueueItem
    {
        $item=new QueueItem();$item->planet=$planet;$item->playerUniverse=$planet->owner;$item->queueType='research';$item->itemSlug=$slug;$item->itemLevel=$level;
        $item->durationSeconds=$duration;$item->queuedAt=$at;$item->startedAt=$active?$at:null;$item->paidCosts=$paid;$item->dispatchToken=\Symfony\Component\Uid\Uuid::v4()->toRfc4122();
        if($active) $planet->researchQueueStartedAt=$at;
        $this->em->persist($item);return $item;
    }

    public function testLegacyParallelResearchAndPaidWaitingCommandsKeepTheirScheduleAndCosts(): void
    {
        [$clock,$player,$one,$two,$service]=$this->researchColonies();$membership=$player->currentPlayerUniverse;$at=$clock->now();
        $lab=$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$two,'slug'=>'lab']);$lab->level=25;
        $first=$this->recordedResearch($one,'engineering_foundations',1,200,$at,true,['metal'=>111]);
        $other=$this->recordedResearch($two,'tactical_intelligence',1,100,$at,true,['metal'=>222]);
        $waiting=$this->recordedResearch($one,'engineering_foundations',2,50,$at,false,['metal'=>333]);$waiting->dependsOn=$first;$this->em->flush();
        $catalog=self::getContainer()->get(GameCatalog::class);$resources=self::getContainer()->get(ResourceService::class);
        $expected=self::getContainer()->get(\App\Service\Queue\OperationCalculator::class)->calculate($catalog->getTierForUniverse($membership->universe,'technologies','engineering_foundations','basic'),'technologies',3,1,['lab'=>['basic'=>25]],$catalog->forUniverse($membership->universe));
        $beforeOne=$resources->snapshot($one)['metal']['stock'];$beforeTwo=$resources->snapshot($two)['metal']['stock'];
        $service->add($membership,$two,'technologies','engineering_foundations',1,str_repeat('a',32));
        $new=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$two,'itemSlug'=>'engineering_foundations']);
        self::assertSame(3,$new->itemLevel);self::assertSame($waiting,$new->dependsOn);self::assertNull($new->startedAt);
        self::assertSame($expected['costs'],$new->paidCosts);self::assertSame($expected['duration'],$new->durationSeconds);
        self::assertEqualsWithDelta($beforeOne,$resources->snapshot($one)['metal']['stock'],.000001);
        self::assertEqualsWithDelta($beforeTwo-$expected['costs']['metal'],$resources->snapshot($two)['metal']['stock'],.000001);
        self::assertSame(['metal'=>111],$first->paidCosts);self::assertSame(['metal'=>222],$other->paidCosts);self::assertSame(['metal'=>333],$waiting->paidCosts);
        self::assertEquals($at,$first->startedAt);self::assertEquals($at,$other->startedAt);
        $lab->level=1;$this->em->flush();
        $clock->sleep(200);$service->synchronize($membership);self::assertEquals($at->modify('+200 seconds'),$waiting->startedAt);self::assertNull($new->startedAt);
        $clock->sleep(50);$service->synchronize($membership);self::assertEquals($at->modify('+250 seconds'),$new->startedAt);
        self::assertSame($expected['duration'],$new->durationSeconds);self::assertSame($expected['costs'],$new->paidCosts);
        $clock->sleep($new->durationSeconds+1);$service->synchronize($membership);
        self::assertSame(3,$this->em->getRepository(PlayerTechnology::class)->findOneBy(['playerUniverse'=>$membership,'slug'=>'engineering_foundations'])->level);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count(['playerUniverse'=>$membership]));
    }

    public function testResearchLimitOfThirtyCommandsIsSharedAcrossColonies(): void
    {
        [$clock,$player,$one,$two,$service]=$this->researchColonies();$count=0;
        foreach(self::getContainer()->get(GameCatalog::class)->all('technologies') as $slug=>$definition) {
            foreach(GameCatalog::TIERS as $tier) {
                $item=$this->recordedResearch($count%2?$two:$one,$slug,1,10000,$clock->now(),$count===0);$item->tier=$tier;
                if(++$count===30) break 2;
            }
        }
        $this->em->flush();$this->expectException(\DomainException::class);$this->expectExceptionMessage('File pleine');
        $service->add($player->currentPlayerUniverse,$two,'technologies','engineering_foundations',1,str_repeat('a',32));
    }

    public function testResearchFilesRemainIndependentBetweenUniverses(): void
    {
        $this->clock();$player=$this->player('UniversesResearch');$service=self::getContainer()->get(QueueService::class);$memberships=[];
        foreach(['omega-1','turbo-1'] as $index=>$slug) {
            $planet=$this->join($player,$slug);$lab=new PlanetBuilding();$lab->planet=$planet;$lab->slug='lab';$lab->level=1;$this->em->persist($lab);$this->em->flush();
            $membership=$player->currentPlayerUniverse;$memberships[]=$membership;
            $service->add($membership,$planet,'technologies','engineering_foundations',1,str_repeat($index?'b':'a',32));
        }
        $repository=self::getContainer()->get(\App\Repository\GameRepository::class);
        foreach($memberships as $membership) {$queue=$repository->researchQueue($membership);self::assertCount(1,$queue);self::assertNotNull($queue[0]->startedAt);}
    }

    public function testCaptureCancelsCrossColonyDependentsAndStartsOnlyTheNextGlobalResearch(): void
    {
        [$clock,$player,$one,$two,$service]=$this->researchColonies();$membership=$player->currentPlayerUniverse;
        $service->add($membership,$one,'technologies','engineering_foundations',1,str_repeat('a',32));
        $service->add($membership,$two,'technologies','engineering_foundations',1,str_repeat('b',32));
        $service->add($membership,$two,'technologies','tactical_intelligence',1,str_repeat('c',32));
        $resources=self::getContainer()->get(ResourceService::class);$before=$resources->snapshot($two)['metal']['stock'];
        $journal=$this->em->wrapInTransaction(function()use($service,$membership,$one,$two){
            $manager=self::getContainer()->get(\App\Manager\GameManager::class);$manager->lock($membership);$manager->lock($one);$manager->lock($two);
            return $service->cancelForCaptureLocked($one,$membership);
        });
        self::assertCount(2,$journal);self::assertNull($one->researchQueueStartedAt);
        $queue=self::getContainer()->get(\App\Repository\GameRepository::class)->researchQueue($membership);
        self::assertCount(1,$queue);self::assertSame('tactical_intelligence',$queue[0]->itemSlug);self::assertSame($two,$queue[0]->planet);self::assertEquals($clock->now(),$queue[0]->startedAt);
        self::assertEqualsWithDelta($before,$resources->snapshot($two)['metal']['stock'],.000001);
    }

    public function testResearchBoostAndStaleMessagesRespectTheCommonHead(): void
    {
        [$clock,$player,$one,$two,$service]=$this->researchColonies();$membership=$player->currentPlayerUniverse;
        $service->add($membership,$one,'technologies','engineering_foundations',1,str_repeat('a',32));$service->add($membership,$two,'technologies','tactical_intelligence',1,str_repeat('b',32));
        [$first,$second]=self::getContainer()->get(\App\Repository\GameRepository::class)->researchQueue($membership);
        $id=$first->id;$oldToken=$first->dispatchToken;$paid=$first->paidCosts;
        $service->boost($membership,$id,2,str_repeat('c',32));$token=$first->dispatchToken;$finish=$first->finishAt();
        self::assertNotSame($oldToken,$token);self::assertSame($paid,$first->paidCosts);self::assertNull($second->startedAt);
        $clock->sleep($first->durationSeconds);$service->complete($id,$oldToken);self::assertSame(2,$this->em->getRepository(QueueItem::class)->count(['playerUniverse'=>$membership]));
        $service->complete($id,$token);self::assertEquals($finish,$second->startedAt);$service->complete($id,$token);
        self::assertSame(1,$this->em->getRepository(QueueItem::class)->count(['playerUniverse'=>$membership]));
    }
}
