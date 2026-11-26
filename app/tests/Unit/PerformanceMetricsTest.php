<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Performance\PerformanceMetrics;
use PHPUnit\Framework\TestCase;

final class PerformanceMetricsTest extends TestCase
{
    public function testBucketBoundsAndHighlightThresholds(): void
    {
        self::assertSame(0, PerformanceMetrics::bucket(0));
        self::assertSame(20, PerformanceMetrics::bucket(11));
        self::assertSame(1000, PerformanceMetrics::bucket(999));
        self::assertSame(1050, PerformanceMetrics::bucket(1001));
        self::assertSame(2500, PerformanceMetrics::bucket(2499));
        self::assertSame(2550, PerformanceMetrics::bucket(2501));
        self::assertSame(600_000, PerformanceMetrics::bucket(900_000));
    }

    public function testOnlyCoarseKeysAreAccepted(): void
    {
        self::assertTrue(PerformanceMetrics::accepts('app_map_v2', 'mobile', 'lcp'));
        self::assertTrue(PerformanceMetrics::accepts('rabbitmq', 'server', 'broker_publish'));
        self::assertFalse(PerformanceMetrics::accepts('/empire/27', 'mobile', 'lcp'));
        self::assertFalse(PerformanceMetrics::accepts('app_map_v2', 'Exact Browser 1.0', 'lcp'));
        self::assertFalse(PerformanceMetrics::accepts('app_map_v2', 'mobile', 'select_player_name'));
    }
}
