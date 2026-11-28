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

    private function level(string $group, string $slug, int $level, string $tier = 'basic', ?Planet $planet = null): PlanetBuilding|PlayerTechnology
    {
        if ($group === 'buildings') {
            $row = new PlanetBuilding();
            $row->planet = $planet ?? $this->planet;
        } else {
            $row = new PlayerTechnology();
            $row->playerUniverse = $this->membership;
        }

        $row->slug = $slug;
        $row->tier = $tier;
        $row->level = $level;
        $this->em->persist($row);
        $this->em->flush();
        return $row;
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bunkerTiers')]
    public function testBunkerCanBeQueuedWithItsMinesAndPreviousRanks(string $tier):void
    {
        $this->level('buildings','mine_metal',1,$tier);$this->level('buildings','mine_nano',1,$tier);
        $this->preparePreviousBunkers($tier);
        self::assertSame(0,$this->em->getRepository(PlayerTechnology::class)->count(['playerUniverse'=>$this->membership,'slug'=>'structural_armor']));
        foreach(['shipyard','lab'] as $slug)self::assertSame(0,$this->em->getRepository(PlanetBuilding::class)->count(['planet'=>$this->planet,'slug'=>$slug]));
        self::getContainer()->get(QueueService::class)->add($this->membership,$this->planet,'buildings','fortress',1,str_repeat('2',32),$tier);
        $item=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$this->planet,'itemSlug'=>'fortress']);
        self::assertNotNull($item);self::assertSame($tier,$item->tier);self::assertSame(1,$item->itemLevel);
        self::assertTrue(self::getContainer()->get(CatalogAccess::class)->acquired($this->membership,$this->planet,'buildings','fortress',$tier));
    }

    public function testFirstLaboratoryConstructionPaysItsNewBasicQuote(): void
    {
        self::getContainer()->get(QueueService::class)->add(
            $this->membership, $this->planet, 'buildings', 'lab', 1, str_repeat('a', 32), 'basic'
        );
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $this->planet, 'itemSlug' => 'lab']);
        self::assertNotNull($item);
        self::assertSame(1800, $item->durationSeconds);
        self::assertSame(4800, $item->paidCosts['metal']);
        self::assertSame(2600, $item->paidCosts['nano']);
        self::assertEquals(0, array_sum(array_diff_key($item->paidCosts, array_flip(['metal', 'nano']))));
    }

    public static function bunkerTiers():array
    {
        return [['basic'],['intermediate'],['superior']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('missingBunkerBuildings')]
    public function testBunkerRequiresBothMinesOfTheSameRank(string $tier,int $metal,int $nano,string $missing):void
    {
        $this->level('buildings','mine_metal',$metal,$tier);$this->level('buildings','mine_nano',$nano,$tier);
        $this->preparePreviousBunkers($tier);
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

    private function preparePreviousBunkers(string $tier): void
    {
        if ($tier !== 'basic') {
            $this->level('buildings', 'fortress', 8, 'basic');
        }
        if ($tier === 'superior') {
            $this->level('buildings', 'fortress', 8, 'intermediate');
        }
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
        // Le niveau 1 local suffit pour lancer une recherche déjà débloquée au laboratoire de niveau 2.
        $this->level('buildings', 'lab', 1, planet: $this->other);
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

    #[\PHPUnit\Framework\Attributes\DataProvider('researchRanks')]
    public function testUnlockedResearchRequiresALaboratoryOfItsRankOnTheStartingColony(string $tier, string $laboratoryLabel): void
    {
        $this->level('buildings', 'lab', 10, $tier);
        if ($tier !== 'basic') {
            $this->level('technologies', 'engineering_foundations', 5, $tier === 'superior' ? 'intermediate' : 'basic');
            $this->level('buildings', 'lab', 10, planet: $this->other);
        }
        $queue = self::getContainer()->get(QueueService::class);
        $queue->synchronize($this->membership);
        $access = self::getContainer()->get(CatalogAccess::class);
        self::assertTrue($access->acquired($this->membership, $this->other, 'technologies', 'engineering_foundations', $tier));

        $connection = $this->em->getConnection();
        $stocksQuery = 'SELECT current_stock FROM planet_resource WHERE planet_id = ? ORDER BY resource_type';
        $stocks = $connection->fetchFirstColumn($stocksQuery, [$this->other->id]);
        $colonyId = $this->other->id;

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage($laboratoryLabel . ' de niveau 1 requis sur cette colonie');
        try {
            $queue->add($this->membership, $this->other, 'technologies', 'engineering_foundations', 1, str_repeat('5', 32), $tier);
        } finally {
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM queue_item'));
            self::assertSame($stocks, $connection->fetchFirstColumn($stocksQuery, [$colonyId]));
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('researchRanks')]
    public function testLevelOneLaboratoryCanStartAnEmpireUnlockOfItsRank(string $tier, string $laboratoryLabel): void
    {
        // Le niveau 2 sur la première colonie débloque l’architecture navale pour tout l’univers.
        $this->level('buildings', 'lab', 2, $tier);
        if ($tier !== 'basic') {
            $this->level('technologies', 'naval_engineering', 5, $tier === 'superior' ? 'intermediate' : 'basic');
        }
        $this->level('technologies', 'engineering_foundations', 2, $tier);
        $this->level('buildings', 'lab', 1, $tier, $this->other);

        $queue = self::getContainer()->get(QueueService::class);
        $view = self::getContainer()->get(ColonyView::class)->data($this->membership, $this->other, true, 'technologies');
        $card = array_values(array_filter($view['cards']['technologies'], static fn(array $card): bool => $card['definition']['slug'] === 'naval_engineering' && $card['tier'] === $tier))[0];
        $queue->add($this->membership, $this->other, 'technologies', 'naval_engineering', 1, str_repeat('6', 32), $tier);

        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $this->other]);
        self::assertSame($card['costs'], $item->paidCosts);
        self::assertSame($card['duration'], $item->durationSeconds);
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $this->other]) as $stock) {
            self::assertEqualsWithDelta(100000000 - $card['costs'][$stock->resourceType], $stock->currentStock, 1e-6);
        }
        self::assertNotNull($item, $laboratoryLabel . ' de niveau 1 doit suffire après le déblocage.');
        self::assertSame($tier, $item->tier);
        self::assertSame(1, $item->itemLevel);
    }

    public static function researchRanks(): iterable
    {
        yield 'Basique' => ['basic', 'Laboratoire colonial'];
        yield 'Intermédiaire' => ['intermediate', 'Institut de recherche orbitale'];
        yield 'Supérieur' => ['superior', 'Complexe scientifique planétaire'];
    }

    public static function industrialBuildingRanks(): iterable
    {
        foreach (['mine_metal', 'mine_nano', 'power_plant'] as $slug) {
            yield $slug.'/intermediate' => [$slug, 'intermediate', ['basic' => 25], 'intermediate', 5];
            yield $slug.'/superior' => [$slug, 'superior', ['basic' => 50, 'intermediate' => 25], 'superior', 5];
        }
        yield 'mine_hydrogen/basic' => ['mine_hydrogen', 'basic', [], 'basic', 3];
        yield 'mine_hydrogen/intermediate' => ['mine_hydrogen', 'intermediate', ['basic' => 25], 'intermediate', 3];
        yield 'mine_hydrogen/superior' => ['mine_hydrogen', 'superior', ['basic' => 50, 'intermediate' => 25], 'superior', 3];
        yield 'lab/intermediate' => ['lab', 'intermediate', ['basic' => 10], 'basic', 5];
        yield 'lab/superior' => ['lab', 'superior', ['basic' => 10, 'intermediate' => 10], 'intermediate', 5];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('industrialBuildingRanks')]
    public function testIndustrialRankRequiresResearchAndKeepsItsLocalAccess(
        string $slug, string $tier, array $previousLevels, string $researchTier, int $requiredResearchLevel,
    ): void {
        $parents = [];
        foreach ($previousLevels as $previousTier => $level) {
            $parents[] = $this->level('buildings', $slug, $level, $previousTier);
        }
        $research = $this->level('technologies', 'engineering_foundations', $requiredResearchLevel - 1, $researchTier);
        $queue = self::getContainer()->get(QueueService::class);
        $access = self::getContainer()->get(CatalogAccess::class);
        $queue->synchronize($this->membership);
        self::assertFalse($access->acquired($this->membership, $this->planet, 'buildings', $slug, $tier));

        $research->level = $requiredResearchLevel;
        $this->em->flush();
        $queue->synchronize($this->membership);
        self::assertTrue($access->acquired($this->membership, $this->planet, 'buildings', $slug, $tier));
        // La recherche est partagée dans l’univers. Les prérequis de bâtiments restent locaux.
        self::assertSame($previousLevels === [], $access->acquired($this->membership, $this->other, 'buildings', $slug, $tier));

        // La nouvelle grille ne retire pas un accès acquis si ses conditions disparaissent.
        foreach ([...$parents, $research] as $row) {
            $this->em->remove($row);
        }
        $this->em->flush();
        $queue->add($this->membership, $this->planet, 'buildings', $slug, 1, str_repeat('4', 32), $tier);
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $this->planet, 'itemSlug' => $slug]);
        self::assertNotNull($item);
        self::assertSame($tier, $item->tier);
        self::assertSame(1, $item->itemLevel);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('industrialBuildingRanks')]
    public function testDirectIndustrialConstructionRejectsInsufficientResearch(
        string $slug, string $tier, array $previousLevels, string $researchTier, int $requiredResearchLevel,
    ): void {
        foreach ($previousLevels as $previousTier => $level) {
            $this->level('buildings', $slug, $level, $previousTier);
        }
        $this->level('technologies', 'engineering_foundations', $requiredResearchLevel - 1, $researchTier);
        $connection = $this->em->getConnection();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Déblocage initial');
        try {
            self::getContainer()->get(QueueService::class)->add(
                $this->membership, $this->planet, 'buildings', $slug, 1, str_repeat('3', 32), $tier,
            );
        } finally {
            // Le refus doit annuler toute écriture de commande dans la transaction.
            self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM queue_item'));
        }
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

    #[\PHPUnit\Framework\Attributes\DataProvider('cappedStorageRanks')]
    public function testStorageCapAppliesToCardsAndNewOrders(string $tier, int $level): void
    {
        $this->level('buildings', 'mine_metal', 5, $tier);
        if ($tier === 'intermediate') {
            // Un niveau historique au-dessus du plafond satisfait toujours le seuil du rang suivant.
            $this->level('buildings', 'storage_metal', 6, 'basic');
        }
        $this->level('buildings', 'storage_metal', $level, $tier);
        $queue = self::getContainer()->get(QueueService::class);
        $queue->synchronize($this->membership);

        $cards = self::getContainer()->get(ColonyView::class)->data($this->membership, $this->planet, true, 'buildings')['cards']['buildings'];
        $storageCards = array_filter($cards, static fn (array $card): bool => $card['definition']['slug'] === 'storage_metal');
        $storageCards = array_column($storageCards, null, 'tier');
        self::assertSame($level, $storageCards[$tier]['level']);
        self::assertTrue($storageCards[$tier]['maximum']);
        self::assertSame([], $storageCards[$tier]['failures']);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Niveau maximum atteint.');
        $queue->add($this->membership, $this->planet, 'buildings', 'storage_metal', 1, str_repeat('1', 32), $tier);
    }

    public static function cappedStorageRanks(): iterable
    {
        yield 'Basique au plafond' => ['basic', 5];
        yield 'Basique au-dessus du plafond' => ['basic', 6];
        yield 'Intermédiaire au plafond' => ['intermediate', 5];
    }

    public function testSuperiorStorageStillAcceptsAnUpgradePastFive(): void
    {
        $this->level('buildings', 'mine_metal', 5, 'superior');
        $this->level('buildings', 'storage_metal', 5, 'basic');
        $this->level('buildings', 'storage_metal', 5, 'intermediate');
        $this->level('buildings', 'storage_metal', 5, 'superior');

        $queue = self::getContainer()->get(QueueService::class);
        $queue->add($this->membership, $this->planet, 'buildings', 'storage_metal', 1, str_repeat('1', 32), 'superior');
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $this->planet, 'itemSlug' => 'storage_metal']);
        self::assertNotNull($item);
        self::assertSame('superior', $item->tier);
        self::assertSame(6, $item->itemLevel);
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
