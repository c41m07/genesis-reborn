<?php
namespace App\Tests\Service;

use App\Service\Colony\FactoryEconomy;
use PHPUnit\Framework\TestCase;

final class FactoryEconomyTest extends TestCase
{
    public function testZeroElectricityStockDoesNotThrottleSufficientSupply():void
    {
        $result=FactoryEconomy::simulate(['electricity'=>.5,'hydrogen'=>0,'metal'=>0], [['resource'=>'metal','rate'=>1,'electricity'=>.5,'hydrogen'=>0]], ['electricity'=>0,'hydrogen'=>0,'metal'=>0],['electricity'=>100,'hydrogen'=>100,'metal'=>100],10);
        self::assertEqualsWithDelta(10,$result['stocks']['metal'],1e-8);self::assertEqualsWithDelta(0,$result['stocks']['electricity'],1e-8);
    }
    public function testBrownoutUsesAvailablePowerAndTenPercentFloor():void
    {
        $result=FactoryEconomy::simulate(['electricity'=>.45,'hydrogen'=>0,'metal'=>0], [['resource'=>'metal','rate'=>1,'electricity'=>1,'hydrogen'=>0]], ['electricity'=>0,'hydrogen'=>0,'metal'=>0],['electricity'=>100,'hydrogen'=>100,'metal'=>100],100);
        self::assertEqualsWithDelta(.505,$result['efficiency'],1e-8);self::assertEqualsWithDelta(50.5,$result['stocks']['metal'],1e-6);self::assertEqualsWithDelta(0,$result['stocks']['electricity'],1e-8);
    }
    public function testHydrogenConstrainsAntimatterAndNoHydrogenStopsIt():void
    {
        $factories=[['resource'=>'antimatter','rate'=>.01,'electricity'=>.1,'hydrogen'=>.08]];$caps=['electricity'=>100,'hydrogen'=>100,'antimatter'=>100];
        $limited=FactoryEconomy::simulate(['electricity'=>1,'hydrogen'=>.02,'antimatter'=>0],$factories,['electricity'=>0,'hydrogen'=>0,'antimatter'=>0],$caps,100);
        self::assertEqualsWithDelta(.25,$limited['antimatter_efficiency'],1e-8);self::assertEqualsWithDelta(.25,$limited['stocks']['antimatter'],1e-6);self::assertEqualsWithDelta(0,$limited['stocks']['hydrogen'],1e-8);
        $empty=FactoryEconomy::simulate(['electricity'=>1,'hydrogen'=>0,'antimatter'=>0],$factories,['electricity'=>0,'hydrogen'=>0,'antimatter'=>0],$caps,100);
        self::assertEqualsWithDelta(0,$empty['stocks']['antimatter'],1e-8);
    }
    public function testOfflineResultIsIndependentOfMaterializationFrequency():void
    {
        $base=['electricity'=>.4,'hydrogen'=>.03,'metal'=>.2];$factories=[['resource'=>'metal','rate'=>.2,'electricity'=>.5,'hydrogen'=>0],['resource'=>'antimatter','rate'=>.01,'electricity'=>.1,'hydrogen'=>.08]];$caps=['electricity'=>20,'hydrogen'=>20,'metal'=>20,'antimatter'=>20];$stocks=['electricity'=>2,'hydrogen'=>1,'metal'=>0,'antimatter'=>0];
        $single=FactoryEconomy::simulate($base,$factories,$stocks,$caps,3600);$first=FactoryEconomy::simulate($base,$factories,$stocks,$caps,1200);$split=FactoryEconomy::simulate($base,$factories,$first['stocks'],$caps,2400);
        foreach($single['stocks'] as $key=>$value)self::assertEqualsWithDelta($value,$split['stocks'][$key],1e-6,$key);
    }

    public function testAntimatterStopsConsumingWhenFullIncludingIfItFillsDuringTheAbsence():void
    {
        $base=['electricity'=>2,'hydrogen'=>.075,'antimatter'=>0];
        $factories=[['resource'=>'antimatter','rate'=>.1,'electricity'=>.05,'hydrogen'=>.8]];
        $caps=['electricity'=>20000,'hydrogen'=>20000,'antimatter'=>100];
        foreach([100=>10270,99=>10262] as $initial=>$expectedHydrogen){
            $result=FactoryEconomy::simulate($base,$factories,['electricity'=>1000,'hydrogen'=>10000,'antimatter'=>$initial],$caps,3600);
            self::assertSame(100.0,$result['stocks']['antimatter']);
            self::assertEqualsWithDelta($expectedHydrogen,$result['stocks']['hydrogen'],1e-7);
            self::assertSame(0.0,$result['hydrogen_consumption']);
            self::assertSame(0.0,$result['electricity_consumption']);
            self::assertSame(0.0,$result['rates']['antimatter']);
        }
    }

    public function testFullHydrogenTankKeepsSupplyingTheReactorAndStopsWithoutADownstreamConsumer():void
    {
        $base=['electricity'=>2,'hydrogen'=>.075,'antimatter'=>0];
        $hydrogen=['resource'=>'hydrogen','rate'=>1,'electricity'=>.1,'hydrogen'=>0];
        $reactor=['resource'=>'antimatter','rate'=>.1,'electricity'=>.05,'hydrogen'=>.8];
        $stocks=['electricity'=>1000,'hydrogen'=>20000,'antimatter'=>0];$caps=['electricity'=>20000,'hydrogen'=>20000,'antimatter'=>10000];
        $active=FactoryEconomy::simulate($base,[$hydrogen,$reactor],$stocks,$caps,3600);
        self::assertSame(20000.0,$active['stocks']['hydrogen']);
        self::assertEqualsWithDelta(360,$active['stocks']['antimatter'],1e-7);
        self::assertEqualsWithDelta(.1225,$active['electricity_consumption'],1e-10);
        $idle=FactoryEconomy::simulate($base,[$hydrogen],$stocks,$caps,3600);
        self::assertSame(0.0,$idle['electricity_consumption']);
        self::assertSame(0.0,$idle['rates']['hydrogen']);
    }

    public function testSaturatedMineFreesElectricityForOtherFactories():void
    {
        $result=FactoryEconomy::simulate(['electricity'=>1,'metal'=>0,'nano'=>0],[
            ['resource'=>'metal','rate'=>1,'electricity'=>1,'hydrogen'=>0],
            ['resource'=>'nano','rate'=>1,'electricity'=>1,'hydrogen'=>0],
        ],['electricity'=>0,'metal'=>100,'nano'=>0],['electricity'=>100,'metal'=>100,'nano'=>100],10);
        self::assertSame(1.0,$result['efficiency']);
        self::assertSame(10.0,$result['stocks']['nano']);
        self::assertSame(0.0,$result['rates']['metal']);
        self::assertSame(1.0,$result['electricity_consumption']);
    }

    public function testTinyElectricityResidueCannotDiscardTheRemainingProductionTime():void
    {
        $result=FactoryEconomy::simulate(['electricity'=>0,'metal'=>0],[['resource'=>'metal','rate'=>1,'electricity'=>1,'hydrogen'=>0]],['electricity'=>1e-8,'metal'=>0],['electricity'=>100,'metal'=>100],100);
        self::assertEqualsWithDelta(10,$result['stocks']['metal'],1e-8);
        self::assertSame(0.0,$result['stocks']['electricity']);
    }
}
