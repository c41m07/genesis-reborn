<?php
namespace App\Tests\Service;

use App\Entity\{Player,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator,CombatUnitFactory,PlayerCombatSimulation,TechnologyEffectCalculator};
use App\Service\Fleet\EspionageReportReader;
use App\Service\PirateCampaign\{PirateBattleScenario, PirateEncounterSnapshot};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class PlayerCombatSimulationBatchTest extends TestCase
{
    private PlayerCombatSimulation $simulation;
    private PlayerUniverse $owner;

    protected function setUp(): void
    {
        $em=$this->createMock(EntityManagerInterface::class);
        foreach(['find','getRepository','getConnection','persist','flush'] as $method) $em->expects(self::never())->method($method);
        $repo=new GameRepository($em);$catalog=new GameCatalog(dirname(__DIR__,2).'/config/game');
        $this->simulation=new PlayerCombatSimulation(new CombatSimulator(),new CombatUnitFactory($catalog,new TechnologyEffectCalculator($catalog)),$catalog,$repo,new EspionageReportReader($repo));
        $this->owner=new PlayerUniverse();$this->owner->player=new Player();$this->owner->universe=new Universe();$this->owner->universe->slug='batch-test';$this->owner->universe->name='Batch test';
    }

    public function testEveryTrialMatchesTheExistingEngineAndTheBatchIsReproducible(): void
    {
        $forces=['attacker'=>['ships'=>['fighter'=>['basic'=>8]],'research'=>['weapons_engineering'=>['basic'=>2]]],
            'defender'=>['ships'=>['fighter'=>['intermediate'=>2]],'arrival'=>2,'defenses'=>['ground_turret_anti_light'=>['basic'=>1]],'fortress'=>['basic'=>3]]];
        $batch=$this->simulation->runBatch($this->owner,$forces,12345);
        self::assertSame($batch,$this->simulation->runBatch($this->owner,$forces,12345));
        self::assertCount(10,$batch['trials']);self::assertSame(10,array_sum($batch['outcomes']));
        self::assertSame(range(12345,12354),array_column($batch['trials'],'seed'));
        foreach($batch['trials'] as $trial) {
            $expected=$this->simulation->run($this->owner,$forces,$trial['seed']);
            foreach(['outcome','losses','survivors','rounds_played'] as $field) self::assertSame($expected[$field],$trial[$field]);
            foreach($expected['rounds'] as $index=>$round) {
                self::assertSame($round['number'],$trial['rounds'][$index]['number']);
                self::assertSame(count($round['shots']),$trial['rounds'][$index]['shots']);
                self::assertSame(count(array_filter($round['shots'],fn($shot)=>$shot['hit'])),$trial['rounds'][$index]['hits']);
                self::assertSame(count(array_filter($round['shots'],fn($shot)=>$shot['destroyed'])),$trial['rounds'][$index]['destroyed']);
            }
        }
        $counts=array_count_values(array_column($batch['trials'],'outcome'));
        foreach($batch['outcomes'] as $outcome=>$count) self::assertSame($counts[$outcome]??0,$count);
    }

    public function testSeedsStayDistinctAtTheUpperBoundaryAndAllWinsGiveOneHundredPercent(): void
    {
        $batch=$this->simulation->runBatch($this->owner,['attacker'=>['ships'=>['fighter'=>['basic'=>1]]]],0xffffffff-4);
        self::assertSame([4294967291,4294967292,4294967293,4294967294,4294967295,0,1,2,3,4],array_column($batch['trials'],'seed'));
        self::assertSame(100,$batch['win_percent']);self::assertSame(['attacker_wins'=>10,'defender_wins'=>0,'draw'=>0,'ongoing'=>0],$batch['outcomes']);
    }

    public function testDefeatsAreCountedFromTheAttackersPerspective(): void
    {
        $batch=$this->simulation->runBatch($this->owner,['defender'=>['ships'=>['fighter'=>['basic'=>1]]]],0);
        self::assertSame(0,$batch['win_percent']);self::assertSame(['attacker_wins'=>0,'defender_wins'=>10,'draw'=>0,'ongoing'=>0],$batch['outcomes']);
    }

    public function testSevenWinsGiveSeventyPercentUsingAllTenCombats(): void
    {
        $this->owner->universe->gameSettings=['catalog_overrides'=>['ships'=>['fighter'=>['hp'=>1,'shield'=>0]]]];
        $forces=['attacker'=>['ships'=>['fighter'=>['basic'=>1]]],'defender'=>['ships'=>['fighter'=>['basic'=>1]]]];
        $batch=$this->simulation->runBatch($this->owner,$forces,14);
        self::assertSame(7,$batch['outcomes']['attacker_wins']);self::assertSame(3,$batch['outcomes']['defender_wins']);
        self::assertSame(70,$batch['win_percent']);self::assertSame(10,array_sum($batch['outcomes']));
    }

    public function testRequestBudgetSelectsBackgroundWithoutTruncatingEitherFleet(): void
    {
        $this->owner->universe->gameSettings=['catalog_overrides'=>['ships'=>['fighter'=>['base_attack'=>0,'shots_min'=>2,'shots_max'=>2]]]];
        $forces=['attacker'=>['ships'=>['fighter'=>['basic'=>250]]],'defender'=>['ships'=>['fighter'=>['basic'=>250]]]];
        $batch=$this->simulation->runBatch($this->owner,$forces,42);
        self::assertCount(10,$batch['trials']);self::assertCount(500,$batch['trials'][0]['survivors']);
        $this->owner->universe->gameSettings['catalog_overrides']['ships']['fighter']['shots_max']=3;
        $this->assertFalse($this->simulation->prepare($this->owner, $forces, 42)['inline']);
        $this->assertSame($this->simulation->run($this->owner, $forces, 42)['outcome'],
            $this->simulation->runTrial($this->simulation->prepare($this->owner, $forces, 42), 42, 0)['outcome']);
    }

    public function testTwoMillionShipFleetsPlusSeparateDefensesAreSnapshotted(): void
    {
        $forces = [
            'attacker' => ['ships' => ['fighter' => ['basic' => 600_000, 'intermediate' => 400_000]]],
            'defender' => [
                'arrival' => 3,
                'ships' => ['fighter' => ['basic' => 1_000_000]],
                'defenses' => ['ground_turret_anti_light' => ['basic' => 500_000]],
            ],
        ];

        $prepared = $this->simulation->prepare($this->owner, $forces, 42);
        self::assertFalse($prepared['inline']);
        self::assertTrue($prepared['grouped_survivors']);
        self::assertSame(1_000_000, array_sum(array_column($prepared['participants'][0]['units'], 'quantity')));
        self::assertSame(1_000_000, array_sum(array_column($prepared['participants'][1]['units'], 'quantity')));
        self::assertSame(500_000, $prepared['defenses'][0]['quantity']);
        self::assertSame(3, $prepared['participants'][1]['arrival_round']);
    }

    public function testPiratePreviewUsesFrozenRulesAndProfilesEvenAfterBalanceChanges(): void
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $units = new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog));
        $encounter = PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 3]], ['metal' => 100]);
        $pirates = $units->ships($this->owner->universe, $encounter->composition(), []);
        $pirates[0]['hp'] = 456;
        $modifiers = ['fighter' => ['fighter' => 0.3]];
        $battle = PirateBattleScenario::freeze($encounter, $pirates, $modifiers, 3, 21.0);
        $this->owner->universe->gameSettings = ['catalog_overrides' => ['ships' => ['fighter' => ['hp' => 77]]]];
        $scenario = $this->simulation->prepare($this->owner, [
            'attacker' => ['ships' => ['fighter' => ['basic' => 2]], 'arrival' => 3],
            'defender' => ['ships' => ['fighter' => ['basic' => 17]], 'arrival' => 3, 'defenses' => ['ground_turret_anti_light' => ['basic' => 15]]],
        ], 42, $battle);

        self::assertSame(77, $scenario['participants'][0]['units'][0]['hp']);
        self::assertSame($battle->snapshot()['units'], $scenario['participants'][1]['units']);
        self::assertSame(456, $scenario['participants'][1]['units'][0]['hp']);
        self::assertSame([1, 1], array_column($scenario['participants'], 'arrival_round'));
        self::assertSame($encounter->composition(), $scenario['forces']['defender']['ships']);
        self::assertSame($modifiers, $scenario['modifiers']);
        self::assertSame(3, $scenario['rounds']);
        self::assertSame(21.0, $scenario['recharge']);
        self::assertSame([], $scenario['defenses']);

        $simulator = new CombatSimulator();
        $native = $battle->resolve($simulator, PirateBattleScenario::freezeUnits($scenario['participants'][0]['units']), 42);
        $trial = $this->simulation->runTrial($scenario, 42, 0);
        self::assertSame($native['outcome'], $trial['outcome']);
        self::assertSame($native['rounds_played'], $trial['rounds_played']);
    }

    public function testOnlyBlankApprovedUnknownInputsBecomeZeroForTheCalculation(): void
    {
        $choices = $this->simulation->choices($this->owner);
        $draft = [
            'attacker' => ['ships' => ['fighter' => ['basic' => '4']]],
            'defender' => [
                'ships' => ['fighter' => ['basic' => '2']],
                'research' => ['weapons_engineering' => ['basic' => '3', 'intermediate' => '', 'superior' => '-1']],
                'defenses' => ['ground_turret_anti_light' => ['basic' => '']],
                'fortress' => ['basic' => '', 'intermediate' => '2'],
            ],
        ];
        $intel = ['forces' => ['ships' => ['fighter' => ['basic' => 2]], 'defenses' => []]];

        $calculation = $this->simulation->withConfirmedUnknownAssumptions($draft, $intel, $choices);

        self::assertSame($draft['attacker'], $calculation['attacker']);
        self::assertSame($draft['defender']['ships'], $calculation['defender']['ships']);
        self::assertSame($draft['defender']['defenses'], $calculation['defender']['defenses']);
        self::assertSame(['basic' => '3', 'intermediate' => 0, 'superior' => '-1'], $calculation['defender']['research']['weapons_engineering']);
        self::assertSame(['basic' => 0, 'intermediate' => '2'], $calculation['defender']['fortress']);
        self::assertSame('', $draft['defender']['research']['weapons_engineering']['intermediate']);
        self::assertSame($draft, $this->simulation->withConfirmedUnknownAssumptions($draft, null, $choices));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidInputs')]
    public function testBatchRejectsInvalidInputsWithoutNormalizingTheFirstSeed(array $forces,int $seed,bool $forgotten=false): void
    {
        if($forgotten) $this->owner->forgottenAt=new \DateTimeImmutable();
        $this->expectException(\DomainException::class);
        $this->simulation->runBatch($this->owner,$forces,$seed);
    }

    public static function invalidInputs(): array
    {
        $valid=['attacker'=>['ships'=>['fighter'=>['basic'=>1]]]];
        return ['negative seed'=>[$valid,-1],'overflow seed'=>[$valid,0x100000000],
            'forgotten owner'=>[$valid,42,true],'empty forces'=>[[],42],
            'too many units'=>[['attacker'=>['ships'=>['fighter'=>['basic'=>600_000, 'intermediate'=>400_001]]]],42],
            'unsupported third fleet'=>[['support'=>[], 'attacker'=>['ships'=>['fighter'=>['basic'=>1]]]],42],
            'malformed forces'=>[['attacker'=>['ships'=>'bad']],42],
            'invalid arrival'=>[['attacker'=>['ships'=>['fighter'=>['basic'=>1]],'arrival'=>11]],42]];
    }
}
