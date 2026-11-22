<?php

namespace App\Tests\Unit;

use App\Service\Admin\OutboxDiagnostic;
use PHPUnit\Framework\TestCase;

final class OutboxDiagnosticTest extends TestCase
{
    public function testEveryKnownKindDistinguishesDeadlineWithoutClaimingExecution(): void
    {
        foreach (['queue', 'fleet', 'combat', 'universe', 'notification', 'guild_project', 'occupation', 'reconstruction'] as $kind) {
            foreach ([false, true] as $due) {
                $result = OutboxDiagnostic::describe($kind, $due);
                self::assertSame($due ? 'due' : 'future', $result['state']);
                self::assertSame($kind === 'fleet', $result['fleet_link']);
                self::assertNotEmpty($result['guidance']);
                self::assertArrayNotHasKey('replay', $result);
                self::assertArrayNotHasKey('completed', $result);
            }
        }
    }

    public function testUnknownKindsNeverSuggestReplayOrGuessAResourceLink(): void
    {
        foreach (['', 'Fleet', 'unexpected', ' fleet', '<script>'] as $kind) {
            foreach ([false, true] as $due) {
                $result = OutboxDiagnostic::describe($kind, $due);
                self::assertSame('unsupported', $result['state']);
                self::assertSame('Examen humain requis', $result['label']);
                self::assertFalse($result['fleet_link']);
                self::assertStringContainsString('Aucune reprise', $result['guidance']);
            }
        }
    }
}
