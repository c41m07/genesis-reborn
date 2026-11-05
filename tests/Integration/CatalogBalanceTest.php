<?php
namespace App\Tests\Integration;

use App\Entity\{AdminAuditLog,PlanetBuilding,PlanetResource,Player,Universe};
use App\Service\Admin\CatalogBalanceService;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogBalanceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private Player $admin;
    protected function setUp():void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test')throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();$this->admin=new Player();$this->admin->email='balance-admin@example.test';$this->admin->username='BalanceAdmin';$this->admin->password='test';$this->admin->roles=['ROLE_ADMIN'];$this->em->persist($this->admin);$this->em->flush();
    }
    private function universe(string $slug='turbo-1'):Universe{return $this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);}
    private function values(CatalogBalanceService $service,Universe $universe,string $group,string $slug):array
    {foreach($service->entries($universe)[$group] as $item)if($item['slug']===$slug)return array_map(static fn(array $field)=>$field['value'],$item['fields']);throw new \LogicException('Entrée catalogue absente.');}
    public function testOverridesAreScopedResettableAndAudited():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$catalog=static::getContainer()->get(GameCatalog::class);$turbo=$this->universe();$omega=$this->universe('omega-1');
        $values=$this->values($service,$turbo,'ships','fighter');$values['precision']=88;$values['cost_profiles.basic.metal']=4500;$service->save($this->admin,$turbo->id,'ships','fighter',$values);
        self::assertSame(88.0,$catalog->getForUniverse($turbo,'ships','fighter')['precision']);self::assertSame(4500,$catalog->getForUniverse($turbo,'ships','fighter')['cost_profiles']['basic']['metal']);
        self::assertSame(55,$catalog->getForUniverse($omega,'ships','fighter')['precision']);self::assertSame(1,$this->em->getRepository(AdminAuditLog::class)->count());
        static::getContainer()->get(\App\Service\Admin\AdminService::class)->setUniverseSettings($this->admin,$turbo->id,['multipliers'=>array_fill_keys(['building_time','research_time','ship_build_time','resource_production','travel_time'],1.0),'limits'=>['max_planets'=>9,'max_fleet_size'=>100],'features'=>['guilds_enabled'=>true,'planet_conquest'=>true]]);self::assertSame(88.0,$catalog->getForUniverse($turbo,'ships','fighter')['precision']);
        $service->reset($this->admin,$turbo->id,'ships','fighter');self::assertSame(55,$catalog->getForUniverse($turbo,'ships','fighter')['precision']);self::assertSame(3,$this->em->getRepository(AdminAuditLog::class)->count());
    }
    public function testTechnologyAndDefenseValuesResolvePerUniverse():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$catalog=static::getContainer()->get(GameCatalog::class);$turbo=$this->universe();$omega=$this->universe('omega-1');
        $tech=$this->values($service,$turbo,'technologies','weapon_calibration');$tech['effect.value_per_level']=0.07;$service->save($this->admin,$turbo->id,'technologies','weapon_calibration',$tech);
        $ships=[$catalog->getForUniverse($turbo,'ships','fighter')];$boosted=static::getContainer()->get(\App\Service\Combat\TechnologyEffectCalculator::class)->applyToShips($ships,['weapon_calibration'=>1],$turbo);self::assertEqualsWithDelta(74.9,$boosted[0]['precision'],.000001);
        self::assertSame(0.01,$catalog->getForUniverse($omega,'technologies','weapon_calibration')['effect']['value_per_level']);
        $defense=$this->values($service,$turbo,'defenses','ground_turret_anti_light');$defense['hp']=900;$service->save($this->admin,$turbo->id,'defenses','ground_turret_anti_light',$defense);
        self::assertSame(900.0,$catalog->getForUniverse($turbo,'defenses','ground_turret_anti_light')['hp']);self::assertSame(430,$catalog->getForUniverse($omega,'defenses','ground_turret_anti_light')['hp']);
    }
    public function testEditorRejectsOutOfRangeValues():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$universe=$this->universe();$values=$this->values($service,$universe,'ships','fighter');$values['precision']=1001;
        $this->expectException(\DomainException::class);$service->save($this->admin,$universe->id,'ships','fighter',$values);
    }
    public function testEditorRejectsInconsistentShotRange():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$universe=$this->universe();$values=$this->values($service,$universe,'ships','fighter');$values['shots_min']=4;$values['shots_max']=2;
        $this->expectException(\DomainException::class);$service->save($this->admin,$universe->id,'ships','fighter',$values);
    }
    public function testEditorRejectsFieldsOutsideTheAllowlist():void
    {
        $service=static::getContainer()->get(CatalogBalanceService::class);$universe=$this->universe();$values=$this->values($service,$universe,'ships','fighter');$values['max_level']=30;
        $this->expectException(\DomainException::class);$service->save($this->admin,$universe->id,'ships','fighter',$values);
    }
    public function testNewBuildsUseOverridesWhileExistingQueueSnapshotsStayFixed():void
    {
        $data=new RegistrationData();$data->email='queue-builder@example.test';$data->username='QueueBuilder';$data->plainPassword='Long-test-password-2026';$player=static::getContainer()->get(RegistrationService::class)->register($data);$universe=$this->universe();static::getContainer()->get(UniverseService::class)->select($player,$universe->id);$planet=$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);$queues=static::getContainer()->get(\App\Service\Queue\QueueService::class);$queues->add($player->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('a',32));$first=$this->em->getRepository(\App\Entity\QueueItem::class)->findOneBy(['itemSlug'=>'mine_metal']);$oldCosts=$first->paidCosts;$oldDuration=$first->durationSeconds;
        $balance=static::getContainer()->get(CatalogBalanceService::class);$values=$this->values($balance,$universe,'buildings','mine_metal');$values['cost_profiles.basic.metal']=5000;$values['base_build_time']=600;$balance->save($this->admin,$universe->id,'buildings','mine_metal',$values);
        self::assertSame($oldCosts,$first->paidCosts);self::assertSame($oldDuration,$first->durationSeconds);$queues->add($player->currentPlayerUniverse,$planet,'buildings','mine_metal',1,str_repeat('b',32));$items=$this->em->getRepository(\App\Entity\QueueItem::class)->findBy(['itemSlug'=>'mine_metal'],['id'=>'ASC']);self::assertCount(2,$items);self::assertSame(9000,$items[1]->paidCosts['metal']);self::assertGreaterThan($oldDuration,$items[1]->durationSeconds);
    }
    public function testBuildingProductionChangeMaterializesThenRefreshesExistingColony():void
    {
        $data=new RegistrationData();$data->email='builder@example.test';$data->username='Builder';$data->plainPassword='Long-test-password-2026';$player=static::getContainer()->get(RegistrationService::class)->register($data);$universe=$this->universe();static::getContainer()->get(UniverseService::class)->select($player,$universe->id);$planet=$this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        $plant=new PlanetBuilding();$plant->planet=$planet;$plant->slug='power_plant';$plant->level=2;$this->em->persist($plant);$this->em->flush();$resources=static::getContainer()->get(ResourceService::class);$resources->refreshProduction($planet);$before=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'electricity'])->productionRate;
        $balance=static::getContainer()->get(CatalogBalanceService::class);$values=$this->values($balance,$universe,'buildings','power_plant');$values['production_per_level']=2.5;$planetId=$planet->id;$balance->save($this->admin,$universe->id,'buildings','power_plant',$values);$this->em->clear();$planet=$this->em->find(\App\Entity\Planet::class,$planetId);$after=$this->em->getRepository(PlanetResource::class)->findOneBy(['planet'=>$planet,'resourceType'=>'electricity'])->productionRate;
        self::assertGreaterThan($before,$after);$factor=1.2-(($planet->position-1)*0.05);self::assertEqualsWithDelta(4.6*$factor,$after-$before,.000001);
    }
}
