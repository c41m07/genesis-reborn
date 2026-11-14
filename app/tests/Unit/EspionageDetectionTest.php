<?php
namespace App\Tests\Unit;

use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\EspionageDetection;
use PHPUnit\Framework\TestCase;

final class EspionageDetectionTest extends TestCase
{
    private function settings():array { return (new GameCatalog(dirname(__DIR__,2).'/config/game'))->all('espionage'); }

    public function testRanksAndMixedMissionsUseCountsWithoutIntelBonus():void
    {
        foreach([
            [['basic'=>1],50], [['intermediate'=>1],40], [['superior'=>1],30],
            [['basic'=>4,'superior'=>1],50], [['basic'=>1,'intermediate'=>2],45],
            [['basic'=>3,'superior'=>5],45], [['intermediate'=>2,'superior'=>1],39],
            [['basic'=>0,'superior'=>1],30],
        ] as [$probes,$expected]) self::assertSame($expected,EspionageDetection::chance($probes,0,0,0,$this->settings()),json_encode($probes));
    }

    public function testDefensiveCompleteBatchesAndCap():void
    {
        foreach([0=>30,4=>30,5=>31,99=>49,100=>50,101=>50,10000=>50] as $defenders=>$expected) {
            self::assertSame($expected,EspionageDetection::chance(['superior'=>1],0,0,$defenders,$this->settings()));
        }
    }

    public function testTechnologiesExtraProbesRoundingAndLimits():void
    {
        foreach([
            [['basic'=>1],2,1,0,60], [['superior'=>1],3,1,100,70],
            [['basic'=>1],10,0,0,95], [['superior'=>1],0,10,0,5],
            [['basic'=>101],0,0,0,95], [['basic'=>3,'superior'=>5],5,0,0,95],
            [['basic'=>1,'superior'=>7],0,0,0,40],
        ] as [$probes,$counter,$stealth,$defenders,$expected]) self::assertSame($expected,EspionageDetection::chance($probes,$counter,$stealth,$defenders,$this->settings()));
    }

    public function testValuesComeFromConfiguration():void
    {
        $settings=$this->settings();$settings['base_detection_by_tier']['superior']=12;
        $settings['local_probe_batch_size']=2;$settings['local_probe_weight']=2;$settings['local_probe_bonus_cap']=3;
        self::assertSame(15,EspionageDetection::chance(['superior'=>1],0,0,100,$settings));
    }

    public function testEmptyMissionIsRejected():void
    {
        $this->expectException(\LogicException::class);
        EspionageDetection::chance(['basic'=>0],0,0,0,$this->settings());
    }
}
