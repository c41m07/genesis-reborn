<?php

namespace App\Service\Player;

use Psr\Clock\ClockInterface;

/**
 * Opérations cryptographiques sans écriture, transport ni droit d'accès.
 * L'appelant devra fournir exclusivement la génération active sous verrou.
 */
final class VersionedVerificationLink
{
    public const ALGORITHM_V1 = 1;

    public function __construct(private ClockInterface $clock)
    {
    }

    public function generate(
        int $playerId,
        int $version,
        string $keyId,
        \DateTimeImmutable $expiresAt,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
    ): VerificationLinkGeneration {
        $expiry = $expiresAt->getTimestamp();
        if ($playerId < 1 || $version < 1 || !preg_match('/\A[A-Za-z0-9_-]{1,32}\z/', $keyId)
            || $expiry <= $this->clock->now()->getTimestamp()) {
            throw new \InvalidArgumentException('Paramètres de génération invalides.');
        }
        $key = $keys->resolve($keyId);
        if ($key === null || strlen($key) < 32) {
            throw new \DomainException('Clé de confirmation indisponible.');
        }
        $nonce = bin2hex(random_bytes(32));
        $link = $this->deriveV1($playerId, $version, $keyId, $nonce, $expiry, $key);

        return new VerificationLinkGeneration(
            $playerId, $version, self::ALGORITHM_V1, $keyId, $nonce, $expiry, hash('sha256', $link),
        );
    }

    /** Ne crée jamais de génération de secours et ne prolonge pas le lien. */
    public function reproduce(VerificationLinkGeneration $current, #[\SensitiveParameter] VerificationLinkKeyResolver $keys): ?string
    {
        if (!$this->isCurrentFormatAndUnexpired($current)) {
            return null;
        }
        $key = $keys->resolve($current->keyId);
        if ($key === null || strlen($key) < 32) {
            return null;
        }
        $link = $this->deriveV1($current->playerId, $current->version, $current->keyId, $current->nonce, $current->expiresAt, $key);

        // Un trousseau incohérent ne doit pas produire un lien inutilisable.
        return hash_equals($current->fingerprint, hash('sha256', $link)) ? $link : null;
    }

    public function matches(VerificationLinkGeneration $current, #[\SensitiveParameter] string $link): bool
    {
        return $this->isCurrentFormatAndUnexpired($current)
            && preg_match('/\A[A-Za-z0-9_-]{43}\z/', $link) === 1
            && hash_equals($current->fingerprint, hash('sha256', $link));
    }

    private function isCurrentFormatAndUnexpired(VerificationLinkGeneration $current): bool
    {
        return $current->algorithm === self::ALGORITHM_V1 && $current->expiresAt > $this->clock->now()->getTimestamp();
    }

    private function deriveV1(
        int $playerId,
        int $version,
        string $keyId,
        string $nonce,
        int $expiry,
        #[\SensitiveParameter] string $key,
    ): string {
        // Encodage canonique : seuls des entiers positifs et des champs sans
        // séparateur entrent dans le message. Le format V1 doit rester stable.
        $payload = implode('|', ['genesis-reborn:email-verification:1', $playerId, $version, $keyId, $nonce, $expiry]);
        $mac = hash_hmac('sha256', $payload, $key, true);

        // Identique en longueur et alphabet au lien historique de 32 octets.
        return rtrim(strtr(base64_encode($mac), '+/', '-_'), '=');
    }
}
