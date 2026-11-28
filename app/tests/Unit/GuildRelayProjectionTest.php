<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Entity\Fleet;
use App\Service\Guild\GuildRelayProjection;
use PHPUnit\Framework\TestCase;

final class GuildRelayProjectionTest extends TestCase
{
    public function testRelayOnlyShowsCurrentLocalSegmentInActualDirection(): void
    {
        $fleet = new Fleet();
        $fleet->mission = 'transport';
        $fleet->status = 'traveling';
        $fleet->arrivalAt = new \DateTimeImmutable('@1900000300');
        $fleet->navigation = ['seconds' => 300, 'active' => ['status' => 'traveling', 'at' => 1900000000, 'progress' => 0], 'legs' => [
            ['from' => [11, 1, 0, 0, 2], 'to' => [11, 1, 0, 0, 10], 'hyper' => false, 'seconds' => 100],
            ['from' => [11, 1, 0, 0, 10], 'to' => [99, 1, 1, 1, 10], 'hyper' => true, 'seconds' => 100],
            ['from' => [99, 1, 1, 1, 10], 'to' => [99, 1, 1, 1, 4], 'hyper' => false, 'seconds' => 100],
        ]];
        $halfway = new \DateTimeImmutable('@1900000050');
        $row = GuildRelayProjection::contact($fleet, 11, $halfway);
        self::assertSame(['direction', 'from', 'to', 'observedAt', 'until'], array_keys($row));
        self::assertSame('outbound', $row['direction']);
        self::assertEqualsWithDelta(6.0, $row['from'], 0.00001);
        self::assertSame(10.0, $row['to']);
        self::assertSame(1900000100, $row['until']);
        self::assertNull(GuildRelayProjection::contact($fleet, 99, $halfway));
        self::assertNull(GuildRelayProjection::contact($fleet, 11, new \DateTimeImmutable('@1900000150')));
        self::assertSame('inbound', GuildRelayProjection::contact($fleet, 99, new \DateTimeImmutable('@1900000250'))['direction']);

        $fleet->status = 'returning';
        $fleet->navigation['active']['status'] = 'returning';
        self::assertSame('outbound', GuildRelayProjection::contact($fleet, 99, $halfway)['direction']);
        self::assertSame('inbound', GuildRelayProjection::contact($fleet, 11, new \DateTimeImmutable('@1900000250'))['direction']);
        $fleet->navigation['recall'] = ['progress' => 0.5];
        self::assertNull(GuildRelayProjection::contact($fleet, 99, $halfway));
        unset($fleet->navigation['recall']);
        $fleet->mission = 'spy';
        self::assertNull(GuildRelayProjection::contact($fleet, 99, $halfway));
    }
}
