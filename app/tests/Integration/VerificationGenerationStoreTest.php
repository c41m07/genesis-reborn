<?php

namespace App\Tests\Integration;

use App\Entity\{EmailVerificationToken, Player, PlayerVerificationGeneration, VerificationDeliveryRequest, VerificationRecoveryEvent};
use App\Service\Player\{VerificationGenerationStore, VerificationLinkKeyResolver, VersionedVerificationLink};
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineMigrations\{Version20261120000200, Version20261120000300};
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
        $versions = [Version20261120000200::class, Version20261120000300::class];
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
                $proc = new Process([PHP_BINARY, dirname(__DIR__).'/Support/verification-generation-worker.php', (string) $playerId, $operation, $dir, $side, (string) $rowId], input: in_array($operation, ['consume', 'mixed'], true) ? (is_array($link) ? $link[$side] : $link) : null, timeout: 20);
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
}
