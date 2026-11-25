<?php
namespace App\Tests\Service;

use App\Service\Catalog\GameCatalog;
use App\Service\Requirement\RequirementChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RankProgressionRequirementsTest extends TestCase
{
    /** La référence métier est indépendante des fichiers du catalogue. */
    public static function profiles():iterable
    {
        $buildings=[
            'shipyard'=>['mine_metal'=>2,'mine_nano'=>2],
            'fortress'=>['mine_metal'=>1,'mine_nano'=>1],
            'storage_metal'=>['mine_metal'=>5], 'storage_nano'=>['mine_nano'=>5],
            'storage_hydrogen'=>['mine_hydrogen'=>5], 'storage_electricity'=>['power_plant'=>5],
            'storage_antimatter'=>['mine_antimatter'=>5],
        ];
        $technologies=[
            'engineering_foundations'=>[1,[]], 'naval_engineering'=>[2,['engineering_foundations'=>2]],
            'colonial_expansion'=>[2,['naval_engineering'=>1]],
            'nanomaterial_synthesis'=>[3,['engineering_foundations'=>3]],
            'weapons_engineering'=>[3,['naval_engineering'=>2]],
            'tactical_intelligence'=>[3,['naval_engineering'=>2]],
            'modular_cargo_bays'=>[3,['naval_engineering'=>2]],
            'vector_propulsion'=>[4,['naval_engineering'=>3]],
            'weapon_calibration'=>[4,['weapons_engineering'=>2]],
            'structural_armor'=>[4,['nanomaterial_synthesis'=>2,'weapons_engineering'=>2]],
            'target_acquisition'=>[5,['weapon_calibration'=>2,'naval_engineering'=>3]],
            'cryogenic_reserves'=>[5,['vector_propulsion'=>2]],
            'automated_defense_repair'=>[5,['structural_armor'=>2]],
            'intelligence_countermeasures'=>[5,['tactical_intelligence'=>2]],
            'signature_masking'=>[6,['tactical_intelligence'=>3]],
            'signature_reduction'=>[7,['signature_masking'=>2,'nanomaterial_synthesis'=>3]],
            'shield_harmonics'=>[7,['structural_armor'=>3,'weapons_engineering'=>3]],
            'evasive_navigation'=>[8,['vector_propulsion'=>4,'target_acquisition'=>3]],
            'rapid_fire_cycle'=>[10,['weapons_engineering'=>5,'target_acquisition'=>4]],
        ];
        $ships=[
            'fighter'=>[1,[]], 'probe'=>[1,[]], 'transport'=>[2,['modular_cargo_bays'=>1]],
            'colony_ship'=>[2,['colonial_expansion'=>1]],
            'interceptor'=>[3,['vector_propulsion'=>1,'weapon_calibration'=>1]],
            'heavy_fighter'=>[4,['structural_armor'=>1,'weapon_calibration'=>2]],
            'corvette'=>[5,['naval_engineering'=>4,'vector_propulsion'=>2,'structural_armor'=>2]],
            'bomber'=>[6,['weapons_engineering'=>3,'target_acquisition'=>2]],
            'stealth_fighter'=>[6,['signature_masking'=>2,'vector_propulsion'=>2]],
            'frigate'=>[8,['structural_armor'=>3,'shield_harmonics'=>1]],
            'destroyer'=>[10,['weapons_engineering'=>5,'target_acquisition'=>3,'shield_harmonics'=>2]],
            'cruiser'=>[12,['structural_armor'=>5,'shield_harmonics'=>4,'vector_propulsion'=>4]],
            'battleship'=>[15,['structural_armor'=>7,'shield_harmonics'=>6,'rapid_fire_cycle'=>2]],
            'capital'=>[20,['weapons_engineering'=>10,'structural_armor'=>10,'shield_harmonics'=>10,'rapid_fire_cycle'=>5,'vector_propulsion'=>7]],
        ];
        $defenses=[
            'ground_turret_anti_light'=>[1,[]],
            'ground_turret_anti_escort'=>[3,['weapons_engineering'=>2,'target_acquisition'=>1]],
            'ground_turret_anti_heavy'=>[5,['weapons_engineering'=>5,'structural_armor'=>3]],
            'ground_turret_anti_capital'=>[8,['weapons_engineering'=>8,'target_acquisition'=>4,'rapid_fire_cycle'=>3]],
        ];
        foreach(GameCatalog::TIERS as $index=>$tier){
            foreach (['mine_metal', 'mine_nano', 'mine_hydrogen', 'power_plant', 'lab'] as $slug) {
                $rules = [];
                $isLaboratory = $slug === 'lab';
                if ($index > 0) {
                    $lowerLevel = $isLaboratory ? 10 : 25;
                    $basicLevel = $index === 2 && !$isLaboratory ? 50 : $lowerLevel;
                    $rules['buildings'] = [
                        ['slug' => $slug, 'tier' => 'basic', 'level' => $basicLevel],
                    ];
                    if ($index === 2) {
                        $rules['buildings'][] = ['slug' => $slug, 'tier' => 'intermediate', 'level' => $lowerLevel];
                    }
                }
                if ($index > 0 || $slug === 'mine_hydrogen') {
                    // Le laboratoire utilise la recherche précédente pour rester débloquable.
                    $researchTier = $isLaboratory ? GameCatalog::TIERS[$index - 1] : $tier;
                    $rules['technologies'] = [
                        ['slug' => 'engineering_foundations', 'tier' => $researchTier, 'level' => $slug === 'mine_hydrogen' ? 3 : 5],
                    ];
                }
                yield 'buildings/'.$slug.'/'.$tier => ['buildings', $slug, $tier, $rules];
            }
            $antimatterBuildings = [['slug' => 'mine_hydrogen', 'tier' => $tier, 'level' => 5]];
            if ($tier !== 'basic') {
                $antimatterBuildings[] = ['slug' => 'mine_antimatter', 'tier' => 'basic', 'level' => $tier === 'superior' ? 50 : 25];
            }
            if ($tier === 'superior') {
                $antimatterBuildings[] = ['slug' => 'mine_antimatter', 'tier' => 'intermediate', 'level' => 25];
            }
            yield 'buildings/mine_antimatter/'.$tier => [
                'buildings',
                'mine_antimatter',
                $tier,
                [
                    'buildings' => $antimatterBuildings,
                    'technologies' => [['slug' => 'engineering_foundations', 'tier' => $tier, 'level' => 5]],
                ],
            ];

            foreach(['buildings'=>$buildings,'technologies'=>$technologies,'ships'=>$ships,'defenses'=>$defenses] as $group=>$entries){
                foreach($entries as $slug=>$spec){
                    $rules=$group==='buildings'?['buildings'=>$spec]:[
                        'buildings'=>[match($group){'technologies'=>'lab','ships'=>'shipyard',default=>'fortress'}=>$spec[0]],
                        'technologies'=>$spec[1],
                    ];
                    $expected=[];
                    foreach($rules as $kind=>$parents)foreach($parents as $parent=>$level)
                        $expected[$kind][]=['slug'=>$parent,'tier'=>$tier,'level'=>$level];
                    if ($group === 'technologies' && $index > 0) {
                        $expected['technologies'][] = ['slug' => $slug, 'tier' => GameCatalog::TIERS[$index - 1], 'level' => 5];
                    }
                    if ($group === 'buildings') {
                        $previousRankLevel = match ($slug) {
                            'shipyard' => 20,
                            'fortress' => 8,
                            default => 5, // Les stockages sont les seules autres familles de cette liste.
                        };
                        foreach (array_slice(GameCatalog::TIERS, 0, $index) as $previousTier) {
                            $expected['buildings'][] = ['slug' => $slug, 'tier' => $previousTier, 'level' => $previousRankLevel];
                        }
                    }
                    yield $group.'/'.$slug.'/'.$tier=>[$group,$slug,$tier,$expected];
                }
            }
        }
    }

    #[DataProvider('profiles')]
    public function testCompleteGridAndEveryRequiredThreshold(string $group,string $slug,string $tier,array $expected):void
    {
        $catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');
        $checker=new RequirementChecker($catalog);
        $definition=$catalog->get($group,$slug);
        self::assertSame($expected,$checker->forTier($definition,$tier));
        self::assertSame($expected,$checker->forLevel($catalog->withTier($definition,$tier),25));
        $levels=['buildings'=>[],'technologies'=>[]];
        foreach($expected as $kind=>$parents)foreach($parents as $parent)
            $levels[$kind][$parent['slug']][$parent['tier']]=$parent['level'];
        self::assertSame([],$checker->check($expected,$levels['buildings'],$levels['technologies']));
        foreach($expected as $kind=>$parents)foreach($parents as $parent){
            $insufficient=$levels;
            --$insufficient[$kind][$parent['slug']][$parent['tier']];
            self::assertNotEmpty($checker->check($expected,$insufficient['buildings'],$insufficient['technologies']));
            // Un niveau élevé dans les autres rangs ne remplace jamais le rang demandé.
            foreach(GameCatalog::TIERS as $otherTier)if($otherTier!==$parent['tier'])
                $insufficient[$kind][$parent['slug']][$otherTier]=200;
            self::assertNotEmpty($checker->check($expected,$insufficient['buildings'],$insufficient['technologies']));
        }
    }
}
