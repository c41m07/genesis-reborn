<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Catalog\GameCatalog;
use App\Service\Fleet\{FleetNavigation, MovementCalculator};
use App\Service\PirateCampaign\{PirateConvoyQuote, PirateConvoyTimeline, PirateEncounterSnapshot};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PirateConvoyTest extends TestCase
{
    public function testFrozenCatalogueUsesAllRanksWithoutPowerOrPlayerDiscount(): void
    {
        $catalogue = new GameCatalog(dirname(__DIR__, 2).'/config/game');
        $omega = new Universe();
        $omega->slug = 'omega-1';
        $composition = ['probe' => ['basic' => 3, 'intermediate' => 2, 'superior' => 1]];
        $snapshot = PirateConvoyQuote::captureCatalogue($catalogue, $omega, $composition);
        self::assertEquals(['metal' => 1028, 'nano' => 1256, 'antimatter' => 0, 'hydrogen' => 0], $snapshot->buildCosts());
        self::assertSame(
            ['metal' => ['whole' => 514, 'extra_half' => false], 'nano' => ['whole' => 628, 'extra_half' => false],
                'antimatter' => ['whole' => 0, 'extra_half' => false], 'hydrogen' => ['whole' => 0, 'extra_half' => false]],
            PirateConvoyQuote::halfOfFrozenCatalogue($snapshot)->exactParts(),
        );
    }

    public function testOddAmountsStayExactWithoutElectricity(): void
    {
        $snapshot = PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 1]], ['metal' => 83, 'nano' => 1, 'electricity' => 200]);
        self::assertSame([
            'metal' => ['whole' => 41, 'extra_half' => true], 'nano' => ['whole' => 0, 'extra_half' => true],
            'antimatter' => ['whole' => 0, 'extra_half' => false], 'hydrogen' => ['whole' => 0, 'extra_half' => false],
        ], PirateConvoyQuote::halfOfFrozenCatalogue($snapshot)->exactParts());
    }

    public function testNeverDispatchBeforeVictoryOrSettleBeforeActualArrival(): void
    {
        $convoy = $this->convoy();
        $from = [2, 1, 0, 0, null];
        $to = [2, 1, 0, 0, 5];
        self::assertSame('eligible', $convoy->state());
        self::assertFalse($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(70)));
        try {
            $convoy->dispatch(7, 8, 9, $from, $to, 'private-first', $this->at(4), $this->at(60));
            self::fail('Un départ avant la victoire est invalide.');
        } catch (\DomainException) {
            self::assertSame('eligible', $convoy->state());
        }
        self::assertTrue($convoy->dispatch(7, 8, 9, $from, $to, 'private-first', $this->at(8), $this->at(68)));
        self::assertFalse($convoy->dispatch(7, 8, 10, $from, $to, 'private-second', $this->at(9), $this->at(69)));
        self::assertSame('traveling', $convoy->state());
        self::assertNull($convoy->attestedPositionForDeparture());
        self::assertFalse($convoy->arriveWhenDue('private-first', $this->at(67)));
        self::assertFalse($convoy->arriveWhenDue('private-second', $this->at(68)));
        self::assertFalse($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(69)));
        self::assertTrue($convoy->arriveWhenDue('private-first', $this->at(70)));
        self::assertSame('arrived', $convoy->state());
        self::assertFalse($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(69)));
        self::assertTrue($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(80)));
        self::assertSame('settled', $convoy->state());
        self::assertFalse($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(81)));
        self::assertFalse($convoy->arriveWhenDue('private-first', $this->at(81)));
        self::assertFalse($convoy->dispatch(7, 8, 10, $from, $to, 'private-third', $this->at(95), $this->at(156)));
        self::assertEquals(['departed_at' => $this->at(8), 'due_at' => $this->at(68),
            'arrived_at' => $this->at(70), 'settled_at' => $this->at(80)], $convoy->chronology());
    }

    public function testFullWarehouseKeepsTheEntireLotAtTheSameColony(): void
    {
        $convoy = $this->convoy();
        $from = [2, 1, 0, 0, null];
        $to = [2, 1, 0, 0, 5];
        self::assertTrue($convoy->dispatch(7, 8, 9, $from, $to, 'first', $this->at(8), $this->at(68)));
        self::assertTrue($convoy->arriveWhenDue('first', $this->at(70)));
        self::assertTrue($convoy->recordCapacityShortage(7, 8, 9, $this->at(70)));
        self::assertSame('waiting_capacity', $convoy->state());
        self::assertFalse($convoy->dispatch(7, 8, 10, $to, [3, 1, 4, 2, 8], 'second', $this->at(70), $this->at(130)));
        self::assertFalse($convoy->mayTryDeposit(7, 8, 10, $this->at(72)));
        self::assertFalse($convoy->mayTryDeposit(14, 8, 9, $this->at(72)));
        self::assertFalse($convoy->mayTryDeposit(7, 14, 9, $this->at(72)));
        self::assertSame(41.5, $convoy->quote->depositAmounts()['metal']);
        self::assertTrue($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(75)));
        self::assertFalse($convoy->recordCapacityShortage(7, 8, 9, $this->at(80)));
    }

    public function testLostColonyNeedsASecondRealTripFromTheAttestedEndOfFirstLeg(): void
    {
        $convoy = $this->convoy();
        $relay = [2, 1, 0, 0, null];
        $lost = [2, 1, 0, 0, 5];
        $other = [3, 1, 4, 2, 8];
        self::assertTrue($convoy->dispatch(7, 8, 9, $relay, $lost, 'first', $this->at(8), $this->at(68)));
        self::assertFalse($convoy->recordDestinationLoss(7, 8, 9, $this->at(35)));
        self::assertFalse($convoy->dispatch(7, 8, 10, $relay, $other, 'second', $this->at(35), $this->at(95)));
        self::assertTrue($convoy->arriveWhenDue('first', $this->at(70)));
        self::assertFalse($convoy->recordDestinationLoss(15, 8, 9, $this->at(70)));
        self::assertTrue($convoy->recordDestinationLoss(7, 8, 9, $this->at(70)));
        self::assertSame('destination_lost', $convoy->state());
        self::assertSame($lost, $convoy->attestedPositionForDeparture());
        self::assertFalse($convoy->dispatch(7, 8, 10, $relay, $other, 'second', $this->at(70), $this->at(130)));
        self::assertFalse($convoy->dispatch(15, 8, 10, $lost, $other, 'second', $this->at(70), $this->at(130)));
        self::assertFalse($convoy->dispatch(7, 8, 10, $lost, $other, 'first', $this->at(70), $this->at(130)));
        self::assertTrue($convoy->dispatch(7, 8, 10, $lost, $other, 'second', $this->at(71), $this->at(131)));
        self::assertFalse($convoy->arriveWhenDue('first', $this->at(132)));
        self::assertFalse($convoy->arriveWhenDue('second', $this->at(130)));
        self::assertTrue($convoy->arriveWhenDue('second', $this->at(132)));
        self::assertFalse($convoy->recordSettlementAfterCredit(7, 8, 9, $this->at(132)));
        self::assertTrue($convoy->recordSettlementAfterCredit(7, 8, 10, $this->at(132)));
        self::assertSame(41.5, $convoy->quote->depositAmounts()['metal']);
    }

    public function testNoDepartureWhenVictoryIsProvedButTheSourceIsNot(): void
    {
        $convoy = PirateConvoyTimeline::afterVictory(4, $this->at(5), $this->quote(), 7, 8, null);
        self::assertSame('eligible', $convoy->state());
        self::assertNull($convoy->attestedPositionForDeparture());
        self::assertFalse($convoy->dispatch(7, 8, 9, [2, 1, 0, 0, null], [2, 1, 0, 0, 5],
            'first', $this->at(8), $this->at(68)));
    }

    public function testNoProductionRightCanBeModelledWithoutAVictory(): void
    {
        $this->expectException(\DomainException::class);
        PirateConvoyTimeline::afterVictory(1, null, $this->quote(), 7, 8, null);
    }

    public function testAQuoteBeyondTheStoragePrecisionIsNeverRoundedDownForDeposit(): void
    {
        $quote = PirateConvoyQuote::halfOfFrozenCatalogue(PirateEncounterSnapshot::freeze(
            ['fighter' => ['basic' => 1]], ['metal' => PHP_INT_MAX],
        ));
        self::assertTrue($quote->exactParts()['metal']['extra_half']);
        $this->expectException(\DomainException::class);
        $quote->depositAmounts();
    }

    private function convoy(): PirateConvoyTimeline
    {
        return PirateConvoyTimeline::afterVictory(4, $this->at(5), $this->quote(), 7, 8, [2, 1, 0, 0, null]);
    }

    #[DataProvider('routes')]
    public function testSystemAnchorDistanceAndDurationCanBeMirrored(array $relay, array $colony): void
    {
        $calculator = new MovementCalculator();
        $navigation = new FleetNavigation();
        $ships = [
            ['quantity' => 1, 'slug' => 'transport', 'speed_system' => 50, 'speed_galaxy' => 500,
                'fuel_travel_system' => 0.5, 'fuel_travel_galaxy' => 2, 'fuel_orbit' => 0, 'cargo_capacity' => 50000],
        ];
        $forward = $calculator->quote($colony, $relay, $ships, 1.0, 2.0, 'inverse');
        // L'ancre de géométrie vaut 10, sans devenir une position colonisable dans la carte.
        $point = $navigation->point($relay);
        self::assertSame(10, $point['position']);
        $reverse = $navigation->distances($point, $colony);
        $quoted = $calculator->quoteDistances($reverse['normal'], $reverse['hyper'], $reverse['same'], $ships, 1.0, 2.0, 'inverse');
        self::assertGreaterThanOrEqual(60, $quoted['seconds']);
        self::assertSame($forward['seconds'], $quoted['seconds']);
        self::assertEqualsWithDelta($forward['distance'], $quoted['distance'], 1e-8);
    }

    public static function routes(): array
    {
        return [
            'système du poste' => [[2, 1, 1, 4, null], [2, 1, 1, 4, 5]],
            'autre système' => [[2, 1, 1, 4, null], [3, 1, 7, 9, 5]],
        ];
    }

    private function quote(): PirateConvoyQuote
    {
        return PirateConvoyQuote::halfOfFrozenCatalogue(
            PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 1]], ['metal' => 83]),
        );
    }

    private function at(int $offset): \DateTimeImmutable
    {
        return (new \DateTimeImmutable('2026-11-21T08:00:00Z'))->modify('+'.$offset.' seconds');
    }
}
