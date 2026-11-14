<?php
namespace App\Tests\Service;

use App\Entity\{Player,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator,CombatUnitFactory,PlayerCombatSimulation,TechnologyEffectCalculator};
use App\Service\Fleet\EspionageReportReader;
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

    public function testExistingUnitAndWorkLimitsArePreservedForEachCombat(): void
    {
        $this->owner->universe->gameSettings=['catalog_overrides'=>['ships'=>['fighter'=>['base_attack'=>0,'shots_min'=>2,'shots_max'=>2]]]];
        $forces=['attacker'=>['ships'=>['fighter'=>['basic'=>250]]],'defender'=>['ships'=>['fighter'=>['basic'=>250]]]];
        $batch=$this->simulation->runBatch($this->owner,$forces,42);
        self::assertCount(10,$batch['trials']);self::assertCount(500,$batch['trials'][0]['survivors']);
        $this->owner->universe->gameSettings['catalog_overrides']['ships']['fighter']['shots_max']=3;
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Scénario trop volumineux');
        $this->simulation->runBatch($this->owner,$forces,42);
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
            'too many units'=>[['attacker'=>['ships'=>['fighter'=>['basic'=>501]]]],42],
            'malformed forces'=>[['attacker'=>['ships'=>'bad']],42],
            'invalid arrival'=>[['attacker'=>['ships'=>['fighter'=>['basic'=>1]],'arrival'=>11]],42]];
    }
}
