<?php
namespace App\Tests\Service;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\TechnologyEffectCalculator;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class BalanceMechanicsTest extends TestCase
{
    private function catalog(): GameCatalog
    {
        return new GameCatalog(dirname(__DIR__, 2) . '/config/game');
    }

    private function universe(): Universe
    {
        $universe = new Universe();
        $universe->slug = 'omega-1';
        $universe->name = 'Omega';

        return $universe;
    }

    public function testMineOutputScalesAfterStoredEnergyIsExhausted(): void
    {
        $window = \App\Service\Colony\ResourceService::miningWindow(0.15, 0.30, 3, 30);

        self::assertSame(20.0, $window['full_mining_seconds']);
        self::assertSame(10.0, $window['throttled_mining_seconds']);
        self::assertEqualsWithDelta(0.5, $window['throttle'], 0.000001);
        self::assertEquals(0.0, $window['energy_stock']);
    }

    public function testSelectedCombatResearchChangesShipStatistics(): void
    {
        $catalog = $this->catalog();
        $universe = $this->universe();
        $fighter = $catalog->getForUniverse($universe, 'ships', 'fighter');
        $result = (new TechnologyEffectCalculator($catalog))->applyToShips(
            [$fighter],
            ['advanced_targeting' => 5, 'precision_fire' => 3, 'shield_technology' => 5, 'weapon_technology' => 5, 'advanced_propulsion' => 5],
            $universe,
        )[0];

        self::assertSame(86.0, $result['tracking']);
        self::assertSame(85.0, $result['precision']);
        self::assertSame(62.5, $result['shield']);
        self::assertSame(50.0, $result['base_attack']);
        self::assertSame(62.5, $result['speed_system']);
    }

    public function testSelectedShipyardAndFabricationResearchAffectQueuePlan(): void
    {
        $catalog = $this->catalog();
        $universe = $catalog->forUniverse($this->universe());
        $fighter = $universe['catalog']['ships']['fighter'];
        $plan = (new OperationCalculator($catalog))->calculate(
            $fighter,
            'ships',
            1,
            1,
            ['shipyard' => 10],
            $universe,
            ['advanced_engineering' => 5],
        );

        self::assertSame(30, $plan['duration']);
        self::assertSame(['metal' => 2250, 'energy' => 750, 'antimatter' => 0, 'hydrogen' => 0, 'carbon' => 0], $plan['costs']);
    }

    public function testLaboratoryLevelOneAppliesTenPercentResearchTimeReduction(): void
    {
        $catalog = $this->catalog();
        $universe = $catalog->forUniverse($this->universe());
        $research = $universe['catalog']['technologies']['colonization'];
        $plan = (new OperationCalculator($catalog))->calculate($research, 'technologies', 1, 1, ['lab' => 1], $universe);

        self::assertSame(259200, $plan['duration']);
    }
}
