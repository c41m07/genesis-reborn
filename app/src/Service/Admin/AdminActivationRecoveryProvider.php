<?php

namespace App\Service\Admin;

use App\Service\Player\{VerificationLinkKeyResolver, VerificationRecoveryTransport};
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Le conteneur ne doit jamais découvrir ou autoriser ce couple implicitement. */
#[Exclude]
final readonly class AdminActivationRecoveryProvider
{
    public function __construct(
        #[\SensitiveParameter] public VerificationLinkKeyResolver $keys,
        public VerificationRecoveryTransport $transport,
    ) {
    }
}
