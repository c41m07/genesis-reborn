<?php
namespace App\Tests\Unit;

use App\Service\Player\EmpireConversionSchedule;
use PHPUnit\Framework\TestCase;

final class EmpireConversionScheduleTest extends TestCase
{
    public function testDeadlineIsTheFirstParisMidnightAtOrAfterTheThreshold(): void
    {
        foreach([
            ['2026-11-08T12:00:00Z','2026-11-08T23:00:00+00:00'],
            ['2026-11-08T22:59:59Z','2026-11-08T23:00:00+00:00'],
            ['2026-11-08T23:00:00Z','2026-11-08T23:00:00+00:00'],
            ['2026-11-08T23:00:01Z','2026-11-09T23:00:00+00:00'],
            ['2026-07-08T12:00:00Z','2026-07-08T22:00:00+00:00'],
        ] as [$threshold,$expected]) self::assertSame($expected,EmpireConversionSchedule::atOrAfter(new \DateTimeImmutable($threshold))->format(DATE_ATOM));
    }

    public function testMidnightFollowsParisDaylightSavingInsteadOfAddingTwentyFourHours(): void
    {
        foreach([
            ['2026-03-28T23:00:01Z','2026-03-29T22:00:00+00:00'],
            ['2026-10-24T22:00:01Z','2026-10-25T23:00:00+00:00'],
        ] as [$threshold,$expected]) self::assertSame($expected,EmpireConversionSchedule::atOrAfter(new \DateTimeImmutable($threshold))->format(DATE_ATOM));
        self::assertSame('2026-03-28T23:00:00+00:00',EmpireConversionSchedule::latestAt(new \DateTimeImmutable('2026-03-29T20:00:00Z'))->format(DATE_ATOM));
        self::assertSame('2026-10-24T22:00:00+00:00',EmpireConversionSchedule::latestAt(new \DateTimeImmutable('2026-10-25T20:00:00Z'))->format(DATE_ATOM));
    }
}
