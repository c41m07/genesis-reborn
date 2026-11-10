<?php
declare(strict_types=1);
namespace App\Tests\Unit;

use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use PHPUnit\Framework\TestCase;

final class ConstructionMaximumTest extends TestCase
{
    private function fixture(): array
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2) . '/config/game');
        return [new OperationCalculator($catalog), ['slug' => 'test', '_tier' => 'basic', 'base_build_time' => 1.25, 'costs_base' => ['metal' => 3, 'nano' => 2]], ['catalog' => ['buildings' => $catalog->all('buildings'), 'technologies' => $catalog->all('technologies')], 'multipliers' => ['ship_build_time' => 1]]];
    }

    public function testMaximumExceedsOneHundredAndUsesEveryResource(): void
    {
        [$calculator, $definition, $config] = $this->fixture();
        self::assertSame(150, $calculator->maximum($definition, 'defenses', ['metal' => ['stock' => 450], 'nano' => ['stock' => 300]], [], $config));
        self::assertSame(0, $calculator->maximum($definition, 'defenses', ['metal' => ['stock' => 2], 'nano' => ['stock' => 300]], [], $config));
        self::assertSame(10, $calculator->maximum($definition, 'defenses', ['metal' => ['stock' => 450], 'nano' => ['stock' => 300]], [], $config, [], 10));
    }

    public function testBatchRoundingDoesNotMultiplyTheRoundedUnitCost(): void
    {
        [$calculator, $definition, $config] = $this->fixture();
        $technologies = ['naval_engineering' => ['basic' => 25, 'intermediate' => 25, 'superior' => 25]];
        self::assertSame(2, $calculator->calculate($definition, 'ships', 1, 1, [], $config, $technologies)['costs']['metal']);
        self::assertSame(150, $calculator->maximum($definition, 'ships', ['metal' => ['stock' => 270], 'nano' => ['stock' => 180]], [], $config, $technologies));
    }

    public function testMaximumFitsTheStoredDurationWithoutChangingTheUnitTime(): void
    {
        [$calculator, $definition, $config] = $this->fixture();
        $stocks = ['metal' => ['stock' => 1e15], 'nano' => ['stock' => 1e15]];
        self::assertSame(1717986917, $calculator->maximum($definition, 'defenses', $stocks, [], $config));
    }
}
