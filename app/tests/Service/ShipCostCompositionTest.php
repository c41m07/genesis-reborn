<?php
namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class ShipCostCompositionTest extends TestCase
{
    private const SHARES=[
        'basic'=>['metal'=>60,'nano'=>40,'antimatter'=>0,'hydrogen'=>0],
        'intermediate'=>['metal'=>45,'nano'=>45,'antimatter'=>10,'hydrogen'=>0],
        'superior'=>['metal'=>30,'nano'=>45,'antimatter'=>20,'hydrogen'=>5],
    ];
    private const WEIGHTS=['metal'=>2,'nano'=>4,'antimatter'=>8,'hydrogen'=>1];

    private function fixture():array
    {
        $fixture=json_decode(file_get_contents(dirname(__DIR__).'/Fixtures/catalog-costs-2026-11-05.json'),true,512,JSON_THROW_ON_ERROR);
        $ships=$fixture['entries']['ships'];
        foreach($ships as &$ship)foreach($ship['costs'] as &$profiles)foreach($profiles as &$levels)$levels=$levels[1];
        unset($ship,$profiles,$levels);
        return ['ships'=>$ships,'max_research_rounding_difference'=>$fixture['maximum_difference_by_group']['ships']];
    }

    private function value(array $costs):int
    {
        $value=0;foreach(self::WEIGHTS as $resource=>$weight)$value+=($costs[$resource]??0)*$weight;return $value;
    }

    public function testEveryShipKeepsItsBudgetAndOnlyItsResourceProfilesChange():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$fixture=$this->fixture();
        self::assertCount(14,$catalog->all('ships'));self::assertCount(14,$fixture['ships']);
        foreach($fixture['ships'] as $slug=>$expected){
            $definition=$catalog->get('ships',$slug);$nonCost=$definition;unset($nonCost['cost_profiles']);
            // JSON peut sérialiser une valeur flottante entière sans sa partie décimale.
            $historical=$expected['non_cost_fields'];
                foreach(['levels','unlock_requirements','tier_unlock_requirements','description','tier_descriptions'] as $field){unset($historical[$field],$nonCost[$field]);}
                self::assertEquals($historical,$nonCost,$slug.' : caractéristiques hors coûts');
            foreach(self::SHARES as $tier=>$shares){
                $costs=$catalog->costs($catalog->withTier($definition,$tier));$budget=$this->value($expected['old_profiles'][$tier]);
                self::assertSame($budget,$this->value($costs),"$slug/$tier : budget économique");
                if($tier==='superior')self::assertGreaterThan(0,$costs['hydrogen']);
                else self::assertSame(0,$costs['hydrogen']);
                foreach($shares as $resource=>$share){
                    // La tolérance couvre les quantités entières, sans tolérer un changement de budget.
                    self::assertLessThanOrEqual(8.0,abs($costs[$resource]*self::WEIGHTS[$resource]-$budget*$share/100),"$slug/$tier/$resource");
                }
                if($tier==='basic')self::assertSame(0,$costs['antimatter']);
                else self::assertGreaterThan(0,$costs['antimatter']);
            }
        }
    }

    public function testResearchAndBatchOrdersMatchFrozenRoundedQuotes():void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');$calculator=new OperationCalculator($catalog);$fixture=$this->fixture();
        $universe=new Universe();$universe->slug='cost-study';$universe->name='Étude des coûts';$maximumDifference=0;
        foreach($fixture['ships'] as $slug=>$ship)foreach($ship['costs'] as $tier=>$profiles)foreach($profiles as $profile=>$quantities){
            $ranks=match($profile){'none'=>[],'mid'=>['basic'=>10,'intermediate'=>5,'superior'=>0],'max'=>['basic'=>25,'intermediate'=>25,'superior'=>25]};
            foreach($quantities as $quantity=>$expected){
                $actual=$calculator->calculate($catalog->getTierForUniverse($universe,'ships',$slug,$tier),'ships',1,(int)$quantity,[],$catalog->forUniverse($universe),$ranks?['naval_engineering'=>$ranks]:[]);
                $expected['new']['costs']['electricity']=(int)ceil($expected['new']['duration']/60);
                self::assertSame($expected['new'],$actual,"$slug/$tier/$profile/$quantity");
                self::assertSame($expected['old']['duration'],$actual['duration']);
                $difference=$this->value($actual['costs'])-$this->value($expected['old']['costs']);
                self::assertSame($expected['economic_difference'],$difference);
                if($profile==='none')self::assertSame(0,$difference);
                else self::assertLessThanOrEqual(14,abs($difference),'Borne des arrondis séparés des quatre ressources.');
                $maximumDifference=max($maximumDifference,abs($difference));
            }
        }
        self::assertSame($fixture['max_research_rounding_difference'],$maximumDifference);
    }
}
