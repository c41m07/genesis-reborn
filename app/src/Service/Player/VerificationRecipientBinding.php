<?php

namespace App\Service\Player;

/** Empreinte du destinataire stocké, jamais de copie supplémentaire de l'adresse. */
final class VerificationRecipientBinding
{
    public static function fingerprint(#[\SensitiveParameter] string $address, string $linkFingerprint): string
    {
        return hash('sha256', $linkFingerprint.'|'.$address);
    }

    public static function matches(?string $fingerprint, #[\SensitiveParameter] string $address, string $linkFingerprint): bool
    {
        return $fingerprint !== null && hash_equals($fingerprint, self::fingerprint($address, $linkFingerprint));
    }
}
