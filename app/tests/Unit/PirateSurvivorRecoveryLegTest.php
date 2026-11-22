<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\PirateCampaign\{PirateBlockedReturnEvidence, PirateSurvivorRecoveryLeg};
use PHPUnit\Framework\TestCase;
use Symfony\Component\Uid\Uuid;

final class PirateSurvivorRecoveryLegTest extends TestCase
{
    public function testAcceptsOnlyExactActiveGenerationTokenAndElapsedWholeLeg(): void
    {
        $t = new \DateTimeImmutable('2026-11-20T20:00:00Z');
        $token = Uuid::v4()->toRfc4122();
        $leg = $this->order($t, $token);
        self::assertSame(8, $leg->generation);
        self::assertSame(2.25, $leg->remainingHydrogen());
        self::assertFalse($leg->isCurrent(4, 2, 7, $token));
        self::assertFalse($leg->isCurrent(4, 3, 8, $token));
        self::assertFalse($leg->isCurrent(5, 2, 8, $token));
        self::assertNull($leg->arrive(4, 2, 8, Uuid::v4()->toRfc4122(), $t->modify('+110 seconds'), true, true));
        self::assertNull($leg->arrive(4, 2, 8, $token, $t->modify('+89 seconds'), true, true));
        $now = $t->modify('+101 seconds');
        $done = $leg->arrive(4, 2, 8, $token, $now, true, true);
        self::assertSame(PirateSurvivorRecoveryLeg::DELIVERED, $done->state);
        self::assertSame($now, $done->observedAt);
        self::assertSame(2.25, $done->remainingHydrogen());
        self::assertNull($done->arrive(4, 2, 8, $token, $now, true, true));
    }

    public function testLostDestinationOrImpossibleInstallDoesNotPretendToDeliver(): void
    {
        $t = new \DateTimeImmutable('2026-11-20T20:00:00Z');
        $token = Uuid::v4()->toRfc4122();
        $leg = $this->order($t, $token);
        $at = $t->modify('+90 seconds');
        $lost = $leg->arrive(4, 2, 8, $token, $at, false, true);
        self::assertSame(PirateSurvivorRecoveryLeg::DESTINATION_LOST, $lost->state);
        self::assertSame([5, 3, 4, 8, 9], $lost->destination, 'Dernier point effectivement atteint, pas retour au site.');
        self::assertSame(2.25, $lost->remainingHydrogen());
        self::assertFalse($lost->isCurrent(4, 2, 8, $token));
        $blocked = $leg->arrive(4, 2, 8, $token, $at, true, false);
        self::assertSame(PirateSurvivorRecoveryLeg::CAPACITY_BLOCKED, $blocked->state);
        self::assertSame(2.25, $blocked->remainingHydrogen());
    }

    public function testTinyFuelDeficitCannotBeRoundedIntoAnAuthorizedDeparture(): void
    {
        $t = new \DateTimeImmutable('2026-11-20T20:00:00Z');
        $this->expectException(\DomainException::class);
        $this->order($t, Uuid::v4()->toRfc4122(), ['hydrogen' => 5.000000001]);
    }

    public function testQuoteCannotReuseOldRevisionOrShortenDuration(): void
    {
        $t = new \DateTimeImmutable('2026-11-20T20:00:00Z');
        $token = Uuid::v4()->toRfc4122();
        foreach ([['carrier_revision' => 6], ['arrival_at' => $t->modify('+3 seconds')], ['target_id' => 2]] as $change) {
            try {
                $this->order($t, $token, $change);
                self::fail('Devis incompatible avec la preuve.');
            } catch (\DomainException $error) {
                self::assertStringContainsString('preuve ou le devis', $error->getMessage());
            }
        }
    }

    public function testSubsecondInputCannotShortenTheElapsedLeg(): void
    {
        $t = new \DateTimeImmutable('2026-11-20T20:00:00.800000Z');
        $this->expectException(\DomainException::class);
        $this->order($t, Uuid::v4()->toRfc4122(), ['arrival_at' => new \DateTimeImmutable('2026-11-20T20:01:30Z')]);
    }

    private function order(\DateTimeImmutable $t, string $token, array $override = []): PirateSurvivorRecoveryLeg
    {
        $proof = new PirateBlockedReturnEvidence(
            4, 2, 2, 6, 7, $t->modify('-20 seconds'), [5, 3, 4, 8, null], 5.0, PirateBlockedReturnEvidence::HELD_BEFORE_RETURN,
        );
        $quote = $override + [
            'target_id' => 22, 'carrier_revision' => 7, 'seconds' => 90,
            'hydrogen' => 2.75, 'arrival_at' => $t->modify('+90 seconds'),
        ];

        return PirateSurvivorRecoveryLeg::depart($proof, $quote, [5, 3, 4, 8, 9], $token, $t);
    }
}
