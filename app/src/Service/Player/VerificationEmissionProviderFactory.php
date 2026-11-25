<?php

declare(strict_types=1);

namespace App\Service\Player;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Transport\Dsn;

final class VerificationEmissionProviderFactory
{
    public function __construct(
        #[Autowire('%env(string:EMAIL_VERIFICATION_ACTIVE_KEY_ID)%')] private readonly string $activeKeyId,
        #[Autowire('%env(string:EMAIL_VERIFICATION_KEYS_JSON)%')] #[\SensitiveParameter] private readonly string $keyringJson,
        #[Autowire('%env(string:EMAIL_VERIFICATION_SMTP_DSN)%')] #[\SensitiveParameter] private readonly string $smtpDsn,
        private readonly VerificationEmailMessageFactory $messages,
    ) {
    }

    public function create(): VerificationEmissionProvider
    {
        if (!self::validKeyId($this->activeKeyId)) {
            throw new \DomainException('Identifiant actif manquant ou invalide (EMAIL_VERIFICATION_ACTIVE_KEY_ID).');
        }
        $keys = $this->keyring();
        if ($keys->resolve($this->activeKeyId) === null) {
            throw new \DomainException('La clé active est absente du trousseau (EMAIL_VERIFICATION_KEYS_JSON).');
        }
        if (!preg_match('/\A(?:smtp|smtps):\/\/[^\x00-\x20\x7f]+\z/', $this->smtpDsn)) {
            throw new \DomainException('Renseignez un DSN SMTP direct dédié (EMAIL_VERIFICATION_SMTP_DSN).');
        }
        try {
            $transport = SymfonyVerificationEmissionTransport::forSingleSmtp(Dsn::fromString($this->smtpDsn), $this->messages);
        } catch (\Throwable) {
            // Ni le DSN ni la cause originale du fournisseur ne doivent suivre l’erreur.
            throw new \DomainException('Le transport dédié doit être SMTP direct avec TLS et certificat vérifié (EMAIL_VERIFICATION_SMTP_DSN).');
        }

        return new VerificationEmissionProvider($this->activeKeyId, $keys, $transport);
    }

    private function keyring(): VerificationLinkKeyResolver
    {
        try {
            $keyring = json_decode($this->keyringJson, false, 3, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \DomainException('Trousseau JSON manquant ou invalide (EMAIL_VERIFICATION_KEYS_JSON).');
        }
        if (!$keyring instanceof \stdClass) {
            throw new \DomainException('Le trousseau doit être un objet JSON (EMAIL_VERIFICATION_KEYS_JSON).');
        }
        $keys = [];
        foreach (get_object_vars($keyring) as $id => $encoded) {
            $key = is_string($encoded) ? base64_decode($encoded, true) : false;
            if (!self::validKeyId((string) $id) || $key === false || strlen($key) !== 32 || base64_encode($key) !== $encoded) {
                throw new \DomainException('Le trousseau exige des identifiants valides et des clés dédiées de 32 octets encodées en base64 (EMAIL_VERIFICATION_KEYS_JSON).');
            }
            $keys[$id] = $key;
        }

        // Laisser le magasin reproduire une génération active avec son identifiant historique.
        return new class($keys) implements VerificationLinkKeyResolver {
            /** @param array<int|string, string> $keys */
            public function __construct(#[\SensitiveParameter] private readonly array $keys)
            {
            }

            public function resolve(string $keyId): ?string
            {
                return $this->keys[$keyId] ?? null;
            }
        };
    }

    private static function validKeyId(string $id): bool
    {
        return preg_match('/\A[A-Za-z0-9_-]{1,32}\z/', $id) === 1;
    }
}
