<?php

namespace App\Service\Player;

/** Port non raccordé : seules les fixtures l'implémentent dans ce lot. */
interface VerificationRecoveryTransport
{
    /** Le transport doit préserver la confidentialité de l'argument, y compris dans ses traces. */
    public function handoff(int $playerId, #[\SensitiveParameter] string $link): VerificationRecoveryOutcome;
}
