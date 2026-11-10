<?php
namespace App\Tests\Service;

use App\Entity\{Universe,Galaxy,SolarSystem,Planet};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{FactoryEconomy,ResourceService};
use App\Service\Event\GameEventService;
use App\Service\Player\VacationCalendar;
use App\Service\Queue\OperationCalculator;
use App\Tests\Support\ProducerBalanceReference;
use Doctrine\ORM\{EntityManagerInterface,EntityRepository};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class ProducerBalanceTest extends TestCase
{
    private GameCatalog $catalog;
    private ResourceService $resources;
    protected function setUp():void
    {
        $this->catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');
        $repository=$this->createStub(EntityRepository::class);$repository->method('findBy')->willReturn([]);
        $em=$this->createStub(EntityManagerInterface::class);$em->method('getRepository')->willReturn($repository);
        $repo=new GameRepository($em);$manager=new GameManager($em);$clock=new MockClock('2026-11-09T12:00:00Z');
        $this->resources=new ResourceService($repo,$manager,$this->catalog,$clock,new GameEventService($repo,$manager,$clock),new VacationCalendar($repo,$clock));
    }
    private function planet(int $position=5,int $speed=1):Planet
    {
        $u=new Universe();$u->slug='producer-regression';$u->name='Production';
        $u->gameSettings=['multipliers'=>['resource_production'=>$speed,'building_time'=>1/$speed]];
        $g=new Galaxy();$g->universe=$u;$s=new SolarSystem();$s->galaxy=$g;$p=new Planet();$p->solarSystem=$s;$p->position=$position;return $p;
    }
    private function value(array $costs):int
    {
        return 2*($costs['metal']??0)+4*($costs['nano']??0)+8*($costs['antimatter']??0)+($costs['hydrogen']??0);
    }
    public function testCatalogMatchesApprovedDefinitionsAndPreservesOtherFieldsAndCurves():void
    {
        $fixture=ProducerBalanceReference::fixture();
        foreach($fixture['definitions'] as $slug=>$expected){
            $actual=$this->catalog->get('buildings',$slug);self::assertSame($expected,$actual,$slug);
            $old=$fixture['historical_definitions'][$slug];
            foreach(['base_build_time','production_per_level','electricity_use_per_level','hydrogen_per_output','cost_profiles','economy_value_budget','effect_note'] as $field)unset($old[$field],$actual[$field]);
            self::assertSame($old,$actual,$slug.' : identité et prérequis conservés');
        }
        $rules=$this->catalog->rules();
        self::assertSame(4,$rules['antimatter_hydrogen_per_unit']);
        $old=$fixture['historical_rules'];
        foreach(['antimatter_hydrogen_per_unit','initial_stock','colony_initial_stock','base_production','base_storage_capacity'] as $field)unset($rules[$field],$old[$field]);
        self::assertSame($old,$rules);
    }
    public function testApprovedBasicPricesAndDerivedRankBudgetsAreExact():void
    {
        foreach(['mine_metal'=>[1600,2000,2500],'mine_nano'=>[1600,2000,2502],'mine_hydrogen'=>[1600,2000,2502],'mine_antimatter'=>[1200,1500,1875],'power_plant'=>[4800,6000,7500]] as $slug=>$budgets){
            foreach(GameCatalog::TIERS as $i=>$tier){$costs=$this->catalog->costs($this->catalog->withTier($this->catalog->get('buildings',$slug),$tier));self::assertSame($budgets[$i],$this->value($costs),$slug.'/'.$tier);}
        }
    }
    public function testRealQuotesAt20And40And80MatchThePreImplementationSimulation():void
    {
        $calculator=new OperationCalculator($this->catalog);$p=$this->planet();$u=$p->solarSystem->galaxy->universe;
        foreach(ProducerBalanceReference::fixture()['quotes'] as $row){
            $tech=$row['research']==='none'?[]:['engineering_foundations'=>['basic'=>25],'nanomaterial_synthesis'=>['basic'=>25]];
            $actual=$calculator->calculate($this->catalog->getTierForUniverse($u,'buildings',$row['building'],'basic'),'buildings',$row['level'],1,[],$this->catalog->forUniverse($u),$tech);
            self::assertSame($row['costs'],$actual['costs']);self::assertSame($row['duration_seconds'],$actual['duration']);
        }
    }
    public function testNetStocksAndDepletedResourcesMatch108FrozenScenariosAndHourlyReplay():void
    {
        $historicalRules=ProducerBalanceReference::fixture()['historical_rules'];
        $historicalRules['antimatter_hydrogen_per_unit']=$this->catalog->rules()['antimatter_hydrogen_per_unit'];
        $property=new \ReflectionProperty(GameCatalog::class,'data');$data=$property->getValue($this->catalog);
        $data['rules']=$historicalRules;$property->setValue($this->catalog,$data);
        $method=new \ReflectionMethod(ResourceService::class,'operatingInput');$count=0;
        foreach(ProducerBalanceReference::fixture()['economy'] as $row){
            $p=$this->planet($row['position'],$row['speed']);$levels=array_fill_keys(array_keys(ProducerBalanceReference::fixture()['definitions']),['basic'=>$row['level']]);
            if($row['shape']==='half_power')$levels['power_plant']['basic']=$row['level']/2;
            if($row['shape']==='double_reactor')$levels['mine_antimatter']['basic']=$row['level']*2;
            $input=$method->invoke($this->resources,$p,$levels);$stocks=array_fill_keys(array_keys($this->catalog->rules()['resources']),0.0);$caps=[];
            foreach($stocks as $r=>$_)$caps[$r]=$row['capacities']==='unlimited'?1e20:$this->resources->capacity($p,$r,$levels);
            $result=FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$caps,86400);
            foreach($row['final_stocks'] as $r=>$amount)self::assertEqualsWithDelta($amount,$result['stocks'][$r],1e-5,$row['shape'].'/'.$r);
            self::assertEqualsWithDelta($row['final_efficiency'],$result['efficiency'],1e-9);self::assertEqualsWithDelta($row['final_antimatter_efficiency'],$result['antimatter_efficiency'],1e-9);
            for($hour=0;$hour<24;$hour++)$stocks=FactoryEconomy::simulate($input['base'],$input['factories'],$stocks,$caps,3600)['stocks'];
            foreach($stocks as $r=>$amount)self::assertEqualsWithDelta($result['stocks'][$r],$amount,1e-5);++$count;
        }
        self::assertSame(108,$count);
    }
    public function testCumulativeConstructionTimesAndRoundedPricesMatchTheConfirmedRhythm():void
    {
        $calculator=new OperationCalculator($this->catalog);$fixture=ProducerBalanceReference::fixture();
        foreach($fixture['schedules'] as $row){
            $p=$this->planet(5,$row['speed']);$u=$p->solarSystem->galaxy->universe;$seconds=0;$costs=array_fill_keys(array_keys($row['costs']),0);
            $tech=$row['research']==='none'?[]:['engineering_foundations'=>['basic'=>25],'nanomaterial_synthesis'=>['basic'=>25]];
            foreach($fixture['definitions'] as $slug=>$_)for($level=1;$level<=$row['level'];$level++){
                $quote=$calculator->calculate($this->catalog->getTierForUniverse($u,'buildings',$slug,'basic'),'buildings',$level,1,[],$this->catalog->forUniverse($u),$tech);
                $seconds+=$quote['duration'];foreach($quote['costs'] as $r=>$amount)$costs[$r]+=$amount;
            }
            self::assertSame($row['duration_seconds'],$seconds);foreach($costs as $r=>$amount)self::assertEquals($row['costs'][$r],$amount);
        }
    }
    public function testRankFactorsAndPerUniverseOverridesRemainAuthoritative():void
    {
        $p=$this->planet();$u=$p->solarSystem->galaxy->universe;$method=new \ReflectionMethod(ResourceService::class,'operatingInput');
        foreach(['basic'=>1.0,'intermediate'=>1.5,'superior'=>2.25] as $tier=>$factor){
            $input=$method->invoke($this->resources,$p,['mine_antimatter'=>[$tier=>1],'power_plant'=>[$tier=>1]]);$factories=array_column($input['factories'],null,'slug');
            self::assertEqualsWithDelta(90*$factor,$factories['mine_antimatter']['rate']*3600,1e-9);self::assertEqualsWithDelta(360*$factor,$factories['mine_antimatter']['hydrogen']*3600,1e-9);
            self::assertEqualsWithDelta(1080*$factor,$factories['power_plant']['rate']*3600,1e-9);
        }
        $u->gameSettings['catalog_overrides']['buildings']['mine_antimatter']=['production_per_level'=>.1,'hydrogen_per_output'=>8];
        $input=$method->invoke($this->resources,$p,['mine_antimatter'=>['basic'=>1]]);$factory=$input['factories'][0];
        self::assertEqualsWithDelta(360,$factory['rate']*3600,1e-9);self::assertEqualsWithDelta(2880,$factory['hydrogen']*3600,1e-9);
    }
}
