<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator, CombatUnitFactory, TechnologyEffectCalculator};
use App\Service\Fleet\MovementCalculator;
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCombatSchedule, PirateFlightQuote};
use PHPUnit\Framework\TestCase;

final class PirateNativeEstimatesTest extends TestCase
{
    public function testQuotedHydrogenKeepsNativeFractionAndAnchorMinimumButDoesNotSelectAFuelRule(): void
    {
        [$catalog, $universe] = $this->catalogue();
        $probe = $catalog->getTierForUniverse($universe, 'ships', 'probe', 'basic') + ['quantity' => 1];
        $fighter = $catalog->getTierForUniverse($universe, 'ships', 'fighter', 'basic') + ['quantity' => 2];
        $movement = new MovementCalculator();
        $estimate = new PirateFlightQuote($movement);
        $system = [1, 1, 0, 0];

        $near = $estimate->compare([1, 1, 0, 0, 9], $system, [$probe], 1.0, 1.0, 'proportional');
        self::assertSame(30, $near['leg_seconds']);
        self::assertSame(0.1, $near['one_way_hydrogen']);
        self::assertSame(0.2, $near['round_trip_hydrogen']);
        self::assertSame(16000.0, $near['total_capacity']);
        $normal = $estimate->compare([1, 1, 0, 0, 5], $system, [$probe], 1.0, 1.0, 'proportional');
        self::assertSame(38, $normal['leg_seconds']);
        self::assertSame(0.5, $normal['one_way_hydrogen']);
        self::assertSame(1.0, $normal['round_trip_hydrogen']);

        $escort = $estimate->compare([1, 1, 0, 0, 5], $system, [$fighter], 1.0, 1.0, 'proportional', 10 / 60);
        self::assertSame(1185, $escort['leg_seconds']);
        self::assertSame(5.0, $escort['one_way_hydrogen']);
        self::assertSame(10.0, $escort['round_trip_hydrogen']);
        self::assertEqualsWithDelta(0.01, $escort['orbit_hydrogen'], 1e-12);
        self::assertSame(10.01, $escort['round_trip_with_orbit_hydrogen']);

        $otherSystem = [2, 1, 1, 0];
        foreach (['proportional', 'inverse'] as $policy) {
            $raw = $movement->quote([1, 1, 0, 0, 5], [...$otherSystem, null], [$probe], 0.5, 1.0, $policy);
            $comparison = $estimate->compare([1, 1, 0, 0, 5], $otherSystem, [$probe], 0.5, 1.0, $policy);
            self::assertSame($raw['seconds'], $comparison['leg_seconds']);
            self::assertSame($raw['one_way_fuel'], $comparison['one_way_hydrogen']);
            self::assertSame($raw['total_fuel'], $comparison['round_trip_hydrogen']);
        }
    }

    public function testQuoteRejectsANonSystemAnchorAndDefersCrossGalaxyRulesToNativeCalculator(): void
    {
        [$catalog, $universe] = $this->catalogue();
        $probe = $catalog->getTierForUniverse($universe, 'ships', 'probe', 'basic') + ['quantity' => 1];
        $estimate = new PirateFlightQuote(new MovementCalculator());
        foreach ([[2, 2, 0, 0], [2, 1, 0, 0, 1]] as $invalidTarget) {
            try {
                $estimate->compare([1, 1, 0, 0, 5], $invalidTarget, [$probe], 1.0, 1.0, 'proportional');
                self::fail('Une mauvaise ancre ne doit pas produire de devis.');
            } catch (\DomainException $exception) {
                self::assertNotEmpty($exception->getMessage());
            }
        }
    }

    public function testAnAlreadyCalculatedNativeResultGivesOnlyDueDatesAndNeverBeforeArrival(): void
    {
        [$catalog, $universe] = $this->catalogue();
        $units = new CombatUnitFactory($catalog, new TechnologyEffectCalculator($catalog));
        $catalogue = new PirateCampaignCatalogue($catalog, $units);
        $scenario = $catalogue->prepareForServer('balise_17', $universe)->battleForServer();
        $result = $scenario->resolve(new CombatSimulator(), $units->ships($universe, ['fighter' => ['basic' => 2]], []), 15);
        $arrival = new \DateTimeImmutable('2026-10-25T00:59:58+00:00');
        $arrival = $arrival->setTimezone(new \DateTimeZone('Europe/Paris'));
        $schedule = new PirateCombatSchedule();

        self::assertNull($schedule->afterArrival($arrival, $arrival->modify('-1 second'), $result));
        $dates = $schedule->afterArrival($arrival, $arrival, $result);
        self::assertSame(['rounds_due', 'resolution_due', 'maximum_due'], array_keys($dates));
        self::assertCount($result['rounds_played'], $dates['rounds_due']);
        self::assertLessThan(10, $result['rounds_played']);
        self::assertSame(60, $dates['rounds_due'][0]->getTimestamp() - $arrival->getTimestamp());
        self::assertSame(60 * $result['rounds_played'], $dates['resolution_due']->getTimestamp() - $arrival->getTimestamp());
        self::assertSame(600, $dates['maximum_due']->getTimestamp() - $arrival->getTimestamp());

        $full = $schedule->afterArrival($arrival, $arrival, ['outcome' => 'defender_wins', 'rounds_played' => 10]);
        self::assertCount(10, $full['rounds_due']);
        self::assertEquals($full['maximum_due'], $full['resolution_due']);
        $this->expectException(\DomainException::class);
        $schedule->afterArrival($arrival, $arrival, ['outcome' => 'ongoing', 'rounds_played' => 1]);
    }

    public function testImpossibleRoundCountIsNotAValidTerminalResult(): void
    {
        $arrival = new \DateTimeImmutable('2026-11-21T21:00:00Z');
        $this->expectException(\DomainException::class);
        (new PirateCombatSchedule())->afterArrival($arrival, $arrival, ['outcome' => 'draw', 'rounds_played' => 11]);
    }

    /** @return array{GameCatalog,Universe} */
    private function catalogue(): array
    {
        $catalog = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $universe = new Universe();
        $universe->slug = 'omega-1';

        return [$catalog, $universe];
    }
}
