<?php

namespace App\Tests\Integration;

use App\Entity\{EmailVerificationToken, Player, PlayerVerificationGeneration, VerificationDeliveryRequest, VerificationRecoveryEvent};
use App\Service\Player\{VerificationGenerationStore, VerificationLinkKeyResolver, VersionedVerificationLink};
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineMigrations\{Version20261120000200, Version20261120000300, Version20261120000400, Version20261120000500};
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

final class VerificationGenerationStoreTest extends KernelTestCase
{
    private ?Connection $db = null;

    protected function tearDown(): void
    {
        if ($this->db !== null) {
            $this->migrate($this->db, 'down');
        }
        parent::tearDown();
    }

    private function migrate(Connection $db, string $direction): void
    {
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000200.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000300.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000400.php';
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000500.php';
        $versions = [Version20261120000200::class, Version20261120000300::class, Version20261120000400::class, Version20261120000500::class];
        foreach ($direction === 'up' ? $versions : array_reverse($versions) as $version) {
            $migration = new $version($db, new \Psr\Log\NullLogger());
            $migration->{$direction}(new Schema());
            foreach ($migration->getSql() as $query) {
                $db->executeStatement($query->getStatement());
            }
        }
    }

    private function fixture(): array
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $params = $db->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base non isolée.');
        }
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema = new SchemaTool($em);
        $schema->dropSchema($metadata);
        $schema->createSchema(array_filter($metadata, static fn ($m) => !in_array($m->name, [PlayerVerificationGeneration::class, VerificationRecoveryEvent::class], true)));
        $player = new Player();
        $player->email = 'generation@example.test';
        $player->username = 'Generation';
        $player->password = 'synthetic';
        $em->persist($player);
        $em->flush();
        $clock = new MockClock('2030-01-01T00:00:00Z');
        $codec = new VersionedVerificationLink($clock);
        $keys = new class implements VerificationLinkKeyResolver {
            public function resolve(string $keyId): ?string
            {
                return $keyId === 'fixture' ? str_repeat('k', 32) : null;
            }
        };
        return [$em, $db, $player, $clock, new VerificationGenerationStore($db, $clock, $codec), $codec, $keys];
    }

    private function up(Connection $db): void
    {
        $this->migrate($db, 'up');
        $this->db = $db;
    }

    private function race(int $playerId, string $first, string $second, int $rowId = 0, string|array|null $link = null): array
    {
        $dir = sys_get_temp_dir().'/verification-generation-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $processes = [];
        try {
            foreach (['a' => $first, 'b' => $second] as $side => $operation) {
                $proc = new Process([PHP_BINARY, dirname(__DIR__).'/Support/verification-generation-worker.php', (string) $playerId, $operation, $dir, $side, (string) $rowId], input: in_array($operation, ['consume', 'mixed', 'prepare-handoff'], true) ? (is_array($link) ? $link[$side] : $link) : null, timeout: 20);
                $proc->start();
                $processes[] = $proc;
            }
            $limit = microtime(true) + 15;
            while (!is_file($dir.'/ready-a') || !is_file($dir.'/ready-b')) {
                if (microtime(true) > $limit) {
                    self::fail('Processus concurrents non prêts.');
                }
                usleep(10000);
            }
            touch($dir.'/go');
            return array_map(static function (Process $p) {
                $p->wait();
                self::assertTrue($p->isSuccessful(), $p->getErrorOutput());
                return json_decode($p->getOutput(), true, flags: JSON_THROW_ON_ERROR);
            }, $processes);
        } finally {
            foreach ($processes as $p) {
                $p->stop();
            }
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testMigrationAndRollbackPreserveHistoricalFingerprint(): void
    {
        [$em, $db, $player, $clock, $store, , $keys] = $this->fixture();
        $legacy = new EmailVerificationToken();
        $legacy->player = $player;
        $legacy->tokenHash = hash('sha256', 'synthetic-historical-link');
        $legacy->expiresAt = $clock->now()->modify('+1 hour');
        $em->persist($legacy);
        $em->flush();
        $snapshot = $db->fetchAssociative('SELECT * FROM player_email_verification_token WHERE id = ?', [$legacy->id]);
        $this->up($db);
        self::assertNull($store->prepare($player->id, 'fixture', $clock->now()->modify('+1 day'), $keys));
        $clock->sleep(3600);
        self::assertNotNull($store->prepare($player->id, 'fixture', $clock->now()->modify('+1 day'), $keys));
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM player_email_verification_token WHERE id = ?', [$legacy->id]));
        $this->migrate($db, 'down');
        $this->db = null;
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM player_email_verification_token WHERE id = ?', [$legacy->id]));
        self::assertFalse($db->createSchemaManager()->tablesExist(['player_verification_generation']));
        self::assertFalse($db->createSchemaManager()->tablesExist(['verification_recovery_event']));
    }

    public function testConcurrentPreparationPreservesSingleGenerationAndExpiry(): void
    {
        [, $db, $player, $clock, $store, , $keys] = $this->fixture();
        $this->up($db);
        self::assertSame([1, 1], $this->race($player->id, 'prepare', 'prepare'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM player_verification_generation'));
        $expiry = (int) $db->fetchOne('SELECT expires_at FROM player_verification_generation');
        self::assertContains($expiry - $clock->now()->getTimestamp(), [3600, 7200]);
        self::assertSame($expiry, $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 day'), $keys)->expiresAt);
    }

    public function testRevocationAndConsumptionCompeteAndStaleLinkCannotReturn(): void
    {
        [, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $original = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $link = $codec->reproduce($original, $keys);
        self::assertTrue($store->revoke($player->id, $original->version));
        self::assertFalse($store->consume($player->id, $original->version, $link));
        $next = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        self::assertSame(2, $next->version);
        self::assertFalse($store->consume($player->id, $next->version, $link));
        $newLink = $codec->reproduce($next, $keys);
        [$revoked, $consumed] = $this->race($player->id, 'revoke', 'consume', $next->version, $newLink);
        self::assertNotSame($revoked, $consumed);
        self::assertFalse($store->consume($player->id, $next->version, $newLink));
        self::assertSame($consumed, $db->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]) !== null);
        self::assertSame($consumed ? 'consumed' : 'revoked', $db->fetchOne('SELECT state FROM player_verification_generation'));
    }

    public function testConcurrentRecoveryAddsOneDurableEventAndNeverRewritesUnknown(): void
    {
        [$em, $db, $player, $clock, $store] = $this->fixture();
        $this->up($db);
        $original = new VerificationDeliveryRequest();
        $original->player = $player;
        $original->requestKeyHash = hash('sha256', 'original');
        $original->state = 'unknown';
        $original->requestedAt = $clock->now()->modify('-10 minutes');
        $original->cooldownUntil = $clock->now()->modify('-5 minutes');
        $original->resolvedAt = $clock->now()->modify('-5 minutes');
        $em->persist($original);
        $em->flush();
        $snapshot = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]);
        self::assertSame('too_early', $store->recordRecovery($player->id, $original->id, str_repeat('c', 64), 301));
        $outcomes = $this->race($player->id, 'recover', 'recover', $original->id);
        self::assertEqualsCanonicalizing(['blocked', 'recorded'], $outcomes);
        $winner = $outcomes[0] === 'recorded' ? 'a' : 'b';
        self::assertSame('replayed', $store->recordRecovery($player->id, $original->id, str_repeat($winner, 64), 300));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]));
    }

    private function mixed(EntityManagerInterface $em, VersionedVerificationLink $codec, MockClock $clock): \App\Service\Player\MixedVerificationLinkReader
    {
        return new \App\Service\Player\MixedVerificationLinkReader($em, new \App\Manager\GameManager($em), $codec, $clock);
    }

    public function testMixedReaderConfirmsLegacyAndVersionedWithoutKeyAtReadTime(): void
    {
        [$em, $db, $legacyPlayer, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $versioned = new Player();
        $versioned->email = 'versioned@example.test';
        $versioned->username = 'Versioned';
        $versioned->password = 'synthetic';
        $em->persist($versioned);
        $legacy = new EmailVerificationToken();
        $legacyLink = str_repeat('l', 43);
        $legacy->player = $legacyPlayer;
        $legacy->tokenHash = hash('sha256', $legacyLink);
        $legacy->expiresAt = $clock->now()->modify('+1 hour');
        $em->persist($legacy);
        $em->flush();
        $generation = $store->prepare($versioned->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $link = $codec->reproduce($generation, $keys);
        $reader = $this->mixed($em, $codec, $clock);
        self::assertFalse($reader->confirm('invalid-format'));
        self::assertTrue($reader->confirm($legacyLink));
        self::assertNotNull($legacyPlayer->emailVerifiedAt);
        self::assertNull($versioned->emailVerifiedAt);
        self::assertNull($legacy->id); // Suppression cohérente avec l'ORM.
        self::assertTrue($reader->confirm($link)); // Aucun résolveur fourni.
        self::assertNotNull($versioned->emailVerifiedAt);
        self::assertFalse($reader->confirm($legacyLink));
        self::assertFalse($reader->confirm($link));
        self::assertSame('consumed', $db->fetchOne('SELECT state FROM player_verification_generation'));
    }

    public function testMixedReaderRejectsRevokedExpiredAmbiguousAndLockedStates(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $link = $codec->reproduce($generation, $keys);
        $reader = $this->mixed($em, $codec, $clock);

        $legacy = new EmailVerificationToken();
        $legacy->player = $player;
        $legacy->tokenHash = $generation->fingerprint;
        $legacy->expiresAt = $clock->now()->modify('+1 hour');
        $em->persist($legacy);
        $em->flush();
        self::assertFalse($reader->confirm($link)); // Empreinte présente dans les deux familles.
        $em->remove($legacy);
        $em->flush();

        $loaded = $em->find(PlayerVerificationGeneration::class, $player->id);
        self::assertSame('active', $loaded->state);
        self::assertTrue($store->revoke($player->id, $generation->version));
        self::assertFalse($reader->confirm($link));
        self::assertSame('revoked', $loaded->state); // Refresh de l'identity map.

        $next = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $nextLink = $codec->reproduce($next, $keys);
        self::assertFalse($reader->confirm($link));
        $db->update('player', ['locked_at' => '2030-01-01 00:00:00'], ['id' => $player->id]);
        self::assertFalse($reader->confirm($nextLink));
        self::assertNotNull($player->lockedAt);
        $db->update('player', ['locked_at' => null], ['id' => $player->id]);
        $clock->sleep(3600);
        self::assertFalse($reader->confirm($nextLink));
        self::assertNull($player->emailVerifiedAt);
    }

    public function testConcurrentMixedReadersConsumeAtMostOnce(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $link = $codec->reproduce($generation, $keys);
        self::assertEqualsCanonicalizing([false, true], $this->race($player->id, 'mixed', 'mixed', $generation->version, $link));
        self::assertFalse($this->mixed($em, $codec, $clock)->confirm($link));
        self::assertNotNull($player->emailVerifiedAt);
        self::assertSame('consumed', $db->fetchOne('SELECT state FROM player_verification_generation'));
    }

    public function testMixedReaderSerializesBothFamiliesForSameAccount(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $vLink = $codec->reproduce($generation, $keys);
        // Représente une donnée inattendue si un ancien écrivain n'a pas
        // encore été arrêté ; aucun endpoint ne crée cet état dans le test.
        $legacyLink = str_repeat('l', 43);
        $legacy = new EmailVerificationToken();
        $legacy->player = $player;
        $legacy->tokenHash = hash('sha256', $legacyLink);
        $legacy->expiresAt = $clock->now()->modify('+1 hour');
        $em->persist($legacy);
        $em->flush();
        [$historic, $versioned] = $this->race($player->id, 'mixed', 'mixed', 0, ['a' => $legacyLink, 'b' => $vLink]);
        self::assertNotSame($historic, $versioned);
        self::assertSame($historic ? 'revoked' : 'consumed', $db->fetchOne('SELECT state FROM player_verification_generation'));
        self::assertSame($historic ? 0 : 1, (int) $db->fetchOne('SELECT COUNT(*) FROM player_email_verification_token'));
        $reader = $this->mixed($em, $codec, $clock);
        self::assertFalse($reader->confirm($legacyLink));
        self::assertFalse($reader->confirm($vLink));
    }

    public function testMixedReaderDoesNotExposeRawLinkInDeliberateRejectionTrace(): void
    {
        [$em, $db, , $clock, , $codec] = $this->fixture();
        $this->up($db);
        $link = str_repeat('w', 43);
        $old = ini_get('zend.exception_ignore_args');
        ini_set('zend.exception_ignore_args', '0');
        $db->beginTransaction();
        try {
            $this->mixed($em, $codec, $clock)->confirm($link);
            self::fail('Transaction externe acceptée.');
        } catch (\LogicException $error) {
            self::assertInstanceOf(\SensitiveParameterValue::class, $error->getTrace()[0]['args'][0]);
            self::assertStringNotContainsString($link, (string) $error);
        } finally {
            $db->rollBack();
            ini_set('zend.exception_ignore_args', $old);
        }
    }

    private function unknown(EntityManagerInterface $em, Player $player, MockClock $clock): VerificationDeliveryRequest
    {
        $original = new VerificationDeliveryRequest();
        $original->player = $player;
        $original->requestKeyHash = hash('sha256', 'original');
        $original->state = 'unknown';
        $original->requestedAt = $clock->now()->modify('-10 minutes');
        $original->cooldownUntil = $clock->now()->modify('-5 minutes');
        $original->resolvedAt = $clock->now()->modify('-5 minutes');
        $em->persist($original);
        $em->flush();
        return $original;
    }

    public function testRecoveryReserveRacesAndLinksOneNewReceiptToUnchangedUnknown(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $original = $this->unknown($em, $player, $clock);
        $old = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]);
        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $fingerprint = $generation->fingerprint;
        $link = $codec->reproduce($generation, $keys);
        $results = $this->race($player->id, 'reserve-recovery', 'reserve-recovery', $original->id);
        self::assertEqualsCanonicalizing(['reserved', 'blocked'], array_column($results, 'result'));
        $winner = $results[0]['result'] === 'reserved' ? 0 : 1;
        $id = $results[$winner]['request_id'];
        self::assertNotSame($id, $original->id);
        $service = new \App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec);
        self::assertSame(['result' => 'replayed', 'request_id' => $id], $service->reserve($player->id, $original->id, str_repeat($winner === 0 ? 'a' : 'b', 64), 300, $keys));
        $event = $db->fetchAssociative('SELECT * FROM verification_recovery_event');
        self::assertSame($id, (int) $event['recovery_request_id']);
        self::assertSame($generation->version, (int) $event['generation_version']);
        self::assertSame('recovery_reserved', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame($old, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]));
        self::assertSame($generation->expiresAt, (int) $db->fetchOne('SELECT expires_at FROM player_verification_generation'));
        $serialized = json_encode($event, JSON_THROW_ON_ERROR);
        self::assertStringNotContainsString($link, $serialized);
        self::assertStringNotContainsString($fingerprint, $serialized);
        self::assertStringNotContainsString($generation->nonce, $serialized);
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, new \App\Manager\GameManager($em), $clock);
        self::assertNotSame('claimed', $lifecycle->claim($id, 120)['result']);
    }

    public function testRecoveryReserveRollsBackJournalWhenReceiptInsertFails(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $original = $this->unknown($em, $player, $clock);
        $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $service = new \App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec);
        $db->executeStatement("CREATE TRIGGER fixture_deny_recovery BEFORE INSERT ON verification_delivery_request FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic receipt insert failure'");
        try {
            $service->reserve($player->id, $original->id, str_repeat('e', 64), 300, $keys);
            self::fail('La réservation devait échouer après l’insertion du journal.');
        } catch (\Doctrine\DBAL\Exception $e) {
            self::assertStringContainsString('synthetic receipt insert failure', $e->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER fixture_deny_recovery');
        }
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$original->id]));
        self::assertSame('reserved', $service->reserve($player->id, $original->id, str_repeat('e', 64), 300, $keys)['result']);
    }

    public function testRecoveryReserveRefusesOtherAccountSuspensionLegacyAndExpiredGeneration(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $other = new Player();
        $other->email = 'other-recovery@example.test';
        $other->username = 'OtherRecovery';
        $other->password = 'synthetic';
        $em->persist($other);
        $em->flush();
        $original = $this->unknown($em, $player, $clock);
        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $service = new \App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec);
        $key = str_repeat('f', 64);
        self::assertSame('ineligible', $service->reserve($other->id, $original->id, $key, 300, $keys)['result']);
        self::assertSame('too_early', $service->reserve($player->id, $original->id, $key, 301, $keys)['result']);
        $db->update('player', ['locked_at' => '2030-01-01 00:00:00'], ['id' => $player->id]);
        self::assertSame('ineligible', $service->reserve($player->id, $original->id, $key, 300, $keys)['result']);
        $db->update('player', ['locked_at' => null], ['id' => $player->id]);
        $legacy = new EmailVerificationToken();
        $legacy->player = $player;
        $legacy->tokenHash = hash('sha256', 'synthetic-legacy');
        $legacy->expiresAt = $clock->now()->modify('+1 hour');
        $em->persist($legacy);
        $em->flush();
        self::assertSame('legacy_active', $service->reserve($player->id, $original->id, $key, 300, $keys)['result']);
        $clock->sleep(3600);
        self::assertSame('generation_unavailable', $service->reserve($player->id, $original->id, $key, 300, $keys)['result']);
        self::assertSame($generation->expiresAt, (int) $db->fetchOne('SELECT expires_at FROM player_verification_generation'));
        self::assertSame($legacy->tokenHash, $db->fetchOne('SELECT token_hash FROM player_email_verification_token'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
    }

    private function recoveryFixture(int $lifetime = 3600): array
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $original = $this->unknown($em, $player, $clock);
        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+'.$lifetime.' seconds'), $keys);
        $reservation = (new \App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec))
            ->reserve($player->id, $original->id, str_repeat('e', 64), 300, $keys);
        self::assertSame('reserved', $reservation['result']);
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, new \App\Manager\GameManager($em), $clock);

        return [$db, $player, $clock, $store, $original, $generation, $reservation['request_id'], $lifecycle, $codec, $keys];
    }

    public function testRecoveryClaimOnlyOneOwnerAcrossConcurrentProcesses(): void
    {
        [$db, $player, , , $original, , $id] = $this->recoveryFixture();
        $snapshot = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]);
        self::assertEqualsCanonicalizing(['claimed', 'unavailable'], $this->race($player->id, 'claim-recovery', 'claim-recovery', $id));
        self::assertSame('recovery_dispatching', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]));
    }

    public function testRecoveryClaimLimitsLeaseAndDoesNotPublishLateFakeOutcome(): void
    {
        [$db, , $clock, , $original, $generation, $id, $lifecycle, $codec, $keys] = $this->recoveryFixture(30);
        $claim = $lifecycle->claimRecovery($id, 120);
        self::assertSame('claimed', $claim['result']);
        self::assertSame($generation->version, $claim['generation_version']);
        self::assertSame($clock->now()->modify('+30 seconds')->format('Y-m-d H:i:s'), $db->fetchOne('SELECT lease_expires_at FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertSame('rejected', $lifecycle->complete($id, str_repeat('f', 64), 'transport_accepted')['result']);
        // Double de test : aucune mise en file ni destinataire ; résultat tardif.
        $fake = $this->createMock(\App\Service\Player\VerificationRecoveryTransport::class);
        $fake->expects(self::once())->method('handoff')->with(self::isInt(), $codec->reproduce($generation, $keys))
            ->willReturnCallback(static function () use ($clock): \App\Service\Player\VerificationRecoveryOutcome {
                $clock->sleep(30);
                return \App\Service\Player\VerificationRecoveryOutcome::TransportAccepted;
            });
        self::assertSame('unknown', (new \App\Service\Player\VerificationRecoveryDeliveryAdapter())->deliver($id, $claim['claim_token'], $lifecycle, $keys, $fake));
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$original->id]));
        self::assertSame('unavailable', $lifecycle->claimRecovery($id, 120)['result']);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staleRecoveryCases')]
    public function testRecoveryClaimCancelsBeforeTransportWhenStateChanges(string $change): void
    {
        [$db, $player, $clock, $store, $original, $generation, $id, $lifecycle] = $this->recoveryFixture(30);
        match ($change) {
            'suspended' => $db->update('player', ['locked_at' => '2030-01-01 00:00:00'], ['id' => $player->id]),
            'expired' => $clock->sleep(30),
            'revoked' => $store->revoke($player->id, $generation->version),
            'wrong_version' => $db->update('verification_recovery_event', ['generation_version' => 2], ['recovery_request_id' => $id]),
            'broken_binding' => $db->update('verification_recovery_event', ['request_key_hash' => str_repeat('b', 64)], ['recovery_request_id' => $id]),
            'initial_not_unknown' => $db->update('verification_delivery_request', ['state' => 'cancelled'], ['id' => $original->id]),
            'legacy_active' => $db->insert('player_email_verification_token', ['player_id' => $player->id, 'token_hash' => str_repeat('b', 64), 'expires_at' => '2030-01-01 00:30:00']),
        };
        $snapshot = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]);
        self::assertSame(['result' => 'cancelled'], $lifecycle->claimRecovery($id, 120));
        self::assertSame('cancelled', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertNull($db->fetchOne('SELECT claim_hash FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$original->id]));
    }

    public static function staleRecoveryCases(): iterable
    {
        foreach (['suspended', 'expired', 'revoked', 'wrong_version', 'broken_binding', 'initial_not_unknown', 'legacy_active'] as $case) {
            yield $case => [$case];
        }
    }

    public function testInconsistentPriorPossessionIsNeverClassifiedAsCancelled(): void
    {
        [$db, , , , , , $id, $lifecycle] = $this->recoveryFixture();
        $db->update('verification_delivery_request', ['claim_hash' => str_repeat('a', 64)], ['id' => $id]);
        self::assertSame('unavailable', $lifecycle->claimRecovery($id, 120)['result']);
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
    }

    public function testRecoveryClaimRollbackDoesNotIssuePossession(): void
    {
        [$db, $player, , , , , $id, $lifecycle] = $this->recoveryFixture();
        $db->executeStatement("CREATE TRIGGER fixture_deny_claim BEFORE UPDATE ON verification_delivery_request FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic claim persistence failure'");
        try {
            $lifecycle->claimRecovery($id, 120);
            self::fail('Un claim a été retourné malgré l’échec de validation.');
        } catch (\Doctrine\DBAL\Exception $e) {
            self::assertStringContainsString('synthetic claim persistence failure', $e->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER fixture_deny_claim');
        }
        self::assertSame('recovery_reserved', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertNull($db->fetchOne('SELECT claim_hash FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertEqualsCanonicalizing(['claimed', 'unavailable'], $this->race($player->id, 'claim-recovery', 'claim-recovery', $id));
    }

    public function testDeliveryBeforeRevocationNeverHandsOffAndAuditsNonDispatch(): void
    {
        [$db, $player, , $store, $original, $generation, $id, $lifecycle, , $keys] = $this->recoveryFixture();
        $claim = $lifecycle->claimRecovery($id, 120);
        self::assertSame(['result' => 'unavailable'], $lifecycle->prepareRecoveryDelivery($id, str_repeat('b', 64), $keys));
        self::assertNull($db->fetchOne('SELECT handoff_at FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertTrue($store->revoke($player->id, $generation->version));
        $fake = $this->createMock(\App\Service\Player\VerificationRecoveryTransport::class);
        $fake->expects(self::never())->method('handoff');
        $adapter = new \App\Service\Player\VerificationRecoveryDeliveryAdapter();
        self::assertSame('not_dispatched', $adapter->deliver($id, $claim['claim_token'], $lifecycle, $keys, $fake));
        self::assertSame('cancelled', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        self::assertSame('not_dispatched', $db->fetchOne('SELECT delivery_outcome FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertNull($db->fetchOne('SELECT handoff_at FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$original->id]));
    }

    public function testHandoffCommitsOnceEvenIfGenerationIsRevokedDuringTransport(): void
    {
        [$db, $player, , $store, $original, $generation, $id, $lifecycle, $codec, $keys] = $this->recoveryFixture();
        $claim = $lifecycle->claimRecovery($id, 120);
        $link = $codec->reproduce($generation, $keys);
        $adapter = new \App\Service\Player\VerificationRecoveryDeliveryAdapter();
        $fake = $this->createMock(\App\Service\Player\VerificationRecoveryTransport::class);
        $fake->expects(self::once())->method('handoff')->with($player->id, $link)->willReturnCallback(
            function () use ($db, $player, $store, $generation, $adapter, $id, $lifecycle, $keys, $fake, $claim): \App\Service\Player\VerificationRecoveryOutcome {
                self::assertFalse($db->isTransactionActive());
                self::assertSame('unavailable', $adapter->deliver($id, $claim['claim_token'], $lifecycle, $keys, $fake));
                self::assertTrue($store->revoke($player->id, $generation->version));
                return \App\Service\Player\VerificationRecoveryOutcome::TransportAccepted;
            },
        );
        self::assertSame('transport_accepted', $adapter->deliver($id, $claim['claim_token'], $lifecycle, $keys, $fake));
        self::assertSame('transport_accepted', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        $event = $db->fetchAssociative('SELECT * FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]);
        self::assertSame('transport_accepted', $event['delivery_outcome']);
        self::assertNotNull($event['delivery_resolved_at']);
        self::assertStringNotContainsString($link, json_encode($event, JSON_THROW_ON_ERROR));
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$original->id]));
    }

    public function testTwoOwnersUsingSameClaimCanCommitOnlyOneHandoff(): void
    {
        [$db, $player, , , , , $id, $lifecycle] = $this->recoveryFixture();
        $claim = $lifecycle->claimRecovery($id, 120);
        self::assertEqualsCanonicalizing(['handoff', 'unavailable'], $this->race($player->id, 'prepare-handoff', 'prepare-handoff', $id, $claim['claim_token']));
        self::assertNotNull($db->fetchOne('SELECT handoff_at FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertNull($db->fetchOne('SELECT delivery_outcome FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertSame('rejected', $lifecycle->complete($id, $claim['claim_token'], 'transport_accepted')['result']);
    }

    public function testAuditFailureRollsBackReceiptOutcomeAfterFakeAcceptance(): void
    {
        [$db, , , , , , $id, $lifecycle, , $keys] = $this->recoveryFixture();
        $claim = $lifecycle->claimRecovery($id, 120);
        $fake = $this->createMock(\App\Service\Player\VerificationRecoveryTransport::class);
        $fake->expects(self::once())->method('handoff')->willReturnCallback(function () use ($db): \App\Service\Player\VerificationRecoveryOutcome {
            // Installation après le point de remise, dans cette seule fixture.
            $db->executeStatement("CREATE TRIGGER fixture_deny_delivery BEFORE UPDATE ON verification_recovery_event FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'synthetic delivery audit failure'");
            return \App\Service\Player\VerificationRecoveryOutcome::TransportAccepted;
        });
        try {
            (new \App\Service\Player\VerificationRecoveryDeliveryAdapter())->deliver($id, $claim['claim_token'], $lifecycle, $keys, $fake);
            self::fail('Un succès aurait été annoncé malgré l’échec d’audit.');
        } catch (\Doctrine\DBAL\Exception $e) {
            self::assertStringContainsString('synthetic delivery audit failure', $e->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER fixture_deny_delivery');
        }
        self::assertNotNull($db->fetchOne('SELECT handoff_at FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertNull($db->fetchOne('SELECT delivery_outcome FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]));
        self::assertSame('recovery_dispatching', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
    }

    public function testExplicitRecoveryGoesFromUnknownToOneAuditedDeliveryAndMixedConfirmation(): void
    {
        [$em, $db, $player, $clock, $store, $codec, $keys] = $this->fixture();
        $this->up($db);
        $manager = new \App\Manager\GameManager($em);
        $reservations = new \App\Service\Player\VerificationRequestReservation(
            $em, $manager, new \App\Repository\VerificationDeliveryRequestRepository($em), $clock,
        );
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, $manager, $clock);
        $recovery = new \App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec);
        $adapter = new \App\Service\Player\VerificationRecoveryDeliveryAdapter();

        $generation = $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $expected = $codec->reproduce($generation, $keys);
        self::assertNotNull($expected);
        $original = $reservations->reserve($player->id, str_repeat('a', 64), 300);
        self::assertSame('reserved', $original['result']);
        $originalId = $original['request_id'];
        $oldClaim = $lifecycle->claim($originalId, 120);
        self::assertSame('claimed', $oldClaim['result']);
        self::assertSame('completed', $lifecycle->complete($originalId, $oldClaim['claim_token'], 'unknown')['result']);
        $snapshot = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$originalId]);

        $requestKey = str_repeat('b', 64);
        self::assertSame('too_early', $recovery->reserve($player->id, $originalId, $requestKey, 300, $keys)['result']);
        $clock->sleep(300);
        $reserved = $recovery->reserve($player->id, $originalId, $requestKey, 300, $keys);
        self::assertSame('reserved', $reserved['result']);
        $id = $reserved['request_id'];
        self::assertNotSame($originalId, $id);
        self::assertSame(['result' => 'replayed', 'request_id' => $id], $recovery->reserve($player->id, $originalId, $requestKey, 300, $keys));
        $claim = $lifecycle->claimRecovery($id, 120);
        self::assertSame('claimed', $claim['result']);

        $captured = null;
        $transport = $this->createMock(\App\Service\Player\VerificationRecoveryTransport::class);
        $transport->expects(self::once())->method('handoff')->with($player->id, $expected)->willReturnCallback(
            static function (int $playerId, #[\SensitiveParameter] string $link) use ($db, &$captured): \App\Service\Player\VerificationRecoveryOutcome {
                self::assertFalse($db->isTransactionActive());
                $captured = $link;
                return \App\Service\Player\VerificationRecoveryOutcome::TransportAccepted;
            },
        );
        self::assertSame('transport_accepted', $adapter->deliver($id, $claim['claim_token'], $lifecycle, $keys, $transport));
        self::assertSame('unavailable', $adapter->deliver($id, $claim['claim_token'], $lifecycle, $keys, $transport));
        self::assertSame('transport_accepted', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$id]));
        $audit = $db->fetchAssociative('SELECT * FROM verification_recovery_event WHERE recovery_request_id = ?', [$id]);
        self::assertSame($originalId, (int) $audit['original_request_id']);
        self::assertSame($generation->version, (int) $audit['generation_version']);
        self::assertSame('transport_accepted', $audit['delivery_outcome']);
        self::assertNotNull($audit['handoff_at']);
        self::assertNotNull($audit['delivery_resolved_at']);
        self::assertSame($generation->expiresAt, (int) $db->fetchOne('SELECT expires_at FROM player_verification_generation WHERE player_id = ?', [$player->id]));
        self::assertSame($generation->fingerprint, $db->fetchOne('SELECT fingerprint FROM player_verification_generation WHERE player_id = ?', [$player->id]));
        self::assertStringNotContainsString($expected, json_encode($audit, JSON_THROW_ON_ERROR));

        self::assertNotNull($captured);
        $reader = $this->mixed($em, $codec, $clock);
        self::assertTrue($reader->confirm($captured));
        self::assertFalse($reader->confirm($captured));
        self::assertNotNull($player->emailVerifiedAt);
        self::assertSame('consumed', $db->fetchOne('SELECT state FROM player_verification_generation WHERE player_id = ?', [$player->id]));
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$originalId]));
    }

    private function syntheticAdmin(EntityManagerInterface $em, MockClock $clock): Player
    {
        $admin = new Player();
        $admin->email = 'admin-recovery@example.test';
        $admin->username = 'RecoveryAdmin';
        $admin->password = 'synthetic';
        $admin->roles = ['ROLE_ADMIN'];
        $admin->emailVerifiedAt = $clock->now();
        $em->persist($admin);
        $em->flush();

        return $admin;
    }

    /** Le processus enfant tente la même lecture verrouillée que l'appel réel. */
    private function raceAdminWhileHoldingAccount(Connection $db, int $lockedId, int $adminId, int $playerId, int $originalId, callable $mutate): array
    {
        $dir = sys_get_temp_dir().'/verification-admin-recovery-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $proc = new Process([
            PHP_BINARY, dirname(__DIR__).'/Support/verification-generation-worker.php', (string) $playerId,
            'reserve-recovery-admin', $dir, 'a', (string) $originalId, (string) $adminId,
        ], timeout: 20);
        $db->beginTransaction();
        try {
            $db->fetchOne('SELECT id FROM player WHERE id = ? FOR UPDATE', [$lockedId]);
            $proc->start();
            $limit = microtime(true) + 10;
            while (!is_file($dir.'/ready-a')) {
                if (microtime(true) > $limit) {
                    self::fail('Processus de réservation non prêt.');
                }
                usleep(10000);
            }
            touch($dir.'/go');
            do {
                $reads = array_filter($db->fetchAllAssociative('SHOW FULL PROCESSLIST'), static fn (array $row): bool =>
                    preg_match('/SELECT roles, email_verified_at, locked_at FROM player WHERE id = [\x27"]?'.$lockedId.'[\x27"]? FOR UPDATE/', (string) ($row['Info'] ?? '')) === 1);
                if ($reads !== []) {
                    break;
                }
                if (microtime(true) > $limit) {
                    self::fail('La lecture verrouillée du processus n’a pas été observée.');
                }
                usleep(10000);
            } while (true);
            $mutate();
            $db->commit();
            $proc->wait();
            self::assertTrue($proc->isSuccessful(), $proc->getErrorOutput());
            return json_decode($proc->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            $proc->stop();
            foreach (glob($dir.'/*') as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
    }

    public function testAdminRecoveryWaitingOnActorLockSeesCommittedRevocation(): void
    {
        [$em, $db, $player, $clock, $store, , $keys] = $this->fixture();
        $this->up($db);
        $admin = $this->syntheticAdmin($em, $clock);
        $original = $this->unknown($em, $player, $clock);
        $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $response = $this->raceAdminWhileHoldingAccount($db, $admin->id, $admin->id, $player->id, $original->id,
            static fn (): int => $db->update('player', ['roles' => '[]'], ['id' => $admin->id]));
        self::assertSame(['result' => 'denied'], $response);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM admin_audit_log'));
    }

    public function testAdminRecoveryWaitingOnTargetLockDoesNotRetargetNewReceipt(): void
    {
        [$em, $db, $player, $clock, $store, , $keys] = $this->fixture();
        $this->up($db);
        $admin = $this->syntheticAdmin($em, $clock);
        $original = $this->unknown($em, $player, $clock);
        $store->prepare($player->id, 'fixture', $clock->now()->modify('+1 hour'), $keys);
        $response = $this->raceAdminWhileHoldingAccount($db, $player->id, $admin->id, $player->id, $original->id,
            static fn (): int => $db->insert('verification_delivery_request', [
                'player_id' => $player->id, 'request_key_hash' => str_repeat('a', 64), 'state' => 'unknown',
                'requested_at' => '2029-12-31 23:53:00', 'resolved_at' => '2029-12-31 23:54:00', 'cooldown_until' => '2029-12-31 23:58:00',
            ]));
        self::assertSame(['result' => 'blocked', 'request_id' => null], $response);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request WHERE id = ?', [$original->id]));
    }

    public function testAdminRecoverySelectorDoesNotDistinguishForeignFromUnknownReceipt(): void
    {
        [$em, $db, $player, $clock, , $codec, $keys] = $this->fixture();
        $this->up($db);
        $admin = $this->syntheticAdmin($em, $clock);
        $foreign = $this->unknown($em, $admin, $clock);
        $service = new \App\Service\Player\VerificationRecoveryOrchestrator($db, $clock, $codec);
        $key = str_repeat('d', 64);
        $expected = ['result' => 'ineligible', 'request_id' => null];
        self::assertSame($expected, $service->reserveForAdmin($admin->id, $player->id, $foreign->id, $key, 300, $keys));
        self::assertSame($expected, $service->reserveForAdmin($admin->id, $player->id, 2147483647, $key, 300, $keys));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
    }
}
