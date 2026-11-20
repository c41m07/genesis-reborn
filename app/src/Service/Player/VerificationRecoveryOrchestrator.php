<?php

namespace App\Service\Player;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/** Réserve seulement. Un appelant autorisé restera responsable de la remise. */
final class VerificationRecoveryOrchestrator
{
    public function __construct(
        private Connection $db,
        private ClockInterface $clock,
        private VersionedVerificationLink $links,
    ) {
    }

    /** Donnée de préparation seulement ; jamais rendue dans le formulaire. */
    public function candidateForForm(int $playerId): ?int
    {
        $candidate = $this->db->fetchAssociative(
            'SELECT r.id, r.state, r.resolved_at FROM verification_delivery_request r
             INNER JOIN player p ON p.id = r.player_id
             WHERE r.player_id = ? AND p.email_verified_at IS NULL AND p.locked_at IS NULL
             ORDER BY r.id DESC LIMIT 1',
            [$playerId],
        );
        return $candidate !== false && $candidate['state'] === 'unknown' && $candidate['resolved_at'] !== null
            ? (int) $candidate['id']
            : null;
    }

    /**
     * L'identité du joueur doit provenir d'un parcours autorisé, jamais d'un
     * identifiant saisi librement. La possession du reçu ne fait pas autorité.
     *
     * @return array{result: string, request_id: ?int}
     */
    public function reserve(
        int $playerId,
        int $originalId,
        #[\SensitiveParameter] string $requestKey,
        int $delaySeconds,
        VerificationLinkKeyResolver $keys,
    ): array {
        return $this->reserveLocked($playerId, $originalId, $requestKey, $delaySeconds, $keys);
    }

    /**
     * Le reçu attendu vient du contexte serveur, pas d'un champ de formulaire.
     * Une intention périmée ne doit pas s'appliquer au nouveau reçu du compte.
     * @return array{result: string, request_id: ?int}
     */
    public function reserveForAdmin(
        int $adminId,
        int $playerId,
        int $expectedOriginalId,
        #[\SensitiveParameter] string $requestKey,
        int $delaySeconds,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
    ): array {
        return $this->reserveLocked($playerId, $expectedOriginalId, $requestKey, $delaySeconds, $keys, $adminId);
    }

    private function reserveLocked(
        int $playerId,
        int $originalId,
        #[\SensitiveParameter] string $requestKey,
        int $delaySeconds,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
        ?int $adminId = null,
    ): array {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $requestKey) || $delaySeconds < 1 || $delaySeconds > 86400) {
            throw new \InvalidArgumentException('Paramètres de récupération invalides.');
        }
        if ($this->db->isTransactionActive()) {
            throw new \LogicException('La récupération exige sa propre transaction.');
        }

        $hash = hash('sha256', $requestKey);
        return $this->db->transactional(function () use ($playerId, $originalId, $hash, $delaySeconds, $keys, $adminId): array {
            if ($adminId !== null) {
                (new VerificationAdminActorGuard())->lockAndCheck($this->db, $adminId, $playerId);
            }
            $player = $this->db->fetchAssociative('SELECT email, email_verified_at, locked_at FROM player WHERE id = ? FOR UPDATE', [$playerId]);
            if ($player === false || $player['email_verified_at'] !== null || $player['locked_at'] !== null) {
                return $this->result('ineligible');
            }
            // Verrou compte d'abord, puis le reçu du même compte. La requête
            // ne découvre pas une intention de récupération d'un autre joueur.
            $initial = $this->db->fetchAssociative('SELECT state, resolved_at, cooldown_until FROM verification_delivery_request WHERE id = ? AND player_id = ? FOR UPDATE', [$originalId, $playerId]);
            if ($initial === false || $initial['state'] !== 'unknown' || $initial['resolved_at'] === null) {
                return $this->result('ineligible');
            }
            $journal = $this->db->fetchAssociative('SELECT request_key_hash, recovery_request_id FROM verification_recovery_event WHERE original_request_id = ? FOR UPDATE', [$originalId]);
            if ($journal !== false) {
                if ($journal['recovery_request_id'] !== null && hash_equals($journal['request_key_hash'], $hash)) {
                    return $this->result('replayed', (int) $journal['recovery_request_id']);
                }
                // Un ancien journal isolé ne prouve pas qu'un reçu est sûr.
                return $this->result('blocked');
            }

            $last = $this->db->fetchOne('SELECT id FROM verification_delivery_request WHERE player_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$playerId]);
            if ((int) $last !== $originalId || $this->db->fetchOne('SELECT id FROM verification_delivery_request WHERE player_id = ? AND request_key_hash = ? FOR UPDATE', [$playerId, $hash]) !== false) {
                return $this->result('blocked');
            }

            $now = $this->clock->now();
            $zone = $now->getTimezone();
            $resolved = new \DateTimeImmutable($initial['resolved_at'], $zone);
            $cooldown = new \DateTimeImmutable($initial['cooldown_until'], $zone);
            if ($now < $cooldown || $now < $resolved->modify('+'.$delaySeconds.' seconds')) {
                return $this->result('too_early');
            }
            $date = $now->format('Y-m-d H:i:s');
            if ($this->db->fetchOne('SELECT id FROM player_email_verification_token WHERE player_id = ? AND expires_at > ? LIMIT 1 FOR UPDATE', [$playerId, $date]) !== false) {
                // Le lien historique ne peut pas être reproduit à partir de son hash.
                return $this->result('legacy_active');
            }
            $row = $this->db->fetchAssociative('SELECT * FROM player_verification_generation WHERE player_id = ? FOR UPDATE', [$playerId]);
            if ($row === false || $row['state'] !== 'active' || (int) $row['expires_at'] <= $now->getTimestamp()
                || !VerificationRecipientBinding::matches($row['recipient_fingerprint'], $player['email'], $row['fingerprint'])) {
                return $this->result('generation_unavailable');
            }
            $generation = new VerificationLinkGeneration(
                $playerId, (int) $row['version'], (int) $row['algorithm'], $row['key_id'],
                $row['nonce'], (int) $row['expires_at'], $row['fingerprint'],
            );
            if ($this->links->reproduce($generation, $keys) === null) {
                return $this->result('generation_unavailable');
            }

            // Le journal, le nouveau reçu et leur relation sont commis
            // ensemble. Le résultat du reçu d'origine n'est jamais mis à jour.
            $this->db->insert('verification_recovery_event', [
                'original_request_id' => $originalId,
                'request_key_hash' => $hash,
                'recorded_at' => $now->getTimestamp(),
                'generation_version' => $generation->version,
            ]);
            $this->db->insert('verification_delivery_request', [
                'player_id' => $playerId,
                'request_key_hash' => $hash,
                'state' => 'recovery_reserved',
                'requested_at' => $date,
                'cooldown_until' => $now->modify('+'.$delaySeconds.' seconds')->format('Y-m-d H:i:s'),
            ]);
            $id = (int) $this->db->lastInsertId();
            $this->db->update('verification_recovery_event', ['recovery_request_id' => $id], ['original_request_id' => $originalId]);
            if ($adminId !== null) {
                $this->db->insert('admin_audit_log', [
                    'actor_id' => $adminId,
                    'action' => 'admin_activation_recovery_requested',
                    'target_type' => 'player',
                    'target_id' => $playerId,
                    'created_at' => $date,
                    'details' => json_encode(['request_id' => $id, 'reason' => 'activation_support'], JSON_THROW_ON_ERROR),
                ]);
            }

            return $this->result('reserved', $id);
        });
    }

    private function result(string $result, ?int $id = null): array
    {
        return ['result' => $result, 'request_id' => $id];
    }
}
