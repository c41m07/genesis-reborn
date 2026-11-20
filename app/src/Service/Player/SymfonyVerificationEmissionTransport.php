<?php

namespace App\Service\Player;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\{EsmtpTransportFactory, SmtpTransport};
use Symfony\Component\Mailer\Transport\TransportInterface;
use Symfony\Component\Mime\Email;

/** Remise directe et volontaire, jamais résolue implicitement par le conteneur. */
#[Exclude]
final class SymfonyVerificationEmissionTransport implements VerificationEmissionTransport, VerificationRecoveryTransport
{
    /**
     * Entrée d'injection pour les doubles. Un transport opérationnel doit
     * provenir de forSingleSmtp(), sans dispatcher ou logger du conteneur.
     */
    public function __construct(
        #[\SensitiveParameter] private TransportInterface $transport,
        private VerificationEmailMessageFactory $messages,
    ) {
    }

    public static function forSingleSmtp(
        #[\SensitiveParameter] Dsn $dsn,
        VerificationEmailMessageFactory $messages,
    ): self {
        $factory = new EsmtpTransportFactory();
        if (!$factory->supports($dsn)
            || !$dsn->getBooleanOption('verify_peer', true)
            || ($dsn->getScheme() === 'smtp' && (!$dsn->getBooleanOption('require_tls') || !$dsn->getBooleanOption('auto_tls', true)))) {
            throw new \DomainException('Transport direct TLS non disponible.');
        }

        // La fabrique native construit un seul transport sans bus, listener
        // ni journal. Elle ne contacte aucun hôte à la construction.
        try {
            return new self($factory->create($dsn), $messages);
        } catch (\Throwable) {
            throw new \DomainException('Transport direct TLS non disponible.');
        }
    }

    public function confirmation(#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $link, string $username): VerificationRecoveryOutcome
    {
        // NullTransport retourne un SentMessage synthétique, mais jette le mail.
        // Il est donc explicitement exclu, comme les transports composites.
        if (!$this->transport instanceof SmtpTransport || !preg_match('/\A[A-Za-z0-9_-]{43}\z/', $link)) {
            return VerificationRecoveryOutcome::NotDispatched;
        }
        try {
            $message = $this->messages->confirmation($address, $link, $username);
        } catch (\Throwable) {
            // Avant l'appel au transport, aucune prise en charge n'est possible.
            return VerificationRecoveryOutcome::NotDispatched;
        }

        return $this->sendOnce($message);
    }

    public function handoff(#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $link, string $username): VerificationRecoveryOutcome
    {
        return $this->confirmation($address, $link, $username);
    }

    public function welcome(#[\SensitiveParameter] string $address, string $username): void
    {
        if (!$this->transport instanceof SmtpTransport) {
            return;
        }
        try {
            $message = $this->messages->welcome($address, $username);
        } catch (\Throwable) {
            return;
        }
        // Message indépendant. Aucune déclaration de réception ou relance.
        $this->sendOnce($message);
    }

    private function sendOnce(#[\SensitiveParameter] Email $message): VerificationRecoveryOutcome
    {
        try {
            // Le contrat Symfony est SentMessage|null. Le résultat positif
            // signifie une prise en charge, jamais une arrivée en boîte mail.
            return $this->transport->send($message) === null
                ? VerificationRecoveryOutcome::Unknown
                : VerificationRecoveryOutcome::TransportAccepted;
        } catch (\Throwable) {
            // Une exception peut suivre DATA. Ni détails, ni second envoi.
            return VerificationRecoveryOutcome::Unknown;
        }
    }
}
