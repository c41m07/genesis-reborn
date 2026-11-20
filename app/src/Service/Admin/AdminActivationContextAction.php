<?php

namespace App\Service\Admin;

use App\Entity\Player;

/** Capacité facultative : aucun fournisseur du vrai conteneur ne l'implémente. */
interface AdminActivationContextAction extends AdminActivationAction
{
    public function prepareFormKey(Player $actor, int $playerId): ?string;
}
