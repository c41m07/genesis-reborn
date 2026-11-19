<?php

namespace App\Tests\Service;

use App\Entity\{Player, PlayerUniverse, Universe};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulationView, CombatSimulator, CombatUnitFactory, PlayerCombatSimulation, TechnologyEffectCalculator};
use App\Service\Fleet\EspionageReportReader;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class CombatSimulationViewTest extends TestCase
{
    private function catalog(): GameCatalog
    {
        return new GameCatalog(dirname(__DIR__, 2).'/config/game');
    }

    public function testTenRealEngineResultsKeepTheirOutcomesAndConserveEveryUnitByCamp(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        foreach (['find', 'getRepository', 'getConnection', 'persist', 'flush'] as $method) {
            $entityManager->expects(self::never())->method($method);
        }
        $repository = new GameRepository($entityManager);
        $catalog = $this->catalog();
        $simulation = new PlayerCombatSimulation(new CombatSimulator(), new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog)), $catalog, $repository, new EspionageReportReader($repository));
        $owner = new PlayerUniverse();
        $owner->player = new Player();
        $owner->universe = new Universe();
        $owner->universe->slug = 'simulation-view-test';
        $owner->universe->name = 'Simulation de test';
        $forces = [
            'attacker' => ['ships' => ['fighter' => ['basic' => 8]], 'research' => ['weapons_engineering' => ['basic' => 2]]],
            'defender' => ['ships' => ['fighter' => ['intermediate' => 2]], 'arrival' => 2, 'defenses' => ['ground_turret_anti_light' => ['basic' => 1]], 'fortress' => ['basic' => 3]],
        ];
        $batch = $simulation->runBatch($owner, $forces, 12345);
        $view = (new CombatSimulationView($catalog))->prepare($batch, $forces, $simulation->choices($owner));
        self::assertSame($batch['outcomes'], $view['outcomes']);
        foreach ($view['trials'] as $index => $trial) {
            self::assertSame($batch['trials'][$index]['survivors'], $trial['survivors']);
            self::assertSame($batch['trials'][$index]['losses'], $trial['losses']);
            self::assertCount(1, $trial['report']['attacker']['rows']);
            self::assertCount(2, $trial['report']['defender']['rows']);
            $losses = 0;
            foreach ($trial['report'] as $camp) {
                self::assertSame($camp['initial'], $camp['remaining'] + $camp['lost']);
                foreach ($camp['rows'] as $row) {
                    self::assertSame($row['initial'], $row['remaining'] + $row['lost']);
                    $losses += $row['lost'];
                }
            }
            self::assertSame(array_sum($trial['losses']), $losses);
            self::assertSame('Chasseur multirôle', $trial['report']['defender']['rows'][0]['label']);
            self::assertSame(2, $trial['report']['defender']['rows'][0]['arrival']);
            self::assertSame(1, $trial['report']['defender']['rows'][1]['arrival']);
        }
    }

    public function testOneDamagedSurvivorIsOneUnitAndIgnoredAttackerDefensesStayIgnored(): void
    {
        $catalog = $this->catalog();
        $view = (new CombatSimulationView($catalog))->prepare(
            ['trials' => [['survivors' => [['camp' => 'attacker', 'slug' => 'fighter', 'tier' => 'basic', 'hp' => .1]]]]],
            ['attacker' => ['ships' => ['fighter' => ['basic' => '2']], 'defenses' => 'ignored-by-engine']],
            ['ships' => $catalog->all('ships'), 'defenses' => $catalog->all('defenses')],
        );
        self::assertSame(1, $view['trials'][0]['report']['attacker']['remaining']);
        self::assertSame(1, $view['trials'][0]['report']['attacker']['lost']);
        self::assertSame([], $view['trials'][0]['report']['defender']['rows']);
    }

    public function testAnInconsistentResultIsNotHiddenByAClampedLossCount(): void
    {
        $catalog = $this->catalog();
        $this->expectException(\LogicException::class);
        (new CombatSimulationView($catalog))->prepare(
            ['trials' => [['survivors' => array_fill(0, 2, ['camp' => 'attacker', 'slug' => 'fighter', 'tier' => 'basic'])]]],
            ['attacker' => ['ships' => ['fighter' => ['basic' => 1]]]],
            ['ships' => $catalog->all('ships'), 'defenses' => $catalog->all('defenses')],
        );
    }
}
