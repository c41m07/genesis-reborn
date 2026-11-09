<?php
namespace App\Tests\Unit;

use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Service\Player\EmpireActivityService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class EmpireActivityServiceTest extends TestCase
{
    public function testUnknownActivityDoesNotUseTheRegistrationDate(): void
    {
        $membership = new PlayerUniverse();
        $membership->joinedAt = new \DateTimeImmutable('2000-01-01T00:00:00Z');
        $service = $this->service(new MockClock('2026-11-07T12:00:00Z'));

        self::assertSame([
            'status' => 'untracked', 'inactive_at' => null,
            'conversion_due_at' => null, 'conversion_due' => false,
        ], $service->snapshot($membership));
    }

    public function testExactInactivityAndConversionThresholds(): void
    {
        $membership = new PlayerUniverse();
        $membership->lastActivityAt = new \DateTimeImmutable('2026-11-07T12:00:00Z');
        $clock = new MockClock('2026-11-21T11:59:59Z');
        $service = $this->service($clock);

        self::assertSame('active', $service->snapshot($membership)['status']);
        $clock->sleep(1);
        self::assertSame('inactive', $service->snapshot($membership)['status']);
        self::assertFalse($service->snapshot($membership)['conversion_due']);
        $clock->sleep(16 * 86400 - 1);
        self::assertFalse($service->snapshot($membership)['conversion_due']);
        $clock->sleep(1);
        self::assertFalse($service->snapshot($membership)['conversion_due']);
        $clock->sleep(11*3600);
        self::assertTrue($service->snapshot($membership)['conversion_due']);
        self::assertSame('2026-12-07T23:00:00+00:00', $service->snapshot($membership)['conversion_due_at']);
    }

    public function testThresholdsUseElapsedTimeAcrossDaylightSavingChange(): void
    {
        $membership = new PlayerUniverse();
        $membership->lastActivityAt = new \DateTimeImmutable('2026-10-24T12:00:00', new \DateTimeZone('Europe/Paris'));
        $clock = new MockClock('2026-11-07T09:59:59Z');
        $service = $this->service($clock);

        self::assertSame('active', $service->snapshot($membership)['status']);
        $clock->sleep(1);
        self::assertSame('inactive', $service->snapshot($membership)['status']);
    }

    private function service(MockClock $clock): EmpireActivityService
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $calendar = new \App\Service\Player\VacationCalendar(new \App\Repository\GameRepository($em), $clock);
        return new EmpireActivityService(new GameManager($em), $clock, $calendar, new \App\Service\Player\VacationPolicy($clock));
    }
}
