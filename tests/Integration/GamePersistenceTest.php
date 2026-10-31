<?php
namespace App\Tests\Integration;
use App\Entity\{Player,PlayerUniverse,Universe,Planet,QueueItem,PlanetBuilding,PlanetResource,OutboxMessage,PlayerTechnology};
use App\Service\Universe\{UniverseImporter,UniverseService,UniverseLifecycleService};
use App\Service\Notification\OutboxRelay;
use App\Message\UniverseClosureDue;
use App\MessageHandler\UniverseClosureDueHandler;
use App\Service\Player\RegistrationService;
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
        $s->add($m,$planet,'buildings','mine_carbon',1,str_repeat('c',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);
        self::assertSame(2,$items[1]->itemLevel);self::assertSame($items[0],$items[1]->dependsOn);
        $s->cancel($m,$items[0]->id,str_repeat('d',32));
        $left=$this->em->getRepository(QueueItem::class)->findAll();self::assertCount(1,$left);self::assertSame('mine_carbon',$left[0]->itemSlug);self::assertNotNull($left[0]->startedAt);
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
        try { self::getContainer()->get(QueueService::class)->add($p->currentPlayerUniverse,$planet,'buildings','shipyard',1,str_repeat('a',32));self::fail('Ressources insuffisantes attendues.'); }
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
        $p=$this->player();$planet=$this->join($p);$this->ships($planet,['probe'=>1]);$m=$p->currentPlayerUniverse;
        $systems=$this->em->getRepository(\App\Entity\SolarSystem::class)->findBy(['galaxy'=>$planet->solarSystem->galaxy],['id'=>'ASC']);$target=$systems[1];
        $service=self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $quote=$service->quote($m,$planet,'explore',$target->id,['probe'=>1],.5);
        self::assertLessThanOrEqual($quote['fuel_capacity'],$quote['total_fuel']);self::assertSame(0,$quote['cargo_capacity']);
        $hydrogen=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'hydrogen']);$before=$hydrogen->currentStock;
        $service->send($m,$planet,'explore',$target->id,['probe'=>1],.5,[],str_repeat('e',32));
        $service->send($m,$planet,'explore',$target->id,['probe'=>1],.5,[],str_repeat('e',32));
        self::assertEqualsWithDelta($before-$quote['total_fuel'],$hydrogen->currentStock,.000001);
        $fleet=$this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['status'=>'traveling']);$old=$fleet->dispatchToken;
        $this->land($fleet,$service);self::assertSame('completed',$fleet->status);
        $service->arrive($fleet->id,$old);
        self::assertSame(1,$this->em->getRepository(\App\Entity\PlayerDiscovery::class)->count(['playerUniverse'=>$m,'solarSystem'=>$target]));
        self::assertSame(0,$this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'probe'])->quantity);
        self::assertNull($fleet->arrivalAt);
    }
    public function testTransportDeliversFreightWithoutSpendingReservedReturnFuelTwice(): void
    {
        $p=$this->player();$origin=$this->join($p);$this->ships($origin,['transport'=>1]);$m=$p->currentPlayerUniverse;
        $target=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$target->owner=$m;
        self::getContainer()->get(ResourceService::class)->initialize($target);$this->em->flush();
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$target]) as $r) $r->productionRate=0;$this->em->flush();
        $resource=fn($planet,$slug)=>$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>$slug]);
        $targetMetal=$resource($target,'metal')->currentStock;$targetHydrogen=$resource($target,'hydrogen')->currentStock;
        $sourceHydrogen=$resource($origin,'hydrogen')->currentStock;
        $service=self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $q=$service->quote($m,$origin,'transport',$target->id,['transport'=>1],.5,['metal'=>123,'hydrogen'=>67]);
        $service->send($m,$origin,'transport',$target->id,['transport'=>1],.5,['metal'=>123,'hydrogen'=>67],str_repeat('f',32));
        self::assertEqualsWithDelta($sourceHydrogen-$q['total_fuel']-67,$resource($origin,'hydrogen')->currentStock,.000001);
        $fleet=$this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['status'=>'traveling']);$old=$fleet->dispatchToken;
        $this->land($fleet,$service);$service->arrive($fleet->id,$old);
        self::assertEqualsWithDelta($targetMetal+123,$resource($target,'metal')->currentStock,.000001);
        self::assertEqualsWithDelta($targetHydrogen+67,$resource($target,'hydrogen')->currentStock,.000001);
        self::assertEqualsWithDelta($q['one_way_fuel'],$fleet->reservedFuel,.000001);
        $this->land($fleet,$service);self::assertSame('completed',$fleet->status);
        self::assertEqualsWithDelta($sourceHydrogen-$q['total_fuel']-67,$resource($origin,'hydrogen')->currentStock,.000001);
    }
    public function testFuelTankCannotTransportFreightWithoutCargoShip(): void
    {
        $p=$this->player();$origin=$this->join($p);$this->ships($origin,['fighter'=>1]);$m=$p->currentPlayerUniverse;
        $target=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$target->owner=$m;$this->em->flush();
        $this->expectException(\DomainException::class);
        self::getContainer()->get(\App\Service\Fleet\FleetService::class)->quote($m,$origin,'transport',$target->id,['fighter'=>1],1,['metal'=>1]);
    }
    public function testSoloColonyShipCanColonizeKnownPlanet(): void
    {
        $p=$this->player();$origin=$this->join($p);$this->ships($origin,['colony_ship'=>1]);$m=$p->currentPlayerUniverse;
        $target=$this->em->getRepository(Planet::class)->findOneBy(['solarSystem'=>$origin->solarSystem,'owner'=>null]);$target->npcPlanet=false;
        $service=self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $service->send($m,$origin,'colonize',$target->id,['colony_ship'=>1],1,[],str_repeat('a',32));
        $fleet=$this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['status'=>'traveling']);$this->land($fleet,$service);
        self::assertSame($m->id,$target->owner->id);
        $kit=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class)->rules()['colony_initial_stock'];
        foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$target]) as $resource) self::assertEquals($kit[$resource->resourceType],$resource->currentStock);
        self::assertSame(5,$this->em->getRepository(PlanetResource::class)->count(['planet'=>$target]));
        self::assertSame(0,$this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet'=>$fleet,'slug'=>'colony_ship'])->quantity);
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
        $q->add($p->currentPlayerUniverse,$planet,'buildings','mine_carbon',1,str_repeat('b',32));
        $expected=10000+$old*$duration+$metal->productionRate*(3600-$duration)-800;
        self::assertEqualsWithDelta($expected,$r->snapshot($planet)['metal']['stock'],.000001);
        self::assertSame(1,$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$planet,'slug'=>'mine_metal'])->level);
        $before=$r->snapshot($planet)['metal']['stock'];$q->complete($completedId,$completedToken);
        self::assertEqualsWithDelta($before,$r->snapshot($planet)['metal']['stock'],.000001);
    }
    public function testCatchupRespectsStorageCapBeforeStorageUpgrade(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p);$q=self::getContainer()->get(QueueService::class);$r=self::getContainer()->get(ResourceService::class);
        $q->add($p->currentPlayerUniverse,$planet,'buildings','storage_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$planet]);$duration=$item->durationSeconds;
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=200000;$rate=$metal->productionRate;$this->em->flush();
        $clock->sleep(3600);$q->synchronize($p->currentPlayerUniverse);
        self::assertEqualsWithDelta(200000+$rate*(3600-$duration),$r->snapshot($planet)['metal']['stock'],.000001);
        self::assertSame(300000.0,$r->snapshot($planet)['metal']['capacity']);
    }
    public function testResearchSameTechnologyCannotRunOnTwoPlanets(): void
    {
        $this->clock();$p=$this->player();$one=$this->join($p);$two=$this->secondPlanet($p);
        foreach([$one,$two] as $planet) { $lab=new PlanetBuilding();$lab->planet=$planet;$lab->slug='lab';$lab->level=1;$this->em->persist($lab); }$this->em->flush();
        $q=self::getContainer()->get(QueueService::class);$q->add($p->currentPlayerUniverse,$one,'technologies','basic_engineering',1,str_repeat('a',32));
        $this->expectException(\DomainException::class);$q->add($p->currentPlayerUniverse,$two,'technologies','basic_engineering',1,str_repeat('b',32));
    }
    public function testDifferentResearchQueuesCanCompleteOnDifferentPlanets(): void
    {
        $clock=$this->clock();$p=$this->player();$one=$this->join($p);$two=$this->secondPlanet($p);
        foreach([$one,$two] as $planet) { $lab=new PlanetBuilding();$lab->planet=$planet;$lab->slug='lab';$lab->level=1;$this->em->persist($lab); }$this->em->flush();
        $q=self::getContainer()->get(QueueService::class);$q->add($p->currentPlayerUniverse,$one,'technologies','basic_engineering',1,str_repeat('a',32));$q->add($p->currentPlayerUniverse,$two,'technologies','advanced_targeting',1,str_repeat('b',32));
        $clock->sleep(60);$q->synchronize($p->currentPlayerUniverse);
        self::assertSame(2,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$p->currentPlayerUniverse]));
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
    }
    public function testShipyardAndDefensesAreDeliveredExactlyOnce(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p);
        foreach(['shipyard','fortress'] as $slug) { $b=new PlanetBuilding();$b->planet=$planet;$b->slug=$slug;$b->level=1;$this->em->persist($b); }$this->em->flush();
        $q=self::getContainer()->get(QueueService::class);$q->add($p->currentPlayerUniverse,$planet,'ships','fighter',2,str_repeat('a',32));$q->add($p->currentPlayerUniverse,$planet,'defenses','ground_turret',3,str_repeat('b',32));
        $clock->sleep(3600);$q->synchronize($p->currentPlayerUniverse);$q->synchronize($p->currentPlayerUniverse);
        self::assertSame(2,$this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['slug'=>'fighter'])->quantity);
        self::assertSame(3,$this->em->getRepository(\App\Entity\PlanetDefense::class)->findOneBy(['planet'=>$planet])->quantity);
    }
    public function testBoostRedispatchesOnlyHeadAndIgnoresOldMessage(): void
    {
        $clock=$this->clock();$p=$this->player();$planet=$this->join($p,'omega-1');$q=self::getContainer()->get(QueueService::class);
        $q->add($p->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));$q->add($p->currentPlayerUniverse,$planet,'buildings','mine_carbon',1,str_repeat('b',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);$head=$items[0];$next=$items[1];$token=$head->dispatchToken;$nextDuration=$next->durationSeconds;
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
    public function testPowerPlantActuallyIncreasesEnergyProduction(): void
    {
        $clock=$this->clock();$a=$this->player();$planet=$this->join($a);$resources=self::getContainer()->get(ResourceService::class);$before=$resources->snapshot($planet)['energy']['rate'];
        $q=self::getContainer()->get(QueueService::class);$q->add($a->currentPlayerUniverse,$planet,'buildings','power_plant',1,str_repeat('a',32));$clock->sleep(60);$q->synchronize($a->currentPlayerUniverse);
        self::assertGreaterThan($before,$resources->snapshot($planet)['energy']['rate']);
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
        foreach($keys as $key) { $process=new \Symfony\Component\Process\Process([PHP_BINARY,dirname(__DIR__).'/Support/concurrent_action.php',(string)$p->currentPlayerUniverse->id,(string)$planet->id,$key]);$process->setTimeout(30);$process->start();$processes[]=$process; }
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
        $p=$this->player();$planet=$this->join($p);$metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=1500;$metal->productionRate=0;$this->em->flush();
        $codes=$this->concurrentProcesses($p,$planet,[str_repeat('a',32),str_repeat('b',32)]);sort($codes);self::assertSame([0,10],$codes);
        self::assertSame(1,(int)$this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM queue_item'));
        self::assertEqualsWithDelta(700,(float)$this->em->getConnection()->fetchOne('SELECT current_stock FROM planet_resource WHERE id=?',[$metal->id]),.00001);
    }

    public function testCancellationRefundsPaidCostsAndDependentsExactlyOnce():void
    {
        $this->clock();$p=$this->player();$planet=$this->join($p,'omega-1');$m=$p->currentPlayerUniverse;$q=self::getContainer()->get(QueueService::class);
        $q->add($m,$planet,'buildings','mine_metal',1,str_repeat('a',32));$q->add($m,$planet,'buildings','mine_metal',1,str_repeat('b',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);$id=$items[0]->id;
        self::assertSame(['metal'=>800,'energy'=>400],$items[0]->paidCosts);
        $metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$before=$metal->currentStock;
        // Changing tomorrow's prices must not change yesterday's paid costs.
        $catalog=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class);$field=new \ReflectionProperty($catalog,'data');$data=$field->getValue($catalog);$data['buildings']['mine_metal']['costs_base']['metal']=99999;$field->setValue($catalog,$data);
        $q->cancel($m,$id,str_repeat('c',32));$expected=$before+(800+1440)*.25;self::assertEqualsWithDelta($expected,$metal->currentStock,.000001);
        $q->cancel($m,$id,str_repeat('d',32));self::assertEqualsWithDelta($expected,$metal->currentStock,.000001);self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
    }
    public function testCancellationRefundRespectsStorageCapacity():void
    {
        $this->clock();$p=$this->player();$planet=$this->join($p,'omega-1');$q=self::getContainer()->get(QueueService::class);$q->add($p->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));
        $item=$this->em->getRepository(QueueItem::class)->findOneBy([]);$metal=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'metal']);$metal->currentStock=199999;$this->em->flush();
        $q->cancel($p->currentPlayerUniverse,$item->id,str_repeat('b',32));self::assertSame(200000.0,$metal->currentStock);
    }
    public function testCatalogueBenefitsMatchActualProductionAndResearchDependencies():void
    {
        $p=$this->player();$planet=$this->join($p);$insights=self::getContainer()->get(\App\Service\Colony\CatalogueInsights::class);$catalog=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        self::assertArrayNotHasKey('mine_energy',$catalog->all('buildings'));
        $benefit=$insights->benefits($catalog->get('buildings','power_plant'),'buildings',1,$planet,[],[])[0];
        $resources=self::getContainer()->get(ResourceService::class);self::assertEqualsWithDelta($resources->rate($planet,'energy',0,['power_plant'=>1])*3600,$benefit['after'],.000001);self::assertGreaterThan($benefit['before'],$benefit['after']);
        $unlocks=$insights->unlocks('technologies','basic_engineering',2);self::assertContains('advanced_propulsion',array_column($unlocks,'slug'));
        $tree=$insights->tree(['lab'=>1],['basic_engineering'=>2]);$nodes=array_merge(...array_values($tree));$propulsion=array_values(array_filter($nodes,fn($n)=>$n['definition']['slug']==='advanced_propulsion'))[0];self::assertTrue($propulsion['available']);
        $tree=$insights->tree([],['basic_engineering'=>2]);$nodes=array_merge(...array_values($tree));$propulsion=array_values(array_filter($nodes,fn($n)=>$n['definition']['slug']==='advanced_propulsion'))[0];self::assertFalse($propulsion['available']);
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
        foreach ([['espionage_technology', 2], ['stealth_technology', 5]] as [$slug, $level]) {
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
        $service = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $service->send($sender->currentPlayerUniverse, $origin, 'spy', $target->id, ['probe' => 1], 1, [], str_repeat('e', 32));
        $spyFleet = $this->em->getRepository(\App\Entity\Fleet::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'status' => 'traveling', 'mission' => 'spy']);
        self::assertNotNull($spyFleet);
        $clock->sleep($spyFleet->travelSeconds + 1);
        $service->arrive($spyFleet->id, $spyFleet->dispatchToken);

        $report = $this->em->getRepository(\App\Entity\EspionageReport::class)->findOneBy(['sender' => $sender->currentPlayerUniverse]);
        self::assertNotNull($report);
        self::assertSame('success', $report->result);
        self::assertSame(0, $report->detectionChance);
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

    public function testDetectedSpyMissionAlertsTargetAndStoresNoIntel(): void
    {
        $clock = $this->clock();
        $sender = $this->player('CaughtSpy');
        $origin = $this->join($sender);
        $targetOwner = $this->player('CounterSpy');
        $target = $this->join($targetOwner);
        $this->ships($origin, ['probe' => 2]);
        $localFleet = $this->ships($target, ['probe' => 20]);
        $discovery = $this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse' => $sender->currentPlayerUniverse, 'solarSystem' => $target->solarSystem]);
        if (!$discovery) { $discovery = new \App\Entity\PlayerDiscovery(); $discovery->playerUniverse = $sender->currentPlayerUniverse; $discovery->solarSystem = $target->solarSystem; $this->em->persist($discovery); }
        $counter = new PlayerTechnology();
        $counter->playerUniverse = $targetOwner->currentPlayerUniverse;
        $counter->slug = 'counter_espionage';
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
        self::assertSame('detected', $report->result);
        self::assertSame(100, $report->detectionChance);
        self::assertSame(2, $report->probesSent);
        self::assertSame([], $report->snapshot);
        self::assertSame(0, $report->intelCategories);
        self::assertSame('completed', $spyFleet->status);
        self::assertSame(0, $this->em->getRepository(\App\Entity\FleetShip::class)->findOneBy(['fleet' => $spyFleet, 'slug' => 'probe'])->quantity);
        $alerts = $this->em->getRepository(\App\Entity\Notification::class)->findBy(['recipient' => $targetOwner->currentPlayerUniverse]);
        self::assertNotEmpty(array_filter($alerts, static fn ($notification): bool => str_contains($notification->message, 'Sondes d’espionnage détectées')));
        self::assertSame('idle', $localFleet->status);
    }


    public function testNpcSpyReportInitializesGarrisonAndUsesItsCounterTechnology(): void
    {
        $clock = $this->clock();
        $sender = $this->player('NpcSpy');
        $origin = $this->join($sender);
        $this->ships($origin, ['probe' => 2]);
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
        self::assertSame('detected', $report->result);
        self::assertSame(4, $report->targetCounterEspionageLevel);
        self::assertSame(100, $report->detectionChance);
        self::assertSame([], $report->snapshot);
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


}
