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

        // Première lecture cohérente après le verrou du compte : toutes les
        // écritures de filiation du même compte sont déjà sérialisées. Ne pas
        // verrouiller un intervalle d’identifiants absent, partagé entre comptes.
        if ($db->fetchOne('SELECT original_request_id FROM verification_recovery_event WHERE recovery_request_id = ?', [$requestId]) !== false) {
            return null;
        }

        return [
            'version' => (int) $receipt['versioned_generation_version'],
            'resolved_at' => $receipt['resolved_at'],
            'cooldown_until' => $receipt['cooldown_until'],
        ];
    }
}
