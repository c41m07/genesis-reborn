<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, PirateAttackCampaign, PirateConvoyDelivery, PirateReconnaissance, Planet, PlanetResource, Player, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Message\{PirateAttackDue, PirateConvoyDue, PirateSurvivorsDue};
use App\MessageHandler\{PirateAttackDueHandler, PirateSurvivorsDueHandler};
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\{CombatSimulator, CombatUnitFactory};
use App\Service\Fleet\MovementCalculator;
use App\Service\Notification\OutboxRelay;
use App\Service\PirateCampaign\{PirateAttackFuelReceipt, PirateAttackService, PirateBattleScenario, PirateCampaignCatalogue, PirateCampaignPresenter, PirateCampaignProgress, PirateCampaignSequence, PirateConvoyDeliveryService, PirateConvoyDispatchToken, PirateDistressSite, PirateEncounterSnapshot, PiratePreparedEncounter, PirateReconnaissanceService, PirateSurvivorRecoveryService};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Process\Process;

final class PirateAttackPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PirateAttackService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-21T09:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $this->service = self::getContainer()->get(PirateAttackService::class);
    }

    public function testNoPersistentAttackBeforeReport(): void
    {
        [$member, $home, $fighter] = $this->fixture();
        $db = $this->em->getConnection();
        try {
            $this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 3);
            self::fail('La preuve de reconnaissance doit être reçue.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('rapport', $error->getMessage());
        }
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$fighter->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testPayingAttackUsesNativeRoundTripAndOrbitWithoutTakingFreightOrRepeatingDebit(): void
    {
        [$member, $home, $fighter, $probe, $other] = $this->fixture(omega: true, scoutPaid: true);
        $this->finishRecon();
        $this->stampStocks($home->id, ['hydrogen' => 100.0]);
        $idle = $fighter->fleet;
        $idle->storedFuel = 0.5;
        $idle->cargo = ['hydrogen' => 0.25];
        $this->em->flush();
        $composition = ['fighter' => ['basic' => 2]];
        $system = $home->solarSystem;
        $originCoords = [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $home->position];
        $targetCoords = array_slice($originCoords, 0, 4);
        $units = self::getContainer()->get(CombatUnitFactory::class)->ships($member->universe, $composition, []);
        $catalog = self::getContainer()->get(GameCatalog::class);
        $native = self::getContainer()->get(MovementCalculator::class)->quote($originCoords, [...$targetCoords, null], $units,
            1.0, $catalog->forUniverse($member->universe)['multipliers']['travel_time'],
            $catalog->rules()['fuel_ratio_policy'], (int) $catalog->rules()['combat_window_seconds'] / 3600);
        self::assertGreaterThan(0, $native['orbit_fuel']);
        self::assertTrue($this->service->launchWithOmegaPilotFuel($member, $home, 'signal', 0, $composition));
        self::assertFalse($this->service->launchWithOmegaPilotFuel($member, $home, 'signal', 0, $composition));

        $campaign = $this->campaign();
        $receipt = PirateAttackFuelReceipt::read($campaign->timeline['pilot_attack_fuel']);
        self::assertSame(1, $receipt->attempt);
        self::assertSame($home->id, $receipt->originId);
        self::assertSame($originCoords, $receipt->origin);
        self::assertSame($targetCoords, $receipt->site);
        self::assertSame($native['one_way_fuel'], $receipt->budget->legHydrogen);
        self::assertSame($native['orbit_fuel'], $receipt->budget->orbitHydrogen);
        self::assertSame($native['total_fuel'], $receipt->budget->initialHydrogen);
        self::assertSame($native['seconds'], $campaign->arrivalAt->getTimestamp() - $campaign->departedAt->getTimestamp());
        self::assertEqualsWithDelta(100.0 - ($native['total_fuel'] - 0.5), $this->resourceStock($home->id, 'hydrogen'), 1e-9);
        self::assertSame(0.5, $receipt->fromHold);
        self::assertSame(0.0, $idle->storedFuel);
        self::assertSame(['hydrogen' => 0.25], $idle->cargo);
        self::assertSame(1, $this->stock($fighter->id));
        self::assertSame(1, $this->stock($probe->id));
        self::assertNull($this->service->statusFor($other, 'signal'));
        self::assertArrayNotHasKey('pilot_attack_fuel', $this->service->statusFor($member, 'signal'));
        $db = $this->em->getConnection();
        self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_attack'"));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status <> 'idle'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_convoy_delivery'));

        // Le snapshot de mouvement vient des mêmes profils natifs que le devis et non du catalogue futur.
        $member->universe->gameSettings['catalog_overrides']['ships']['fighter'] = ['speed_system' => 12, 'fuel_travel_system' => 9.0];
        $this->em->flush();
        $this->em->clear();
        $saved = PirateAttackFuelReceipt::read($this->campaign()->timeline['pilot_attack_fuel']);
        $carry = $saved->survivorMovement(['fighter' => ['basic' => 1]])[0];
        foreach (['speed_system', 'speed_galaxy', 'fuel_travel_system', 'fuel_travel_galaxy', 'fuel_orbit', 'cargo_capacity', 'fuel_capacity'] as $field) {
            self::assertEquals($units[0][$field], $carry[$field], 'Profil figé : '.$field);
        }
        self::assertSame(1, $carry['quantity']);
        self::assertNotEquals(12, $carry['speed_system']);
        self::assertNotEquals(9.0, $carry['fuel_travel_system']);
    }

    public function testFundedAttackRequiresTheNativeReconnaissanceReceipt(): void
    {
        [$member, $home, $fighter] = $this->fixture(omega: true, definition: 'balise_17');
        $this->finishRecon();
        try {
            $this->service->launchWithOmegaPilotFuel($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]);
            self::fail('Un rapport de fixture non payé ne doit pas amorcer l’expédition pilote.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('prélèvement natif', $error->getMessage());
        }
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testFundedAttackDoesNotPayFromFreightWhenPlanetAndHoldAreEmpty(): void
    {
        [$member, $home, $fighter] = $this->fixture(omega: true, scoutPaid: true);
        $this->finishRecon();
        $this->stampStocks($home->id, ['hydrogen' => 0.0]);
        $idle = $fighter->fleet;
        $idle->cargo = ['hydrogen' => 100.0];
        $this->em->flush();
        try {
            $this->service->launchWithOmegaPilotFuel($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]);
            self::fail('Le fret n’est pas un moyen de paiement du vol.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('Ressources insuffisantes', $error->getMessage());
        }
        $db = $this->em->getConnection();
        self::assertSame(100, (int) json_decode($db->fetchOne('SELECT cargo FROM fleet WHERE id = ?', [$idle->id]), true)['hydrogen']);
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(0.0, $this->resourceStock($home->id, 'hydrogen'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testFundedAttackRefusesLeavingFreightWithoutSufficientLocalPorters(): void
    {
        [$member, $home, $fighter] = $this->fixture(omega: true, scoutPaid: true);
        $this->finishRecon();
        $this->stampStocks($home->id, ['hydrogen' => 100.0]);
        $idle = $fighter->fleet;
        $idle->cargo = ['metal' => 400.0];
        $this->em->flush();
        try {
            $this->service->launchWithOmegaPilotFuel($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]);
            self::fail('Les porteurs restants ne peuvent pas garder ce fret.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('vaisseaux restants', $error->getMessage());
        }
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(100.0, $this->resourceStock($home->id, 'hydrogen'));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testFundedAttackRollsBackBothHydrogenSourcesAndForcesWhenOutboxFails(): void
    {
        [$member, $home, $fighter] = $this->fixture(omega: true, scoutPaid: true);
        $this->finishRecon();
        $this->stampStocks($home->id, ['hydrogen' => 100.0]);
        $idle = $fighter->fleet;
        $idle->storedFuel = 0.5;
        $this->em->flush();
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_attack_test_reject_outbox BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture funded attack outbox failure'");
        try {
            $this->service->launchWithOmegaPilotFuel($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]);
            self::fail('Le départ ne peut pas délier prélèvement, réserve et ordre.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture funded attack outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_attack_test_reject_outbox');
        }
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(100.0, $this->resourceStock($home->id, 'hydrogen'));
        self::assertSame(0.5, (float) $db->fetchOne('SELECT stored_fuel FROM fleet WHERE id = ?', [$idle->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testFundedReturnUsesActualCombatClosureAndDepositsSurvivorsOnlyOnce(): void
    {
        [$owner, $origin, $stock, $other, $campaign] = $this->startFundedVictory();
        $combatDue = $campaign->nextAt;
        $this->advanceTo($combatDue->modify('+19 seconds'));
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        $return = $campaign->timeline['pilot_return'];
        self::assertSame('traveling', $return['state']);
        self::assertSame($this->clock->now()->format(DATE_ATOM), $return['observed_at']);
        self::assertEquals($this->clock->now()->modify('+'.$campaign->travelSeconds.' seconds'), $campaign->nextAt);
        self::assertGreaterThan(0, $return['carried']);
        self::assertSame(1, $this->stock($stock->id));
        $remaining = $campaign->reservedShips['fighter']['basic'];
        $stockHydrogen = $this->resourceStock($origin->id, 'hydrogen');
        $token = $campaign->dispatchToken;
        $this->advanceTo($campaign->nextAt->modify('-1 second'));
        self::assertSame(1000, $this->service->processDue($campaign->id, $token));
        self::assertSame(1, $this->stock($stock->id));
        $this->advanceTo($campaign->nextAt->modify('+10 seconds'));
        $this->service->processDue($campaign->id, $token);
        $this->service->processDue($campaign->id, $token);
        self::assertSame(1 + $remaining, $this->stock($stock->id));
        self::assertSame($stockHydrogen, $this->resourceStock($origin->id, 'hydrogen'));
        self::assertSame('arrived', $campaign->timeline['pilot_return']['state']);
        self::assertSame(0.0, $campaign->timeline['pilot_return']['carried']);
        self::assertSame($this->clock->now()->format(DATE_ATOM), $campaign->timeline['settled']);
        self::assertSame([], $campaign->reservedShips);
        self::assertArrayNotHasKey('pilot_blocked_return', $campaign->timeline);
        self::assertNull($this->service->statusFor($other, 'signal'));
    }

    public function testOriginLostBeforeReturnHoldsSurvivorsAtActualSiteWithTheUnusedLeg(): void
    {
        [$owner, $origin, $stock, $other, $campaign] = $this->startFundedVictory();
        $origin->owner = $other;
        $this->em->flush();
        $this->advanceTo($campaign->nextAt->modify('+7 seconds'));
        $token = $campaign->dispatchToken;
        $this->service->processDue($campaign->id, $token);
        $this->service->processDue($campaign->id, $token);
        $evidence = $campaign->timeline['pilot_blocked_return'];
        self::assertSame('origin_unavailable', $campaign->timeline['pilot_return']['state']);
        self::assertSame('held_before_return', $evidence['phase']);
        self::assertSame($owner->id, $evidence['membership']);
        self::assertSame($owner->player->id, $evidence['account']);
        self::assertSame($owner->universe->id, $evidence['universe']);
        self::assertSame($this->clock->now()->format(DATE_ATOM), $evidence['observed_at']);
        self::assertSame([...$campaign->timeline['pilot_attack_fuel']['site'], null], $evidence['position']);
        self::assertEquals($campaign->timeline['pilot_attack_fuel']['leg'], $evidence['hydrogen']);
        self::assertNull($campaign->timeline['returnDue']);
        self::assertNull($campaign->nextAt);
        self::assertNull($campaign->dispatchToken);
        self::assertNotSame([], $campaign->reservedShips);
        self::assertSame(1, $this->stock($stock->id));
        self::assertNull($this->service->statusFor($other, 'signal'));
        $catalog = self::getContainer()->get(PirateCampaignCatalogue::class);
        $presenter = new PirateCampaignPresenter($catalog, new PirateCampaignProgress($catalog), self::getContainer()->get(GameCatalog::class));
        $ownerView = $presenter->forProgress($owner, $campaign->reconnaissance, $campaign, $this->clock->now());
        self::assertNotNull($ownerView);
        self::assertStringNotContainsString('pilot_blocked_return', json_encode($ownerView, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString('return_settled', json_encode($ownerView, JSON_THROW_ON_ERROR));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pirate_convoy_delivery'));
    }

    public function testOriginLostAfterReturnStartsConsumesFullLegAndOnlyAttestsItsFrozenArrival(): void
    {
        [$owner, $origin, $stock, $other, $campaign] = $this->startFundedVictory();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertSame('traveling', $campaign->timeline['pilot_return']['state']);
        $token = $campaign->dispatchToken;
        $due = $campaign->nextAt;
        $origin->owner = $other;
        $this->em->flush();
        $this->advanceTo($due->modify('-1 second'));
        self::assertSame(1000, $this->service->processDue($campaign->id, $token));
        self::assertArrayNotHasKey('pilot_blocked_return', $campaign->timeline);
        $this->advanceTo($due->modify('+13 seconds'));
        $this->service->processDue($campaign->id, $token);
        $this->service->processDue($campaign->id, $token);
        $evidence = $campaign->timeline['pilot_blocked_return'];
        self::assertSame('blocked_arrival', $evidence['phase']);
        self::assertSame(0.0, $evidence['hydrogen']);
        self::assertSame($campaign->timeline['pilot_attack_fuel']['origin'], $evidence['position']);
        self::assertSame($this->clock->now()->format(DATE_ATOM), $evidence['observed_at']);
        self::assertSame('return_blocked', $this->service->statusFor($owner, 'signal')['state']);
        $projector = new PirateCampaignProgress(self::getContainer()->get(PirateCampaignCatalogue::class));
        $progress = $projector->forViewer($owner, $campaign->reconnaissance, $campaign, $this->clock->now());
        self::assertSame('blocked', $progress['returning']);
        self::assertArrayNotHasKey('return_settled', $progress['dates']);
        self::assertNull($this->service->statusFor($other, 'signal'));
        self::assertSame(1, $this->stock($stock->id));
        self::assertNotSame([], $campaign->reservedShips);
    }

    public function testFundedReturnInstallRollbackDoesNotRecordArrivalOrLoseReservedShips(): void
    {
        [$owner, $origin, $stock, , $campaign] = $this->startFundedVictory();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        $this->advanceTo($campaign->nextAt);
        $id = $campaign->id;
        $token = $campaign->dispatchToken;
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_attack_test_return_install BEFORE UPDATE ON fleet_ship FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture funded return failure'");
        try {
            $this->service->processDue($id, $token);
            self::fail('La remise et la preuve du retour doivent rester dans la même transaction.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture funded return failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_attack_test_return_install');
        }
        $row = $db->fetchAssociative('SELECT timeline, reserved_ships, dispatch_token FROM pirate_attack_campaign WHERE id = ?', [$id]);
        $history = json_decode($row['timeline'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('traveling', $history['pilot_return']['state']);
        self::assertSame($token, $row['dispatch_token']);
        self::assertNotSame([], json_decode($row['reserved_ships'], true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->stock($stock->id));
    }

    public function testPrivateRecoveryRequotesAndDeliversTheLotAndOnlyItsNativeRemainder(): void
    {
        [$owner, $target, $other, $campaign, $recovery] = $this->recoverableFundedSurvivors();
        $id = $campaign->id;
        self::assertNull($recovery->preview($other, $id, 1, 1, $target->id));
        self::assertNull($recovery->preview($owner, $id, 1, 2, $target->id));
        self::assertNull($recovery->preview($owner, $id, 1, 1, $campaign->originId));

        $first = $recovery->preview($owner, $id, 1, 1, $target->id);
        self::assertNotNull($first);
        self::assertGreaterThan(0, $first['remaining']);
        self::assertGreaterThan(0, $first['hydrogen']);
        $db = $this->em->getConnection();
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_survivors'"));
        $owner->universe->gameSettings['multipliers']['travel_time'] = 2.0;
        $this->em->flush();
        self::assertFalse($recovery->launch($owner, $id, 1, 1, $target->id, $first['digest']));
        $current = $recovery->preview($owner, $id, 1, 1, $target->id);
        self::assertNotNull($current);
        self::assertNotSame($first['digest'], $current['digest']);
        $start = $this->clock->now()->modify('+12 seconds');
        $this->advanceTo($start);
        self::assertTrue($recovery->launch($owner, $id, 1, 1, $target->id, $current['digest']));
        $saved = $campaign->timeline['pilot_recovery'];
        self::assertSame($start->format(DATE_ATOM), $saved['departure']);
        $due = new \DateTimeImmutable($saved['due']);
        self::assertSame($current['seconds'], $due->getTimestamp() - $start->getTimestamp());
        self::assertSame(2, $saved['generation']);
        self::assertFalse($recovery->launch($owner, $id, 1, 1, $target->id, $current['digest']));
        self::assertNull($recovery->preview($owner, $id, 1, 1, $target->id));
        $event = $db->fetchAssociative("SELECT target_id, token, due_at FROM outbox_message WHERE kind = 'pirate_survivors'");
        self::assertSame($id, (int) $event['target_id']);
        self::assertSame($saved['token'], $event['token']);
        self::assertSame($due->format('Y-m-d H:i:s'), $event['due_at']);
        self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_survivors'"));
        self::assertNull($recovery->processDue($id, str_repeat('0', 36)));
        $this->advanceTo($due->modify('-1 second'));
        $message = new PirateSurvivorsDue($id, $event['token']);
        $handler = self::getContainer()->get(PirateSurvivorsDueHandler::class);
        try {
            $handler($message);
            self::fail('Un événement précoce doit être replanifié.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(1000, $error->getRetryDelay());
        }
        $survivors = $campaign->reservedShips['fighter']['basic'];
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fleet WHERE home_planet_id = ?', [$target->id]));
        $at = $due->modify('+9 seconds');
        $this->advanceTo($at);
        $handler($message);
        $handler($message);
        self::assertSame('delivered', $campaign->timeline['pilot_recovery']['state']);
        self::assertSame($at->format(DATE_ATOM), $campaign->timeline['pilot_recovery']['observed']);
        self::assertSame($at->format(DATE_ATOM), $campaign->timeline['settled']);
        self::assertSame([], $campaign->reservedShips);
        self::assertFalse($campaign->returnBlocked);
        $idle = $db->fetchAssociative('SELECT id, stored_fuel, cargo FROM fleet WHERE home_planet_id = ?', [$target->id]);
        self::assertEqualsWithDelta($current['remaining'], (float) $idle['stored_fuel'], 1e-9);
        self::assertSame([], json_decode($idle['cargo'], true, flags: JSON_THROW_ON_ERROR));
        self::assertSame($survivors, (int) $db->fetchOne("SELECT quantity FROM fleet_ship WHERE fleet_id = ? AND slug = 'fighter'", [(int) $idle['id']]));
        self::assertSame(0.0, $this->resourceStock($target->id, 'hydrogen'));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status <> 'idle'"));
    }

    public function testPrivateRecoveryRecordsLostDestinationWithoutDepositingOrRenewingOldProof(): void
    {
        [$owner, $target, $other, $campaign, $recovery] = $this->recoverableFundedSurvivors();
        $id = $campaign->id;
        $quote = $recovery->preview($owner, $id, 1, 1, $target->id);
        self::assertTrue($recovery->launch($owner, $id, 1, 1, $target->id, $quote['digest']));
        $job = $campaign->timeline['pilot_recovery'];
        $target->owner = $other;
        $this->em->flush();
        $this->advanceTo(new \DateTimeImmutable($job['due']));
        $recovery->processDue($id, $job['token']);
        $recovery->processDue($id, $job['token']);
        $terminal = $campaign->timeline['pilot_recovery'];
        self::assertSame('destination_lost', $terminal['state']);
        self::assertSame($job['destination'], $terminal['destination']);
        self::assertEquals($quote['remaining'], $terminal['initial'] - $terminal['cost']);
        self::assertTrue($campaign->returnBlocked);
        self::assertNotSame([], $campaign->reservedShips);
        self::assertNull($recovery->preview($owner, $id, 1, 1, $target->id));
        $db = $this->em->getConnection();
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fleet WHERE home_planet_id = ?', [$target->id]));
        self::assertSame(0.0, $this->resourceStock($target->id, 'hydrogen'));
    }

    public function testPrivateRecoveryAtReducedCapacityCannotDepositShipsOrFuelSeparately(): void
    {
        [$owner, $target, , $campaign, $recovery] = $this->recoverableFundedSurvivors();
        $id = $campaign->id;
        $quote = $recovery->preview($owner, $id, 1, 1, $target->id);
        self::assertTrue($recovery->launch($owner, $id, 1, 1, $target->id, $quote['digest']));
        self::assertGreaterThan(0.0, $quote['remaining']);
        $job = $campaign->timeline['pilot_recovery'];
        $owner->universe->gameSettings['catalog_overrides']['ships']['fighter'] = ['fuel_capacity' => 0, 'cargo_capacity' => 0];
        $this->em->flush();
        $this->advanceTo(new \DateTimeImmutable($job['due']));
        $recovery->processDue($id, $job['token']);
        self::assertSame('capacity_blocked', $campaign->timeline['pilot_recovery']['state']);
        self::assertNotSame([], $campaign->reservedShips);
        self::assertTrue($campaign->returnBlocked);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM fleet WHERE home_planet_id = ?', [$target->id]));
        self::assertSame(0.0, $this->resourceStock($target->id, 'hydrogen'));
    }

    public function testPrivateRecoveryOutboxFailureDoesNotConsumeTheAttestation(): void
    {
        [$owner, $target, , $campaign, $recovery] = $this->recoverableFundedSurvivors();
        $id = $campaign->id;
        $quote = $recovery->preview($owner, $id, 1, 1, $target->id);
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_recovery_test_outbox BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture repli outbox failure'");
        try {
            $recovery->launch($owner, $id, 1, 1, $target->id, $quote['digest']);
            self::fail('Une preuve de départ ne peut survivre à l’échec de son outbox.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture repli outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_recovery_test_outbox');
        }
        $saved = json_decode($db->fetchOne('SELECT timeline FROM pirate_attack_campaign WHERE id = ?', [$id]), true, flags: JSON_THROW_ON_ERROR);
        self::assertArrayNotHasKey('pilot_recovery', $saved);
        self::assertSame('held_before_return', $saved['pilot_blocked_return']['phase']);
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_survivors'"));
    }

    public function testPrivateRecoveryConcurrencyConsumesTheEvidenceExactlyOnce(): void
    {
        [$owner, $target, , $campaign, $recovery] = $this->recoverableFundedSurvivors();
        $id = $campaign->id;
        $quote = $recovery->preview($owner, $id, 1, 1, $target->id);
        $db = $this->em->getConnection();
        $dir = sys_get_temp_dir().'/gens-recovery-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $barrier = $dir.'/start';
        $clockFile = $dir.'/clock';
        $first = $this->clock->now();
        $late = $first->modify('+45 seconds')->modify('+800000 microseconds');
        file_put_contents($clockFile, $first->format('Y-m-d\\TH:i:s.uP'));
        $data = ['clock_file' => $clockFile, 'owner' => $owner->id,
            'campaign' => $id, 'target' => $target->id, 'digest' => $quote['digest']];
        $workers = $ids = $results = [];
        $db->beginTransaction();
        try {
            $db->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$owner->id]);
            for ($index = 0; $index < 2; ++$index) {
                $input = $data + ['barrier' => $barrier, 'ready' => $dir.'/ready-'.$index];
                $proc = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_pirate_recovery.php', json_encode($input, JSON_THROW_ON_ERROR)]);
                $proc->setTimeout(25);
                $proc->start();
                $workers[] = $proc;
                $deadline = microtime(true) + 12;
                while (!is_file($input['ready']) && microtime(true) < $deadline) {
                    usleep(10000);
                }
                self::assertFileExists($input['ready'], $proc->getErrorOutput());
                $ids[] = (int) file_get_contents($input['ready']);
            }
            touch($barrier);
            $deadline = microtime(true) + 12;
            do {
                $blocked = 0;
                foreach ($db->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $blocked, 'Deux transactions réellement concurrentes sur la preuve.');
            file_put_contents($clockFile, $late->format('Y-m-d\\TH:i:s.uP'));
            $db->commit();
            foreach ($workers as $proc) {
                self::assertSame(0, $proc->wait(), $proc->getOutput().$proc->getErrorOutput());
                $results[] = json_decode(trim($proc->getOutput()), true, flags: JSON_THROW_ON_ERROR);
            }
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($workers as $proc) {
                if ($proc->isRunning()) {
                    $proc->stop();
                }
            }
            foreach (glob($dir.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        sort($results);
        self::assertSame([false, true], $results);
        self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_survivors'"));
        $persisted = json_decode($db->fetchOne('SELECT timeline FROM pirate_attack_campaign WHERE id = ?', [$id]), true, flags: JSON_THROW_ON_ERROR);
        $start = new \DateTimeImmutable($persisted['pilot_recovery']['departure']);
        $due = new \DateTimeImmutable($persisted['pilot_recovery']['due']);
        self::assertEquals($first->modify('+46 seconds'), $start);
        self::assertSame($quote['seconds'], $due->getTimestamp() - $start->getTimestamp());
    }

    public function testPrivateRecoveryInstallFailureLeavesTravelAndEverySurvivorReplayable(): void
    {
        [$owner, $target, , $campaign, $recovery] = $this->recoverableFundedSurvivors();
        $id = $campaign->id;
        $quote = $recovery->preview($owner, $id, 1, 1, $target->id);
        self::assertTrue($recovery->launch($owner, $id, 1, 1, $target->id, $quote['digest']));
        $job = $campaign->timeline['pilot_recovery'];
        $this->advanceTo(new \DateTimeImmutable($job['due']));
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_recovery_test_install BEFORE INSERT ON fleet_ship FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture repli install failure'");
        try {
            $recovery->processDue($id, $job['token']);
            self::fail('Une remise incomplète ne peut consommer ni carburant ni vaisseau.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture repli install failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_recovery_test_install');
        }
        $row = $db->fetchAssociative('SELECT timeline, reserved_ships FROM pirate_attack_campaign WHERE id = ?', [$id]);
        self::assertEquals($job, json_decode($row['timeline'], true, flags: JSON_THROW_ON_ERROR)['pilot_recovery']);
        self::assertNotSame([], json_decode($row['reserved_ships'], true, flags: JSON_THROW_ON_ERROR));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM fleet WHERE home_planet_id = ?', [$target->id]));
    }

    /** @return array{PlayerUniverse, Planet, PlayerUniverse, PirateAttackCampaign, PirateSurvivorRecoveryService} */
    private function recoverableFundedSurvivors(): array
    {
        [$owner, $origin, , $other, $campaign] = $this->startFundedVictory();
        $next = $this->createReplacementColony($owner, $origin);
        $origin->owner = $other;
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertSame('held_before_return', $campaign->timeline['pilot_blocked_return']['phase']);

        return [$owner, $next, $other, $campaign, self::getContainer()->get(PirateSurvivorRecoveryService::class)];
    }

    public function testActualCarrierLossLeavesInsufficientFuelAndCannotBeOverwrittenByRetry(): void
    {
        [$owner, $origin, $fighter, $probe, $campaign] = $this->startFundedDefeat(keepOneLowCapacitySurvivor: true);
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertNotSame('attacker_wins', $campaign->report['outcome']);
        self::assertSame('return_fuel_insufficient', $this->service->statusFor($owner, 'signal')['state']);
        self::assertSame('insufficient_hydrogen', $campaign->timeline['pilot_return']['state']);
        self::assertEquals(1, $campaign->timeline['pilot_return']['carried']);
        self::assertSame(['fighter' => ['basic' => 1]], $campaign->reservedShips);
        self::assertArrayNotHasKey('pilot_blocked_return', $campaign->timeline);
        self::assertNull($campaign->nextAt);
        self::assertNull($campaign->dispatchToken);
        self::assertNull($campaign->timeline['returnDue']);
        $db = $this->em->getConnection();
        $events = (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_attack'");
        self::assertSame(2, $events, 'L’arrivée et le combat, jamais un troisième ordre pour un faux retour.');
        $stock = $this->resourceStock($origin->id, 'hydrogen');
        try {
            $this->service->launchWithOmegaPilotFuel($owner, $origin, 'signal', 1, ['fighter' => ['basic' => 1]]);
            self::fail('Une relance ne doit pas écraser les survivants retenus.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('survivants', $error->getMessage());
        }
        $persisted = json_decode($db->fetchOne('SELECT reserved_ships FROM pirate_attack_campaign WHERE id = ?', [$campaign->id]), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(['fighter' => ['basic' => 1]], $persisted);
        self::assertSame($stock, $this->resourceStock($origin->id, 'hydrogen'));
        self::assertSame($events, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_attack'"));
        self::assertSame(1, $this->stock($probe->id));
    }

    public function testActualLossOfEveryShipLeavesNoReturnAndPermitsRetryWithoutAnotherScout(): void
    {
        [$owner, $origin, $fighter, $probe, $campaign] = $this->startFundedDefeat(keepOneLowCapacitySurvivor: false);
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertSame('defender_wins', $campaign->report['outcome']);
        self::assertSame([], $campaign->reservedShips);
        self::assertSame('retryable', $this->service->statusFor($owner, 'signal')['state']);
        self::assertArrayNotHasKey('pilot_blocked_return', $campaign->timeline);
        self::assertArrayNotHasKey('pilot_return', $campaign->timeline);
        self::assertNull($campaign->nextAt);
        $hydrogen = $this->resourceStock($origin->id, 'hydrogen');
        $proof = $campaign->scenario;
        self::assertTrue($this->service->launchWithOmegaPilotFuel($owner, $origin, 'signal', 1, ['fighter' => ['basic' => 1]]));
        self::assertSame(2, $campaign->timeline['pilot_attack_fuel']['attempt']);
        self::assertSame($proof, $campaign->scenario);
        self::assertLessThan($hydrogen, $this->resourceStock($origin->id, 'hydrogen'));
        self::assertSame(1, $this->stock($probe->id));
    }

    private function startFundedDefeat(bool $keepOneLowCapacitySurvivor): array
    {
        // L'adversaire a son vrai profil gelé pendant le vol ; le catalogue joueur synthétique
        // varie ensuite, pour éprouver la perte d'un porteur sans toucher une règle réelle.
        $enemy = ['hp' => 1000000, 'shield' => 0, 'base_attack' => 1200, 'precision' => 300, 'tracking' => 300,
            'shots_min' => 1, 'shots_max' => 1];
        [$owner, $origin, $fighter, $probe] = $this->fixture(omega: true, scoutPaid: true, scoutOpponentOverride: $enemy);
        $this->finishRecon();
        $owner->universe->gameSettings['catalog_overrides']['ships']['fighter'] = $keepOneLowCapacitySurvivor
            ? ['hp' => 1000000, 'shield' => 0, 'base_attack' => 1, 'fuel_capacity' => 1, 'cargo_capacity' => 0]
            : ['hp' => 1, 'shield' => 0, 'base_attack' => 1];
        $fleet = ['fighter' => ['basic' => 1]];
        if ($keepOneLowCapacitySurvivor) {
            $this->addShips($fighter->fleet, 'heavy_fighter', 1);
            $owner->universe->gameSettings['catalog_overrides']['ships']['heavy_fighter'] =
                ['hp' => 1, 'shield' => 0, 'base_attack' => 1, 'signature' => 150];
            $fleet['heavy_fighter'] = ['basic' => 1];
        }
        $this->em->flush();
        self::assertTrue($this->service->launchWithOmegaPilotFuel($owner, $origin, 'signal', 0, $fleet));
        $campaign = $this->campaign();
        $report = $campaign->reconnaissance->battleProofFor($owner, $this->clock->now());
        $scenario = $report['prepared']->battleForServer();
        $campaign->timeline['seed'] = $this->findSeed($campaign, $scenario, static function (array $result) use ($keepOneLowCapacitySurvivor): bool {
            $lost = $keepOneLowCapacitySurvivor ? ['escort:heavy_fighter' => 1] : ['escort:fighter' => 1];

            return $result['outcome'] !== 'attacker_wins' && $result['losses'] === $lost;
        });
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);

        return [$owner, $origin, $fighter, $probe, $campaign];
    }

    private function startFundedVictory(): array
    {
        [$owner, $origin, $stock, , $other] = $this->fixture(omega: true, scoutPaid: true);
        $this->finishRecon();
        self::assertTrue($this->service->launchWithOmegaPilotFuel($owner, $origin, 'signal', 0, ['fighter' => ['basic' => 2]]));
        $campaign = $this->campaign();
        $reported = $campaign->reconnaissance->battleProofFor($owner, $this->clock->now());
        $campaign->timeline['seed'] = $this->seedWithVictory($campaign, $reported['prepared']->battleForServer());
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertNull($campaign->report);
        self::assertArrayNotHasKey('pilot_return', $campaign->timeline);

        return [$owner, $origin, $stock, $other, $campaign];
    }

    public function testCatalogueProfileAndCostsSurviveChangesBeforeRevealAndFirstAttack(): void
    {
        [$member, $home, $stock, $probe, $ally] = $this->fixture(definition: 'balise_17');
        $db = $this->em->getConnection();
        $row = $db->fetchAssociative('SELECT prepared_encounter, build_costs FROM pirate_reconnaissance');
        $preparedAtLaunch = json_decode($row['prepared_encounter'], true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(1440, json_decode($row['build_costs'], true, flags: JSON_THROW_ON_ERROR)['metal']);

        // Modifier le catalogue en vigueur pendant le vol de la sonde, puis relire toutes les preuves.
        $member->universe->gameSettings['catalog_overrides']['ships']['fighter'] = [
            'base_attack' => 50000,
            'cost_profiles' => ['basic' => ['metal' => 2345]],
        ];
        $this->em->flush();
        $future = self::getContainer()->get(PirateCampaignCatalogue::class)->prepareForServer('balise_17', $member->universe);
        self::assertSame(2345, $future->encounterForServer()->buildCosts()['metal']);
        self::assertNotSame($preparedAtLaunch['scenario']['units'][0]['base_attack'], $future->battleForServer()->snapshot()['units'][0]['base_attack']);
        $this->finishRecon();

        $mId = $member->id;
        $pId = $home->id;
        $allyId = $ally->id;
        $this->em->clear();
        $member = $this->em->find(PlayerUniverse::class, $mId);
        $home = $this->em->find(Planet::class, $pId);
        $ally = $this->em->find(PlayerUniverse::class, $allyId);
        $recon = $this->em->getRepository(PirateReconnaissance::class)->findOneBy(['membership' => $member, 'missionKey' => 'signal']);
        $progress = new PirateCampaignProgress(self::getContainer()->get(PirateCampaignCatalogue::class));
        self::assertNull($progress->forViewer($ally, $recon, null, $this->clock->now()));
        $report = $progress->forViewer($member, $recon, null, $this->clock->now());
        self::assertSame('RenseignementRecu', $report['phase']);
        self::assertSame(['fighter' => ['basic' => 1]], $report['composition']);
        self::assertArrayNotHasKey('cost', $report);

        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 3));
        $attack = $this->campaign();
        self::assertSame($preparedAtLaunch['scenario'], $attack->scenario);
        self::assertSame(1, $this->stock($stock->id));
        self::assertSame(1, $this->stock($probe->id));
        self::assertSame('AttaqueEngagee', $progress->forViewer($member, $recon, $attack, $this->clock->now())['phase']);
    }

    public function testLegacyReportWithoutFrozenProfilesCannotStartAnAttack(): void
    {
        [$member, $home, $fighter] = $this->fixture(legacy: true);
        $this->finishRecon();
        self::assertSame('reported', self::getContainer()->get(PirateReconnaissanceService::class)->statusFor($member, 'signal')['status']);
        try {
            $this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 3);
            self::fail('L’ancien rapport ne doit pas être complété à partir du catalogue courant.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('scénario figé', $error->getMessage());
        }
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testReservationRealLossAndSurvivorsReturnAtSeparateDeadlines(): void
    {
        $scenario = $this->scenario(400);
        [$member, $home, $stock, $probe, $ally] = $this->fixture($scenario);
        $this->finishRecon();
        $db = $this->em->getConnection();
        $fleet = ['fighter' => ['basic' => 2]];
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, $fleet, 3));
        self::assertFalse($this->service->launch($member, $home, 'signal', 0, $fleet, 3));
        self::assertSame(1, $this->stock($stock->id));
        self::assertSame(1, $this->stock($probe->id));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status <> 'idle'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM combat_event'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM notification'));
        self::assertNull($this->service->statusFor($ally, 'signal'));

        // Utiliser les profils réellement saisis au départ, avec une graine figée pour vérifier les pertes exactes.
        $campaign = $this->campaign();
        $campaign->timeline['seed'] = $this->seedWithOneLoss($campaign, $scenario);
        $this->em->flush();
        $due = $campaign->nextAt;
        $travelSeconds = $campaign->travelSeconds;
        $messageId = $campaign->id;
        $token = $campaign->dispatchToken;
        self::assertGreaterThan($campaign->departedAt->getTimestamp(), $campaign->arrivalAt->getTimestamp());
        self::assertGreaterThan($campaign->arrivalAt->getTimestamp(), $due->getTimestamp());
        self::assertSame(1, self::getContainer()->get(OutboxRelay::class)->run());
        $member = $this->em->find(PlayerUniverse::class, $member->id);
        $home = $this->em->find(Planet::class, $home->id);
        $message = new PirateAttackDue($messageId, $token);
        $handler = self::getContainer()->get(PirateAttackDueHandler::class);
        $this->advanceTo($due->modify('-1 second'));
        try {
            $handler($message);
            self::fail('Le bon événement reçu trop tôt doit être replanifié.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(1000, $error->getRetryDelay());
        }
        self::assertNull($this->service->statusFor($member, 'signal')['report']);
        self::assertSame(1, $this->stock($stock->id));

        $this->advanceTo($due);
        $handler($message);
        $handler($message);
        $campaign = $this->campaign();
        self::assertSame('returning', $this->service->statusFor($member, 'signal')['state']);
        self::assertSame('defender_wins', $campaign->report['outcome']);
        self::assertSame(['escort:fighter' => 1], $campaign->report['losses']);
        self::assertSame(['fighter' => ['basic' => 1]], $campaign->reservedShips);
        self::assertSame(1, $this->stock($stock->id));
        $returnDue = $campaign->nextAt;
        self::assertSame($travelSeconds, $returnDue->getTimestamp() - $due->getTimestamp());

        $return = new PirateAttackDue($campaign->id, $campaign->dispatchToken);
        $this->advanceTo($returnDue->modify('-1 second'));
        $handler($message); // Ancien message du combat : aucune nouvelle tentative ni aucun nouveau report.
        try {
            $handler($return);
            self::fail('Le retour précoce doit être replanifié sans remettre la réserve.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(1000, $error->getRetryDelay());
        }
        self::assertSame(1, $this->stock($stock->id));
        $this->advanceTo($returnDue);
        $handler($return);
        $handler($return);
        self::assertSame(2, $this->stock($stock->id));
        self::assertSame('retryable', $this->service->statusFor($member, 'signal')['state']);
        self::assertFalse($this->service->launch($member, $home, 'signal', 0, $fleet));
        self::assertTrue($this->service->launch($member, $home, 'signal', 1, $fleet));
        self::assertSame(2, $this->service->statusFor($member, 'signal')['attempt']);
        self::assertSame($scenario->snapshot(), $this->campaign()->scenario);
        self::assertSame(1, $this->stock($probe->id));
    }

    public function testInvalidDestinationKeepsSurvivorsReservedAndVictoryTerminal(): void
    {
        $win = $this->scenario(enemyHp: 1);
        [$member, $home, $stock, $probe, $ally] = $this->fixture($win);
        $this->finishRecon();
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 5));
        $campaign = $this->campaign();
        $campaign->timeline['seed'] = $this->seedWithVictory($campaign, $win);
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertSame('attacker_wins', $this->service->statusFor($member, 'signal')['report']['outcome']);
        $home->owner = $ally;
        $this->em->flush();
        $return = $campaign->dispatchToken;
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $return);
        $this->service->processDue($campaign->id, $return);
        self::assertSame('return_blocked', $this->service->statusFor($member, 'signal')['state']);
        self::assertNull($this->service->statusFor($ally, 'signal'));
        self::assertSame(['fighter' => ['basic' => 2]], $campaign->reservedShips);
        self::assertSame(1, $this->stock($stock->id));
        self::assertSame(1, $this->stock($probe->id));
    }

    public function testFailedAttackOutboxRollsBackReservedForces(): void
    {
        [$member, $home, $fighter] = $this->fixture();
        $this->finishRecon();
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_attack_test_reject_outbox BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture attack outbox failure'");
        try {
            $this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 3);
            self::fail('La réservation sans phase planifiée doit être annulée.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture attack outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_attack_test_reject_outbox');
        }
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testFailedReturnOutboxLeavesCombatUnresolvedAndReservationIntact(): void
    {
        [$member, $home, $stock] = $this->fixture();
        $this->finishRecon();
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 3));
        $campaign = $this->campaign();
        $token = $campaign->dispatchToken;
        $target = $campaign->id;
        $this->advanceTo($campaign->nextAt);
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_attack_test_reject_return BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture return outbox failure'");
        try {
            $this->service->processDue($target, $token);
            self::fail('Le combat ne doit pas être enregistré sans la phase de retour de ses survivants.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture return outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_attack_test_reject_return');
        }
        $row = $db->fetchAssociative('SELECT report, dispatch_token, reserved_ships FROM pirate_attack_campaign WHERE id = ?', [$target]);
        self::assertNull($row['report']);
        self::assertSame($token, $row['dispatch_token']);
        self::assertSame(['fighter' => ['basic' => 2]], json_decode($row['reserved_ships'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->stock($stock->id));

        self::ensureKernelShutdown();
        self::bootKernel();
        self::getContainer()->set('clock', $this->clock);
        $service = self::getContainer()->get(PirateAttackService::class);
        $service->processDue($target, $token);
        $service->processDue($target, $token);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('defender_wins', $this->campaign()->report['outcome']);
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testConcurrentReturnDoesNotInstallSurvivorsTwice(): void
    {
        $win = $this->scenario(enemyHp: 1);
        [$member, $home, $stock] = $this->fixture($win);
        $this->finishRecon();
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], 5));
        $campaign = $this->campaign();
        $campaign->timeline['seed'] = $this->seedWithVictory($campaign, $win);
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        $this->advanceTo($campaign->nextAt);

        $db = $this->em->getConnection();
        $directory = sys_get_temp_dir().'/ges-pirate-return-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $barrier = $directory.'/go';
        $workers = [];
        $db->beginTransaction();
        try {
            // Tenir le verrou de l'empire jusqu'à l'arrivée simultanée des deux copies de message.
            $db->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$member->id]);
            $common = ['now' => $this->clock->now()->format(DATE_ATOM), 'campaign' => $campaign->id,
                'token' => $campaign->dispatchToken, 'barrier' => $barrier];
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_pirate_attack.php',
                    json_encode($common + ['ready' => $directory.'/'.$index], JSON_THROW_ON_ERROR)]);
                $process->setTimeout(25);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = glob($directory.'/[0-9]') ?: [];
                if (count($ready) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $ready, implode('; ', array_map(static fn (Process $p): string => $p->getErrorOutput(), $workers)));
            $ids = array_map(static fn (string $file): int => (int) file_get_contents($file), $ready);
            touch($barrier);
            do {
                $blocked = 0;
                foreach ($db->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $blocked, 'Les deux workers doivent attendre en même temps.');
            $db->commit();
            foreach ($workers as $worker) {
                self::assertSame(0, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
                self::assertSame('processed', trim($worker->getOutput()));
            }
            $this->em->refresh($campaign);
            self::assertSame('won', $campaign->timeline['state']);
            self::assertSame(3, $this->stock($stock->id));
            self::assertSame([], $campaign->reservedShips);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    public function testPrivateArrivalHasAnOpaqueTwoRoundResultWithAtomicRecovery(): void
    {
        $scenario = $this->scenario(enemyHp: 150, rounds: 10);
        [$member, $home, $ship, $probe, $ally] = $this->fixture($scenario);
        $memberId = $member->id;
        $shipId = $ship->id;
        $probeId = $probe->id;
        $this->finishRecon();
        self::assertTrue($this->service->launchWithPrivateArrival($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]));
        $attack = $this->campaign();
        $attack->timeline['seed'] = $this->findSeed($attack, $scenario, static fn (array $result): bool =>
            $result['rounds_played'] === 2 && $result['outcome'] === 'attacker_wins');
        $this->em->flush();
        $arrivalId = $attack->id;
        $arrivalToken = $attack->dispatchToken;
        $arrival = $attack->arrivalAt;
        self::assertEquals($arrival, $attack->nextAt);
        $this->advanceTo($arrival->modify('-1 second'));
        self::assertSame(1000, $this->service->processDue($arrivalId, $arrivalToken));
        self::assertArrayNotHasKey('opaque_battle', $this->campaign()->timeline);
        self::assertNull($this->service->statusFor($member, 'signal')['next_at']);

        // Même si la réception survient après le dernier round nominal, elle ne publie pas de résultat.
        $confirmed = $arrival->modify('+150 seconds');
        $this->advanceTo($confirmed);
        $this->service->processDue($arrivalId, $arrivalToken);
        $attack = $this->campaign();
        $sealed = $attack->timeline;
        $combatToken = $attack->dispatchToken;
        self::assertNotSame($arrivalToken, $combatToken);
        self::assertEquals($arrival->modify('+120 seconds'), $attack->nextAt);
        self::assertSame($confirmed->format(DATE_ATOM), $sealed['arrival_observed']);
        self::assertNull($attack->report);
        self::assertSame(1, $this->stock($shipId));
        self::assertSame(['fighter' => ['basic' => 2]], $attack->reservedShips);
        self::assertSame('combat_in_progress', $this->service->statusFor($member, 'signal')['state']);
        self::assertNull($this->service->statusFor($member, 'signal')['next_at']);
        self::assertNull($this->service->statusFor($ally, 'signal'));
        $this->service->processDue($arrivalId, $arrivalToken);
        self::assertSame($sealed, $this->campaign()->timeline);

        $db = $this->em->getConnection();
        $outbox = (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message');
        $db->executeStatement("CREATE TRIGGER v2_attack_reject_return BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture v2 outbox failure'");
        try {
            $this->service->processDue($arrivalId, $combatToken);
            self::fail('La publication doit échouer avec l’ordre de retour.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture v2 outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS v2_attack_reject_return');
        }
        $row = $db->fetchAssociative('SELECT report, timeline, dispatch_token FROM pirate_attack_campaign');
        self::assertNull($row['report']);
        self::assertSame($sealed, json_decode($row['timeline'], true, flags: JSON_THROW_ON_ERROR));
        self::assertSame($combatToken, $row['dispatch_token']);
        self::assertSame($outbox, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        self::assertSame(1, $this->stock($shipId));

        self::ensureKernelShutdown();
        self::bootKernel();
        self::getContainer()->set('clock', $this->clock);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->service = self::getContainer()->get(PirateAttackService::class);
        $member = $this->em->find(PlayerUniverse::class, $memberId);
        $processedAt = $confirmed->modify('+4 seconds');
        $this->advanceTo($processedAt);
        $this->service->processDue($arrivalId, $combatToken);
        $this->service->processDue($arrivalId, $combatToken);
        $attack = $this->campaign();
        self::assertSame(['outcome' => 'attacker_wins', 'rounds_played' => 2, 'losses' => ['pirates:fixture_raider' => 1]], $attack->report);
        self::assertSame($processedAt->format(DATE_ATOM), $attack->timeline['resolved']);
        self::assertArrayNotHasKey('opaque_battle', $attack->timeline);
        self::assertSame('returning', $this->service->statusFor($member, 'signal')['state']);
        $returnToken = $attack->dispatchToken;
        self::assertEquals($processedAt->modify('+'.$attack->travelSeconds.' seconds'), $attack->nextAt);
        $this->advanceTo($attack->nextAt);
        $this->service->processDue($arrivalId, $returnToken);
        $this->service->processDue($arrivalId, $returnToken);
        self::assertSame(3, $this->stock($shipId));
        self::assertSame(1, $this->stock($probeId));
        self::assertSame('won', $this->service->statusFor($member, 'signal')['state']);
    }

    public function testPrivateArrivalUsesTenRoundsIfNeededWithoutPrePublishingDefeat(): void
    {
        [$member, $home, $ship] = $this->fixture($this->scenario(rounds: 10));
        $this->finishRecon();
        self::assertTrue($this->service->launchWithPrivateArrival($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]));
        $attack = $this->campaign();
        $arrival = $attack->arrivalAt;
        $this->advanceTo($arrival);
        $this->service->processDue($attack->id, $attack->dispatchToken);
        $attack = $this->campaign();
        $battleToken = $attack->dispatchToken;
        self::assertEquals($arrival->modify('+600 seconds'), $attack->nextAt);
        $this->advanceTo($arrival->modify('+599 seconds'));
        self::assertSame(1000, $this->service->processDue($attack->id, $battleToken));
        self::assertNull($this->service->statusFor($member, 'signal')['report']);
        self::assertNull($this->service->statusFor($member, 'signal')['next_at']);
        $this->advanceTo($arrival->modify('+600 seconds'));
        $this->service->processDue($attack->id, $battleToken);
        self::assertSame(10, $this->campaign()->report['rounds_played']);
        self::assertSame('defender_wins', $this->campaign()->report['outcome']);
        self::assertSame(1, $this->stock($ship->id));
    }

    public function testConvoyRightHydratesUnderLockAndUniquenessDoesNotDependOnAttemptNumber(): void
    {
        [$member, $home, $stock] = $this->fixture($this->scenario(enemyHp: 1, rounds: 10));
        $this->finishRecon();
        self::assertTrue($this->service->launchWithPrivateArrival($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]));
        $attack = $this->campaign();
        $this->advanceTo($attack->nextAt);
        $this->service->processDue($attack->id, $attack->dispatchToken);
        $attack = $this->campaign();
        $this->advanceTo($attack->nextAt);
        $this->service->processDue($attack->id, $attack->dispatchToken);
        $attack = $this->campaign();
        self::assertSame('attacker_wins', $attack->report['outcome']);
        $convoy = PirateConvoyDelivery::forVictory($attack, $member, $this->clock->now());
        $this->em->persist($convoy);
        $timeline = $convoy->timeline();
        self::assertSame(21.0, $timeline->quote->depositAmounts()['metal']);
        $token = PirateConvoyDispatchToken::generate(1);
        $system = $home->solarSystem;
        self::assertTrue($timeline->dispatch($member->id, $member->universe->id, $home->id,
            $timeline->attestedPositionForDeparture(), [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $home->position],
            $token, $this->clock->now(), $this->clock->now()->modify('+60 seconds')));
        $convoy->replaceState($timeline);
        $this->em->flush();
        $rightId = $convoy->id;
        $attackId = $attack->id;
        $memberId = $member->id;
        $stockId = $stock->id;
        $this->em->clear();

        $manager = self::getContainer()->get(\App\Manager\GameManager::class);
        $manager->transaction(function () use ($manager, $rightId, $token, $memberId): void {
            $fresh = $this->em->find(PirateConvoyDelivery::class, $rightId);
            $manager->lockCurrent($fresh);
            self::assertSame($memberId, $fresh->membershipId);
            self::assertSame($token, $fresh->timeline()->checkpoint()['token']);
            self::assertSame('traveling', $fresh->timeline()->state());
        });
        self::assertSame(1, $this->stock($stockId), 'Le droit et son état ne déposent rien au départ.');

        // Même progression, faux autre numéro d'essai : le SGBD doit rejeter un second droit.
        $attack = $this->em->find(PirateAttackCampaign::class, $attackId);
        $member = $this->em->find(PlayerUniverse::class, $memberId);
        ++$attack->timeline['attempt'];
        $second = PirateConvoyDelivery::forVictory($attack, $member, $this->clock->now());
        self::assertNotSame($convoy->winningAttempt, $second->winningAttempt);
        $db = $this->em->getConnection();
        try {
            $this->em->persist($second);
            $this->em->flush();
            self::fail('Un même combat gagné ne peut produire un second droit.');
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_convoy_delivery'));
        }
    }

    public function testPilotConvoyIsScheduledOnlyAfterVictoryAndRetriesTheWholeLotAtTheSameColony(): void
    {
        [$owner, $home, $ally, $attack, $event] = $this->pilotVictoryWithConvoy();
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        $db = $this->em->getConnection();
        $right = $this->em->find(PirateConvoyDelivery::class, (int) $event['target_id']);
        $due = $right->timeline()->chronology()['due_at'];
        self::assertGreaterThan(new \DateTimeImmutable($attack->timeline['resolved']), $due);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_convoy_delivery'));
        $catalogue = self::getContainer()->get(PirateCampaignCatalogue::class);
        $sequence = new PirateCampaignSequence($catalogue, new PirateCampaignProgress($catalogue));
        self::assertSame([
            'current_mission' => 'escorte_interceptee', 'next_signal' => 'escorte_interceptee', 'consultable' => ['balise_17'],
        ], $sequence->forViewer($owner, [['reconnaissance' => $attack->reconnaissance, 'attack' => $attack]], $this->clock->now()));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status <> 'idle'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM transport_report'));
        self::assertNull($service->retryDeposit($ally, $right->id));

        $this->advanceTo($due->modify('-1 second'));
        self::assertNull($service->processDue($right->id, 'fixture-obsolete'));
        self::assertSame(1000, $service->processDue($right->id, $event['token']));
        self::assertSame('traveling', $right->timeline()->state());
        self::assertSame(0.0, $this->resourceStock($home->id, 'metal'));
        self::assertGreaterThanOrEqual(1, self::getContainer()->get(OutboxRelay::class)->run());
        $sent = self::getContainer()->get('messenger.transport.async')->getSent();
        self::assertCount(1, array_filter($sent, static fn ($envelope): bool => $envelope->getMessage() instanceof PirateConvoyDue));
        $home = $this->em->find(Planet::class, $home->id);
        $owner = $this->em->find(PlayerUniverse::class, $owner->id);
        $right = $this->em->find(PirateConvoyDelivery::class, $right->id);

        $late = $due->modify('+7 seconds');
        $this->advanceTo($late);
        $capacity = self::getContainer()->get(\App\Service\Colony\ResourceService::class)->capacity($home, 'metal', []);
        $this->stampStocks($home->id, ['metal' => $capacity - 41, 'nano' => 0.0, 'antimatter' => 0.0]);
        $service->processDue($right->id, $event['token']);
        $service->processDue($right->id, $event['token']);
        self::assertSame('waiting_capacity', $right->timeline()->state());
        self::assertEquals($late, $right->timeline()->chronology()['arrived_at']);
        self::assertSame($capacity - 41, $this->resourceStock($home->id, 'metal'));
        self::assertSame(0.0, $this->resourceStock($home->id, 'nano'), 'Une autre ressource du lot ne peut pas être versée seule.');
        self::assertSame(0.0, $this->resourceStock($home->id, 'antimatter'));
        $this->stampStocks($home->id, ['metal' => $capacity - 41.5]);
        self::assertSame('settled', $service->retryDeposit($owner, $right->id));
        self::assertSame('settled', $service->retryDeposit($owner, $right->id));
        self::assertSame($capacity, $this->resourceStock($home->id, 'metal'));
        self::assertSame(0.5, $this->resourceStock($home->id, 'nano'));
        self::assertSame(3.5, $this->resourceStock($home->id, 'antimatter'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testConvoyReceiptFailureRollsBackWholeDepositAndCanBeReplayed(): void
    {
        [, $home, , , $event] = $this->pilotVictoryWithConvoy();
        $rightId = (int) $event['target_id'];
        $homeId = $home->id;
        $right = $this->em->find(PirateConvoyDelivery::class, $rightId);
        $this->advanceTo($right->timeline()->chronology()['due_at']);
        $this->stampStocks($homeId, ['metal' => 0.0, 'nano' => 0.0, 'antimatter' => 0.0]);
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER convoy_reject_receipt BEFORE UPDATE ON pirate_convoy_delivery FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture refused convoy receipt'");
        try {
            self::getContainer()->get(PirateConvoyDeliveryService::class)->processDue($rightId, $event['token']);
            self::fail('Le reçu doit échouer avec le dépôt.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture refused convoy receipt', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS convoy_reject_receipt');
        }
        self::assertSame(0.0, $this->resourceStock($homeId, 'metal'));
        self::assertSame(0.0, $this->resourceStock($homeId, 'nano'));
        self::assertNull($db->fetchOne('SELECT settled_at FROM pirate_convoy_delivery WHERE id = ?', [$rightId]));
        self::assertSame($event['token'], $db->fetchOne('SELECT dispatch_token FROM pirate_convoy_delivery WHERE id = ?', [$rightId]));
        self::ensureKernelShutdown();
        self::bootKernel();
        self::getContainer()->set('clock', $this->clock);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        $service->processDue($rightId, $event['token']);
        $service->processDue($rightId, $event['token']);
        self::assertSame(41.5, $this->resourceStock($homeId, 'metal'));
        self::assertSame(0.5, $this->resourceStock($homeId, 'nano'));
        self::assertSame('settled', $this->em->find(PirateConvoyDelivery::class, $rightId)->timeline()->state());
    }

    public function testConvoyAttestsLostDestinationWithoutTransferringToNewOwner(): void
    {
        [, $home, $ally, , $event] = $this->pilotVictoryWithConvoy();
        $right = $this->em->find(PirateConvoyDelivery::class, (int) $event['target_id']);
        $this->advanceTo($right->timeline()->chronology()['due_at']);
        $home->owner = $ally;
        $this->em->flush();
        $this->stampStocks($home->id, ['metal' => 0.0, 'nano' => 0.0, 'antimatter' => 0.0]);
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        $service->processDue($right->id, $event['token']);
        $service->processDue($right->id, $event['token']);
        self::assertSame('destination_lost', $right->timeline()->state());
        self::assertSame($home->position, $right->timeline()->attestedPositionForDeparture()[4]);
        self::assertSame(0.0, $this->resourceStock($home->id, 'metal'));
        self::assertNull($service->retryDeposit($ally, $right->id));
    }

    public function testConvoyArrivalAndManualClaimCompeteWithoutSecondCredit(): void
    {
        [$member, $home, , , $event] = $this->pilotVictoryWithConvoy();
        $rightId = (int) $event['target_id'];
        $right = $this->em->find(PirateConvoyDelivery::class, $rightId);
        $this->advanceTo($right->timeline()->chronology()['due_at']);
        $this->stampStocks($home->id, ['metal' => 0.0, 'nano' => 0.0, 'antimatter' => 0.0]);
        $reply = $this->raceConvoy($member, $rightId, $event['token'], 'claim', $home->id);
        self::assertContains($reply, ['traveling', 'settled']);
        self::assertSame(41.5, $this->resourceStock($home->id, 'metal'));
        self::assertSame(0.5, $this->resourceStock($home->id, 'nano'));
        self::assertSame(3.5, $this->resourceStock($home->id, 'antimatter'));
        $this->em->refresh($right);
        self::assertSame('settled', $right->timeline()->state());
    }

    public function testConvoyRetargetCompetesWithLostArrivalAndOldTokenCannotSettleNewLeg(): void
    {
        [$member, $lost, $newOwner, , $event] = $this->pilotVictoryWithConvoy();
        $next = $this->createReplacementColony($member, $lost);
        $rightId = (int) $event['target_id'];
        $right = $this->em->find(PirateConvoyDelivery::class, $rightId);
        $departed = $right->timeline()->chronology()['departed_at'];
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        self::assertSame('traveling', $service->chooseOwnedDestination($member, $rightId, $next->id));
        self::assertEquals($departed, $right->timeline()->chronology()['departed_at']);

        $lost->owner = $newOwner;
        $this->em->flush();
        $this->advanceTo($right->timeline()->chronology()['due_at']);
        $reply = $this->raceConvoy($member, $rightId, $event['token'], 'retarget', $next->id);
        self::assertSame('traveling', $reply);
        $this->em->refresh($right);
        if ($right->timeline()->state() === 'destination_lost') {
            // Si la commande a pris le verrou avant l'arrivée, elle n'a eu aucun effet : reprise explicite.
            self::assertSame('traveling', $service->chooseOwnedDestination($member, $rightId, $next->id));
        }
        $after = $right->timeline()->checkpoint();
        self::assertSame(2, $after['generation']);
        self::assertSame($lost->position, $after['position'][4], 'Le devis repart du point réellement atteint.');
        self::assertNotSame($event['token'], $after['token']);
        self::assertTrue(PirateConvoyDispatchToken::matches($after['token'], 2));
        // Positions 4→5 (10 UA) avec le cargo Basique exact (104 UA/h), pas depuis l’entrée 10.
        self::assertSame(347, (new \DateTimeImmutable($after['due_at']))->getTimestamp() - $this->clock->now()->getTimestamp());
        self::assertSame(0.0, $this->resourceStock($next->id, 'metal'));
        $db = $this->em->getConnection();
        self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_convoy' AND token = ?", [$after['token']]));
        $this->advanceTo(new \DateTimeImmutable($after['due_at']));
        $this->stampStocks($next->id, ['metal' => 0.0, 'nano' => 0.0, 'antimatter' => 0.0]);
        $service->processDue($rightId, $event['token']);
        self::assertSame(0.0, $this->resourceStock($next->id, 'metal'), 'Même après le nouveau terme, le jeton ancien reste muet.');
        $service->processDue($rightId, $after['token']);
        $service->processDue($rightId, $after['token']);
        self::assertSame(41.5, $this->resourceStock($next->id, 'metal'));
        self::assertSame(0.0, $this->resourceStock($lost->id, 'metal'));
    }

    public function testNoOriginAtVictoryMayStartFirstTripAndRollsBackWithTheOutbox(): void
    {
        [$member, $lost, , , $event] = $this->pilotVictoryWithConvoy(loseOriginBeforeVictory: true);
        self::assertFalse($event);
        $right = $this->em->getRepository(PirateConvoyDelivery::class)->findOneBy([]);
        self::assertSame('eligible', $right->timeline()->state());
        $rightId = $right->id;
        $ownerId = $member->id;
        $next = $this->createReplacementColony($member, $lost);
        $nextId = $next->id;
        $db = $this->em->getConnection();
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        self::assertNull($service->chooseOwnedDestination($member, $rightId, $lost->id));
        $db->executeStatement("CREATE TRIGGER convoy_reject_new_trip BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture refused new convoy trip'");
        try {
            $service->chooseOwnedDestination($member, $rightId, $nextId);
            self::fail('Le premier trajet choisi doit échouer avec l’outbox.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture refused new convoy trip', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS convoy_reject_new_trip');
        }
        self::assertNull($db->fetchOne('SELECT dispatch_token FROM pirate_convoy_delivery WHERE id = ?', [$rightId]));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'pirate_convoy'"));
        self::ensureKernelShutdown();
        self::bootKernel();
        self::getContainer()->set('clock', $this->clock);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $owner = $this->em->find(PlayerUniverse::class, $ownerId);
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        self::assertSame('traveling', $service->chooseOwnedDestination($owner, $rightId, $nextId));
        $planned = $this->em->find(PirateConvoyDelivery::class, $rightId)->timeline()->checkpoint();
        self::assertSame(1, $planned['generation']);
        self::assertNull($planned['position'][4], 'Sans ancien trajet, l’origine attestée est l’ancre civile.');
        self::assertSame(0.0, $this->resourceStock($nextId, 'metal'));
    }

    public function testConvoyRetargetKeepsItsFullTravelDurationAfterWaitingOnPlanet(): void
    {
        [$member, $lost, $other, , $event] = $this->pilotVictoryWithConvoy();
        $next = $this->createReplacementColony($member, $lost);
        $rightId = (int) $event['target_id'];
        $right = $this->em->find(PirateConvoyDelivery::class, $rightId);
        $lost->owner = $other;
        $this->em->flush();
        $this->advanceTo($right->timeline()->chronology()['due_at']);
        $service = self::getContainer()->get(PirateConvoyDeliveryService::class);
        $service->processDue($rightId, $event['token']);
        self::assertSame('destination_lost', $right->timeline()->state());

        $first = $this->clock->now();
        $later = $first->modify('+45 seconds');
        $reply = $this->advanceClockDuringConvoyLock('retarget', $member, $rightId, $event['token'], $next->id, lockStocks: false,
            first: $first, later: $later);
        self::assertSame('traveling', $reply);
        $this->em->refresh($right);
        $timeline = $right->timeline();
        self::assertEquals($later, $timeline->chronology()['departed_at']);
        self::assertEquals($later->modify('+347 seconds'), $timeline->chronology()['due_at']);
        // À l'ancienne ETA (calculée avant l'attente), il manque encore 45 secondes : pas de dépôt anticipé.
        $this->advanceTo($first->modify('+347 seconds'));
        self::assertSame(45000, $service->processDue($rightId, $timeline->checkpoint()['token']));
        self::assertSame(0.0, $this->resourceStock($next->id, 'metal'));
    }

    public function testConvoyArrivalAndReceiptAreObservedAfterWaitingOnStocks(): void
    {
        [$member, $home, , , $event] = $this->pilotVictoryWithConvoy();
        $rightId = (int) $event['target_id'];
        $right = $this->em->find(PirateConvoyDelivery::class, $rightId);
        $first = $right->timeline()->chronology()['due_at'];
        $later = $first->modify('+25 seconds');
        $this->advanceTo($later);
        $this->stampStocks($home->id, ['metal' => 0.0, 'nano' => 0.0, 'antimatter' => 0.0]);
        self::assertSame('processed', $this->advanceClockDuringConvoyLock('worker', $member, $rightId, $event['token'],
            $home->id, lockStocks: true, first: $first, later: $later));
        $this->em->refresh($right);
        self::assertEquals($later, $right->timeline()->chronology()['arrived_at']);
        self::assertEquals($later, $right->timeline()->chronology()['settled_at']);
        self::assertSame(41.5, $this->resourceStock($home->id, 'metal'));
    }

    public function testConvoyManualClaimDoesNotUseTheWindowThatClosedDuringStockWait(): void
    {
        [$member, $home, , , $event] = $this->pilotVictoryWithConvoy();
        $rightId = (int) $event['target_id'];
        $right = $this->em->find(PirateConvoyDelivery::class, $rightId);
        $this->advanceTo($right->timeline()->chronology()['due_at']);
        $capacity = self::getContainer()->get(\App\Service\Colony\ResourceService::class)->capacity($home, 'metal', []);
        $this->stampStocks($home->id, ['metal' => $capacity]);
        self::getContainer()->get(PirateConvoyDeliveryService::class)->processDue($rightId, $event['token']);
        self::assertSame('waiting_capacity', $right->timeline()->state());
        $first = $this->clock->now();
        $later = $first->modify('+15 seconds');
        $member->universe->endsAt = $first->modify('+5 seconds');
        $this->em->flush();
        $this->stampStocks($home->id, ['metal' => 0.0, 'nano' => 0.0, 'antimatter' => 0.0]);
        self::assertNull($this->advanceClockDuringConvoyLock('claim', $member, $rightId, $event['token'],
            $home->id, lockStocks: true, first: $first, later: $later));
        $this->em->refresh($right);
        self::assertSame('waiting_capacity', $right->timeline()->state());
        self::assertSame(0.0, $this->resourceStock($home->id, 'metal'));
        self::assertSame(0.0, $this->resourceStock($home->id, 'nano'));
    }

    /** Avance une vraie horloge de fixture entre l’attente SQL observée et la sortie du verrou. */
    private function advanceClockDuringConvoyLock(
        string $operation,
        PlayerUniverse $member,
        int $deliveryId,
        string $token,
        int $destination,
        bool $lockStocks,
        \DateTimeImmutable $first,
        \DateTimeImmutable $later,
    ): ?string {
        $db = $this->em->getConnection();
        $path = sys_get_temp_dir().'/ges-convoy-clock-'.bin2hex(random_bytes(8));
        mkdir($path, 0700);
        $barrier = $path.'/go';
        $ready = $path.'/ready';
        $clockFile = $path.'/clock';
        file_put_contents($clockFile, $first->format(DATE_ATOM));
        $data = ['now' => $first->format(DATE_ATOM), 'clock_file' => $clockFile, 'owner' => $member->id,
            'right' => $deliveryId, 'token' => $token, 'operation' => $operation, 'destination' => $destination,
            'barrier' => $barrier, 'ready' => $ready];
        $proc = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_pirate_convoy.php',
            json_encode($data, JSON_THROW_ON_ERROR)]);
        $proc->setTimeout(25);
        $db->beginTransaction();
        try {
            $db->fetchOne($lockStocks
                ? 'SELECT id FROM planet_resource WHERE planet_id = ? FOR UPDATE'
                : 'SELECT id FROM planet WHERE id = ? FOR UPDATE', [$destination]);
            $proc->start();
            $deadline = microtime(true) + 14;
            while (!is_file($ready) && microtime(true) < $deadline) {
                usleep(10000);
            }
            self::assertFileExists($ready, $proc->getErrorOutput());
            $connectionId = (int) file_get_contents($ready);
            touch($barrier);
            $blocked = false;
            do {
                foreach ($db->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    $query = strtoupper($row['Info'] ?? '');
                    if ((int) $row['Id'] === $connectionId && str_contains($query, 'FOR UPDATE')
                        && str_contains($query, $lockStocks ? 'PLANET_RESOURCE' : 'FROM PLANET ')) {
                        $blocked = true;
                        break 2;
                    }
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertTrue($blocked, 'L’horloge ne doit avancer qu’après l’attente réelle du dernier verrou.');
            self::assertSame(0.0, $this->resourceStock($destination, 'metal'), 'Aucun gain pendant le blocage.');
            file_put_contents($clockFile, $later->format(DATE_ATOM));
            $db->commit();
            self::assertSame(0, $proc->wait(), $proc->getOutput().$proc->getErrorOutput());
            $reply = trim($proc->getOutput());

            return $operation === 'worker' ? $reply : json_decode($reply, true, flags: JSON_THROW_ON_ERROR);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            if ($proc->isRunning()) {
                $proc->stop();
            }
            foreach (glob($path.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($path);
        }
    }

    /** Deux connexions réellement concurrentes et arrêtées sur le verrou d'entrée. */
    private function raceConvoy(PlayerUniverse $owner, int $id, string $token, string $otherOperation, int $destination): string
    {
        $db = $this->em->getConnection();
        $path = sys_get_temp_dir().'/ges-convoy-race-'.bin2hex(random_bytes(8));
        mkdir($path, 0700);
        $barrier = $path.'/go';
        $workers = [];
        $db->beginTransaction();
        try {
            $db->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$owner->id]);
            foreach (['worker', $otherOperation] as $i => $operation) {
                $data = ['now' => $this->clock->now()->format(DATE_ATOM), 'owner' => $owner->id, 'right' => $id,
                    'token' => $token, 'operation' => $operation, 'destination' => $destination, 'barrier' => $barrier, 'ready' => $path.'/'.$i];
                $proc = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_pirate_convoy.php',
                    json_encode($data, JSON_THROW_ON_ERROR)]);
                $proc->setTimeout(25);
                $proc->start();
                $workers[] = $proc;
            }
            $deadline = microtime(true) + 14;
            do {
                $ready = glob($path.'/[0-9]') ?: [];
                if (count($ready) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $ready, implode('; ', array_map(static fn (Process $proc): string => $proc->getErrorOutput(), $workers)));
            $ids = array_map(static fn (string $file): int => (int) file_get_contents($file), $ready);
            touch($barrier);
            do {
                $blocked = 0;
                foreach ($db->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $blocked, 'La recette exige deux attentes concomitantes du même verrou de participation.');
            $db->commit();
            foreach ($workers as $proc) {
                self::assertSame(0, $proc->wait(), $proc->getOutput().$proc->getErrorOutput());
            }
            self::assertSame('processed', trim($workers[0]->getOutput()));

            return json_decode(trim($workers[1]->getOutput()), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($workers as $proc) {
                if ($proc->isRunning()) {
                    $proc->stop();
                }
            }
            foreach (glob($path.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($path);
        }
    }

    private function createReplacementColony(PlayerUniverse $member, Planet $existing): Planet
    {
        $planet = new Planet();
        $planet->name = 'Colonie de remplacement synthétique';
        $planet->solarSystem = $existing->solarSystem;
        $planet->position = $existing->position + 1;
        $planet->owner = $member;
        $this->em->persist($planet);
        foreach (['metal', 'nano', 'antimatter', 'hydrogen', 'electricity'] as $name) {
            $stock = new PlanetResource();
            $stock->planet = $planet;
            $stock->resourceType = $name;
            $stock->lastUpdatedAt = $this->clock->now();
            $this->em->persist($stock);
        }
        $this->em->flush();

        return $planet;
    }

    /** @return array{PlayerUniverse,Planet,PlayerUniverse,PirateAttackCampaign,array|false} */
    private function pilotVictoryWithConvoy(bool $loseOriginBeforeVictory = false): array
    {
        [$member, $home, , , $other] = $this->fixture($this->scenario(enemyHp: 1, rounds: 10), omega: true,
            frozenCosts: ['metal' => 83, 'nano' => 1, 'antimatter' => 7, 'electricity' => 100]);
        foreach (['metal', 'nano', 'antimatter', 'hydrogen', 'electricity'] as $name) {
            $stock = new PlanetResource();
            $stock->planet = $home;
            $stock->resourceType = $name;
            $stock->lastUpdatedAt = $this->clock->now();
            $this->em->persist($stock);
        }
        $this->em->flush();
        $this->finishRecon();
        self::assertTrue($this->service->launchWithOmegaPilotConvoy($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]]));
        $attack = $this->campaign();
        $this->advanceTo($attack->nextAt);
        $this->service->processDue($attack->id, $attack->dispatchToken);
        $db = $this->em->getConnection();
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_convoy_delivery'));
        if ($loseOriginBeforeVictory) {
            $home->owner = $other;
            $this->em->flush();
        }
        $attack = $this->campaign();
        $this->advanceTo($attack->nextAt);
        $token = $attack->dispatchToken;
        $this->service->processDue($attack->id, $token);
        $this->service->processDue($attack->id, $token);
        $attack = $this->campaign();
        self::assertSame('attacker_wins', $attack->report['outcome']);
        $event = $db->fetchAssociative("SELECT target_id, token FROM outbox_message WHERE kind = 'pirate_convoy'");
        if ($loseOriginBeforeVictory) {
            self::assertFalse($event);
        } else {
            self::assertIsArray($event);
        }

        return [$member, $home, $other, $attack, $event];
    }

    private function stampStocks(int $planetId, array $amounts): void
    {
        $db = $this->em->getConnection();
        $db->executeStatement('UPDATE planet_resource SET last_updated_at = ? WHERE planet_id = ?',
            [$this->clock->now()->format('Y-m-d H:i:s'), $planetId]);
        foreach ($amounts as $resource => $amount) {
            $db->executeStatement('UPDATE planet_resource SET current_stock = ? WHERE planet_id = ? AND resource_type = ?',
                [(string) $amount, $planetId, $resource]);
        }
    }

    private function resourceStock(int $planetId, string $resource): float
    {
        return (float) $this->em->getConnection()->fetchOne(
            'SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = ?', [$planetId, $resource]);
    }

    private function campaign(): PirateAttackCampaign
    {
        return $this->em->getRepository(PirateAttackCampaign::class)->findOneBy([]);
    }

    private function stock(int $id): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$id]);
    }

    private function scenario(int $enemyAttack = 0, int $enemyHp = 100000000, int $rounds = 1): PirateBattleScenario
    {
        return PirateBattleScenario::freeze($this->encounter(), [[
            'slug' => 'fixture_raider', 'tier' => 'basic', 'quantity' => 1,
            'hp' => $enemyHp, 'shield' => 0, 'precision' => 300, 'tracking' => 300,
            'evasion' => 0, 'signature' => 20, 'base_attack' => $enemyAttack,
            'shots_min' => 1, 'shots_max' => 1,
        ]], [], $rounds, 0);
    }

    private function encounter(): PirateEncounterSnapshot
    {
        return PirateEncounterSnapshot::freeze(['fixture_raider' => ['basic' => 1]], ['metal' => 42]);
    }

    private function seedWithOneLoss(PirateAttackCampaign $campaign, PirateBattleScenario $scenario): int
    {
        return $this->findSeed($campaign, $scenario, static fn (array $result): bool => $result['losses'] === ['escort:fighter' => 1]);
    }

    private function seedWithVictory(PirateAttackCampaign $campaign, PirateBattleScenario $scenario): int
    {
        return $this->findSeed($campaign, $scenario, static fn (array $result): bool => $result['outcome'] === 'attacker_wins');
    }

    private function findSeed(PirateAttackCampaign $campaign, PirateBattleScenario $scenario, \Closure $expected): int
    {
        for ($seed = 0; $seed < 100; ++$seed) {
            if ($expected($scenario->resolve(new CombatSimulator(), $campaign->timeline['units'], $seed))) {
                return $seed;
            }
        }

        self::fail('Le profil adverse de test ne peut pas produire le résultat voulu.');
    }

    private function advanceTo(\DateTimeImmutable $due): void
    {
        $this->clock->sleep($due->getTimestamp() - $this->clock->now()->getTimestamp());
    }

    private function finishRecon(): void
    {
        $event = $this->em->getConnection()->fetchAssociative("SELECT target_id, due_at, token FROM outbox_message WHERE kind = 'pirate_reconnaissance'");
        $this->advanceTo(new \DateTimeImmutable($event['due_at'], new \DateTimeZone('UTC')));
        self::getContainer()->get(PirateReconnaissanceService::class)->deliver((int) $event['target_id'], $event['token']);
        $this->em->getConnection()->executeStatement("DELETE FROM outbox_message WHERE kind = 'pirate_reconnaissance'");
    }

    private function fixture(?PirateBattleScenario $scenario = null, ?string $definition = null, bool $legacy = false, bool $omega = false, ?array $frozenCosts = null, bool $scoutPaid = false, ?array $scoutOpponentOverride = null): array
    {
        $u = new Universe();
        $u->slug = $u->name = $omega ? 'omega-1' : 'pirate-attack-test';
        if ($scoutOpponentOverride !== null) {
            $u->gameSettings['catalog_overrides']['ships']['fighter'] = $scoutOpponentOverride;
        }
        $g = new Galaxy();
        $g->universe = $u;
        $g->name = 'Galaxie synthétique';
        $s = new SolarSystem();
        $s->galaxy = $g;
        $s->name = 'Système synthétique';
        $s->coordX = $s->coordY = 0;
        $member = $this->member($u, 'attack-owner');
        $other = $this->member($u, 'attack-other');
        $p = new Planet();
        $p->solarSystem = $s;
        $p->position = 4;
        $p->name = 'Colonie synthétique';
        $p->owner = $member;
        $d = new PlayerDiscovery();
        $d->playerUniverse = $member;
        $d->solarSystem = $s;
        $idle = new Fleet();
        $idle->playerUniverse = $member;
        $idle->homePlanet = $p;
        foreach ([$u, $g, $s, $p, $d, $idle] as $entity) {
            $this->em->persist($entity);
        }
        $fighter = $this->addShips($idle, 'fighter', 3);
        $probe = $this->addShips($idle, 'probe', 2);
        $this->em->flush();
        $recon = self::getContainer()->get(PirateReconnaissanceService::class);
        $site = PirateDistressSite::inExistingSystem($member, $s);
        if ($scoutPaid) {
            foreach (['metal', 'nano', 'antimatter', 'hydrogen', 'electricity'] as $slug) {
                $stock = new PlanetResource();
                $stock->planet = $p;
                $stock->resourceType = $slug;
                $stock->currentStock = $slug === 'hydrogen' ? 100.0 : 0.0;
                $stock->lastUpdatedAt = $this->clock->now();
                $this->em->persist($stock);
            }
            $this->em->flush();
            $recon->launchDefinedWithOmegaPilotFuel($member, $p, $s, $site, 'signal', $definition ?? 'balise_17', true);
        } elseif ($definition !== null) {
            $recon->launchDefined($member, $p, $s, $site, 'signal', $definition);
        } else {
            $encounter = $frozenCosts === null ? $this->encounter()
                : PirateEncounterSnapshot::freeze($this->encounter()->composition(), $frozenCosts);
            $source = $legacy ? $encounter : new PiratePreparedEncounter('balise_17', $encounter, $scenario ?? $this->scenario());
            $recon->launch($member, $p, $s, $site, 'signal', $source);
        }

        return [$member, $p, $fighter, $probe, $other];
    }

    private function member(Universe $universe, string $name): PlayerUniverse
    {
        $player = new Player();
        $player->username = $name;
        $player->email = $name.'@example.test';
        $player->password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $player->emailVerifiedAt = $this->clock->now();
        $member = new PlayerUniverse();
        $member->player = $player;
        $member->universe = $universe;
        $member->joinedAt = $this->clock->now();
        $this->em->persist($player);
        $this->em->persist($member);

        return $member;
    }

    private function addShips(Fleet $fleet, string $type, int $quantity): FleetShip
    {
        $stock = new FleetShip();
        $stock->fleet = $fleet;
        $stock->slug = $type;
        $stock->quantity = $quantity;
        $this->em->persist($stock);

        return $stock;
    }
}
