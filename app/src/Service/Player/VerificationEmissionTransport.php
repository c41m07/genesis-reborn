<?php

namespace App\Service\Player;

/** Port non raccordé ; aucune implémentation raccordée au conteneur. */
interface VerificationEmissionTransport
{
    public function confirmation(#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $link, string $username): VerificationRecoveryOutcome;

    /** Message distinct qui ne contient pas de lien de confirmation. */
    public function welcome(#[\SensitiveParameter] string $address, string $username): void;
}
