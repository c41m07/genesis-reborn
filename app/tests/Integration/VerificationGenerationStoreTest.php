<?php

namespace App\Tests\Integration;

use App\Entity\{EmailVerificationToken, Player, PlayerVerificationGeneration, VerificationDeliveryRequest, VerificationRecoveryEvent};
use App\Service\Player\{VerificationGenerationStore, VerificationLinkKeyResolver, VersionedVerificationLink};
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use DoctrineMigrations\Version20261120000200;
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
        $migration = new Version20261120000200($db, new \Psr\Log\NullLogger());
        $migration->{$direction}(new Schema());
        foreach ($migration->getSql() as $query) {
            $db->executeStatement($query->getStatement());
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

    private function race(int $playerId, string $first, string $second, int $rowId = 0, ?string $link = null): array
    {
        $dir = sys_get_temp_dir().'/verification-generation-'.bin2hex(random_bytes(8));
        mkdir($dir, 0700);
        $processes = [];
        try {
            foreach (['a' => $first, 'b' => $second] as $side => $operation) {
                $proc = new Process([PHP_BINARY, dirname(__DIR__).'/Support/verification-generation-worker.php', (string) $playerId, $operation, $dir, $side, (string) $rowId], input: $operation === 'consume' ? $link : null, timeout: 20);
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
}
