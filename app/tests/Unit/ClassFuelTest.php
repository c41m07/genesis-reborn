<?php
namespace App\Tests\Unit;

use App\Entity\{Fleet,PlayerUniverse,Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\{FleetFuel,FleetNavigation,MovementCalculator};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ClassFuelTest extends TestCase
{
    public static function coefficients(): iterable
    {
        foreach ([
            'probe'=>[0.01,0.04,0.006], 'interceptor'=>[0.04,0.16,0.024],
            'fighter'=>[0.05,0.20,0.030], 'stealth_fighter'=>[0.06,0.24,0.036],
            'heavy_fighter'=>[0.07,0.28,0.042], 'bomber'=>[0.10,0.40,0.060],
            'corvette'=>[0.12,0.48,0.072], 'frigate'=>[0.18,0.72,0.108],
            'destroyer'=>[0.25,1.00,0.150], 'cruiser'=>[0.30,1.20,0.180],
            'battleship'=>[0.40,1.60,0.240], 'capital'=>[0.50,2.00,0.300],
            'transport'=>[0.10,0.40,0.060], 'colony_ship'=>[0.15,0.60,0.090],
        ] as $slug=>$coefficients) {
            yield $slug=>[$slug,...$coefficients];
        }
    }

    private function catalog(): GameCatalog {return new GameCatalog(dirname(__DIR__,2).'/config/game');}
    private function legacy(): FleetFuel {return new FleetFuel(dirname(__DIR__,2).'/config/legacy/progression-operations-2026-11-15.json');}
    private function fleet(): Fleet
    {
        $universe=new Universe();$universe->slug='fuel-test';$universe->name='Carburant';
        $membership=new PlayerUniverse();$membership->universe=$universe;
        $fleet=new Fleet();$fleet->playerUniverse=$membership;return $fleet;
    }
    private function ship(string $slug,string $tier='basic',int $quantity=1): array
    {
        $catalog=$this->catalog();
        return $catalog->withTier($catalog->get('ships',$slug),$tier)+['quantity'=>$quantity];
    }

    #[DataProvider('coefficients')]
    public function testEveryClassRankQuantitySpeedAndOrbit(string $slug,float $normal,float $hyper,float $orbit): void
    {
        $calculator=new MovementCalculator();
        foreach (GameCatalog::TIERS as $tier) {
            $ship=$this->ship($slug,$tier,7);
            self::assertEquals($normal,$ship['fuel_travel_system']);
            self::assertEquals($hyper,$ship['fuel_travel_galaxy']);self::assertEquals($orbit,$ship['fuel_orbit']);
            $fast=$calculator->quote([1,1,0,0,5],[2,1,1,0,5],[$ship],1,1,'proportional',2);
            $slow=$calculator->quote([1,1,0,0,5],[2,1,1,0,5],[$ship],.5,1,'proportional',2);
            self::assertEqualsWithDelta(7*2*(100*$normal+100*$hyper)+7*2*$orbit,$fast['total_fuel'],1e-8);
            self::assertEqualsWithDelta($fast['one_way_fuel']/2,$slow['one_way_fuel'],1e-8);
            self::assertSame($fast['orbit_fuel'],$slow['orbit_fuel']);
            self::assertLessThanOrEqual($fast['fuel_capacity'],$fast['total_fuel'],'Le réservoir porte le trajet de référence.');
        }
    }

    public function testPromptReferencesAndMixedFleetUseRealMovementCalculation(): void
    {
        $calculator=new MovementCalculator();$origin=[1,1,0,0,5];$near=[2,1,1,0,5];
        foreach (['probe'=>10,'fighter'=>50,'transport'=>100,'capital'=>500] as $slug=>$expected) {
            $quote=$calculator->quote($origin,$near,[$this->ship($slug)],1,1,'proportional');
            self::assertEquals($expected,$quote['total_fuel']);
        }
        $ships=[$this->ship('fighter','basic',20)];$distant=[2,1,10,0,5];
        self::assertEquals(8200,$calculator->quote($origin,$distant,$ships,1,1,'proportional')['total_fuel']);
        self::assertEquals(82000,$calculator->quote($origin,$distant,$this->legacy()->forFleet($this->fleet(),$ships),1,1,'proportional')['total_fuel']);
        $mixed=[$this->ship('probe','basic',2),$this->ship('capital','superior',3),$this->ship('transport','intermediate',4)];
        self::assertEquals(1920,$calculator->quote($origin,$near,$mixed,1,1,'proportional')['total_fuel']);
    }

    public function testFrozenProfilesSurviveLaterCatalogChangesAndKeepOtherStatistics(): void
    {
        $fleet=$this->fleet();$ships=[$this->ship('probe'),$this->ship('transport','superior',3)];
        $quote=(new MovementCalculator())->quote([1,1,0,0,5],[2,1,1,0,5],$ships,1,1,'proportional');
        $fleet->navigation=(new FleetNavigation())->freeze([1,1,0,0,5],[2,1,1,0,5],$ships,1,'proportional',$quote,new \DateTimeImmutable());
        $changed=$ships;
        foreach ($changed as &$ship) {$ship['fuel_travel_system']=9;$ship['fuel_travel_galaxy']=99;$ship['fuel_orbit']=999;}
        unset($ship);
        self::assertSame($ships,$this->legacy()->forFleet($fleet,$changed));
    }

    public function testLegacyProfilesKeepPersistedUniverseOverridesWithoutChangingFleetState(): void
    {
        $fleet=$this->fleet();$fleet->reservedFuel=123;$fleet->cargo=['hydrogen'=>123];
        $fleet->playerUniverse->universe->gameSettings=['catalog_overrides'=>['ships'=>['transport'=>['fuel_travel_system'=>.25]]]];
        $ships=$this->legacy()->forFleet($fleet,[$this->ship('transport')]);
        self::assertSame(.25,$ships[0]['fuel_travel_system']);self::assertSame(2.0,$ships[0]['fuel_travel_galaxy']);
        self::assertNull($fleet->navigation);self::assertSame(123.0,$fleet->reservedFuel);self::assertSame(['hydrogen'=>123],$fleet->cargo);
    }

    public function testIncompleteRecordedProfileIsRefusedInsteadOfBeingRepriced(): void
    {
        $fleet=$this->fleet();$fleet->navigation=['fuel_profiles'=>[]];
        $this->expectException(\DomainException::class);
        $this->legacy()->forFleet($fleet,[$this->ship('fighter')]);
    }
}
