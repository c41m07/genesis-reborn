<?php

namespace App\Service\Player;

enum VerificationRecoveryOutcome: string
{
    // Accepté par le transport, sans preuve de réception du destinataire.
    case TransportAccepted = 'transport_accepted';
    // Seulement avec preuve positive que le transport ne l'a pas pris en charge.
    case NotDispatched = 'not_dispatched';
    case Unknown = 'unknown';
}
