<?php

namespace App\Tests\Unit;

use App\Service\Player\{VerificationLinkKeyResolver, VerificationRecoveryDeliveryAdapter, VerificationRecoveryDeliveryBinding, VerificationRecoveryOutcome, VerificationRecoveryTransport};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class VerificationRecoveryDeliveryAdapterTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testAdapterHandsOffOnceAndClosesWithoutExposingLink(?VerificationRecoveryOutcome $transportOutcome, VerificationRecoveryOutcome $expected): void
    {
        $claim = str_repeat('a', 64);
        $link = str_repeat('l', 43);
        $keys = $this->createStub(VerificationLinkKeyResolver::class);
        $claims = $this->createMock(VerificationRecoveryDeliveryBinding::class);
        $claims->expects(self::once())->method('prepareRecoveryDelivery')->with(7, $claim, $keys)
            ->willReturn(['result' => 'handoff', 'email' => 'recovery-adapter@example.test', 'username' => 'RecoveryAdapter', 'link' => $link]);
        $transport = $this->createMock(VerificationRecoveryTransport::class);
        $expect = $transport->expects(self::once())->method('handoff')->with('recovery-adapter@example.test', $link, 'RecoveryAdapter');
        if ($transportOutcome === null) {
            $expect->willThrowException(new \RuntimeException('factice: '.$link));
        } else {
            $expect->willReturn($transportOutcome);
        }
        $claims->expects(self::once())->method('completeRecoveryDelivery')->with(7, $claim, $expected)
            ->willReturn($expected->value);

        self::assertSame($expected->value, (new VerificationRecoveryDeliveryAdapter())->deliver(7, $claim, $claims, $keys, $transport));
    }

    public static function outcomes(): iterable
    {
        yield 'accepté' => [VerificationRecoveryOutcome::TransportAccepted, VerificationRecoveryOutcome::TransportAccepted];
        yield 'non remis' => [VerificationRecoveryOutcome::NotDispatched, VerificationRecoveryOutcome::NotDispatched];
        yield 'indéterminé explicite' => [VerificationRecoveryOutcome::Unknown, VerificationRecoveryOutcome::Unknown];
        yield 'exception ambiguë' => [null, VerificationRecoveryOutcome::Unknown];
    }

    public function testRefusedPreparationDoesNotCallEitherTransportOrCompletion(): void
    {
        $claims = $this->createMock(VerificationRecoveryDeliveryBinding::class);
        $claims->expects(self::once())->method('prepareRecoveryDelivery')->willReturn(['result' => 'not_dispatched']);
        $claims->expects(self::never())->method('completeRecoveryDelivery');
        $transport = $this->createMock(VerificationRecoveryTransport::class);
        $transport->expects(self::never())->method('handoff');
        self::assertSame('not_dispatched', (new VerificationRecoveryDeliveryAdapter())->deliver(
            7, str_repeat('a', 64), $claims, $this->createStub(VerificationLinkKeyResolver::class), $transport,
        ));
    }
}
