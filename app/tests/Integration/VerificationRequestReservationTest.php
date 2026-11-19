<?php

namespace App\Tests\Integration;

use App\Entity\{Player, VerificationDeliveryRequest};
use App\Service\Player\VerificationRequestReservation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

final class VerificationRequestReservationTest extends KernelTestCase
{
    private function fixture(): array
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $player = new Player();
        $player->email = 'reservation@example.test';
        $player->username = 'Reservation';
        $player->password = 'synthetic';
        $em->persist($player);
        $em->flush();
        return [$em, $player];
    }

    public function testReplayCooldownAndFailClosedState(): void
    {
        [$em, $player] = $this->fixture();
        $clock = new MockClock('2026-11-19T12:00:00Z');
        self::getContainer()->set(ClockInterface::class, $clock);
        $service = new VerificationRequestReservation($em, new \App\Manager\GameManager($em), new \App\Repository\VerificationDeliveryRequestRepository($em), self::getContainer()->get(ClockInterface::class));
        $first = $service->reserve($player->id, str_repeat('a', 64), 300);
        self::assertSame('reserved', $first['result']);
        self::assertSame('replayed', $service->reserve($player->id, str_repeat('a', 64), 300)['result']);
        self::assertSame('blocked', $service->reserve($player->id, str_repeat('b', 64), 300)['result']);
        $request = $em->find(VerificationDeliveryRequest::class, $first['request_id']);
        $request->state = 'transport_accepted';
        $em->flush();
        $clock->sleep(299);
        self::assertSame('blocked', $service->reserve($player->id, str_repeat('b', 64), 300)['result']);
        $clock->sleep(1);
        $second = $service->reserve($player->id, str_repeat('b', 64), 300);
        self::assertSame('reserved', $second['result']);
        $clock->sleep(600);
        self::assertSame('blocked', $service->reserve($player->id, str_repeat('c', 64), 300)['result']);
        $em->find(VerificationDeliveryRequest::class, $second['request_id'])->state = 'unknown';
        $em->flush();
        self::assertSame('blocked', $service->reserve($player->id, str_repeat('c', 64), 300)['result']);
        $player->lockedAt = $clock->now();
        $em->flush();
        self::assertSame('ineligible', $service->reserve($player->id, str_repeat('a', 64), 300)['result']);
        self::assertSame(2, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
    }

    public function testIneligibleAndInvalidRequestsDoNotCreateReceipts(): void
    {
        [$em, $player] = $this->fixture();
        $service = new VerificationRequestReservation($em, new \App\Manager\GameManager($em), new \App\Repository\VerificationDeliveryRequestRepository($em), self::getContainer()->get(ClockInterface::class));
        self::assertSame('ineligible', $service->reserve(2147483647, str_repeat('a', 64), 300)['result']);
        $player->emailVerifiedAt = new \DateTimeImmutable();
        $em->flush();
        self::assertSame('ineligible', $service->reserve($player->id, str_repeat('a', 64), 300)['result']);
        foreach ([['invalid', 300], [str_repeat('a', 64), 0], [str_repeat('a', 64), 86401]] as [$key, $seconds]) {
            try {
                $service->reserve($player->id, $key, $seconds);
                self::fail('La réservation invalide doit être refusée.');
            } catch (\InvalidArgumentException) {
                self::assertSame(0, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
            }
        }
        $em->getConnection()->beginTransaction();
        try {
            $service->reserve($player->id, str_repeat('a', 64), 300);
            self::fail('Une transaction externe ne doit pas produire un reçu non commité.');
        } catch (\LogicException) {
            self::assertSame(0, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
        } finally {
            $em->getConnection()->rollBack();
        }
    }

    public function testPreparedMigrationCreatesUsableReceiptTableInDisposableDatabase(): void
    {
        [$em, $player] = $this->fixture();
        // fixture() a vérifié l'hôte et la base jetables avant toute DDL.
        $connection = $em->getConnection();
        $connection->executeStatement('DROP TABLE verification_delivery_request');
        require_once dirname(__DIR__, 2).'/migrations/Version20261119235900.php';
        $migration = new \DoctrineMigrations\Version20261119235900($connection, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) {
            $connection->executeStatement($query->getStatement(), $query->getParameters(), $query->getTypes());
        }
        $service = new VerificationRequestReservation($em, new \App\Manager\GameManager($em), new \App\Repository\VerificationDeliveryRequestRepository($em), self::getContainer()->get(ClockInterface::class));
        self::assertSame('reserved', $service->reserve($player->id, str_repeat('a', 64), 300)['result']);
        self::assertSame('replayed', $service->reserve($player->id, str_repeat('a', 64), 300)['result']);
        self::assertSame(1, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
        // Retirer la table de migration (nom de FK explicite) avant la fixture
        // ORM suivante, qui connaît son propre nom de contrainte généré.
        $connection->executeStatement('DROP TABLE verification_delivery_request');
    }

    public function testTwoProcessesReserveOnlyOnce(): void
    {
        [$em, $player] = $this->fixture();
        foreach ([true, false] as $sameKey) {
            $em->createQuery('DELETE FROM App\\Entity\\VerificationDeliveryRequest')->execute();
            $directory = sys_get_temp_dir().'/verification-reservation-'.bin2hex(random_bytes(8));
            mkdir($directory, 0700);
            $processes = [];
            try {
                for ($index = 0; $index < 2; ++$index) {
                    $key = str_repeat($sameKey || $index === 0 ? 'a' : 'b', 64);
                    $process = new Process([PHP_BINARY, __DIR__.'/../Support/verification-reservation-worker.php', (string) $player->id, $key, $directory, (string) $index]);
                    $process->setTimeout(20);
                    $process->start();
                    $processes[] = $process;
                }
                $deadline = microtime(true) + 15;
                while (!is_file($directory.'/ready-0') || !is_file($directory.'/ready-1')) {
                    if (microtime(true) > $deadline) {
                        self::fail('Les deux processus ne sont pas prêts : '.implode(' | ', array_map(fn ($p) => $p->getErrorOutput(), $processes)));
                    }
                    usleep(10000);
                }
                // Deux connexions distinctes sont prêtes avant ce signal commun.
                touch($directory.'/go');
                $results = [];
                foreach ($processes as $process) {
                    $process->wait();
                    self::assertTrue($process->isSuccessful(), $process->getErrorOutput().' '.$process->getOutput());
                    $results[] = json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
                }
                $outcomes = array_column($results, 'result');
                sort($outcomes);
                self::assertSame($sameKey ? ['replayed', 'reserved'] : ['blocked', 'reserved'], $outcomes);
                self::assertSame($results[0]['request_id'], $results[1]['request_id']);
                self::assertSame(1, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
            } finally {
                foreach ($processes as $process) {
                    if ($process->isRunning()) {
                        $process->stop();
                    }
                }
                foreach (glob($directory.'/*') as $file) {
                    unlink($file);
                }
                rmdir($directory);
            }
        }
    }
}
