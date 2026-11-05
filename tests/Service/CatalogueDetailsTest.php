<?php
namespace App\Tests\Service;

use App\Entity\{Galaxy,Planet,SolarSystem,Universe};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\{CatalogueDetails,CatalogueInsights};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CatalogueDetailsTest extends KernelTestCase
{
    private GameCatalog $catalog;
    private CatalogueDetails $details;
    private Planet $planet;

    protected function setUp():void
    {
        self::bootKernel();$this->catalog=self::getContainer()->get(GameCatalog::class);
        $this->details=self::getContainer()->get(CatalogueDetails::class);
        $universe=new Universe();$universe->slug='omega-1';
        $galaxy=new Galaxy();$galaxy->universe=$universe;
        $system=new SolarSystem();$system->galaxy=$galaxy;
        $this->planet=new Planet();$this->planet->solarSystem=$system;$this->planet->position=5;
    }

    private function describe(string $group,string $slug,string $tier='basic',int $target=1,array $buildings=[],array $tech=[]):array
    {
        return $this->details->describe($this->catalog->getTierForUniverse($this->planet->solarSystem->galaxy->universe,$group,$slug,$tier),$group,$target,$this->planet,$buildings,$tech);
    }

    public function testEveryCatalogueRankHasUsefulDetailsAndRequirements():void
    {
        $count=0;
        foreach(['buildings','technologies','ships','defenses'] as $group)foreach($this->catalog->all($group) as $base)foreach(GameCatalog::TIERS as $tier){
            $details=$this->describe($group,$base['slug'],$tier);
            self::assertNotEmpty($details['intro'],$base['slug']);self::assertNotEmpty($details['scope']);self::assertNotEmpty($details['sections']);
            foreach($details['sections'] as $section){self::assertNotEmpty($section['title']);self::assertTrue($section['rows']!==[]||$section['paragraphs']!==[]);}
            if(in_array($group,['ships','defenses'],true))self::assertCount(isset($base['secondary_attack'])?8:7,$details['performance']);
            ++$count;
        }
        self::assertSame(150,$count);
    }

    public function testMilitarySheetIncludesResearchAndLocalFortressWithoutChangingBaseStats():void
    {
        $max=['basic'=>25,'intermediate'=>25,'superior'=>25];
        $details=$this->describe('ships','battleship',tech:['weapons_engineering'=>$max]);
        $stats=array_column($details['performance'],null,'label');
        self::assertEqualsWithDelta($stats['Attaque principale par tir']['base']*1.25,$stats['Attaque principale par tir']['effective'],1e-8);
        self::assertEqualsWithDelta($stats['Attaque secondaire par tir']['base']*1.25,$stats['Attaque secondaire par tir']['effective'],1e-8);
        $details=$this->describe('defenses','ground_turret_anti_light',buildings:['fortress'=>['basic'=>10,'intermediate'=>10,'superior'=>10]],tech:['structural_armor'=>$max]);
        $stats=array_column($details['performance'],null,'label');
        self::assertEqualsWithDelta($stats['Structure maximale']['base']*1.25*1.25,$stats['Structure maximale']['effective'],1e-8);
    }

    public function testColonizationDetailsShowLinearGainAndUniverseCap():void
    {
        $details=$this->describe('technologies','colonial_expansion');
        $section=$details['sections'][0];$rows=array_column($section['rows'],null,'label');
        self::assertSame(1,$rows['Limite totale actuelle']['value']);self::assertSame(2,$rows['Limite après ce niveau']['value']);
        self::assertStringContainsString('+1 emplacement',implode(' ',$section['paragraphs']));
        $this->planet->solarSystem->galaxy->universe->gameSettings=['limits'=>['max_planets'=>2]];
        $all=['colonial_expansion'=>['basic'=>4,'intermediate'=>4,'superior'=>3]];
        $section=$this->describe('technologies','colonial_expansion','superior',4,tech:$all)['sections'][0];
        self::assertSame(2,$section['rows'][1]['value']);
        self::assertStringContainsString('plafond de cet univers est atteint',implode(' ',$section['paragraphs']));
        $d=$this->catalog->withTier($this->catalog->get('technologies','colonial_expansion'),'superior');
        $benefit=self::getContainer()->get(CatalogueInsights::class)->benefits($d,'technologies',4,$this->planet,[],$all)[0];
        self::assertSame(2,$benefit['before']);self::assertSame(2,$benefit['after']);
    }

    public function testColonizationLinearRankCaps():void
    {
        $effect=$this->catalog->get('technologies','colonial_expansion')['effect'];
        self::assertSame(1,GameCatalog::colonyLimit(['basic'=>0,'intermediate'=>0,'superior'=>0],$effect,31));
        self::assertSame(2,GameCatalog::colonyLimit(['basic'=>1],$effect,31));
        self::assertSame(6,GameCatalog::colonyLimit(['basic'=>5],$effect,31));
        self::assertSame(16,GameCatalog::colonyLimit(['basic'=>5,'intermediate'=>10],$effect,31));
        self::assertSame(31,GameCatalog::colonyLimit(['basic'=>5,'intermediate'=>10,'superior'=>15],$effect,31));
        self::assertSame(9,GameCatalog::colonyLimit(['basic'=>5,'intermediate'=>10,'superior'=>15],$effect,9));
    }

    public function testUnlocksMatchTheExactResearchRankAndShowFutureMilestones():void
    {
        $basic=$this->describe('technologies','signature_masking')['unlocks'];
        self::assertNotEmpty(array_filter($basic,static fn(array $u):bool=>$u['slug']==='signature_reduction'&&$u['tier']==='intermediate'&&$u['requiredLevel']===5));
        $intermediate=$this->describe('technologies','signature_masking','intermediate')['unlocks'];
        self::assertEmpty(array_filter($intermediate,static fn(array $u):bool=>$u['slug']==='signature_reduction'&&$u['tier']==='intermediate'));
        self::assertNotEmpty(array_filter($intermediate,static fn(array $u):bool=>$u['slug']==='signature_reduction'&&$u['tier']==='superior'&&$u['requiredLevel']===10));
    }

    public function testCounterTargetNamesAreGenericAndIndependentOfShipRank():void
    {
        $reference=null;
        foreach(GameCatalog::TIERS as $tier){
            $details=$this->describe('ships','corvette',$tier);
            $sections=array_values(array_filter($details['sections'],static fn(array $s):bool=>$s['title']==='Contres et faiblesses'));
            self::assertCount(1,$sections);
            self::assertSame(['Chasseur','Capital'],array_column($sections[0]['rows'],'label'));
            self::assertStringContainsString('Chasseur standard, le Chasseur furtif et l’Intercepteur',implode(' ',$sections[0]['paragraphs']));
            if($reference===null)$reference=$sections[0]['rows'];else self::assertSame($reference,$sections[0]['rows']);
            self::assertStringNotContainsString('Dreadnought',json_encode($sections[0]));
            self::assertStringNotContainsString('approche silencieuse',json_encode($sections[0]));
        }
    }

    public function testImpossibleRequirementsAreReportedWithoutChangingTheirLevels():void
    {
        $count=0;
        foreach(['buildings','technologies','ships','defenses'] as $group)foreach($this->catalog->all($group) as $base)foreach(GameCatalog::TIERS as $tier){
            $details=$this->describe($group,$base['slug'],$tier);
            if(array_filter($details['requirements'],static fn(array $r):bool=>$r['unreachable']))++$count;
        }
        self::assertSame(10,$count);
        $requirements=$this->describe('ships','probe','superior')['requirements'];
        $blocked=array_values(array_filter($requirements,static fn(array $r):bool=>$r['unreachable']));
        self::assertCount(1,$blocked);self::assertSame(10,$blocked[0]['required']);self::assertSame(5,$blocked[0]['maximum']);
        $this->planet->solarSystem->galaxy->universe->gameSettings=['catalog_overrides'=>['technologies'=>['tactical_intelligence'=>['max_level'=>10]]]];
        $requirements=$this->describe('ships','probe','superior')['requirements'];
        self::assertEmpty(array_filter($requirements,static fn(array $r):bool=>$r['unreachable']));
    }

    public function testStorageShowsActualProtectionAndProbeDocumentsMissionConsumption():void
    {
        $section=$this->describe('buildings','storage_metal','intermediate',1)['sections'][0];
        $rows=array_column($section['rows'],null,'label');
        self::assertEqualsWithDelta(12500,$rows['Protection supplémentaire']['value'],.000001);
        $probe=$this->describe('ships','probe');$all=implode(' ',array_merge(...array_column($probe['sections'],'paragraphs')));
        self::assertStringContainsString('Toutes les sondes envoyées en espionnage sont consommées',$all);
        self::assertStringContainsString('sans escorte',$all);
    }
}
