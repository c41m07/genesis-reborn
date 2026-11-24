<?php

declare(strict_types=1);

namespace App\Service\Player;

use Doctrine\DBAL\Connection;

/** Lecture réservée à la chaîne de reprise qui peut réellement remettre un lien. */
final class VerificationRecoveryOrigin
{
    /** @return array{version: int, resolved_at: string, cooldown_until: string}|null */
    public static function lock(Connection $db, int $requestId, int $playerId): ?array
    {
        $receipt = $db->fetchAssociative(
            'SELECT state, resolved_at, cooldown_until, versioned_generation_version
             FROM verification_delivery_request WHERE id = ? AND player_id = ? FOR UPDATE',
            [$requestId, $playerId],
        );
        if ($receipt === false || $receipt['state'] !== 'unknown' || $receipt['resolved_at'] === null
            || (int) $receipt['versioned_generation_version'] < 1) {
            return null;
        }

        // Le compte est déjà verrouillé par l’appelant. Un reçu issu d’une
        // reprise n’est jamais une nouvelle origine, même si son issue est inconnue.
        if ($db->fetchOne('SELECT original_request_id FROM verification_recovery_event WHERE recovery_request_id = ? FOR UPDATE', [$requestId]) !== false) {
            return null;
        }

        return [
            'version' => (int) $receipt['versioned_generation_version'],
            'resolved_at' => $receipt['resolved_at'],
            'cooldown_until' => $receipt['cooldown_until'],
        ];
    }
}
