<?php
namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class CatalogCostCompositionTest extends TestCase
{
    private const WEIGHTS=['metal'=>2,'nano'=>4,'antimatter'=>8,'hydrogen'=>1];
    private const SHARES=[
        'basic'=>['metal'=>60,'nano'=>40,'antimatter'=>0,'hydrogen'=>0],
        'intermediate'=>['metal'=>45,'nano'=>45,'antimatter'=>10,'hydrogen'=>0],
        'superior'=>['metal'=>30,'nano'=>45,'antimatter'=>20,'hydrogen'=>5],
    ];
    private function fixture():array
    {
        return json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/catalog-costs-2026-11-05.json'),true,512,JSON_THROW_ON_ERROR);
    }
    private function value(array $costs):int
    {
        $value=0;foreach(self::WEIGHTS as $resource=>$weight)$value+=($costs[$resource]??0)*$weight;return $value;
    }
    public function testHistoricalResourceRedistributionKeepsThePreviousBaseBudgets():void
    {
        $catalog=\App\Tests\Support\ProducerBalanceReference::historicalCatalog();$fixture=$this->fixture();$count=0;
        foreach(['buildings'=>13,'technologies'=>19,'ships'=>14,'defenses'=>4] as $group=>$expectedCount){
            self::assertCount($expectedCount,$catalog->all($group));
            foreach($fixture['entries'][$group] as $slug=>$expected){
                $definition=$catalog->get($group,$slug);$nonCost=$definition;unset($nonCost['cost_profiles']);
                $historical=$expected['non_cost_fields'];
                foreach(['levels','unlock_requirements','tier_unlock_requirements','description','tier_descriptions'] as $field){unset($historical[$field],$nonCost[$field]);}
                self::assertEquals($historical,$nonCost,"$group/$slug : hors coûts");
                foreach(self::SHARES as $tier=>$shares){
                    ++$count;$costs=$catalog->costs($catalog->withTier($definition,$tier));$budget=$this->value($expected['old_profiles'][$tier]);
                    self::assertSame($budget,$this->value($costs),"$group/$slug/$tier : budget");
                    foreach($shares as $resource=>$share)self::assertLessThanOrEqual(8.0,abs($costs[$resource]*self::WEIGHTS[$resource]-$budget*$share/100),"$group/$slug/$tier/$resource");
                    if($tier==='basic'){self::assertSame(0,$costs['antimatter']);self::assertSame(0,$costs['hydrogen']);}
                    elseif($tier==='intermediate'){self::assertGreaterThan(0,$costs['antimatter']);self::assertSame(0,$costs['hydrogen']);}
                    else{self::assertGreaterThan(0,$costs['antimatter']);self::assertGreaterThan(0,$costs['hydrogen']);}
                }
            }
        }
        self::assertSame(150,$count);
    }
    public function testHistoricalCostsAtCurveBoundariesAndAfterResearchMatchFrozenQuotes():void
    {
        $catalog=\App\Tests\Support\ProducerBalanceReference::historicalCatalog();$operation=new OperationCalculator($catalog);$fixture=$this->fixture();
        $universe=new Universe();$universe->slug='common-cost-study';$universe->name='Grille économique commune';$count=0;$maxima=[];
        foreach($fixture['entries'] as $group=>$entries)foreach($entries as $slug=>$entry)foreach($entry['costs'] as $tier=>$profiles)foreach($profiles as $profile=>$levels){
            $ranks=match($profile){'none'=>[],'mid'=>['basic'=>10,'intermediate'=>5,'superior'=>0],'max'=>['basic'=>25,'intermediate'=>25,'superior'=>25]};
            $tech=$ranks?($group==='buildings'?['nanomaterial_synthesis'=>$ranks,'engineering_foundations'=>$ranks]:['naval_engineering'=>$ranks]):[];
            foreach($levels as $level=>$quantities)foreach($quantities as $quantity=>$expected){
                ++$count;$actual=$operation->calculate($catalog->getTierForUniverse($universe,$group,$slug,$tier),$group,(int)$level,(int)$quantity,[],$catalog->forUniverse($universe),$tech);
                self::assertSame($expected['new'],$actual,"$group/$slug/$tier/$profile/$level/$quantity");
                self::assertSame($expected['old']['duration'],$actual['duration']);
                $delta=$this->value($actual['costs'])-$this->value($expected['old']['costs']);
                self::assertSame($expected['economic_difference'],$delta);
                if($profile==='none'&&(int)$level===1)self::assertSame(0,$delta);
                else self::assertLessThanOrEqual(28,abs($delta),'Borne des arrondis séparés lors de la croissance et de la réduction.');
                $maxima[$group]=max($maxima[$group]??0,abs($delta));
            }
        }
        self::assertSame($fixture['quote_count'],$count);self::assertSame($fixture['maximum_difference_by_group'],$maxima);
    }
    public function testStartingStocksCanFundBasicExtractorsWithoutRareInputs():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$operation=new OperationCalculator($catalog);
        $universe=new Universe();$universe->slug='bootstrap-study';$universe->name='Amorçage';$sum=array_fill_keys(array_keys(self::WEIGHTS),0);
        foreach(['mine_metal','mine_nano','mine_hydrogen','mine_antimatter'] as $slug){
            $costs=$operation->calculate($catalog->getTierForUniverse($universe,'buildings',$slug,'basic'),'buildings',1,1,[],$catalog->forUniverse($universe))['costs'];
            foreach($costs as $resource=>$amount)$sum[$resource]+=$amount;
        }
        self::assertSame(0,$sum['antimatter']);self::assertSame(0,$sum['hydrogen']);
        foreach(['initial_stock','colony_initial_stock'] as $kind)foreach($sum as $resource=>$amount)self::assertLessThanOrEqual($catalog->rules()[$kind][$resource],$amount,"$kind/$resource");
        self::assertGreaterThan(0,$catalog->rules()['base_production']['metal']);self::assertGreaterThan(0,$catalog->rules()['base_production']['nano']);
    }
}
