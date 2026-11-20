<?php

namespace App\Service\Admin;

use App\Entity\Player;

/** Contrat du formulaire existant ; l'implémentation normale reste inchangée. */
interface AdminActivationAction
{
    public function isEnabled(): bool;

    public function request(Player $actor, int $playerId, #[\SensitiveParameter] string $key): string;
}
