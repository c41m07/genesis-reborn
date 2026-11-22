<?php

namespace App\Service\Player;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Métadonnées d'une génération, sans lien ni clé. Ce n'est pas une entité Doctrine. */
#[Exclude]
final readonly class VerificationLinkGeneration
{
    public function __construct(
        public int $playerId,
        public int $version,
        public int $algorithm,
        public string $keyId,
        public string $nonce,
        public int $expiresAt,
        public string $fingerprint,
    ) {
        if ($playerId < 1 || $version < 1 || $algorithm < 1 || $expiresAt < 1
            || !preg_match('/\A[A-Za-z0-9_-]{1,32}\z/', $keyId)
            || !preg_match('/\A[a-f0-9]{64}\z/', $nonce)
            || !preg_match('/\A[a-f0-9]{64}\z/', $fingerprint)) {
            throw new \InvalidArgumentException('Métadonnées de confirmation invalides.');
        }
    }
}
