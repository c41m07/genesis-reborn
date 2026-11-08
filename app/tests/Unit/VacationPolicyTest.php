<?php
namespace App\Tests\Unit;

use App\Entity\{PlayerUniverse, VacationPeriod};
use App\Service\Player\VacationPolicy;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class VacationPolicyTest extends TestCase
{
    public function testPlanLastsExactlyNinetyDaysAndExpiresWithoutAVisit(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $period = (new VacationPolicy($clock))->plan(new PlayerUniverse());
        self::assertNull($period->id);
        self::assertTrue($period->isActiveAt($clock->now()));
        self::assertSame(90 * 86400, $period->scheduledEndAt->getTimestamp() - $period->startedAt->getTimestamp());
        $clock->sleep(90 * 86400 - 1);
        self::assertTrue($period->isActiveAt($clock->now()));
        $clock->sleep(1);
        self::assertFalse($period->isActiveAt($clock->now()));
    }

    public function testVoluntaryEndRequiresFortyEightHours(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $policy = new VacationPolicy($clock);
        $period = $policy->plan(new PlayerUniverse());
        $clock->sleep(48 * 3600 - 1);
        try {
            $policy->finish($period);
            self::fail('Une sortie avant 48 heures doit être refusée.');
        } catch (\DomainException) {}
        self::assertNull($period->endedAt);
        $clock->sleep(1);
        $policy->finish($period);
        self::assertEquals($clock->now(), $period->endedAt);
        self::assertFalse($period->isActiveAt($clock->now()));
        $end = $period->endedAt;
        $clock->sleep(86400);
        $policy->finish($period);
        self::assertEquals($end, $period->endedAt);
    }

    public function testCooldownStartsAtTheActualEarlyEnd(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $policy = new VacationPolicy($clock);
        $membership = new PlayerUniverse();
        $period = $policy->plan($membership);
        $clock->sleep(48 * 3600);
        $policy->finish($period);
        $clock->sleep(15 * 86400 - 1);
        try {
            $policy->plan($membership, $period);
            self::fail('Une nouvelle activation avant 15 jours doit être refusée.');
        } catch (\DomainException) {}
        $clock->sleep(1);
        self::assertEquals($clock->now(), $policy->plan($membership, $period)->startedAt);
    }

    public function testLateProcessingUsesTheScheduledEndForTheCooldown(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $policy = new VacationPolicy($clock);
        $membership = new PlayerUniverse();
        $period = $policy->plan($membership);
        $clock->sleep(105 * 86400);
        $policy->finish($period);
        self::assertEquals($period->scheduledEndAt, $period->endedAt);
        self::assertEquals($clock->now(), $policy->plan($membership, $period)->startedAt);
    }

    public function testAnotherEmpiresPeriodCannotBeUsedToBypassTheCooldown(): void
    {
        $policy = new VacationPolicy(new MockClock('2026-11-07T12:00:00Z'));
        $previous = $policy->plan(new PlayerUniverse());
        $this->expectException(\DomainException::class);
        $policy->plan(new PlayerUniverse(), $previous);
    }

    public function testAnActivePeriodCannotBeOverlapped(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $policy = new VacationPolicy($clock);
        $membership = new PlayerUniverse();
        $previous = $policy->plan($membership);
        $clock->sleep(86400);
        $this->expectException(\DomainException::class);
        $policy->plan($membership, $previous);
    }

    public function testPausedTimeIsClippedMergedAndDoesNotResetPreviousInactivity(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $policy = new VacationPolicy($clock);
        $membership = new PlayerUniverse();
        $lastActivity = $clock->now()->modify('-10 days');
        $first = $policy->plan($membership);
        $clock->sleep(2 * 86400);
        $second = $policy->plan($membership);
        $clock->sleep(2 * 86400);
        $policy->finish($first);
        $policy->finish($second);
        $clock->sleep(3 * 86400);
        $paused = $policy->pausedSeconds([$second, $first], $lastActivity, $clock->now());
        self::assertSame(4 * 86400, $paused);
        self::assertSame(13 * 86400, $clock->now()->getTimestamp() - $lastActivity->getTimestamp() - $paused);
        self::assertSame(3 * 86400, $policy->pausedSeconds([$first], $first->startedAt->modify('+1 day'), $clock->now()));
        self::assertSame(0, $policy->pausedSeconds([$first], $clock->now(), $lastActivity));
    }

    public function testEachDeadlineIncludesOnlyVacationsBeforeItIsReached(): void
    {
        $clock = new MockClock('2026-11-07T12:00:00Z');
        $policy = new VacationPolicy($clock);
        $from = $clock->now();
        $membership = new PlayerUniverse();
        $clock->sleep(10 * 86400);
        $first = $policy->plan($membership);
        $clock->sleep(2 * 86400);
        $policy->finish($first);
        $clock->sleep(15 * 86400);
        $second = $policy->plan($membership, $first);
        $clock->sleep(2 * 86400);
        $policy->finish($second);
        self::assertEquals($from->modify('+16 days'), $policy->deadlineAfterActiveSeconds([$second, $first], $from, 14 * 86400));
        self::assertEquals($from->modify('+34 days'), $policy->deadlineAfterActiveSeconds([$second, $first], $from, 30 * 86400));
    }

    public function testElapsedHoursRemainExactAcrossDaylightSavingChange(): void
    {
        $clock = new MockClock('2026-10-24T12:00:00+02:00');
        $policy = new VacationPolicy($clock);
        $period = $policy->plan(new PlayerUniverse());
        $clock->sleep(48 * 3600);
        $policy->finish($period);
        self::assertSame(48 * 3600, $policy->pausedSeconds([$period], $period->startedAt, $clock->now()));
        self::assertSame('2026-10-26T10:00:00+00:00', $period->endedAt->format(DATE_ATOM));
    }
}
