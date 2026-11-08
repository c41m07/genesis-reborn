<?php
namespace App\Tests\Unit;

use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Service\Catalog\GameCatalog;
use App\Service\Player\{EmpireActivityService, PlayerProtection};
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class PlayerProtectionTest extends TestCase
{
    public function testSuspensionStartsAtExactlyFourteenDaysWithoutChangingProtectionDates(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $membership = $this->membership($clock);
        $membership->lastActivityAt = $clock->now()->modify('-14 days +1 second');
        $deadline = $membership->recoveryProtectedUntil;
        $cooldown = $membership->lastRecoveryProtectionAt;
        $protection = $this->protection($clock, true);

        self::assertTrue($protection->status($membership, 0)['active']);
        $clock->sleep(1);
        self::assertTrue($protection->suspendedForInactivity($membership));
        self::assertSame(['active'=>false,'until'=>null,'remaining'=>0,'reason'=>null], $protection->status($membership, 0));
        self::assertEquals($deadline, $membership->recoveryProtectedUntil);
        self::assertEquals($cooldown, $membership->lastRecoveryProtectionAt);
    }

    public function testDisabledRulesAndUnknownActivityKeepNormalProtections(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $membership = $this->membership($clock);
        $membership->lastActivityAt = $clock->now()->modify('-20 days');
        self::assertTrue($this->protection($clock, false)->status($membership, 0)['active']);
        $membership->lastActivityAt = null;
        self::assertTrue($this->protection($clock, true)->status($membership, 0)['active']);
        $membership->recoveryProtectedUntil = null;
        $membership->joinedAt = $clock->now()->modify('-1 day');
        self::assertSame('Protection débutant', $this->protection($clock, true)->status($membership, 0)['reason']);
    }

    public function testReturningBeforeThirtyDaysRestoresOnlyAnUnexpiredProtection(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $membership = $this->membership($clock);
        $membership->lastActivityAt = $clock->now()->modify('-20 days');
        $protection = $this->protection($clock, true);
        self::assertFalse($protection->status($membership, 0)['active']);
        $deadline = $membership->recoveryProtectedUntil;
        $membership->lastActivityAt = $clock->now();
        self::assertTrue($protection->status($membership, 0)['active']);
        self::assertSame($deadline->format(DATE_ATOM), $protection->status($membership, 0)['until']);
        $clock->sleep(86400);
        $membership->lastActivityAt = $clock->now();
        self::assertFalse($protection->status($membership, 0)['active']);
        self::assertEquals($deadline, $membership->recoveryProtectedUntil);
    }

    private function protection(MockClock $clock, bool $enabled): PlayerProtection
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $calendar = new \App\Service\Player\VacationCalendar(new \App\Repository\GameRepository($em), $clock);
        $activity = new EmpireActivityService(new GameManager($em), $clock, $calendar, new \App\Service\Player\VacationPolicy($clock));
        return new PlayerProtection(new GameCatalog(dirname(__DIR__, 2) . '/config/game'), $clock, $activity, $calendar, $enabled);
    }

    private function membership(MockClock $clock): PlayerUniverse
    {
        $membership = new PlayerUniverse();
        $membership->player = new \App\Entity\Player();
        $membership->joinedAt = $clock->now()->modify('-40 days');
        $membership->lastRecoveryProtectionAt = $clock->now()->modify('-1 hour');
        $membership->recoveryProtectedUntil = $clock->now()->modify('+1 day');
        return $membership;
    }
}
