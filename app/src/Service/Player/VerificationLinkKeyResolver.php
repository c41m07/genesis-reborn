<?php

namespace App\Service\Player;

/** Fournit au composant la clé binaire désignée, ou null si elle est indisponible. */
interface VerificationLinkKeyResolver
{
    public function resolve(string $keyId): ?string;
}
