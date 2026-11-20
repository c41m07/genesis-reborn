<?php

namespace App\Service\Admin;

interface AdminActivationTransport
{
    /** Retourne transport_accepted, unknown ou not_dispatched. Aucun reçu utilisateur implicite. */
    public function deliver(int $playerId): string;
}
