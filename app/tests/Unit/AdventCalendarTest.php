<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\Advent\{AdventCalendar, AdventGifts};
use PHPUnit\Framework\TestCase;

final class AdventCalendarTest extends TestCase
{
    public function testParisDecemberDoorsAndCatchupBoundary(): void
    {
        $calendar = new AdventCalendar();
        $dates = $calendar->schedule('2026-12-01', '2026-12-31');
        self::assertSame(24, count($dates['doors']));
        self::assertSame('2026-11-30T23:00:00+00:00', $dates['startsAt']->format(DATE_ATOM));
        self::assertSame('2026-12-23T23:00:00+00:00', $dates['doors'][24]->format(DATE_ATOM));
        self::assertSame('2026-12-31T23:00:00+00:00', $dates['closesAt']->format(DATE_ATOM));
        self::assertFalse($calendar->canOpen(new \DateTimeImmutable('2026-12-23T22:59:59Z'), $dates['doors'][24], $dates['closesAt']));
        self::assertTrue($calendar->canOpen(new \DateTimeImmutable('2026-12-23T23:00:00Z'), $dates['doors'][24], $dates['closesAt']));
        self::assertTrue($calendar->canOpen(new \DateTimeImmutable('2026-12-31T22:59:59Z'), $dates['doors'][1], $dates['closesAt']));
        self::assertFalse($calendar->canOpen($dates['closesAt'], $dates['doors'][1], $dates['closesAt']));
    }

    public function testMovedEditionSpansShortCivilDayWithoutOffsetDrift(): void
    {
        $dates = (new AdventCalendar())->schedule('2026-03-27', '2026-04-21');
        self::assertSame('2026-03-28T23:00:00+00:00', $dates['doors'][3]->format(DATE_ATOM));
        self::assertSame('2026-03-29T22:00:00+00:00', $dates['doors'][4]->format(DATE_ATOM));
        self::assertSame(23 * 3600, $dates['doors'][4]->getTimestamp() - $dates['doors'][3]->getTimestamp());
    }

    public function testBadCivilDateCannotBeSilentlyNormalised(): void
    {
        $this->expectException(\DomainException::class);
        (new AdventCalendar())->schedule('2026-11-31', '2026-12-31');
    }

    public function testClosingBeforeDoor24IsRejected(): void
    {
        $this->expectException(\DomainException::class);
        (new AdventCalendar())->schedule('2026-12-01', '2026-12-23');
    }

    public function testNoAmountsAreInventedAndChoiceIdentifiersAreOrdered(): void
    {
        $gifts = array_fill(1, 24, [['metal' => '7'], ['nano' => ''], ['nano' => '3']]);
        $normalised = (new AdventGifts())->validate($gifts);
        self::assertSame([['metal' => 7], ['nano' => 3]], $normalised[24]);
        $gifts[12] = [['metal' => ''], []];
        $this->expectExceptionMessage('porte 12');
        (new AdventGifts())->validate($gifts);
    }

    public function testGenericTokensAndOverflowNeverBecomeResourceLots(): void
    {
        $gifts = array_fill(1, 24, [['tokens' => '1']]);
        $this->expectException(\DomainException::class);
        (new AdventGifts())->validate($gifts);
    }
}
