<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Performance\PerformancePriority;
use PHPUnit\Framework\TestCase;

final class PerformancePriorityTest extends TestCase
{
    public function testSmallSamplesNeverProduceAPercentileOrRecommendAChange(): void
    {
        $few = PerformancePriority::summarize([4300 => 29]);
        self::assertSame(['count' => 29, 'median' => null, 'p95' => null], $few);
        self::assertSame('insufficient', PerformancePriority::page($few, ['count' => 500, 'sql_dominant' => 500, 'twig_dominant' => 0])['status']);
    }

    public function testWeightedPercentilesAndPairedSqlEvidenceGiveAPracticalAction(): void
    {
        $lcp = PerformancePriority::summarize([4100 => 12, 900 => 60, 2000 => 28]);
        self::assertSame(['count' => 100, 'median' => 900, 'p95' => 4100], $lcp);
        $result = PerformancePriority::page($lcp, ['count' => 82, 'sql_dominant' => 63, 'twig_dominant' => 3]);
        self::assertSame('review', $result['status']);
        self::assertStringContainsString('requêtes SQL', $result['action']);
        self::assertStringContainsString('cause n’est pas isolée', PerformancePriority::page($lcp, ['count' => 29, 'sql_dominant' => 29, 'twig_dominant' => 0])['action']);
    }

    public function testQueueDelayIsNotAnHttpRenderingIssue(): void
    {
        $fast = PerformancePriority::summarize([150 => 100]);
        $delayed = PerformancePriority::summarize([15_000 => 100]);
        self::assertStringContainsString('RabbitMQ', PerformancePriority::queue($fast, $delayed, $fast)['action']);
        self::assertStringContainsString('traitements lents', PerformancePriority::queue($fast, $fast, $delayed)['action']);
    }
}
