<?php
namespace App\Tests\Service;

use App\Entity\{Galaxy,Planet,SolarSystem,Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{FactoryEconomy,ResourceService};
use App\Service\Event\GameEventService;
use App\Service\Queue\OperationCalculator;
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class BuildingBalanceTest extends TestCase
{
    private GameCatalog $catalog;
    private ResourceService $resources;

    protected function setUp():void
    {
        $this->catalog=\App\Tests\Support\ProducerBalanceReference::historicalCatalog();
        $entityRepository=$this->createStub(EntityRepository::class);
        $entityRepository->method('findBy')->willReturn([]);
        $em=$this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($entityRepository);
        $repository=new GameRepository($em);$manager=new GameManager($em);$clock=new MockClock('2026-11-05 12:00:00 UTC');
        $events=new GameEventService($repository,$manager,$clock);
        $vacations=new \App\Service\Player\VacationCalendar($repository,$clock);
        $this->resources=new ResourceService($repository,$manager,$this->catalog,$clock,$events,$vacations);
    }

    private function planet(int $position=5,float $speed=1):Planet
    {
        $universe=new Universe();$universe->slug='building-study';$universe->name='Étude';
        $universe->gameSettings=['multipliers'=>['resource_production'=>$speed,'building_time'=>1/$speed,'research_time'=>1/$speed,'ship_build_time'=>1/$speed,'travel_time'=>1/$speed]];
        $galaxy=new Galaxy();$galaxy->universe=$universe;$system=new SolarSystem();$system->galaxy=$galaxy;
        $planet=new Planet();$planet->solarSystem=$system;$planet->position=$position;return $planet;
    }

    private function fixture():array
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/building-balance-a.json'),true,512,JSON_THROW_ON_ERROR);
    }

    public function testHistoricalCostsAndNominalGainsMatchThePreviousStudyAcrossRanksAndPositions():void
    {
        $operation=new OperationCalculator($this->catalog);
        $prices=json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/catalog-costs-2026-11-05.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($this->fixture()['nominal'] as $row){
            $planet=$this->planet($row['position']);$universe=$planet->solarSystem->galaxy->universe;
            $definition=$this->catalog->getTierForUniverse($universe,'buildings',$row['building'],$row['tier']);
            $plan=$operation->calculate($definition,'buildings',$row['level'],1,[],$this->catalog->forUniverse($universe));
            $price=$prices['entries']['buildings'][$row['building']]['costs'][$row['tier']]['none'][$row['level']][1];
            self::assertSame($row['costs'],$price['old']['costs'],'Référence historique conservée.');
            self::assertSame($price['new']['costs'],$plan['costs']);
            self::assertSame($row['construction_seconds'],$plan['duration']);
            $levels=[$row['building']=>[$row['tier']=>1]];
            $gain=($this->resources->rate($planet,$definition['production_resource'],0,$levels)-$this->resources->rate($planet,$definition['production_resource'],0,[]))*3600;
            self::assertEqualsWithDelta($row['production_gain_per_hour'],$gain,1e-7);
            $consumption=$this->resources->factoryConsumption($planet,$row['building'],$levels);
            self::assertEqualsWithDelta($row['electricity_gain_per_hour'],$consumption['electricity']*3600,1e-7);
            self::assertEqualsWithDelta($row['hydrogen_consumption_per_hour'],$consumption['hydrogen']*3600,1e-7);
        }
    }

    public function testUtilityEffectsAndConstructionDurationsKeepTheirSeparateProgressions():void
    {
        $operation=new OperationCalculator($this->catalog);$planet=$this->planet();$universe=$planet->solarSystem->galaxy->universe;
        $prices=json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/catalog-costs-2026-11-05.json'),true,512,JSON_THROW_ON_ERROR);
        foreach($this->fixture()['preserved_costs'] as $row){
            $definition=$this->catalog->getTierForUniverse($universe,$row['group'],$row['slug'],$row['tier']);
            $actual=$operation->calculate($definition,$row['group'],$row['level'],1,[],$this->catalog->forUniverse($universe));
            $expected=$prices['entries'][$row['group']][$row['slug']]['costs'][$row['tier']]['none'][$row['level']][1]['new'];
            if($row['group']!=='buildings') $expected['costs']['electricity']=100*(int)ceil($expected['duration']/60);
            self::assertSame($expected,$actual);
            self::assertSame($row['plan']['duration'],$actual['duration']);
        }
        self::assertSame(1.5625,GameCatalog::tierFactor('superior'));
        self::assertSame(2.25,$this->catalog->productionTierFactor('superior'));
        self::assertSame(356250.0,$this->resources->capacity($planet,'metal',['storage_metal'=>['superior'=>1]]));
    }

    public function testHistoricalProductionAndStocksMatchThePreviousStudyAndHourlyMaterialization():void
    {
        $inputMethod=new \ReflectionMethod(ResourceService::class,'operatingInput');
        foreach($this->fixture()['economy'] as $row){
            $planet=$this->planet(5,$row['speed']);$input=$inputMethod->invoke($this->resources,$planet,$row['buildings']);
            $result=FactoryEconomy::simulate($input['base'],$input['factories'],$row['stocks'],$row['capacities'],$row['elapsed_seconds'],.1);
            foreach($row['expected'] as $field=>$expected){
                if(is_array($expected))foreach($expected as $resource=>$amount)self::assertEqualsWithDelta($amount,$result[$field][$resource],1e-5,$row['scenario'].'/'.$field.'/'.$resource);
                else self::assertEqualsWithDelta($expected,$result[$field],1e-8,$row['scenario'].'/'.$field);
            }
            $stocks=$row['stocks'];$remaining=$row['elapsed_seconds'];
            while($remaining>0){$step=min(3600,$remaining);$stocks=FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$row['capacities'],$step,.1)['stocks'];$remaining-=$step;}
            foreach($stocks as $resource=>$amount)self::assertEqualsWithDelta($result['stocks'][$resource],$amount,1e-5,$row['scenario'].'/découpage/'.$resource);
        }
    }

    public function testPerUniverseProductionOverrideStillAppliesWithTheNewRankFactor():void
    {
        $planet=$this->planet();$universe=$planet->solarSystem->galaxy->universe;
        $universe->gameSettings['catalog_overrides']['buildings']['power_plant']['production_per_level']=.3;
        $before=$this->resources->rate($planet,'electricity',0,[]);
        self::assertEqualsWithDelta(.3*2.25,$this->resources->rate($planet,'electricity',0,['power_plant'=>['superior'=>1]])-$before,1e-10);
    }

    public function testInitialAndColonyStocksCanBootstrapProductionThenFinanceThePowerPlant():void
    {
        $operation=new OperationCalculator($this->catalog);$inputMethod=new \ReflectionMethod(ResourceService::class,'operatingInput');
        foreach(['initial_stock','colony_initial_stock'] as $kind)foreach([1,5,9] as $position){
            $planet=$this->planet($position);$universe=$planet->solarSystem->galaxy->universe;$stocks=$this->catalog->rules()[$kind];$buildings=[];$caps=[];
            foreach($this->catalog->rules()['resources'] as $resource=>$label)$caps[$resource]=$this->resources->capacity($planet,$resource,[]);
            foreach(['mine_metal','mine_nano','mine_hydrogen','mine_antimatter'] as $slug){
                $plan=$operation->calculate($this->catalog->getTierForUniverse($universe,'buildings',$slug,'basic'),'buildings',1,1,$buildings,$this->catalog->forUniverse($universe));
                foreach($plan['costs'] as $resource=>$amount){self::assertGreaterThanOrEqual($amount,$stocks[$resource],"$kind/$position/$slug/$resource");$stocks[$resource]-=$amount;}
                $input=$inputMethod->invoke($this->resources,$planet,$buildings);
                $stocks=FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$caps,$plan['duration'])['stocks'];$buildings[$slug]['basic']=1;
            }
            $input=$inputMethod->invoke($this->resources,$planet,$buildings);
            $after=FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$caps,86400)['stocks'];
            $plant=$operation->calculate($this->catalog->getTierForUniverse($universe,'buildings','power_plant','basic'),'buildings',1,1,$buildings,$this->catalog->forUniverse($universe));
            foreach($plant['costs'] as $resource=>$amount)self::assertGreaterThanOrEqual($amount,$after[$resource],"$kind/$position/centrale/$resource");
        }
    }
}
