<?php
namespace App\Service\Player;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class AccountMailDelivery
{
    public function __construct(private MailerInterface $mailer, private LoggerInterface $logger) {}

    public function send(Email $message, string $kind): bool
    {
        $deliveryId = bin2hex(random_bytes(12));
        for ($attempt = 1; $attempt <= 3; ++$attempt) {
            $context = ['delivery_id' => $deliveryId, 'kind' => $kind, 'attempt' => $attempt];
            try {
                $this->mailer->send($message);
                $this->logger->info('account_mail.transport_accepted', $context);
                return true;
            } catch (TransportExceptionInterface $exception) {
                $context['exception_class'] = $exception::class;
                if ($attempt === 3) {
                    $this->logger->critical('account_mail.failed', $context);
                    return false;
                }
                $this->logger->warning('account_mail.retrying', $context);
                usleep($attempt * 200000);
            }
        }
        return false;
    }
}
