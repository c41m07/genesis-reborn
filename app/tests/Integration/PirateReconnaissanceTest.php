<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, Guild, GuildMember, Planet, Player, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Message\PirateReconnaissanceDue;
use App\MessageHandler\PirateReconnaissanceDueHandler;
use App\Service\Fleet\{FleetService, FleetVisibility};
use App\Service\Notification\OutboxRelay;
use App\Service\PirateCampaign\{PirateDistressSite, PirateEncounterSnapshot, PirateReconnaissanceService};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\{SchemaTool, SchemaValidator};
use Psr\Log\NullLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Process\Process;

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
        $handler($message);
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

    public function testConcurrentDeparturesDebitOnlyOneProbeAndIssueOneOutbox(): void
    {
        [$member, $home, $system, $stock] = $this->fixture();
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
                'origin' => $home->id, 'system' => $system->id, 'barrier' => $barrier];
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
        // Rejouer les deux migrations de campagne depuis le schéma historique, sans toucher aux tables métier.
        $db->executeStatement('DROP TABLE pirate_attack_campaign');
        $db->executeStatement('DROP TABLE pirate_reconnaissance');
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000900.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000930.php';
        foreach ([\DoctrineMigrations\Version20261120000900::class, \DoctrineMigrations\Version20261120000930::class] as $class) {
            $migration = new $class($db, new NullLogger());
            $migration->up(new Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
            }
        }
        self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_reconnaissance'));
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$stock->id]));
        self::assertTrue($this->service->launch($member, $home, $system, PirateDistressSite::inExistingSystem($member, $system), 'test-migration', $this->encounter()));
        self::assertSame('traveling', $this->service->statusFor($member, 'test-migration')['status']);
    }

    private function encounter(): PirateEncounterSnapshot
    {
        return PirateEncounterSnapshot::freeze(['fighter' => ['basic' => 3]], ['metal' => 83]);
    }

    /** @return array{PlayerUniverse, Planet, SolarSystem, FleetShip, PlayerUniverse} */
    private function fixture(): array
    {
        $universe = new Universe();
        $universe->slug = $universe->name = 'pirate-recon-test';
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
