<?php

namespace App\Service\Player;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/** Persistance interne ; aucun appelant HTTP ni transport dans ce lot. */
final class VerificationGenerationStore
{
    public function __construct(
        private Connection $db,
        private ClockInterface $clock,
        private VersionedVerificationLink $links,
    ) {
    }

    public function prepare(int $playerId, string $keyId, \DateTimeImmutable $expiry, VerificationLinkKeyResolver $keys): ?VerificationLinkGeneration
    {
        return $this->withPendingPlayer($playerId, function () use ($playerId, $keyId, $expiry, $keys): ?VerificationLinkGeneration {
            // L'ancien service ne sait pas partager sa génération. Ne pas
            // écraser une empreinte historique encore valide ni ajouter de lien concurrent.
            $now = $this->clock->now();
            $legacy = $this->db->fetchOne('SELECT id FROM player_email_verification_token WHERE player_id = ? AND expires_at > ? LIMIT 1 FOR UPDATE', [$playerId, $now->format('Y-m-d H:i:s')]);
            if ($legacy !== false) {
                return null;
            }
            $row = $this->current($playerId);
            if ($row !== false && $row['state'] === 'active' && (int) $row['expires_at'] > $now->getTimestamp()) {
                return $this->hydrate($playerId, $row);
            }
            if ($row !== false && !in_array($row['state'], ['active', 'revoked'], true)) {
                return null;
            }

            $generation = $this->links->generate($playerId, $row === false ? 1 : (int) $row['version'] + 1, $keyId, $expiry, $keys);
            $data = [
                'version' => $generation->version,
                'algorithm' => $generation->algorithm,
                'key_id' => $generation->keyId,
                'nonce' => $generation->nonce,
                'expires_at' => $generation->expiresAt,
                'fingerprint' => $generation->fingerprint,
                'state' => 'active',
            ];
            if ($row === false) {
                $this->db->insert('player_verification_generation', ['player_id' => $playerId] + $data);
            } else {
                $this->db->update('player_verification_generation', $data, ['player_id' => $playerId]);
            }

            return $generation;
        });
    }

    public function revoke(int $playerId, int $version): bool
    {
        return $this->withPendingPlayer($playerId, fn (): bool => $this->db->update(
            'player_verification_generation', ['state' => 'revoked'], ['player_id' => $playerId, 'version' => $version, 'state' => 'active'],
        ) === 1) ?? false;
    }

    public function consume(int $playerId, int $version, #[\SensitiveParameter] string $link): bool
    {
        return $this->withPendingPlayer($playerId, function () use ($playerId, $version, $link): bool {
            $row = $this->current($playerId);
            if ($row === false || $row['state'] !== 'active' || (int) $row['version'] !== $version
                || !$this->links->matches($this->hydrate($playerId, $row), $link)) {
                return false;
            }
            // La validation du compte et l'épuisement du lien sont atomiques.
            // Les empreintes historiques, même expirées, ne sont pas supprimées.
            $this->db->update('player_verification_generation', ['state' => 'consumed'], ['player_id' => $playerId]);
            $this->db->update('player', ['email_verified_at' => $this->clock->now()->format('Y-m-d H:i:s')], ['id' => $playerId]);
            return true;
        }) ?? false;
    }

    /** Journalise une intention ; ne réserve pas le transport et ne rejuge pas l'envoi initial. */
    public function recordRecovery(int $playerId, int $originalId, #[\SensitiveParameter] string $requestKey, int $delaySeconds): string
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/', $requestKey) || $delaySeconds < 1 || $delaySeconds > 86400) {
            throw new \InvalidArgumentException('Paramètres de récupération invalides.');
        }
        return $this->withPendingPlayer($playerId, function () use ($playerId, $originalId, $requestKey, $delaySeconds): string {
            $request = $this->db->fetchAssociative('SELECT id, state, resolved_at, cooldown_until FROM verification_delivery_request WHERE player_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE', [$playerId]);
            if ($request === false || (int) $request['id'] !== $originalId || $request['state'] !== 'unknown' || $request['resolved_at'] === null) {
                return 'ineligible';
            }

            $keyHash = hash('sha256', $requestKey);
            $existing = $this->db->fetchOne('SELECT request_key_hash FROM verification_recovery_event WHERE original_request_id = ? FOR UPDATE', [$originalId]);
            if ($existing !== false) {
                return hash_equals($existing, $keyHash) ? 'replayed' : 'blocked';
            }
            $now = $this->clock->now();
            $zone = $now->getTimezone();
            $resolved = new \DateTimeImmutable($request['resolved_at'], $zone);
            $cooldown = new \DateTimeImmutable($request['cooldown_until'], $zone);
            if ($now < $cooldown || $now < $resolved->modify('+'.$delaySeconds.' seconds')) {
                return 'too_early';
            }

            $this->db->insert('verification_recovery_event', [
                'original_request_id' => $originalId,
                'request_key_hash' => $keyHash,
                'recorded_at' => $now->getTimestamp(),
            ]);
            return 'recorded';
        }) ?? 'ineligible';
    }

    private function withPendingPlayer(int $playerId, callable $operation): mixed
    {
        if ($this->db->isTransactionActive()) {
            throw new \LogicException('La persistance de confirmation exige sa propre transaction.');
        }

        return $this->db->transactional(function () use ($playerId, $operation): mixed {
            // Le verrou compte est pris avant toute ligne enfant, même si
            // aucune génération n'existe encore. Ne rien préparer sur un cache ORM.
            $player = $this->db->fetchAssociative('SELECT email_verified_at, locked_at FROM player WHERE id = ? FOR UPDATE', [$playerId]);
            return $player !== false && $player['email_verified_at'] === null && $player['locked_at'] === null ? $operation() : null;
        });
    }

    private function current(int $playerId): array|false
    {
        return $this->db->fetchAssociative('SELECT * FROM player_verification_generation WHERE player_id = ? FOR UPDATE', [$playerId]);
    }

    private function hydrate(int $playerId, array $row): VerificationLinkGeneration
    {
        return new VerificationLinkGeneration(
            $playerId, (int) $row['version'], (int) $row['algorithm'], $row['key_id'],
            $row['nonce'], (int) $row['expires_at'], $row['fingerprint'],
        );
    }
}
