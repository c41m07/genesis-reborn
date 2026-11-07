<?php
namespace App\Tests\Unit;

use App\Entity\Universe;
use App\Service\Universe\UniverseClosingClock;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class UniverseClosingClockTest extends TestCase
{
    public function testThresholdExpiryAndMissingDate(): void
    {
        $clock = new MockClock('2030-01-01T00:00:00+00:00');
        $service = new UniverseClosingClock($clock);
        $universe = new Universe();
        self::assertFalse($service->snapshot($universe)['visible']);
        self::assertNull($service->snapshot($universe)['remaining']);
        $universe->endsAt = new \DateTimeImmutable('2030-01-02T01:00:01+01:00');
        self::assertFalse($service->snapshot($universe)['visible']);
        $clock->sleep(1);
        self::assertTrue($service->snapshot($universe)['visible']);
        self::assertSame(86400, $service->snapshot($universe)['remaining']);
        self::assertSame(strtotime('2030-01-02T00:00:01Z'), strtotime($service->snapshot($universe)['endsAt']));
        $clock->sleep(86400);
        self::assertSame(0, $service->snapshot($universe)['remaining']);
        self::assertTrue($service->snapshot($universe)['visible']);
        $clock->sleep(20);
        self::assertSame(0, $service->snapshot($universe)['remaining']);
        $universe->status = 'closed';
        self::assertFalse($service->snapshot($universe)['visible']);
    }
}
