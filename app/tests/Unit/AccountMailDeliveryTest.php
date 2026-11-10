<?php
namespace App\Tests\Unit;

use App\Service\Player\AccountMailDelivery;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class AccountMailDeliveryTest extends TestCase
{
    public function testTransientFailureRecoversWithoutLoggingPrivateData(): void
    {
        $message = (new Email())->from('sender@example.test')->to('private@example.test')->text('secret-token');
        $mailer = $this->createMock(MailerInterface::class);
        $attempts = 0;
        $mailer->expects(self::exactly(2))->method('send')->with($message)->willReturnCallback(function () use (&$attempts): void {
            if (++$attempts === 1) throw new TransportException('smtp://user:password@private.example.test secret-token');
        });
        $contexts = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('account_mail.retrying', self::callback(function (array $context) use (&$contexts): bool {
            $contexts[] = $context;
            return !str_contains(json_encode($context), 'password') && !str_contains(json_encode($context), 'secret-token');
        }));
        $logger->expects(self::once())->method('info')->with('account_mail.transport_accepted', self::callback(function (array $context) use (&$contexts): bool {
            return $context['delivery_id'] === $contexts[0]['delivery_id'] && $context['attempt'] === 2;
        }));
        $logger->expects(self::never())->method('critical');
        self::assertTrue((new AccountMailDelivery($mailer, $logger))->send($message, 'verification'));
    }

    public function testPermanentFailureStopsAndRaisesSanitizedAlert(): void
    {
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(3))->method('send')->willThrowException(new TransportException('private diagnostic'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');
        $logger->expects(self::never())->method('info');
        $logger->expects(self::once())->method('critical')->with('account_mail.failed', self::callback(fn (array $context): bool =>
            $context['attempt'] === 3 && $context['kind'] === 'password_reset' && !str_contains(json_encode($context), 'private diagnostic')
        ));
        self::assertFalse((new AccountMailDelivery($mailer, $logger))->send(new Email(), 'password_reset'));
    }
}
