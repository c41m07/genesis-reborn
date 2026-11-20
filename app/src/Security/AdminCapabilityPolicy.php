<?php

namespace App\Security;

use App\Entity\Player;

final class AdminCapabilityPolicy
{
    public function allows(?Player $actor, string $capability): bool
    {
        if ($actor === null || !in_array($capability, AdminCapability::all(), true)) {
            return false;
        }

        // Compatibilité stricte : aucun nouveau rôle ni préférence ne donne de droits.
        // L'authentification et la confirmation d'adresse restent contrôlées par le firewall.
        return $actor->lockedAt === null && in_array('ROLE_ADMIN', $actor->getRoles(), true);
    }
}
