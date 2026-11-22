<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\{Galaxy, PirateAttackCampaign, PirateReconnaissance, Planet, Player, PlayerUniverse, SolarSystem, Universe};
use App\Service\Fleet\MovementCalculator;
use App\Service\PirateCampaign\{PirateBlockedReturnEvidence, PirateCampaignFuel, PirateEncounterSnapshot, PirateFlightQuote, PirateReturnAttestation, PirateSurvivorFallbackQuote};
use PHPUnit\Framework\TestCase;

final class PirateSurvivorFallbackQuoteTest extends TestCase
{
    public function testSameSystemUsesNativeOneWayExactReserveAndRealElapsedTimeWithoutWriting(): void
    {
        [$campaign, $player, $origin, $target, $ships, $at] = $this->fixture(heldBeforeReturn: true);
        $before = $campaign->timeline;
        $movement = new MovementCalculator();
        $raw = $movement->quote([4, 3, 0, 0, 9], [4, 3, 0, 0, null], $ships, 1.0, 1.0, 'proportional');
        $reserve = (float) $raw['one_way_fuel'];
        self::assertSame(0.1, $reserve);
        $proposal = (new PirateSurvivorFallbackQuote($movement))->propose($campaign, $player, $origin, $target, $this->evidence($reserve), 12, $ships, $at, 1.0, 1.0, 'proportional');

        self::assertNotNull($proposal);
        self::assertSame($raw['seconds'], $proposal['seconds']);
        self::assertSame($reserve, $proposal['hydrogen']);
        self::assertLessThan($raw['total_fuel'], $proposal['hydrogen']);
        self::assertSame($proposal['seconds'], $proposal['arrival_at']->getTimestamp() - $at->getTimestamp());
        self::assertSame('+01:00', $proposal['arrival_at']->format('P')); // Départ juste avant le changement d'heure.
        self::assertSame($target->id, $proposal['target_id']);
        self::assertSame(12, $proposal['carrier_revision']);
        self::assertSame($before, $campaign->timeline);
        self::assertTrue($campaign->returnBlocked);
        self::assertSame(['probe' => ['basic' => 1]], $campaign->reservedShips);
    }

    public function testInterSystemUsesNativeSpiralRatherThanASyntheticPointWhenTheColonyDisappeared(): void
    {
        [$campaign, $player, , $target, $ships, $at] = $this->fixture(heldBeforeReturn: true);
        $target->solarSystem = clone $target->solarSystem;
        $target->solarSystem->id = 5;
        $target->solarSystem->coordX = 3;
        $movement = new MovementCalculator();
        $raw = $movement->quote([5, 3, 3, 0, 9], [4, 3, 0, 0, null], $ships, 0.5, 1.2, 'inverse');
        $proposal = (new PirateSurvivorFallbackQuote($movement))->propose($campaign, $player, null, $target, $this->evidence((float) $raw['one_way_fuel']), 12, $ships, $at, 0.5, 1.2, 'inverse');

        self::assertNotNull($proposal);
        self::assertGreaterThan(0, $raw['hyper_distance']);
        self::assertSame($raw['seconds'], $proposal['seconds']);
        self::assertSame($raw['one_way_fuel'], $proposal['hydrogen']);
    }

