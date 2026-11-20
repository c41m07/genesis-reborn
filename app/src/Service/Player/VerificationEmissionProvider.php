<?php

namespace App\Service\Player;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Couple explicite et indivisible, non découvert par le conteneur. */
#[Exclude]
final readonly class VerificationEmissionProvider
{
    public function __construct(
        public string $keyId,
        #[\SensitiveParameter] public VerificationLinkKeyResolver $keys,
        public VerificationEmissionTransport $transport,
    ) {
    }
}
