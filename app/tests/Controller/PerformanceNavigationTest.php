<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\PerformanceBucket;
use App\Entity\PerformanceReceipt;
use App\Message\QueueItemFinished;
use App\Service\Performance\QueuePerfEligibleStamp;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PerformanceNavigationTest extends WebTestCase
{
    public function testAnonymousSampleGoesFromPageResponseToBoundedAggregatesExactlyOnce(): void
    {
        $previous = getenv('APP_PERFORMANCE_SAMPLE_PERCENT');
        putenv('APP_PERFORMANCE_SAMPLE_PERCENT=100');
        $_SERVER['APP_PERFORMANCE_SAMPLE_PERCENT'] = $_ENV['APP_PERFORMANCE_SAMPLE_PERCENT'] = '100';
        try {
            $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
            $client->disableReboot();
            $manager = self::getContainer()->get(EntityManagerInterface::class);
            $connection = $manager->getConnection();
            $params = $connection->getParams();
            if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
                throw new \RuntimeException('Base de test non isolée.');
            }
            $schema = new SchemaTool($manager);
            $metadata = [
                $manager->getClassMetadata(PerformanceBucket::class),
                $manager->getClassMetadata(PerformanceReceipt::class),
            ];
            $schema->dropSchema($metadata);
            $schema->createSchema($metadata);

            $client->request('GET', '/');
            self::assertResponseIsSuccessful();
            $timing = (string) $client->getResponse()->headers->get('Server-Timing');
            self::assertMatchesRegularExpression('/grperf;desc="[a-z0-9_.]+"/', $timing);
            self::assertStringNotContainsString('http_sql', $timing);
            preg_match('/grperf;desc="([^"]+)"/', $timing, $parts);
            $body = json_encode([
                'sample' => $parts[1], 'device' => 'mobile', 'lcp' => 1752, 'ttfb' => 260, 'html_transfer' => 12,
                'ignored' => 'Ce champ ne doit pas être stocké.',
            ], JSON_THROW_ON_ERROR);
            $headers = ['CONTENT_TYPE' => 'application/json', 'HTTP_SEC_FETCH_SITE' => 'same-origin'];
            for ($index = 0; $index < 2; ++$index) {
                $client->request('POST', '/_performance/navigation', server: $headers, content: $body);
                self::assertResponseStatusCodeSame(204);
            }

            self::assertSame(1, $this->countMetric($connection, 'server', 'http_php'));
            self::assertSame(1, $this->countMetric($connection, 'server', 'http_sql'));
            self::assertSame(1, $this->countMetric($connection, 'server', 'http_twig'));
            self::assertSame(1, $this->countMetric($connection, 'mobile', 'lcp'));
            self::assertSame(1800, (int) $connection->fetchOne("SELECT ceiling_ms FROM performance_bucket WHERE metric = 'lcp'"));
            self::assertSame(1, (int) $connection->fetchOne('SELECT COUNT(*) FROM performance_receipt'));

            $dispatcher = self::getContainer()->get('event_dispatcher');
            $clock = self::getContainer()->get('clock');
            $envelope = new Envelope(new QueueItemFinished(1, 'test-only'), [
                new QueuePerfEligibleStamp((int) $clock->now()->format('Uv') - 250),
            ]);
            $received = new WorkerMessageReceivedEvent($envelope, 'async');
            $dispatcher->dispatch($received);
            $dispatcher->dispatch(new WorkerMessageHandledEvent($received->getEnvelope(), 'async'));
            self::assertSame(1, (int) $connection->fetchOne(
                "SELECT SUM(observations) FROM performance_bucket WHERE scope = 'worker' AND metric = 'worker_wait'",
            ));
            self::assertSame(1, (int) $connection->fetchOne(
                "SELECT SUM(observations) FROM performance_bucket WHERE scope = 'worker' AND metric = 'worker_execute'",
            ));
        } finally {
            if ($previous === false) {
                putenv('APP_PERFORMANCE_SAMPLE_PERCENT');
                unset($_SERVER['APP_PERFORMANCE_SAMPLE_PERCENT'], $_ENV['APP_PERFORMANCE_SAMPLE_PERCENT']);
            } else {
                putenv('APP_PERFORMANCE_SAMPLE_PERCENT=' . $previous);
                $_SERVER['APP_PERFORMANCE_SAMPLE_PERCENT'] = $_ENV['APP_PERFORMANCE_SAMPLE_PERCENT'] = $previous;
            }
        }
    }

    private function countMetric(Connection $connection, string $device, string $metric): int
    {
        return (int) $connection->fetchOne(
            'SELECT SUM(observations) FROM performance_bucket WHERE scope = ? AND device = ? AND metric = ?',
            ['app_home', $device, $metric],
        );
    }
}