    public function testUnattestedInactiveOrMismatchedReturnsAndUnsafeTravelFailClosed(): void
    {
        foreach (['historique', 'reserve insuffisante', 'reserve non finie', 'reserve impossible', 'ancienne version',
            'autre tentative', 'trace traitée un autre instant', 'case illégitime avant le retour', 'encore en route', 'arrivée future',
            'origine toujours à soi', 'autre empire', 'autre univers', 'empire oublié', 'ancienne colonie proposée',
            'autre galaxie', 'positions incohérentes', 'aucun survivant', 'effectifs différents', 'durée hors borne', 'date hors borne'] as $case) {
            [$campaign, $player, $origin, $target, $ships, $at] = $this->fixture(heldBeforeReturn: true);
            $evidence = $this->evidence();
            $revision = 12;
            $multiplier = 1.0;
            switch ($case) {
                case 'historique': $evidence = null; break;
                case 'reserve insuffisante': $evidence = $this->evidence(0.099999999999); break;
                case 'reserve non finie': $evidence = $this->evidence(INF); break;
                case 'reserve impossible': $evidence = $this->evidence(1000000); break;
                case 'ancienne version': $revision--; break;
                case 'autre tentative': $campaign->timeline['attempt']++; break;
                case 'trace traitée un autre instant': $campaign->timeline['settled'] = $at->format(DATE_ATOM); break;
                case 'case illégitime avant le retour': $evidence = $this->evidence(0.1, [4, 3, 0, 0, 5]); break;
                case 'encore en route': $campaign->timeline['state'] = 'returning'; break;
                case 'arrivée future': $at = $at->setTimestamp($at->getTimestamp() - 20); break;
                case 'origine toujours à soi': $origin->owner = $player; break;
                case 'autre empire': $target->owner = $origin->owner; break;
                case 'autre univers':
                    $target->solarSystem = clone $target->solarSystem;
                    $target->solarSystem->galaxy = clone $target->solarSystem->galaxy;
                    $target->solarSystem->galaxy->universe = clone $player->universe;
                    $target->solarSystem->galaxy->universe->id = 99;
                    break;
                case 'empire oublié': $player->forgottenAt = $at; break;
                case 'ancienne colonie proposée': $target->id = $origin->id; break;
                case 'autre galaxie':
                    $target->solarSystem = clone $target->solarSystem;
                    $target->solarSystem->galaxy = clone $target->solarSystem->galaxy;
                    $target->solarSystem->galaxy->id = 99;
                    break;
                case 'positions incohérentes':
                    $target->solarSystem = clone $target->solarSystem;
                    $target->solarSystem->id++;
                    break;
                case 'aucun survivant': $campaign->reservedShips = []; break;
                case 'effectifs différents': $ships[0]['quantity']++; break;
                case 'durée hors borne': $multiplier = 1e40; break;
                case 'date hors borne': $at = $at->setTimestamp(253402300798); break;
            }
            $proposal = (new PirateSurvivorFallbackQuote(new MovementCalculator()))
                ->propose($campaign, $player, $origin, $target, $evidence, $revision, $ships, $at, 1.0, $multiplier, 'proportional');
            self::assertNull($proposal, $case);
        }
    }

    public function testEvidenceSavesCoordinatesIndependentlyOfTheHydrationArray(): void
    {
        $coordinate = 0;
        $evidence = $this->evidence(0.1, [4, 3, 0, &$coordinate, null]);
        $coordinate = 3;
        self::assertSame([4, 3, 0, 0, null], $evidence->position);
    }

    public function testScoutForfeitsTheFullNativeRoundTripWithoutAnOrbitalBudget(): void
    {
        [, , , , $ships] = $this->fixture();
        $flight = new PirateFlightQuote(new MovementCalculator());
        $quote = $flight->compare([4, 3, 0, 0, 9], [4, 3, 0, 0], $ships, 1.0, 1.0, 'proportional');
        $fuel = PirateCampaignFuel::scout($quote);
        self::assertSame(30, $fuel->legSeconds);
        self::assertSame(0.1, $fuel->legHydrogen);
        self::assertSame(0.2, $fuel->amountForDeparture());
        self::assertSame(0.2, $fuel->scoutsForfeitedHydrogen());
        self::assertNull($fuel->arrivalHydrogen(0.1));
    }

    public function testDepartureAndSurvivorBudgetUseNativeFullOrbitAndActualCarriers(): void
    {
        [, , , , $ships] = $this->fixture();
        $ships[0] = array_replace($ships[0], ['slug' => 'fighter', 'fuel_orbit' => 0.03]);
        $flight = new PirateFlightQuote(new MovementCalculator());
        $quote = $flight->compare([4, 3, 0, 0, 5], [4, 3, 0, 0], $ships, 1.0, 1.0, 'proportional', 600 / 3600);
        $fuel = PirateCampaignFuel::expedition($quote);
        self::assertGreaterThan(0, $quote['orbit_hydrogen']);
        self::assertSame($quote['orbit_hydrogen'], $fuel->orbitHydrogen);
        self::assertSame($quote['round_trip_with_orbit_hydrogen'], $fuel->amountForDeparture());
        self::assertSame(0.5, $fuel->afterCombat(['fighter' => ['basic' => 1]], $ships));
        self::assertSame(0.0, $fuel->afterCombat([], []));
        $ships[0]['fuel_capacity'] = 0.125;
        $ships[0]['cargo_capacity'] = 0.125;
        self::assertSame(0.25, $fuel->afterCombat(['fighter' => ['basic' => 1]], $ships));
        self::assertNull($fuel->arrivalHydrogen(0.25)); // Ne pas atteindre l’arrivée par un retour insuffisamment financé.
        self::assertNull($fuel->arrivalHydrogen(0.50001)); // Pas de « réserve volontaire » dans ce contrat.
        self::assertSame(0.0, $fuel->arrivalHydrogen(0.5));
        $this->expectException(\DomainException::class);
        $fuel->afterCombat(['fighter' => ['basic' => 2]], $ships);
    }

