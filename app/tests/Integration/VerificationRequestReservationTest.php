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
        require_once dirname(__DIR__, 2).'/migrations/Version20261120000100.php';
        $lifecycleMigration = new \DoctrineMigrations\Version20261120000100($connection, new \Psr\Log\NullLogger());
        $lifecycleMigration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($lifecycleMigration->getSql() as $query) {
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

    public function testLifecycleOwnershipReplayAndExpiryNeverRequeues(): void
    {
        [$em, $player] = $this->fixture();
        $clock = new MockClock('2026-11-19T12:00:00Z');
        $manager = new \App\Manager\GameManager($em);
        $reservations = new VerificationRequestReservation($em, $manager, new \App\Repository\VerificationDeliveryRequestRepository($em), $clock);
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, $manager, $clock);
        $id = $reservations->reserve($player->id, str_repeat('a', 64), 300)['request_id'];
        $claim = $lifecycle->claim($id, 60);
        self::assertSame('claimed', $claim['result']);
        $receipt = $em->find(VerificationDeliveryRequest::class, $id);
        self::assertNotSame($claim['claim_token'], $receipt->claimHash);
        self::assertSame('unavailable', $lifecycle->claim($id, 60)['result']);
        self::assertSame('rejected', $lifecycle->complete($id, str_repeat('0', 64), 'transport_accepted')['result']);
        self::assertSame('completed', $lifecycle->complete($id, $claim['claim_token'], 'transport_accepted')['result']);
        self::assertSame('replayed', $lifecycle->complete($id, $claim['claim_token'], 'transport_accepted')['result']);
        self::assertSame('conflict', $lifecycle->complete($id, $claim['claim_token'], 'cancelled')['result']);
        $clock->sleep(300);
        $id = $reservations->reserve($player->id, str_repeat('b', 64), 300)['request_id'];
        $claim = $lifecycle->claim($id, 60);
        $clock->sleep(59);
        self::assertSame('unchanged', $lifecycle->expire($id)['result']);
        $clock->sleep(1);
        self::assertSame('expired_unknown', $lifecycle->expire($id)['result']);
        self::assertSame('unknown', $em->find(VerificationDeliveryRequest::class, $id)->state);
        self::assertSame('conflict', $lifecycle->complete($id, $claim['claim_token'], 'transport_accepted')['result']);
        self::assertSame('unavailable', $lifecycle->claim($id, 60)['result']);
        $clock->sleep(3600);
        self::assertSame('blocked', $reservations->reserve($player->id, str_repeat('c', 64), 300)['result']);
        self::assertSame('missing', $lifecycle->claim(2147483647, 60)['result']);
    }

    public function testClaimCancelsNewlyIneligibleAccountWithoutOwnerToken(): void
    {
        [$em, $player] = $this->fixture();
        $clock = new MockClock();
        $manager = new \App\Manager\GameManager($em);
        $reservations = new VerificationRequestReservation($em, $manager, new \App\Repository\VerificationDeliveryRequestRepository($em), $clock);
        $id = $reservations->reserve($player->id, str_repeat('a', 64), 300)['request_id'];
        $player->lockedAt = $clock->now();
        $em->flush();
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, $manager, $clock);
        self::assertSame(['result' => 'cancelled'], $lifecycle->claim($id, 60));
        self::assertNull($em->find(VerificationDeliveryRequest::class, $id)->claimHash);
    }

    public function testFailedClaimCommitNeverReturnsOwnershipAndCanBeRetried(): void
    {
        [$em, $player] = $this->fixture();
        $clock = new MockClock('2026-11-19T12:00:00Z');
        $manager = new \App\Manager\GameManager($em);
        $reservations = new VerificationRequestReservation($em, $manager, new \App\Repository\VerificationDeliveryRequestRepository($em), $clock);
        $id = $reservations->reserve($player->id, str_repeat('a', 64), 300)['request_id'];
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, $manager, $clock);
        $failure = new class {
            public function onFlush(): void
            {
                throw new \RuntimeException('Panne simulée avant commit.');
            }
        };
        $em->getEventManager()->addEventListener([\Doctrine\ORM\Events::onFlush], $failure);
        $claimReturned = false;
        try {
            $lifecycle->claim($id, 60);
            $claimReturned = true;
        } catch (\RuntimeException $exception) {
            self::assertSame('Panne simulée avant commit.', $exception->getMessage());
        }
        self::assertFalse($claimReturned);
        // Nouvelle unité de travail : ne pas croire l'objet mémoire muté avant
        // rollback, ni réutiliser un EntityManager fermé après échec de flush.
        self::ensureKernelShutdown();
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $receipt = $em->find(VerificationDeliveryRequest::class, $id);
        self::assertSame('reserved', $receipt->state);
        self::assertNull($receipt->claimHash);
        self::assertNull($receipt->leaseExpiresAt);
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, new \App\Manager\GameManager($em), $clock);
        self::assertSame('claimed', $lifecycle->claim($id, 60)['result']);
    }

    public static function deliveryFaults(): array
    {
        return [
            'arrêt après revendication avant préparation' => ['before_prepare', 0],
            'arrêt après préparation avant transport' => ['before_send', 0],
            'exception ambiguë pendant transport' => ['during_send', 1],
            'arrêt après acceptation avant finalisation' => ['after_send', 1],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('deliveryFaults')]
    public function testInterruptedFakeDeliveryIsNeverAutomaticallyRetried(string $fault, int $expectedCalls): void
    {
        [$em, $player] = $this->fixture();
        $clock = new MockClock('2026-11-19T12:00:00Z');
        $manager = new \App\Manager\GameManager($em);
        $reservations = new VerificationRequestReservation($em, $manager, new \App\Repository\VerificationDeliveryRequestRepository($em), $clock);
        $lifecycle = new \App\Service\Player\VerificationRequestLifecycle($em, $manager, $clock);
        $id = $reservations->reserve($player->id, str_repeat('a', 64), 300)['request_id'];
        $claim = $lifecycle->claim($id, 60);
        self::assertSame('claimed', $claim['result']);
        $calls = 0;
        $fakeTransport = static function () use (&$calls, $fault): void {
            ++$calls;
            if ($fault === 'during_send') {
                throw new \RuntimeException('Acceptation distante inconnue.');
            }
        };
        // Harnais de test seulement : pas de jeton réel, pas de mailer, aucun
        // raccordement au service public. Le payload préparé est fictif.
        try {
            if ($fault === 'before_prepare') {
                throw new \RuntimeException('Arrêt avant préparation.');
            }
            $prepared = ['synthetic' => true];
            if ($fault === 'before_send') {
                throw new \RuntimeException('Arrêt avant transport.');
            }
            $fakeTransport();
            if ($fault === 'after_send') {
                throw new \RuntimeException('Arrêt avant finalisation.');
            }
            self::fail('Une panne doit interrompre chaque scénario.');
        } catch (\RuntimeException) {
            if ($fault === 'during_send') {
                self::assertSame('completed', $lifecycle->complete($id, $claim['claim_token'], 'unknown')['result']);
            }
            // Les arrêts francs ne publient pas de résultat : seule l'échéance
            // future les rend indéterminés. Aucun succès inventé après coup.
        }
        $clock->sleep(60);
        $lifecycle->expire($id);
        self::assertSame('unknown', $em->find(VerificationDeliveryRequest::class, $id)->state);
        for ($attempt = 0; $attempt < 2; ++$attempt) {
            $retry = $lifecycle->claim($id, 60);
            self::assertSame('unavailable', $retry['result']);
            if ($retry['result'] === 'claimed') {
                $fakeTransport();
            }
        }
        self::assertSame($expectedCalls, $calls);
        self::assertSame('conflict', $lifecycle->complete($id, $claim['claim_token'], 'transport_accepted')['result']);
        $clock->sleep(3600);
        self::assertSame('blocked', $reservations->reserve($player->id, str_repeat('b', 64), 300)['result']);
    }

    public function testTwoProcessesReserveOnlyOnce(): void
    {
        [$em, $player] = $this->fixture();
        foreach (['same_key', 'different_keys', 'claim'] as $scenario) {
            $sameKey = $scenario !== 'different_keys';
            $em->createQuery('DELETE FROM App\\Entity\\VerificationDeliveryRequest')->execute();
            $targetId = $player->id;
            if ($scenario === 'claim') {
                $service = new VerificationRequestReservation($em, new \App\Manager\GameManager($em), new \App\Repository\VerificationDeliveryRequestRepository($em), self::getContainer()->get(ClockInterface::class));
                $targetId = $service->reserve($player->id, str_repeat('c', 64), 300)['request_id'];
            }
            $directory = sys_get_temp_dir().'/verification-reservation-'.bin2hex(random_bytes(8));
            mkdir($directory, 0700);
            $processes = [];
            try {
                for ($index = 0; $index < 2; ++$index) {
                    $key = str_repeat($sameKey || $index === 0 ? 'a' : 'b', 64);
                    $process = new Process([PHP_BINARY, __DIR__.'/../Support/verification-reservation-worker.php', (string) $targetId, $key, $directory, (string) $index, $scenario]);
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
                self::assertSame($scenario === 'claim' ? ['claimed', 'unavailable'] : ($sameKey ? ['replayed', 'reserved'] : ['blocked', 'reserved']), $outcomes);
                if ($scenario !== 'claim') {
                    self::assertSame($results[0]['request_id'], $results[1]['request_id']);
                }
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
