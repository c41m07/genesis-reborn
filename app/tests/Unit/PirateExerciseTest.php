<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Combat\CombatSimulator;
use App\Service\Player\PirateExercise;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PirateExerciseTest extends TestCase
{
    #[DataProvider('exercises')]
    public function testReportReflectsBothOutcomesAndContainsOnlySimulatedForces(int $seed, string $expected): void
    {
        $exercise = new PirateExercise(new CombatSimulator());
        $report = $exercise->report($seed);

        self::assertSame($expected, $report['outcome']);
        self::assertSame(2, $report['escortSurvivors'] + $report['escortLosses']);
        self::assertSame(2, $report['pirateSurvivors'] + $report['pirateLosses']);
        self::assertNotEmpty($report['rounds']);
        self::assertLessThanOrEqual(6, count($report['rounds']));
        self::assertSame($report, $exercise->report($seed));
        foreach ($report['rounds'] as $index => $round) {
            self::assertSame($index + 1, $round['number']);
            self::assertLessThanOrEqual(4, $round['shots']);
            self::assertLessThanOrEqual($round['shots'], $round['hits']);
            self::assertGreaterThanOrEqual(0, $round['shieldDamage']);
            self::assertGreaterThanOrEqual(0, $round['structureDamage']);
        }
    }

    public static function exercises(): iterable
    {
        yield 'escorte victorieuse' => [0, 'defender_wins'];
        yield 'escorte battue' => [1, 'attacker_wins'];
    }
}
