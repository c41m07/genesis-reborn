<?php

namespace App\Tests\Unit;

use App\Service\Admin\OutboxRecoveryInspection;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class OutboxRecoveryInspectionTest extends TestCase
{
    public function testMissingMessageDoesNotClaimSuccess(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects(self::once())->method('fetchAssociative')->willReturn(false);
        $db->expects(self::never())->method('executeStatement');
        self::assertNull((new OutboxRecoveryInspection($db, new MockClock()))->inspect(123));
    }

    public function testEveryStateRefusesReplayAndDoesNotExposeTokens(): void
    {
        $base = [
            'id' => 123, 'target_id' => 456, 'kind' => 'queue', 'token' => 'private-current',
            'queue_id' => 456, 'dispatch_token' => 'private-current',
            'started_at' => '2026-11-20 09:00:00', 'duration_seconds' => 60,
            'due_at' => '2026-11-20 09:01:00', 'owner_id' => 7, 'player_universe_id' => 7,
        ];
        foreach ([
            [[], 'due_unproven'],
            [['kind' => 'notification'], 'unsupported'],
            [['queue_id' => null], 'target_absent'],
            [['dispatch_token' => 'private-new'], 'superseded'],
            [['owner_id' => null], 'ownership_changed'],
            [['owner_id' => 8], 'ownership_changed'],
            [['started_at' => null], 'not_started'],
            [['started_at' => '2026-11-20 11:00:00'], 'not_due'],
            [['due_at' => '2026-11-20 11:00:00'], 'not_due'],
        ] as [$changes, $expected]) {
            $db = $this->createMock(Connection::class);
            $db->expects(self::once())->method('fetchAssociative')->with(self::stringContains('WHERE o.id = ?'), [123])->willReturn(array_replace($base, $changes));
            $db->expects(self::never())->method('executeStatement');
            $result = (new OutboxRecoveryInspection($db, new MockClock('2026-11-20 10:00:00 UTC')))->inspect(123);
            self::assertSame($expected, $result['state']);
            self::assertFalse($result['replay_allowed']);
            self::assertStringNotContainsString('private-', json_encode($result));
            self::assertArrayNotHasKey('token', $result);
            self::assertArrayNotHasKey('dispatch_token', $result);
        }
    }
}
