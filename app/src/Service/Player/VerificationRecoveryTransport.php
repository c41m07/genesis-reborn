<?php

namespace App\Service\Player;

/** Port non raccordé : seules les fixtures l'implémentent dans ce lot. */
interface VerificationRecoveryTransport
{
    /** Instantané serveur déjà contrôlé ; ne pas rechercher un destinataire ni journaliser ses données. */
    public function handoff(#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $link, string $username): VerificationRecoveryOutcome;
}
