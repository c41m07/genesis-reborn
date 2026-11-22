<?php

namespace App\Service\Player;

use Doctrine\DBAL\Connection;

/** Contrôle administratif partagé par les réservations d'activation. */
final class VerificationAdminActorGuard
{
    public function lockAndCheck(Connection $db, int $adminId, int $playerId): void
    {
        if (!$db->isTransactionActive()) {
            throw new \LogicException('Le contrôle de l’acteur exige la transaction de réservation.');
        }

        // Ordre stable, même lorsque deux administrateurs se ciblent
        // mutuellement. Ne jamais s'appuyer sur l'objet de session.
        $ids = array_unique([$adminId, $playerId]);
        sort($ids, SORT_NUMERIC);
        $actor = false;
        foreach ($ids as $id) {
            $row = $db->fetchAssociative('SELECT roles, email_verified_at, locked_at FROM player WHERE id = ? FOR UPDATE', [$id]);
            if ($id === $adminId) {
                $actor = $row;
            }
        }

        $roles = $actor === false ? null : json_decode($actor['roles'], true);
        if ($actor === false || !is_array($roles) || !in_array('ROLE_ADMIN', $roles, true)
            || $actor['locked_at'] !== null || $actor['email_verified_at'] === null) {
            throw new \DomainException('Action administrative non autorisée.');
        }
    }
}