    public function testNativeBudgetCannotBeReservedInFreightOrWithInsufficientCarriers(): void
    {
        [, , , , $ships] = $this->fixture();
        $ships[0]['cargo_capacity'] = 0.1;
        $quote = (new PirateFlightQuote(new MovementCalculator()))->compare(
            [4, 3, 0, 0, 9], [4, 3, 0, 0], $ships, 1.0, 1.0, 'proportional',
        );
        $this->expectException(\DomainException::class);
        PirateCampaignFuel::scout($quote + ['freight_hydrogen' => 10000]);
    }

    public function testHeldAtBattlefieldKeepsReturnFuelAndUsesOnlyVerifiedAnchorGeometry(): void
    {
        [$campaign, $player, $origin, $target, $ships, $at] = $this->fixture();
        $ships[0]['slug'] = 'fighter';
        $campaign->reservedShips = ['fighter' => ['basic' => 1]];
        $site = [8, 3, 3, 0, null];
        $target->solarSystem = clone $target->solarSystem;
        $target->solarSystem->coordX = 3;
        $target->solarSystem->id = 8;
        $movement = new MovementCalculator();
        $flight = new PirateFlightQuote($movement);
        $budget = PirateCampaignFuel::expedition($flight->compare([4, 3, 0, 0, 5], array_slice($site, 0, 4), $ships, 1.0, 1.0, 'proportional', 600 / 3600));
        $route = new PirateReturnAttestation(31, 2, 15, 1, [4, 3, 0, 0, 5], $site, $budget);
        $processed = new \DateTimeImmutable($campaign->timeline['settled']);
        self::assertNull($route->heldAtBattlefield($campaign, 2, 12, $processed, false, $ships)); // Toujours un retour planifié.
        $campaign->timeline['returnDue'] = null;
        $campaign->timeline['resolved'] = $processed->format(DATE_ATOM);
        $campaign->nextAt = $at;
        self::assertNull($route->heldAtBattlefield($campaign, 2, 12, $processed, false, $ships));
        $campaign->nextAt = null;
        self::assertNull($route->heldAtBattlefield($campaign, 2, 12, $processed, true, $ships));
        $other = clone $campaign;
        $other->id++;
        self::assertNull($route->heldAtBattlefield($other, 2, 12, $processed, false, $ships));
        self::assertNull($route->heldAtBattlefield($campaign, 3, 12, $processed, false, $ships));
        $evidence = $route->heldAtBattlefield($campaign, 2, 12, $processed, false, $ships);
        self::assertNotNull($evidence);
        self::assertSame($budget->legHydrogen, $evidence->remainingHydrogen);
        self::assertSame($site, $evidence->position);
        $proposal = (new PirateSurvivorFallbackQuote($movement))->propose(
            $campaign, $player, $origin, $target, $evidence, 12, $ships, $at, 1.0, 1.0, 'proportional',
        );
        $native = $movement->quote([8, 3, 3, 0, 6], $site, $ships, 1.0, 1.0, 'proportional');
        self::assertNotNull($proposal);
        self::assertSame($native['one_way_fuel'], $proposal['hydrogen']);
        self::assertSame($native['seconds'], $proposal['seconds']);
        self::assertNull($campaign->nextAt); // Le devis n’ordonne aucun nouveau départ.
    }

