<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, PirateAttackCampaign, PirateConvoyDelivery, PirateReconnaissance, Planet, Player, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Message\PirateAttackDue;
use App\MessageHandler\PirateAttackDueHandler;
use App\Service\Combat\CombatSimulator;
use App\Service\Notification\OutboxRelay;
use App\Service\PirateCampaign\{PirateAttackService, PirateBattleScenario, PirateCampaignCatalogue, PirateCampaignProgress, PirateConvoyDispatchToken, PirateDistressSite, PirateEncounterSnapshot, PiratePreparedEncounter, PirateReconnaissanceService};
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

    private function fixture(?PirateBattleScenario $scenario = null, ?string $definition = null, bool $legacy = false): array
    {
        $u = new Universe();
        $u->slug = $u->name = 'pirate-attack-test';
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
        if ($definition !== null) {
            $recon->launchDefined($member, $p, $s, $site, 'signal', $definition);
        } else {
            $encounter = $this->encounter();
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
