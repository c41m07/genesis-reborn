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

    public function testFactoryBenefitCardsShowTheNewProductionRanksAndConsumption():void
    {
        $insights=self::getContainer()->get(CatalogueInsights::class);
        $universe=$this->planet->solarSystem->galaxy->universe;
        $plant=$this->catalog->getTierForUniverse($universe,'buildings','power_plant','superior');
        $rows=array_column($insights->benefits($plant,'buildings',1,$this->planet,[],[]),null,'label');
        self::assertEqualsWithDelta(1620,$rows['Production de électricité']['after']-$rows['Production de électricité']['before'],1e-8);
        $reactor=$this->catalog->getTierForUniverse($universe,'buildings','mine_antimatter','superior');
        $rows=array_column($insights->benefits($reactor,'buildings',1,$this->planet,[],[]),null,'label');
        self::assertEqualsWithDelta(810,$rows['Production de antimatière']['after']-$rows['Production de antimatière']['before'],1e-8);
        self::assertEqualsWithDelta(6480,$rows['Consommation d’hydrogène']['after']-$rows['Consommation d’hydrogène']['before'],1e-8);
        self::assertEqualsWithDelta(405,$rows['Consommation électrique']['after']-$rows['Consommation électrique']['before'],1e-8);
    }

    public function testShipCharacteristicsIncludeResearchForTheSelectedRank():void
    {
        $max=['basic'=>25,'intermediate'=>25,'superior'=>25];
        $tech=array_fill_keys(['weapons_engineering','structural_armor','shield_harmonics','weapon_calibration','target_acquisition','evasive_navigation','signature_reduction','vector_propulsion','modular_cargo_bays','cryogenic_reserves','rapid_fire_cycle'],$max);
        $insights=self::getContainer()->get(CatalogueInsights::class);
        $definition=$this->catalog->getTierForUniverse($this->planet->solarSystem->galaxy->universe,'ships','destroyer','intermediate');
        $rows=array_column($insights->benefits($definition,'ships',1,$this->planet,[],$tech),null,'label');
        foreach(['Attaque'=>876.25,'Structure'=>3000.0,'Bouclier maximal'=>1125.0,'Précision de tir'=>115.0,'Suivi de cible'=>80.5,'Capacité d’esquive'=>9.6,'Signature · faible = moins ciblé'=>33.75,'Tir secondaire anti-léger'=>187.5,'Chance de tir supplémentaire par tir de base'=>50.0] as $label=>$expected){
            self::assertEqualsWithDelta($expected,$rows[$label]['value'],1e-8,$label);
        }
        foreach(['Vitesse dans le système'=>'speed_system','Vitesse entre systèmes'=>'speed_galaxy','Capacité de soute'=>'cargo_capacity','Réservoir d’hydrogène'=>'fuel_capacity'] as $label=>$stat)self::assertEqualsWithDelta($definition[$stat]*1.25,$rows[$label]['value'],1e-8,$label);
        self::assertSame('2–4',$rows['Tirs principaux par round']['text']);
        self::assertSame('6–6',$rows['Tirs secondaires par round']['text']);
        self::assertSame(50,$rows['Correction de précision secondaire']['value']);
        self::assertSame(701,$definition['base_attack'],'Le catalogue de base ne doit pas être modifié.');
        $twig=self::getContainer()->get(\Twig\Environment::class);
        $html=$twig->render('ui/catalogue_benefits.html.twig',['card'=>['maximum'=>false,'benefits'=>array_values($rows),'details'=>['unlocks'=>[]],'target'=>1],'group'=>'ships','d'=>$definition,'show_locked_items'=>false]);
        self::assertStringContainsString('876,25',$html);
        self::assertStringContainsString('Vos recherches sont incluses',$html);
    }

    public function testShipCharacteristicsWithoutResearchKeepBaseValuesAndUnarmedShipsDoNotGainShots():void
    {
        $insights=self::getContainer()->get(CatalogueInsights::class);
        $definition=$this->catalog->getTierForUniverse($this->planet->solarSystem->galaxy->universe,'ships','fighter','superior');
        $rows=array_column($insights->benefits($definition,'ships',1,$this->planet,[],[]),null,'label');
        self::assertSame(54,$rows['Attaque']['value']);
        self::assertSame(338,$rows['Structure']['value']);
        self::assertSame(113,$rows['Bouclier maximal']['value']);
        self::assertSame(0.0,$rows['Chance de tir supplémentaire par tir de base']['value']);
        self::assertSame(0,$rows['Structure']['decimals']);
        $probe=$this->catalog->getTierForUniverse($this->planet->solarSystem->galaxy->universe,'ships','probe','basic');
        $rows=array_column($insights->benefits($probe,'ships',1,$this->planet,[],['rapid_fire_cycle'=>['basic'=>25,'intermediate'=>25,'superior'=>25]]),null,'label');
        self::assertSame('Aucun',$rows['Tirs principaux par round']['text']);
        self::assertArrayNotHasKey('Chance de tir supplémentaire par tir de base',$rows);
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

    public function testInitialUnlocksMatchTheExactResearchRank():void
    {
        $basic=$this->describe('technologies','signature_masking')['unlocks'];
        self::assertNotEmpty(array_filter($basic,static fn(array $u):bool=>$u['slug']==='signature_reduction'&&$u['tier']==='intermediate'&&$u['requiredLevel']===5));
        $intermediate=$this->describe('technologies','signature_masking','intermediate')['unlocks'];
        self::assertEmpty(array_filter($intermediate,static fn(array $u):bool=>$u['slug']==='signature_reduction'&&$u['tier']==='intermediate'));
        self::assertNotEmpty(array_filter($intermediate,static fn(array $u):bool=>$u['slug']==='signature_reduction'&&$u['tier']==='superior'&&$u['requiredLevel']===5));
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

    public function testAllInitialRequirementsAreReachableWithinRankCaps():void
    {
        foreach(['buildings','technologies','ships','defenses'] as $group)foreach($this->catalog->all($group) as $base)foreach(GameCatalog::TIERS as $tier){
            $details=$this->describe($group,$base['slug'],$tier);
            self::assertEmpty(array_filter($details['requirements'],static fn(array $r):bool=>$r['unreachable']),$group.'/'.$base['slug'].'/'.$tier);
            self::assertSame($details['requirements'],$this->describe($group,$base['slug'],$tier,25)['requirements']);
        }
        $blocked=$this->describe('ships','probe','superior')['requirements'];
        self::assertContains(5,array_column($blocked,'required'));
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
