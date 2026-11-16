<?php
namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator, TechnologyEffectCalculator};
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class EconomicUnitCatalogTest extends TestCase
{
    public function testSixProfilesKeepTheirPricesDurationsStatisticsAndRankRequirements(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $universe = new Universe();$universe->slug = 'civil-x1';$universe->name = 'Unités civiles';
        $calculator = new OperationCalculator($catalog);
        $prices = ['basic'=>[480,160,0,0], 'intermediate'=>[450,225,25,0], 'superior'=>[374,281,63,124]];
        foreach (['hydrogen_collector'=>[100,20,20,30,900,'mine_hydrogen','vector_propulsion',1],
            'solar_satellite'=>[40,0,0,40,450,'power_plant','engineering_foundations',2]] as $slug=>$reference) {
            [$hp,$shield,$evasion,$signature,$production,$building,$research,$researchLevel] = $reference;
            foreach (['basic'=>[180,300,1,1600], 'intermediate'=>[225,400,1.5,2000], 'superior'=>[282,500,2.25,2500]] as $tier=>$values) {
                [$seconds,$electricity,$factor,$tank] = $values;
                $unit = $catalog->getTierForUniverse($universe, 'ships', $slug, $tier);
                self::assertSame((float)($hp*$factor), (float)$unit['hp']);
                self::assertSame((float)($shield*$factor), (float)$unit['shield']);
                self::assertSame($evasion, $unit['evasion']);self::assertSame($signature, $unit['signature']);
                self::assertSame(0, $unit['base_attack']);self::assertSame(0, $unit['shots_min']);self::assertSame(0, $unit['shots_max']);
                self::assertSame(0, $unit['cargo_capacity']);
                self::assertEquals($production*$factor, $unit['economic_unit']['production_per_hour'][$tier]);
                self::assertSame(['buildings'=>[['slug'=>'shipyard','tier'=>$tier,'level'=>2],['slug'=>$building,'tier'=>$tier,'level'=>5]],
                    'technologies'=>[['slug'=>$research,'tier'=>$tier,'level'=>$researchLevel]]], $unit['tier_unlock_requirements'][$tier]);
                $quote = $calculator->calculate($unit, 'ships', 1, 1, [], $catalog->forUniverse($universe));
                self::assertSame($seconds, $quote['duration']);
                self::assertSame(array_combine(['metal','nano','antimatter','hydrogen'], $prices[$tier])+['electricity'=>$electricity], $quote['costs']);
                if ($slug === 'hydrogen_collector') {
                    $transport = $catalog->getTierForUniverse($universe, 'ships', 'transport', $tier);
                    self::assertSame($transport['speed_system'], $unit['speed_system']);
                    self::assertSame($transport['speed_galaxy'], $unit['speed_galaxy']);
                    self::assertSame($tank, $unit['fuel_capacity']);self::assertSame(['station'], $unit['allowed_missions']);
                } else {
                    self::assertFalse($unit['mobile']);self::assertSame([], $unit['allowed_missions']);
                    foreach (['speed_system','speed_galaxy','fuel_capacity','fuel_travel_system','fuel_travel_galaxy','fuel_orbit'] as $field) self::assertSame(0, $unit[$field]);
                }
            }
        }
    }

    public function testResearchNeverArmsCivilUnitsOrCreatesCargoAndSatelliteMobility(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $universe = new Universe();$universe->slug = 'civil-x1';$universe->name = 'Unités civiles';
        $levels = array_fill_keys(array_keys($catalog->all('technologies')), array_fill_keys(GameCatalog::TIERS, 25));
        $technology = new TechnologyEffectCalculator($catalog);
        foreach (['hydrogen_collector','solar_satellite'] as $slug) foreach (GameCatalog::TIERS as $tier) {
            $unit = $technology->applyToShips([$catalog->getTierForUniverse($universe, 'ships', $slug, $tier)], $levels, $universe)[0];
            self::assertEquals(0, $unit['base_attack']);self::assertEquals(0, $unit['cargo_capacity']);
            self::assertArrayNotHasKey('extra_shot_chances', $unit);
            if ($slug === 'solar_satellite') {
                self::assertEquals(0, $unit['speed_system']);self::assertEquals(0, $unit['speed_galaxy']);self::assertEquals(0, $unit['fuel_capacity']);
            }
            $fighter = $catalog->getTierForUniverse($universe, 'ships', 'fighter', 'basic');
            $result = (new CombatSimulator())->simulate([
                ['id'=>'attacker','role'=>'attacker','camp'=>'a','arrival_round'=>1,'units'=>[$fighter+['quantity'=>1]]],
                ['id'=>'civil','role'=>'defender','camp'=>'b','arrival_round'=>1,'units'=>[$unit+['quantity'=>3]]],
            ], [], $catalog->all('combat_modifiers')['modifiers'], 10, 7);
            foreach ($result['rounds'] as $round) foreach ($round['shots'] as $shot) self::assertStringStartsWith('attacker:', $shot['attacker']);
            foreach ($catalog->all('combat_modifiers')['modifiers'] as $targets) self::assertSame(1, $targets[$slug]);
        }
    }
    public function testSatelliteMovementIsRefusedEvenWithFictitiousPositiveSpeeds(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $unit = $catalog->withTier($catalog->get('ships','solar_satellite'),'superior');
        $unit['speed_system'] = 100;$unit['speed_galaxy'] = 100;$unit['quantity'] = 1;
        $this->expectException(\DomainException::class);
        (new \App\Service\Fleet\MovementCalculator())->quote([1,1,0,0,5],[2,1,1,0,5],[$unit],1,1,'proportional');
    }

}
