<?php
namespace App\Tests\Integration;

use App\Entity\{Player,PlayerUniverse,Universe,Galaxy,SolarSystem,Planet,PlanetBuilding,PlayerTechnology,PlanetDefense,Fleet,FleetShip,QueueItem,PlanetResource};
use App\Service\Requirement\CatalogAccess;
use App\Service\Queue\QueueService;
use App\Service\Colony\{ResourceService,ColonyView};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogAccessTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private PlayerUniverse $membership;
    private Planet $planet;
    private Planet $other;

    protected function setUp():void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test')throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        $u=new Universe();$u->slug='access-test';$u->name='Accès';
        $p=new Player();$p->email='access@example.test';$p->username='Access';$p->password='test';
        $this->membership=new PlayerUniverse();$this->membership->player=$p;$this->membership->universe=$u;$this->membership->joinedAt=new \DateTimeImmutable();$this->membership->catalogUnlocks=PlayerUniverse::INITIAL_CATALOG_UNLOCKS;
        $g=new Galaxy();$g->universe=$u;$g->name='A';
        $s=new SolarSystem();$s->galaxy=$g;$s->name='Système';$s->coordX=0;$s->coordY=0;
        foreach([$u,$p,$this->membership,$g,$s] as $entity)$this->em->persist($entity);
        foreach(['planet'=>4,'other'=>5] as $property=>$position){
            $planet=new Planet();$planet->owner=$this->membership;$planet->solarSystem=$s;$planet->position=$position;$planet->name='Colonie '.$position;
            $this->em->persist($planet);$this->$property=$planet;
        }
        $this->em->flush();
        foreach([$this->planet,$this->other] as $planet){
            self::getContainer()->get(ResourceService::class)->initialize($planet);$this->em->flush();
            foreach($this->em->getRepository(PlanetResource::class)->findBy(['planet'=>$planet]) as $resource)$resource->currentStock=100000000;
        }
        $this->em->flush();
    }

    private function level(string $group,string $slug,int $level,string $tier='basic'):object
    {
        $row=$group==='buildings'?new PlanetBuilding():new PlayerTechnology();
        if($group==='buildings')$row->planet=$this->planet;else $row->playerUniverse=$this->membership;
        $row->slug=$slug;$row->tier=$tier;$row->level=$level;$this->em->persist($row);$this->em->flush();return $row;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bunkerTiers')]
    public function testBunkerCanBeQueuedWithOnlyItsSameRankMines(string $tier):void
    {
        $this->level('buildings','mine_metal',1,$tier);$this->level('buildings','mine_nano',1,$tier);
        self::assertSame(0,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$this->membership,'slug'=>'structural_armor']));
        foreach(['shipyard','lab'] as $slug)self::assertSame(0,$this->em->getRepository(PlanetBuilding::class)->count(['planet'=>$this->planet,'slug'=>$slug]));
        self::getContainer()->get(QueueService::class)->add($this->membership,$this->planet,'buildings','fortress',1,str_repeat('2',32),$tier);
        $item=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->planet,'itemSlug'=>'fortress']);
        self::assertNotNull($item);self::assertSame($tier,$item->tier);self::assertSame(1,$item->itemLevel);
        self::assertTrue(self::getContainer()->get(CatalogAccess::class)->acquired($this->membership,$this->planet,'buildings','fortress',$tier));
    }

    public static function bunkerTiers():array
    {
        return [['basic'],['intermediate'],['superior']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingBunkerBuildings')]
    public function testBunkerRequiresBothMinesOfTheSameRank(string $tier,int $metal,int $nano,string $missing):void
    {
        $this->level('buildings','mine_metal',$metal,$tier);$this->level('buildings','mine_nano',$nano,$tier);
        // Des mines d’un autre rang ne doivent pas ouvrir l’accès demandé.
        foreach(array_diff(['basic','intermediate','superior'],[$tier]) as $otherTier){
            $this->level('buildings','mine_metal',5,$otherTier);$this->level('buildings','mine_nano',5,$otherTier);
        }
        $catalog=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        $connection=$this->em->getConnection();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($catalog->withTier($catalog->get('buildings',$missing),$tier)['label']);
        try {self::getContainer()->get(QueueService::class)->add($this->membership,$this->planet,'buildings','fortress',1,str_repeat('2',32),$tier);}
        finally {self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM queue_item WHERE item_slug=?',['fortress']));}
    }

    public static function missingBunkerBuildings():array
    {
        $cases=[];
        foreach(['basic','intermediate','superior'] as $tier){
            $cases[]=[$tier,0,1,'mine_metal'];
            $cases[]=[$tier,1,0,'mine_nano'];
        }
        return $cases;
    }

    public function testAccessSurvivesMissingConditionsAndRespectsColonyAndUniverseScopes():void
    {
        $lab=$this->level('buildings','lab',2);$mine=$this->level('buildings','mine_metal',2);$nano=$this->level('buildings','mine_nano',2);
        $engineering=$this->level('technologies','engineering_foundations',2);$naval=$this->level('technologies','naval_engineering',1);
        $queue=self::getContainer()->get(QueueService::class);$queue->synchronize($this->membership);
        $access=self::getContainer()->get(CatalogAccess::class);
        self::assertTrue($access->acquired($this->membership,$this->planet,'buildings','shipyard','basic'));
        self::assertFalse($access->acquired($this->membership,$this->other,'buildings','shipyard','basic'));
        self::assertTrue($access->acquired($this->membership,$this->other,'technologies','naval_engineering','basic'));
        foreach([$lab,$mine,$nano,$engineering,$naval] as $row)$this->em->remove($row);$this->em->flush();
        $queue->add($this->membership,$this->planet,'buildings','shipyard',1,str_repeat('a',32));
        $queue->add($this->membership,$this->other,'technologies','naval_engineering',1,str_repeat('b',32));
        self::assertSame(2,$this->em->getRepository(QueueItem::class)->count());
        $cards=self::getContainer()->get(ColonyView::class)->data($this->membership,$this->planet,true,'buildings')['cards'];
        $card=array_values(array_filter($cards['buildings'],static fn($c)=>$c['definition']['slug']==='shipyard'&&$c['tier']==='basic'))[0];
        self::assertSame([],$card['failures']);self::assertTrue($card['details']['acquired']);
        self::assertNotEmpty(array_filter($card['details']['requirements'],static fn($r)=>!$r['met']));
        $twig=self::getContainer()->get(\Twig\Environment::class);
        $html=$twig->render('ui/catalogue_info.html.twig',['card'=>$card,'d'=>$card['definition'],'group'=>'buildings','planet'=>$this->planet,'show_locked_items'=>true,'resources'=>[]]);
        self::assertStringContainsString('Déblocage initial de ce rang',$html);
        self::assertStringContainsString('Accès acquis définitivement',$html);
        self::assertStringNotContainsString('et au niveau',$html);
        $tree=self::getContainer()->get(\App\Service\Colony\CatalogueInsights::class)->tree([],[],$this->membership,$this->planet);
        $nodes=array_merge(...array_values($tree));$node=array_values(array_filter($nodes,static fn($n)=>$n['key']==='buildings:shipyard-basic'))[0];
        self::assertTrue($node['available']);self::assertTrue($node['acquired']);
    }

    public function testRequalificationKeepsPossessionsAndQueuesWithoutGrantingHistoricalAccess():void
    {
        $this->membership->catalogUnlocks=null;
        $this->level('buildings','mine_antimatter',1,'superior');
        $this->level('technologies','shield_harmonics',1,'superior');
        $fleet=new Fleet();$fleet->playerUniverse=$this->membership;$fleet->homePlanet=$this->planet;$fleet->status='returning';$this->em->persist($fleet);
        $ship=new FleetShip();$ship->fleet=$fleet;$ship->slug='capital';$ship->tier='superior';$ship->quantity=1;$this->em->persist($ship);
        $defense=new PlanetDefense();$defense->planet=$this->planet;$defense->slug='ground_turret_anti_capital';$defense->tier='superior';$defense->quantity=1;$this->em->persist($defense);
        $item=new QueueItem();$item->playerUniverse=$this->membership;$item->planet=$this->planet;$item->queueType='building';$item->itemSlug='shipyard';$item->tier='superior';$item->itemLevel=1;$item->quantity=1;$item->paidCosts=['metal'=>123];$item->dispatchToken='legacy-token';$item->durationSeconds=500;$item->queuedAt=new \DateTimeImmutable();$this->em->persist($item);$this->em->flush();
        $command=new \Symfony\Component\Console\Tester\CommandTester(self::getContainer()->get(\App\Command\PreserveCatalogUnlocksCommand::class));
        self::assertSame(0,$command->execute(['--apply'=>true]));
        self::getContainer()->get(QueueService::class)->synchronize($this->membership);
        $access=self::getContainer()->get(CatalogAccess::class);
        foreach([['buildings','mine_antimatter'],['buildings','shipyard'],['technologies','shield_harmonics'],['ships','capital'],['defenses','ground_turret_anti_capital']] as [$group,$slug])self::assertFalse($access->acquired($this->membership,$this->planet,$group,$slug,'superior'));
        self::assertFalse($access->acquired($this->membership,$this->other,'buildings','mine_antimatter','basic'));
        self::assertSame(['metal'=>123],$item->paidCosts);self::assertSame(1,$ship->quantity);self::assertSame(1,$defense->quantity);
        $snapshot=$this->membership->catalogUnlocks;$id=$this->membership->id;$this->em->clear();
        $this->membership=$this->em->find(PlayerUniverse::class,$id);self::assertSame($snapshot,$this->membership->catalogUnlocks);
        self::getContainer()->get(QueueService::class)->synchronize($this->membership);self::assertSame($snapshot,$this->membership->catalogUnlocks);
    }

    public function testMilitaryConstructionKeepsAccessAfterConditionsDisappear():void
    {
        $yard=$this->level('buildings','shipyard',4);$lab=$this->level('buildings','lab',5);$fortress=$this->level('buildings','fortress',1);
        $armor=$this->level('technologies','structural_armor',1);
        $weapon=$this->level('technologies','weapon_calibration',2);
        $queue=self::getContainer()->get(QueueService::class);$queue->synchronize($this->membership);
        foreach([$yard,$lab,$fortress,$weapon,$armor] as $row)$this->em->remove($row);$this->em->flush();
        $stocks=self::getContainer()->get(ResourceService::class)->snapshot($this->planet);
        foreach($stocks as $slug=>$row)self::assertGreaterThan(10000,$row['stock'],$slug.' : surplus disponible pour la construction');
        $queue->add($this->membership,$this->planet,'ships','heavy_fighter',2,str_repeat('c',32));
        $queue->add($this->membership,$this->planet,'defenses','ground_turret_anti_light',3,str_repeat('d',32));
        $items=$this->em->getRepository(QueueItem::class)->findBy([],['id'=>'ASC']);self::assertCount(2,$items);
        self::assertSame(2,$items[0]->quantity);self::assertSame(3,$items[1]->quantity);
        $catalog=self::getContainer()->get(\App\Service\Catalog\GameCatalog::class);
        self::assertSame($catalog->costs($catalog->get('ships','heavy_fighter'),1,2)+['electricity'=>2000],$items[0]->paidCosts);
        $items[0]->startedAt=new \DateTimeImmutable('-1 day');$this->em->flush();$queue->synchronize($this->membership);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
        self::assertSame(2,$this->em->getRepository(FleetShip::class)->findOneBy(['slug'=>'heavy_fighter','tier'=>'basic'])->quantity);
        self::assertSame(3,$this->em->getRepository(PlanetDefense::class)->findOneBy(['planet'=>$this->planet,'slug'=>'ground_turret_anti_light','tier'=>'basic'])->quantity);
    }

    public function testBuildingAndResearchImprovementsCrossFormerMilestonesAndComplete():void
    {
        $this->level('buildings','mine_nano',1);$this->level('buildings','lab',3);$this->level('technologies','naval_engineering',2);
        $this->level('technologies','tactical_intelligence',1);
        $queue=self::getContainer()->get(QueueService::class);
        $queue->add($this->membership,$this->planet,'buildings','mine_nano',1,str_repeat('e',32));
        $queue->add($this->membership,$this->planet,'technologies','tactical_intelligence',1,str_repeat('f',32));
        foreach($this->em->getRepository(QueueItem::class)->findAll() as $item)$item->startedAt=new \DateTimeImmutable('-1 day');
        $this->em->flush();$queue->synchronize($this->membership);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
        self::assertSame(2,$this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet'=>$this->planet,'slug'=>'mine_nano'])->level);
        self::assertSame(2,$this->em->getRepository(PlayerTechnology::class)->findOneBy(['playerUniverse'=>$this->membership,'slug'=>'tactical_intelligence'])->level);
    }

    public function testCompatibilityCommandIsIdempotentAndDryRunDoesNotWrite():void
    {
        $this->membership->catalogUnlocks=null;$this->em->flush();
        $command=new \Symfony\Component\Console\Tester\CommandTester(self::getContainer()->get(\App\Command\PreserveCatalogUnlocksCommand::class));
        self::assertSame(0,$command->execute(['--dry-run'=>true]));self::assertNull($this->membership->catalogUnlocks);
        self::assertSame(0,$command->execute([]));self::assertNull($this->membership->catalogUnlocks);
        self::assertSame(0,$command->execute(['--apply'=>true]));$snapshot=$this->membership->catalogUnlocks;self::assertNotEmpty($snapshot);
        self::assertSame(0,$command->execute(['--apply'=>true]));self::assertSame($snapshot,$this->membership->catalogUnlocks);
        self::assertStringContainsString('0 participation',$command->getDisplay());
        self::assertSame(2,$this->em->getRepository(Planet::class)->count());self::assertSame(1,$this->em->getRepository(PlayerUniverse::class)->count());
    }

    public function testOldRecordedAccessCannotAuthorizeNewOrdersBeforeOrAfterRequalification():void
    {
        $oldKey='planet:'.$this->planet->id.':ships:fighter:intermediate';
        $this->membership->catalogUnlocks=[$oldKey=>true];$this->em->flush();
        $queue=self::getContainer()->get(QueueService::class);
        $queue->synchronize($this->membership);
        self::assertSame([$oldKey=>true],$this->membership->catalogUnlocks,'Le rattrapage ne déclenche pas la bascule.');
        self::assertSame(0,$queue->maximum($this->membership,$this->planet,'ships','fighter','intermediate'));
        $command=new \Symfony\Component\Console\Tester\CommandTester(self::getContainer()->get(\App\Command\PreserveCatalogUnlocksCommand::class));
        self::assertSame(0,$command->execute(['--apply'=>true]));
        self::assertTrue($this->membership->catalogUnlocks[$oldKey],'La preuve historique reste disponible.');
        self::assertFalse(self::getContainer()->get(CatalogAccess::class)->acquired($this->membership,$this->planet,'ships','fighter','intermediate'));
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Déblocage initial');
        $queue->add($this->membership,$this->planet,'ships','fighter',1,str_repeat('4',32),'intermediate');
    }

    public function testConflictingUniverseOverrideIsReportedWithoutBeingErased():void
    {
        $this->membership->catalogUnlocks=null;
        $settings=['catalog_overrides'=>['ships'=>['fighter'=>['unlock_requirements'=>[
            'technologies'=>[['slug'=>'naval_engineering','level'=>5]],
        ]]]]];
        $this->membership->universe->gameSettings=$settings;$this->em->flush();
        $command=new \Symfony\Component\Console\Tester\CommandTester(self::getContainer()->get(\App\Command\PreserveCatalogUnlocksCommand::class));
        self::assertSame(0,$command->execute(['--dry-run'=>true]));
        self::assertStringContainsString('ships/fighter/intermediate',$command->getDisplay());
        self::assertSame(1,$command->execute(['--apply'=>true]));
        self::assertNull($this->membership->catalogUnlocks);
        self::assertSame($settings,$this->membership->universe->gameSettings);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('historicalOperations')]
    public function testHistoricalCompletionKeepsPaymentDurationAndDoesNotGrantAccess(string $group,string $slug):void
    {
        $this->membership->catalogUnlocks=null;
        $item=new QueueItem();$item->playerUniverse=$this->membership;$item->planet=$this->planet;
        $item->queueType=match($group){'buildings'=>'building','technologies'=>'research',default=>'shipyard'};
        $item->itemSlug=$slug;$item->tier='superior';$item->quantity=2;
        $item->itemLevel=in_array($group,['buildings','technologies'],true)?4:null;
        $item->paidCosts=['metal'=>123,'electricity'=>456];$item->durationSeconds=71;
        $item->queuedAt=new \DateTimeImmutable('-1 day');$item->startedAt=$item->queuedAt;
        $item->dispatchToken='historical-completion';$this->em->persist($item);$this->em->flush();
        $queue=self::getContainer()->get(QueueService::class);$queue->synchronize($this->membership);
        self::assertSame(0,$this->em->getRepository(QueueItem::class)->count());
        self::assertNull($this->membership->catalogUnlocks,'Un achèvement ne bascule pas le registre.');
        self::assertSame(['metal'=>123,'electricity'=>456],$item->paidCosts);self::assertSame(71,$item->durationSeconds);
        $class=match($group){'buildings'=>PlanetBuilding::class,'technologies'=>PlayerTechnology::class,'ships'=>FleetShip::class,default=>PlanetDefense::class};
        $result=$this->em->getRepository($class)->findOneBy(['slug'=>$slug,'tier'=>'superior']);
        self::assertNotNull($result);
        self::assertSame(in_array($group,['buildings','technologies'],true)?4:2,$result->level??$result->quantity);
        $command=new \Symfony\Component\Console\Tester\CommandTester(self::getContainer()->get(\App\Command\PreserveCatalogUnlocksCommand::class));
        self::assertSame(0,$command->execute(['--apply'=>true]));
        $queue->synchronize($this->membership);
        self::assertFalse(self::getContainer()->get(CatalogAccess::class)->acquired($this->membership,$this->planet,$group,$slug,'superior'));
    }

    public static function historicalOperations():array
    {
        return [['buildings','shipyard'],['technologies','shield_harmonics'],['ships','capital'],['defenses','ground_turret_anti_capital']];
    }

    public function testPermanentAccessDoesNotBypassResearchLevelCaps():void
    {
        $this->level('technologies','tactical_intelligence',5);$this->level('buildings','lab',3);$this->level('technologies','naval_engineering',2);
        $queue=self::getContainer()->get(QueueService::class);$queue->synchronize($this->membership);
        self::assertTrue(self::getContainer()->get(CatalogAccess::class)->acquired($this->membership,$this->planet,'technologies','tactical_intelligence','basic'));
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Niveau maximum atteint.');
        $queue->add($this->membership,$this->planet,'technologies','tactical_intelligence',1,str_repeat('1',32));
    }
}
