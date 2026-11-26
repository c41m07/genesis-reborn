<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, Guild, GuildMember, PirateReconnaissance, Planet, PlanetResource, Player, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Manager\GameManager;
use App\Message\PirateReconnaissanceDue;
use App\Repository\{GameRepository, PirateCampaignReadRepository};
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use App\MessageHandler\PirateReconnaissanceDueHandler;
use App\Service\Fleet\{FleetService, FleetVisibility};
use App\Service\Notification\OutboxRelay;
use App\Service\PirateCampaign\{PirateCampaignCatalogue, PirateCampaignProgress, PirateCampaignSequence, PirateDistressSite, PirateEncounterSnapshot, PiratePilotCampaignOrder, PiratePilotGate, PiratePilotScoutCommand, PiratePilotSignalResolver, PirateReconnaissanceService, PirateReconReportReader};
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\{SchemaTool, SchemaValidator};
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\{Request, RequestStack};
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Process\Process;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

final class PirateReconnaissanceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PirateReconnaissanceService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->clock = new MockClock('2026-11-21T08:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $this->service = self::getContainer()->get(PirateReconnaissanceService::class);
    }

    public function testScoutCommandChecksRealCsrfCurrentSelectionAndOnlyScoutsTheKnownHome(): void
    {
        [$member, $home, $otherSystem, $stock] = $this->fixture(omega: true);
        $this->preparePilotFuel($home, $stock, 100.0);
        [$disabled] = $this->pilotScoutCommand($member, false);
        self::assertNull($disabled->preview($home, 1));
        [$command, $csrf] = $this->pilotScoutCommand($member);
        $db = $this->em->getConnection();
        $discoveries = (int) $db->fetchOne('SELECT COUNT(*) FROM player_discovery');
        $planets = (int) $db->fetchOne('SELECT COUNT(*) FROM planet');
        $first = $command->preview($home, 1);
        self::assertNotNull($first);
        self::assertSame(100.0, $this->pilotGroundStock($home->id));
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        $token = $csrf->getToken($first['intent']->csrfId())->getValue();
        self::assertFalse($command->execute($home, 1, ['system' => (string) $otherSystem->id], $token));
        self::assertFalse($command->execute($home, 1, null, $token));
        self::assertFalse($command->execute($home, 2, [], $token));
        // La sélection en base change alors que Security conserve l'objet initial.
        $db->executeStatement('UPDATE player SET current_player_universe_id = NULL WHERE id = ?', [$member->player->id]);
        self::assertFalse($command->execute($home, 1, [], $token));
        $db->executeStatement('UPDATE player SET current_player_universe_id = ? WHERE id = ?', [$member->id, $member->player->id]);

        $member->universe->gameSettings['multipliers']['travel_time'] = 8.0;
        $this->em->flush();
        self::assertFalse($command->execute($home, 1, [], $token));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        $changed = $command->preview($home, 1);
        self::assertNotNull($changed);
        self::assertGreaterThan($first['quote']['seconds'], $changed['quote']['seconds']);
        $confirmed = $csrf->getToken($changed['intent']->csrfId())->getValue();
        self::assertTrue($command->execute($home, 1, [], $confirmed));
        self::assertFalse($command->execute($home, 1, [], $confirmed));
        self::assertNull($command->preview($home, 1));
        self::assertSame($home->solarSystem->id, (int) $db->fetchOne('SELECT system_id FROM pirate_reconnaissance'));
        self::assertSame(2, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertEqualsWithDelta(100.0 - $changed['quote']['planet'], $this->pilotGroundStock($home->id), 1e-9);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        self::assertSame($discoveries, (int) $db->fetchOne('SELECT COUNT(*) FROM player_discovery'));
        self::assertSame($planets, (int) $db->fetchOne('SELECT COUNT(*) FROM planet'));
    }

    public function testReportAndImportOnlyReadTheOwnersDeliveredReconnaissance(): void
    {
        [$member, $home, $system, , $ally] = $this->fixture();
        $member->player->currentPlayerUniverse = $member;
        $ally->player->currentPlayerUniverse = $ally;
        $this->em->flush();
        $catalogue = self::getContainer()->get(PirateCampaignCatalogue::class);
        $reader = new PirateReconReportReader(new PirateCampaignReadRepository($this->em), new PiratePilotGate('dev'),
            $catalogue, self::getContainer()->get(GameCatalog::class), $this->clock);
        $key = $catalogue->keys()[0];
        $prepared = $catalogue->prepareForServer($key, $member->universe);
        $site = PirateDistressSite::inExistingSystem($member, $system);
        self::assertTrue($this->service->launch($member, $home, $system, $site, $key, $prepared, true));
        $db = $this->em->getConnection();
        $row = $db->fetchAssociative('SELECT id, dispatch_token, due_at FROM pirate_reconnaissance');
        $message = new PirateReconnaissanceDue((int) $row['id'], $row['dispatch_token']);
        $id = $message->missionId;
        self::assertNull($reader->read($member, $id));
        $due = new \DateTimeImmutable($row['due_at'], new \DateTimeZone('UTC'));
        $this->clock->sleep($due->getTimestamp() - $this->clock->now()->getTimestamp());
        self::assertNull($reader->read($member, $id, forSimulation: true));
        $handler = self::getContainer()->get(PirateReconnaissanceDueHandler::class);
        $handler($message);
        $handler($message);

        $report = $reader->read($member, $id);
        self::assertTrue($report['complete']);
        self::assertTrue($report['detected']);
        self::assertArrayNotHasKey('scenario', $report);
        self::assertSame([], $report['forces']['defenses']);
        self::assertSame([], $report['forces']['research']);
        self::assertSame($prepared->encounterForServer()->composition(), $report['forces']['ships']);
        self::assertSame($prepared->battleForServer()->snapshot(), $reader->read($member, $id, forSimulation: true)['scenario']->snapshot());
        self::assertNull($reader->read($ally, $id, forSimulation: true));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM espionage_report'));

        // Compatibilité : l'ancienne composition reste consultable sans inventer un profil de combat.
        $db->executeStatement('UPDATE pirate_reconnaissance SET prepared_encounter = NULL WHERE id = ?', [$id]);
        self::assertFalse($reader->read($member, $id)['complete']);
        self::assertNull($reader->read($member, $id, forSimulation: true));

        $db->executeStatement('UPDATE player SET current_player_universe_id = NULL WHERE id = ?', [$member->player->id]);
        self::assertNull($reader->read($member, $id));
    }

    public function testScoutCommandLeavesAccountLockOrMissingDiscoveryWithoutAnyOrder(): void
    {
        [$member, $home, , $stock] = $this->fixture(omega: true);
        $this->preparePilotFuel($home, $stock, 100.0);
        [$command, $csrf] = $this->pilotScoutCommand($member);
        $quote = $command->preview($home, 1);
        self::assertNotNull($quote);
        $token = $csrf->getToken($quote['intent']->csrfId())->getValue();
        $db = $this->em->getConnection();
        $other = DriverManager::getConnection($db->getParams());
        $other->beginTransaction();
        try {
            self::assertEquals($member->player->id, $other->fetchOne('SELECT id FROM player WHERE id = ? FOR UPDATE', [$member->player->id]));
            self::assertFalse($command->execute($home, 1, [], $token));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        } finally {
            $other->rollBack();
            $other->close();
        }
        self::assertNotNull($command->preview($home, 1));
        $db->executeStatement('DELETE FROM player_discovery WHERE player_universe_id = ? AND solar_system_id = ?', [$member->id, $home->solarSystem->id]);
        self::assertNull($command->preview($home, 1));
        self::assertFalse($command->execute($home, 1, [], $token));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        self::assertSame(100.0, $this->pilotGroundStock($home->id));
    }

    private function pilotScoutCommand(PlayerUniverse $member, bool $enabled = true): array
    {
        $member->player->currentPlayerUniverse = $member;
        $this->em->flush();
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($member->player);
        $requests = new RequestStack();
        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requests->push($request);
        $csrf = new CsrfTokenManager(null, new SessionTokenStorage($requests));
        $manager = self::getContainer()->get(GameManager::class);
        $campaignCatalogue = self::getContainer()->get(PirateCampaignCatalogue::class);
        $resolver = new PiratePilotSignalResolver(self::getContainer()->get(GameRepository::class), $manager,
            new PirateCampaignReadRepository($this->em), $campaignCatalogue,
            new PiratePilotCampaignOrder($campaignCatalogue, new PirateCampaignSequence($campaignCatalogue, new PirateCampaignProgress($campaignCatalogue))));

        return [new PiratePilotScoutCommand(
            new PiratePilotGate($enabled ? 'dev' : 'test'), $security, $this->clock, $csrf,
            $this->em, $manager, self::getContainer()->get(ActionGuard::class), $resolver, $this->service,
        ), $csrf];
    }

    #[DataProvider('outcomes')]
    public function testPrivateReportUsesNativeDeadlineAndConsumesOneProbe(bool $detected): void
    {
        [$member, $home, $system, $stock, $ally] = $this->fixture();
        $site = PirateDistressSite::inExistingSystem($member, $system);
        $composition = ['fighter' => ['basic' => 7], 'interceptor' => ['intermediate' => 3]];
        $encounter = PirateEncounterSnapshot::freeze($composition, ['nano' => 42]);

        // L'exploration native de ce même système est la référence géométrique, jamais un départ effectif.
        $quote = self::getContainer()->get(FleetService::class)
            ->quote($member, $home, 'explore', $system->id, ['probe' => ['basic' => 1]], 1.0);
        self::assertTrue($this->service->launch($member, $home, $system, $site, 'test-signal', $encounter, $detected));
        $this->em->refresh($stock);
        self::assertSame(2, $stock->quantity);
        $travel = $this->service->statusFor($member, 'test-signal');
        self::assertSame('traveling', $travel['status']);
        self::assertSame($this->clock->now()->modify('+'.$quote['seconds'].' seconds')->format(DATE_ATOM), $travel['due_at']);
        self::assertNull($travel['reported_at']);
        self::assertNull($travel['composition']);
        self::assertNull($travel['detected']);
        self::assertNull($this->service->statusFor($ally, 'test-signal'));
        $otherUniverse = new Universe();
        $otherUniverse->slug = $otherUniverse->name = 'pirate-recon-other';
        $sameAccountElsewhere = new PlayerUniverse();
        $sameAccountElsewhere->universe = $otherUniverse;
        $sameAccountElsewhere->player = $member->player;
        $sameAccountElsewhere->joinedAt = $this->clock->now();
        $this->em->persist($otherUniverse);
        $this->em->persist($sameAccountElsewhere);
        $this->em->flush();
        self::assertNull($this->service->statusFor($sameAccountElsewhere, 'test-signal'));
        $visibility = self::getContainer()->get(FleetVisibility::class);
        self::assertSame([], $visibility->forMap($ally, $system->galaxy));
        self::assertSame([], $visibility->incoming($ally));
        self::assertFalse($this->service->launch($member, $home, $system, $site, 'test-signal', $encounter, !$detected));

        $db = $this->em->getConnection();
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status != 'idle'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM espionage_report'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM notification'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        self::assertSame(1, self::getContainer()->get(OutboxRelay::class)->run());
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        $transport = self::getContainer()->get('messenger.transport.async');
        $envelope = $transport->getSent()[0];
        $message = $envelope->getMessage();
        self::assertInstanceOf(PirateReconnaissanceDue::class, $message);
        self::assertSame($quote['seconds'] * 1000, $envelope->last(DelayStamp::class)->getDelay());

        $member = $this->em->find(PlayerUniverse::class, $member->id);
        $ally = $this->em->find(PlayerUniverse::class, $ally->id);
        $handler = self::getContainer()->get(PirateReconnaissanceDueHandler::class);
        $this->clock->sleep($quote['seconds'] - 1);
        $handler(new PirateReconnaissanceDue($message->missionId, 'obsolete-before'));
        try {
            $handler($message);
            self::fail('Le jeton valide précoce doit être porté par le rejeu natif.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(1000, $error->getRetryDelay());
        }
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
        self::assertSame($travel, $this->service->statusFor($member, 'test-signal'));
        $this->clock->sleep(1);
        $handler(new PirateReconnaissanceDue($message->missionId, 'obsolete'));
        self::assertSame($travel, $this->service->statusFor($member, 'test-signal'));
        $handler($message);
        $report = $this->service->statusFor($member, 'test-signal');
        self::assertSame('reported', $report['status']);
        self::assertSame($this->clock->now()->format(DATE_ATOM), $report['reported_at']);
        self::assertSame($composition, $report['composition']);
        self::assertSame($detected, $report['detected']);
        self::assertNull($this->service->statusFor($ally, 'test-signal'));
        self::assertSame(['status', 'departed_at', 'due_at', 'reported_at', 'composition', 'detected'], array_keys($report));
        $legacy = $this->em->find(PirateReconnaissance::class, $message->missionId);
        self::assertNull($legacy->pilotScoutFuelFor($member, $this->clock->now()));

        $this->clock->sleep(18);
        $handler($message);
        self::assertSame($report, $this->service->statusFor($member, 'test-signal'));
        self::assertSame(2, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM espionage_report'));

        // L'instance en mémoire est encore active ; la lecture doit revérifier l'identité courante en base.
        $db->executeStatement('UPDATE player_universe SET player_id = NULL, forgotten_at = NOW() WHERE id = ?', [$member->id]);
        self::assertTrue($member->isPlayerControlled());
        self::assertNull($this->service->statusFor($member, 'test-signal'));
    }

    public static function outcomes(): array
    {
        return ['détectée' => [true], 'non détectée' => [false]];
    }

    public function testFailureAfterReservationRollsBackTheProbeAndMission(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
        $db = $this->em->getConnection();
        // La panne est injectée après le premier flush (sonde et preuve), au moment d'écrire l'outbox.
        $db->executeStatement("CREATE TRIGGER pirate_test_reject_outbox BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture outbox failure'");
        try {
            $this->service->launch($member, $home, $system, PirateDistressSite::inExistingSystem($member, $system), 'test-panne', $this->encounter());
            self::fail('Une outbox non persistée ne doit jamais donner un départ réussi.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_test_reject_outbox');
        }
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testUndiscoveredSystemCannotConsumeProbeOrEnqueueMission(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
        $db = $this->em->getConnection();
        $db->delete('player_discovery', ['player_universe_id' => $member->id, 'solar_system_id' => $system->id]);
        try {
            $this->service->launch($member, $home, $system, PirateDistressSite::inExistingSystem($member, $system), 'test-inconnu', $this->encounter());
            self::fail('Le système inconnu doit être refusé.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('découvert', $error->getMessage());
        }
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testFailedReportIsRetryableWithoutSecondProbeOrReport(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
        self::assertTrue($this->service->launch($member, $home, $system, PirateDistressSite::inExistingSystem($member, $system), 'test-arrivee', $this->encounter()));
        $db = $this->em->getConnection();
        $event = $db->fetchAssociative('SELECT target_id, token, due_at FROM outbox_message');
        $this->clock->sleep((new \DateTimeImmutable($event['due_at'], new \DateTimeZone('UTC')))->getTimestamp() - $this->clock->now()->getTimestamp());
        $db->executeStatement("CREATE TRIGGER pirate_test_reject_report BEFORE UPDATE ON pirate_reconnaissance FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture report failure'");
        try {
            $this->service->deliver((int) $event['target_id'], $event['token']);
            self::fail('Le premier traitement doit signaler l’échec de remise.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture report failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_test_reject_report');
        }
        self::assertNull($db->fetchOne('SELECT reported_at FROM pirate_reconnaissance'));
        self::assertSame(2, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));

        // Messenger peut rejouer dans un nouveau processus après fermeture de l'EntityManager en erreur.
        self::ensureKernelShutdown();
        self::bootKernel();
        self::getContainer()->set('clock', $this->clock);
        $handler = self::getContainer()->get(PirateReconnaissanceDueHandler::class);
        $message = new PirateReconnaissanceDue((int) $event['target_id'], $event['token']);
        $handler($message);
        $handler($message);
        $db = self::getContainer()->get(EntityManagerInterface::class)->getConnection();
        self::assertSame($event['due_at'], $db->fetchOne('SELECT reported_at FROM pirate_reconnaissance'));
        self::assertSame(2, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
    }

    public function testTwoMissionKeysRemainDistinctAfterTheFirstReport(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
        $site = PirateDistressSite::inExistingSystem($member, $system);
        $db = $this->em->getConnection();
        self::assertTrue($this->service->launch($member, $home, $system, $site, 'test-premiere', $this->encounter()));
        $event = $db->fetchAssociative('SELECT target_id, token, due_at FROM outbox_message');
        $this->clock->sleep((new \DateTimeImmutable($event['due_at'], new \DateTimeZone('UTC')))->getTimestamp() - $this->clock->now()->getTimestamp());
        $this->service->deliver((int) $event['target_id'], $event['token']);
        self::assertFalse($this->service->launch($member, $home, $system, $site, 'test-premiere', $this->encounter()));
        self::assertTrue($this->service->launch($member, $home, $system, $site, 'test-seconde', PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 5]], ['metal' => 50])));
        self::assertSame(1, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        self::assertSame(['fighter' => ['basic' => 3]], $this->service->statusFor($member, 'test-premiere')['composition']);
        self::assertNull($this->service->statusFor($member, 'test-seconde')['composition']);
    }

    public function testMissingBasicProbeDoesNotBorrowAnotherTier(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
        $db = $this->em->getConnection();
        $stock->tier = 'intermediate';
        $this->em->flush();
        try {
            $this->service->launch($member, $home, $system, PirateDistressSite::inExistingSystem($member, $system), 'test-rang', $this->encounter());
            self::fail('Une sonde d’un autre rang ne doit pas être utilisée.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('basique', $error->getMessage());
        }
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
    }

    #[DataProvider('outcomes')]
    public function testOmegaPilotConsumesNativeRoundTripFromHoldThenGroundWithoutRefund(bool $detected): void
    {
        [$member, $home, , $stock, $ally] = $this->fixture(omega: true);
        $this->preparePilotFuel($home, $stock, 10.0);
        $target = $home->solarSystem;
        $native = self::getContainer()->get(FleetService::class)
            ->quote($member, $home, 'explore', $target->id, ['probe' => ['basic' => 1]], 1.0);
        $site = PirateDistressSite::inExistingSystem($member, $target);
        self::assertTrue($this->service->launchDefinedWithPilotFuel($member, $home, $target, $site, 'balise_17', 'balise_17', new \App\Service\PirateCampaign\PiratePilotGate('dev'), $detected));
        self::assertFalse($this->service->launchDefinedWithPilotFuel($member, $home, $target, $site, 'balise_17', 'balise_17', new \App\Service\PirateCampaign\PiratePilotGate('dev'), !$detected));
        $db = $this->em->getConnection();
        $event = $db->fetchAssociative("SELECT target_id, token, due_at FROM outbox_message WHERE kind = 'pirate_reconnaissance'");
        $mission = $this->em->find(PirateReconnaissance::class, (int) $event['target_id']);
        self::assertNull($mission->pilotScoutFuelFor($member, $this->clock->now()));
        self::assertSame(2, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(0.0, (float) $db->fetchOne('SELECT stored_fuel FROM fleet WHERE id = ?', [$stock->fleet->id]));
        self::assertEqualsWithDelta(10.0 - $native['planet_fuel'], $this->pilotGroundStock($home->id), 1e-10);
        self::assertSame($native['seconds'], (new \DateTimeImmutable($event['due_at']))->getTimestamp() - $this->clock->now()->getTimestamp());
        $this->clock->sleep($native['seconds']);
        $this->service->deliver($mission->id, $event['token']);
        $this->service->deliver($mission->id, $event['token']);
        $receipt = $mission->pilotScoutFuelFor($member, $this->clock->now());
        self::assertSame($native['total_fuel'], $receipt->reserved);
        self::assertSame($native['stored_fuel_used'], $receipt->fromHold);
        self::assertSame($native['planet_fuel'], $receipt->fromPlanet);
        self::assertSame($detected, $mission->projectFor($member, $this->clock->now())['detected']);
        self::assertNull($mission->pilotScoutFuelFor($ally, $this->clock->now()));
        self::assertArrayNotHasKey('pilot_scout_fuel', $mission->projectFor($member, $this->clock->now()));
        self::assertEqualsWithDelta(10.0 - $native['planet_fuel'], $this->pilotGroundStock($home->id), 1e-10);
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status <> 'idle'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM espionage_report'));
    }

    #[DataProvider('pilotModes')]
    public function testOmegaPilotHydrogenRollsBackWhenInsufficientOrOutboxFails(bool $canPay): void
    {
        [$member, $home, , $stock] = $this->fixture(omega: true);
        $ground = $canPay ? 10.0 : 0.0;
        $this->preparePilotFuel($home, $stock, $ground);
        $db = $this->em->getConnection();
        if ($canPay) {
            $db->executeStatement("CREATE TRIGGER scout_fuel_reject_outbox BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture scout outbox failure'");
        }
        $target = $home->solarSystem;
        try {
            $this->service->launchDefinedWithPilotFuel($member, $home, $target, PirateDistressSite::inExistingSystem($member, $target),
                'balise_17', 'balise_17', new \App\Service\PirateCampaign\PiratePilotGate('dev'));
            self::fail('La sonde et ses stocks ne doivent pas partir sans tout le carburant et son événement.');
        } catch (DriverException|\DomainException $error) {
            if ($canPay) {
                self::assertInstanceOf(DriverException::class, $error);
                self::assertStringContainsString('fixture scout outbox failure', $error->getMessage());
            } else {
                self::assertInstanceOf(\DomainException::class, $error);
                self::assertStringContainsString('Ressources insuffisantes', $error->getMessage());
            }
        } finally {
            if ($canPay) {
                $db->executeStatement('DROP TRIGGER IF EXISTS scout_fuel_reject_outbox');
            }
        }
        self::assertSame($ground, $this->pilotGroundStock($home->id));
        self::assertSame(0.4, (float) $db->fetchOne('SELECT stored_fuel FROM fleet WHERE id = ?', [$stock->fleet->id]));
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public static function pilotModes(): array
    {
        return [[false], [true]];
    }

    #[DataProvider('pilotModes')]
    public function testConcurrentDeparturesDebitOnlyOneProbeAndIssueOneOutbox(bool $pilot): void
    {
        [$member, $home, $system, $stock] = $this->fixture(omega: $pilot);
        if ($pilot) {
            $this->preparePilotFuel($home, $stock, 10.0);
            $system = $home->solarSystem;
        }
        $db = $this->em->getConnection();
        $directory = sys_get_temp_dir().'/genesis-pirate-race-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $barrier = $directory.'/go';
        $workers = [];
        $db->beginTransaction();
        try {
            // Garder le verrou commun jusqu'à ce que les deux connexions soient réellement en attente.
            $db->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$member->id]);
            $shared = ['now' => $this->clock->now()->format(DATE_ATOM), 'member' => $member->id,
                'origin' => $home->id, 'system' => $system->id, 'pilot' => $pilot, 'barrier' => $barrier];
            foreach ([0, 1] as $index) {
                $worker = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_pirate_reconnaissance.php',
                    json_encode($shared + ['ready' => $directory.'/'.$index], JSON_THROW_ON_ERROR)]);
                $worker->setTimeout(25);
                $worker->start();
                $workers[] = $worker;
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
            self::assertSame(2, $blocked, 'Les deux écritures doivent réellement se chevaucher.');
            $db->commit();
            $statuses = [];
            foreach ($workers as $worker) {
                self::assertSame(0, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
                $statuses[] = trim($worker->getOutput());
            }
            sort($statuses);
            self::assertSame(['duplicate', 'launched'], $statuses);
            self::assertSame(2, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM outbox_message'));
            if ($pilot) {
                self::assertEqualsWithDelta(9.2, $this->pilotGroundStock($home->id), 1e-10);
                self::assertSame(0.0, (float) $db->fetchOne('SELECT stored_fuel FROM fleet WHERE id = ?', [$stock->fleet->id]));
            }
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

    public function testMigrationDoesNotCreateHistoryOrAlterExistingHangars(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
        $db = $this->em->getConnection();
        // Rejouer les migrations de campagne depuis le schéma historique, sans toucher aux tables métier.
        $db->executeStatement('DROP TABLE pirate_convoy_delivery');
        $db->executeStatement('DROP TABLE pirate_attack_campaign');
        $db->executeStatement('DROP TABLE pirate_reconnaissance');
        try {
            require_once dirname(__DIR__, 2).'/migrations/Version20261120000900.php';
            require_once dirname(__DIR__, 2).'/migrations/Version20261120000930.php';
            require_once dirname(__DIR__, 2).'/migrations/Version20261120000950.php';
            require_once dirname(__DIR__, 2).'/migrations/Version20261120001000.php';
            foreach ([\DoctrineMigrations\Version20261120000900::class, \DoctrineMigrations\Version20261120000930::class, \DoctrineMigrations\Version20261120000950::class, \DoctrineMigrations\Version20261120001000::class] as $class) {
                $migration = new $class($db, new NullLogger());
                $migration->up(new Schema());
                foreach ($migration->getSql() as $query) {
                    $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
                }
            }
            self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_convoy_delivery'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
            self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
            self::assertTrue($this->service->launch($member, $home, $system, PirateDistressSite::inExistingSystem($member, $system), 'test-migration', $this->encounter()));
            self::assertSame('traveling', $this->service->statusFor($member, 'test-migration')['status']);
        } finally {
            // Les noms de contraintes des migrations diffèrent de ceux générés par SchemaTool.
            // Retirer uniquement ces tables de fixture, dans l’ordre inverse de leurs dépendances.
            $db->executeStatement('DROP TABLE IF EXISTS pirate_convoy_delivery');
            $db->executeStatement('DROP TABLE IF EXISTS pirate_attack_campaign');
            $db->executeStatement('DROP TABLE IF EXISTS pirate_reconnaissance');
        }
    }

    private function preparePilotFuel(Planet $home, FleetShip $probe, float $stockOnGround): void
    {
        $probe->fleet->storedFuel = 0.4;
        foreach (['metal', 'nano', 'antimatter', 'hydrogen', 'electricity'] as $name) {
            $stock = new PlanetResource();
            $stock->planet = $home;
            $stock->resourceType = $name;
            $stock->currentStock = $name === 'hydrogen' ? $stockOnGround : 0.0;
            $stock->lastUpdatedAt = $this->clock->now();
            $this->em->persist($stock);
        }
        $this->em->flush();
    }

    private function pilotGroundStock(int $planetId): float
    {
        return (float) $this->em->getConnection()->fetchOne(
            'SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = ?', [$planetId, 'hydrogen']);
    }

    private function encounter(): PirateEncounterSnapshot
    {
        return PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 3]], ['metal' => 83]);
    }

    /** @return array{PlayerUniverse, Planet, SolarSystem, FleetShip, PlayerUniverse} */
    private function fixture(bool $omega = false): array
    {
        $universe = new Universe();
        $universe->slug = $universe->name = $omega ? 'omega-1' : 'pirate-recon-test';
        $universe->gameSettings = ['multipliers' => ['travel_time' => 2.5]];
        $galaxy = new Galaxy();
        $galaxy->universe = $universe;
        $galaxy->name = 'Galaxie synthétique';
        $origin = new SolarSystem();
        $origin->galaxy = $galaxy;
        $origin->name = 'Système natal';
        $origin->coordX = $origin->coordY = 0;
        $target = new SolarSystem();
        $target->galaxy = $galaxy;
        $target->name = 'Relais synthétique';
        $target->coordX = 4;
        $target->coordY = 1;
        $member = $this->empire($universe, 'recon-owner');
        $ally = $this->empire($universe, 'recon-ally');
        $home = new Planet();
        $home->solarSystem = $origin;
        $home->position = 4;
        $home->name = 'Colonie synthétique';
        $home->owner = $member;
        $idle = new Fleet();
        $idle->homePlanet = $home;
        $idle->playerUniverse = $member;
        $stock = new FleetShip();
        $stock->fleet = $idle;
        $stock->slug = 'probe';
        $stock->quantity = 3;
        $guild = new Guild();
        $guild->universe = $universe;
        $guild->leader = $member;
        $guild->name = 'Alliance synthétique';
        $guild->tag = 'TEST';
        foreach ([$universe, $galaxy, $origin, $target, $home, $idle, $stock, $guild] as $entity) {
            $this->em->persist($entity);
        }
        foreach ([$member, $ally] as $empire) {
            $entry = new GuildMember();
            $entry->guild = $guild;
            $entry->playerUniverse = $empire;
            $this->em->persist($entry);
            foreach ([$origin, $target] as $system) {
                $entry = new PlayerDiscovery();
                $entry->playerUniverse = $empire;
                $entry->solarSystem = $system;
                $this->em->persist($entry);
            }
        }
        $this->em->flush();

        return [$member, $home, $target, $stock, $ally];
    }

    private function empire(Universe $universe, string $label): PlayerUniverse
    {
        $player = new Player();
        $player->username = $label;
        $player->email = $label.'@example.test';
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
}