    public function testACompletedBlockedArrivalHasNoPhantomFuelOrHistoricalAttestation(): void
    {
        [$campaign, $player, $origin, $target, $ships] = $this->fixture();
        $ships[0]['slug'] = 'fighter';
        $campaign->reservedShips = ['fighter' => ['basic' => 1]];
        $site = [8, 3, 3, 0, null];
        $home = [4, 3, 0, 0, 5];
        $movement = new MovementCalculator();
        $budget = PirateCampaignFuel::expedition((new PirateFlightQuote($movement))->compare($home, array_slice($site, 0, 4),
            $ships, 1.0, 1.0, 'proportional', 600 / 3600));
        $route = new PirateReturnAttestation(31, 2, 15, 1, $home, $site, $budget);
        $resolved = new \DateTimeImmutable('2026-10-25T00:59:00Z');
        $due = $resolved->setTimestamp($resolved->getTimestamp() + $budget->legSeconds);
        $processed = $due->modify('+12 seconds');
        $campaign->timeline['settled'] = $processed->format(DATE_ATOM);
        $campaign->timeline['returnDue'] = $due->format(DATE_ATOM);
        self::assertNull($route->blockedAtArrival($campaign, 2, 12, $processed, false, $budget->legHydrogen, $ships));
        $campaign->timeline['resolved'] = $resolved->format(DATE_ATOM);
        self::assertNull($route->blockedAtArrival($campaign, 2, 12, $processed, true, $budget->legHydrogen, $ships));
        self::assertNull($route->blockedAtArrival($campaign, 2, 12, $processed, false, 0.0, $ships));
        $evidence = $route->blockedAtArrival($campaign, 2, 12, $processed, false, $budget->legHydrogen, $ships);
        self::assertNotNull($evidence);
        self::assertSame($home, $evidence->position);
        self::assertSame(0.0, $evidence->remainingHydrogen);
        self::assertSame(PirateBlockedReturnEvidence::BLOCKED_ARRIVAL, $evidence->phase);
        self::assertNull((new PirateSurvivorFallbackQuote($movement))->propose(
            $campaign, $player, $origin, $target, $evidence, 12, $ships, $processed, 1.0, 1.0, 'proportional',
        ));
    }

    public function testHydrationRefusesAFundedAlreadyCompletedReturn(): void
    {
        $this->expectException(\DomainException::class);
        new PirateBlockedReturnEvidence(31, 2, 15, 1, 12, new \DateTimeImmutable('2026-10-25T00:59:40Z'),
            [4, 3, 0, 0, 5], 0.1, PirateBlockedReturnEvidence::BLOCKED_ARRIVAL);
    }

    private function evidence(float $reserve = 0.1, array $position = [4, 3, 0, 0, null]): PirateBlockedReturnEvidence
    {
        return new PirateBlockedReturnEvidence(31, 2, 15, 1, 12, new \DateTimeImmutable('2026-10-25T00:59:40Z'), $position, $reserve, PirateBlockedReturnEvidence::HELD_BEFORE_RETURN);
    }

    private function fixture(bool $heldBeforeReturn = false): array
    {
        $at = (new \DateTimeImmutable('2026-10-25T00:59:57Z'))->setTimezone(new \DateTimeZone('Europe/Paris'));

        $universe = new Universe();
        $universe->id = 1;
        $universe->status = 'active';
        $member = new PlayerUniverse();
        $member->id = 2;
        $member->player = new Player();
        $member->player->id = 22;
        $member->universe = $universe;
        $other = clone $member;
        $other->id = 11;

        $galaxy = new Galaxy();
        $galaxy->id = 3;
        $galaxy->universe = $universe;
        $system = new SolarSystem();
        $system->id = 4;
        $system->galaxy = $galaxy;
        $system->coordX = 0;
        $system->coordY = 0;
        $origin = new Planet();
        $origin->id = 15;
        $origin->solarSystem = $system;
        $origin->position = 5;
        $origin->owner = $other;
        $target = clone $origin;
        $target->id = 16;
        $target->position = 6;
        $target->owner = $member;

        $recon = new PirateReconnaissance($member, $member->player, $system, 'fixture', $at->modify('-1 hour'),
            $at->modify('-45 minutes'), 'test-only-fixture', false, PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 1]], ['metal' => 10]));
        $recon->reportWhenDue($member, 'test-only-fixture', $at->modify('-40 minutes'));
        $campaign = new PirateAttackCampaign();
        $campaign->id = 31;
        $campaign->reconnaissance = $recon;
        $campaign->originId = 15;
        $campaign->arrivalAt = new \DateTimeImmutable('2026-10-25T00:55:00Z');
        $campaign->returnBlocked = true;
        $campaign->reservedShips = ['probe' => ['basic' => 1]];
        $campaign->report = ['outcome' => 'defender_wins'];
        $campaign->timeline = ['attempt' => 2, 'state' => 'retryable', 'returnDue' => '2026-10-25T00:59:30+00:00', 'settled' => '2026-10-25T00:59:40+00:00'];
        $ships = [['slug' => 'probe', 'tier' => 'basic', 'quantity' => 1, 'speed_system' => 4800, 'speed_galaxy' => 5000,
            'fuel_travel_system' => 0.01, 'fuel_travel_galaxy' => 0.04, 'fuel_orbit' => 0, 'cargo_capacity' => 16000]];

        if ($heldBeforeReturn) {
            $target->position = 9;
            $campaign->timeline['returnDue'] = null;
            $campaign->timeline['resolved'] = $campaign->timeline['settled'];
        }

        return [$campaign, $member, $origin, $target, $ships, $at];
    }
}
