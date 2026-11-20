<?php

namespace App\Service\Admin;

/** Aucun mailer raccordé tant que le parcours public n'est pas coordonné. */
final class UnavailableAdminActivationTransport implements AdminActivationTransport
{
    public function deliver(int $playerId): string
    {
        return 'not_dispatched';
    }
}
