<?php

namespace App\Service\Player;

use App\Entity\{Player, VerificationDeliveryRequest};
use App\Manager\GameManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Cycle interne sans transport : ne remplace pas les droits d'une route admin. */
final class VerificationRequestLifecycle implements VerificationRecoveryDeliveryBinding
{
    public function __construct(private EntityManagerInterface $em, private GameManager $manager, private ClockInterface $clock)
    {
    }

    /** @return array{result: string, claim_token?: string} */
    public function claim(int $requestId, int $leaseSeconds): array
    {
        if ($leaseSeconds < 1 || $leaseSeconds > 3600) {
            throw new \InvalidArgumentException('Durée de revendication invalide.');
        }
        return $this->locked($requestId, function (VerificationDeliveryRequest $request, Player $player) use ($leaseSeconds): array {
            $this->expireLease($request);
            if ($request->state !== 'reserved') {
                return ['result' => 'unavailable'];
            }
            if ($player->emailVerifiedAt !== null || $player->lockedAt !== null) {
                $request->state = 'cancelled';
                $request->resolvedAt = $this->clock->now();
                return ['result' => 'cancelled'];
            }
            return $this->startClaim($request, $this->clock->now()->modify('+'.$leaseSeconds.' seconds'));
        });
    }

    /** @return array{result: string, claim_token?: string, generation_version?: int} */
    public function claimRecovery(int $requestId, int $leaseSeconds): array
    {
        if ($leaseSeconds < 1 || $leaseSeconds > 3600) {
            throw new \InvalidArgumentException('Durée de revendication invalide.');
        }

        return $this->locked($requestId, function (VerificationDeliveryRequest $request, Player $player) use ($leaseSeconds): array {
            $this->expireLease($request);
            if ($request->state !== 'recovery_reserved') {
                return ['result' => 'unavailable'];
            }
            $now = $this->clock->now();
            if ($request->claimHash !== null || $request->leaseExpiresAt !== null || $request->resolvedAt !== null) {
                // Un reçu incohérent pourrait avoir été remis. Ne pas relancer
                // ni affirmer qu'aucun transport n'a été possible.
                $request->state = 'unknown';
                $request->resolvedAt = $now;
                return ['result' => 'unavailable'];
            }
            if ($player->emailVerifiedAt !== null || $player->lockedAt !== null) {
                return $this->cancelRecovery($request, $now);
            }

            $db = $this->em->getConnection();
            // Le compte est déjà verrouillé : toute décision sur ses enfants
            // est sérialisée avec le lecteur, la révocation et la réservation.
            $event = $db->fetchAssociative(
                'SELECT original_request_id, generation_version, request_key_hash FROM verification_recovery_event WHERE recovery_request_id = ? FOR UPDATE',
                [$request->id],
            );
            if ($event === false || $event['generation_version'] === null || !hash_equals($request->requestKeyHash, $event['request_key_hash'])) {
                return $this->cancelRecovery($request, $now);
            }
            $original = $db->fetchAssociative(
                'SELECT state, resolved_at FROM verification_delivery_request WHERE id = ? AND player_id = ? FOR UPDATE',
                [(int) $event['original_request_id'], $player->id],
            );
            if ($original === false || $original['state'] !== 'unknown' || $original['resolved_at'] === null) {
                return $this->cancelRecovery($request, $now);
            }

            $generation = $db->fetchAssociative(
                'SELECT version, algorithm, expires_at, state FROM player_verification_generation WHERE player_id = ? FOR UPDATE',
                [$player->id],
            );
            if ($generation === false || $generation['state'] !== 'active'
                || (int) $generation['algorithm'] !== VersionedVerificationLink::ALGORITHM_V1
                || (int) $generation['version'] !== (int) $event['generation_version']
                || (int) $generation['expires_at'] <= $now->getTimestamp()
                || $db->fetchOne(
                    'SELECT id FROM player_email_verification_token WHERE player_id = ? AND expires_at > ? LIMIT 1 FOR UPDATE',
                    [$player->id, $now->format('Y-m-d H:i:s')],
                ) !== false) {
                return $this->cancelRecovery($request, $now);
            }

            // Ne jamais laisser la possession vivre après la génération.
            $leaseEnd = $now->setTimestamp(min($now->getTimestamp() + $leaseSeconds, (int) $generation['expires_at']));
            return $this->startClaim($request, $leaseEnd, 'recovery_dispatching') + ['generation_version' => (int) $generation['version']];
        });
    }

    /**
     * Instantané privé de remise, après validation du claim et du lien
     * destinataire/génération. Une seule sortie autorisée avant transport.
     *
     * @return array{email: string, username: string, link: string}|null
     */
    public function prepareVersionedEmission(
        int $requestId,
        #[\SensitiveParameter] string $claimToken,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
    ): ?array {
        $result = $this->locked($requestId, function (VerificationDeliveryRequest $request, Player $player) use ($claimToken, $keys): array {
            if (!$this->ownsClaim($request, $claimToken) || $request->state !== 'dispatching'
                || $request->versionedGenerationVersion === null || $request->emissionHandoffAt !== null
                || $this->expireLease($request) || $player->lockedAt !== null || $player->emailVerifiedAt !== null) {
                return [];
            }
            $db = $this->em->getConnection();
            $now = $this->clock->now();
            $row = $db->fetchAssociative(
                'SELECT version, algorithm, key_id, nonce, expires_at, fingerprint, recipient_fingerprint, state FROM player_verification_generation WHERE player_id = ? FOR UPDATE',
                [$player->id],
            );
            if ($row === false || $row['state'] !== 'active' || (int) $row['version'] !== $request->versionedGenerationVersion
                || !VerificationRecipientBinding::matches($row['recipient_fingerprint'], $player->email, $row['fingerprint'])
                || $db->fetchOne('SELECT id FROM player_email_verification_token WHERE player_id = ? AND expires_at > ? LIMIT 1 FOR UPDATE',
                    [$player->id, $now->format('Y-m-d H:i:s')]) !== false) {
                return [];
            }
            $current = new VerificationLinkGeneration(
                $player->id, (int) $row['version'], (int) $row['algorithm'], $row['key_id'],
                $row['nonce'], (int) $row['expires_at'], $row['fingerprint'],
            );
            $link = (new VersionedVerificationLink($this->clock))->reproduce($current, $keys);
            if ($link === null || $this->expireLease($request)) {
                return [];
            }

            // Garder la limite la plus courte. Le transport est appelé après
            // commit ; un changement d'adresse ultérieur invalide ce lien.
            $request->leaseExpiresAt = $now->setTimestamp(min($current->expiresAt, $request->leaseExpiresAt->getTimestamp()));
            $request->emissionHandoffAt = $this->clock->now()->getTimestamp();
            return ['email' => $player->email, 'username' => $player->username, 'link' => $link];
        });

        return isset($result['email']) ? $result : null;
    }

    public function prepareRecoveryDelivery(
        int $requestId,
        #[\SensitiveParameter] string $claimToken,
        #[\SensitiveParameter] VerificationLinkKeyResolver $keys,
    ): array {
        return $this->locked($requestId, function (VerificationDeliveryRequest $request, Player $player) use ($claimToken, $keys): array {
            if (!$this->ownsClaim($request, $claimToken) || $request->state !== 'recovery_dispatching') {
                return ['result' => 'unavailable'];
            }
            if ($this->expireLease($request)) {
                return ['result' => 'unknown'];
            }
            $db = $this->em->getConnection();
            $event = $db->fetchAssociative(
                'SELECT original_request_id, generation_version, request_key_hash, handoff_at, delivery_outcome FROM verification_recovery_event WHERE recovery_request_id = ? FOR UPDATE',
                [$request->id],
            );
            if ($event === false || $event['handoff_at'] !== null || $event['delivery_outcome'] !== null) {
                // Un autre appel possédant la même clé peut déjà être en transport.
                return ['result' => 'unavailable'];
            }
            $now = $this->clock->now();
            $original = $db->fetchAssociative(
                'SELECT state, resolved_at FROM verification_delivery_request WHERE id = ? AND player_id = ? FOR UPDATE',
                [(int) $event['original_request_id'], $player->id],
            );
            $row = $db->fetchAssociative(
                'SELECT version, algorithm, key_id, nonce, expires_at, fingerprint, recipient_fingerprint, state FROM player_verification_generation WHERE player_id = ? FOR UPDATE',
                [$player->id],
            );
            if ($player->emailVerifiedAt !== null || $player->lockedAt !== null
                || !hash_equals($request->requestKeyHash, $event['request_key_hash'])
                || $original === false || $original['state'] !== 'unknown' || $original['resolved_at'] === null
                || $row === false || $row['state'] !== 'active' || (int) $row['version'] !== (int) $event['generation_version']
                || !VerificationRecipientBinding::matches($row['recipient_fingerprint'], $player->email, $row['fingerprint'])
                || $db->fetchOne('SELECT id FROM player_email_verification_token WHERE player_id = ? AND expires_at > ? LIMIT 1 FOR UPDATE',
                    [$player->id, $now->format('Y-m-d H:i:s')]) !== false) {
                return ['result' => $this->finishRecovery($request, VerificationRecoveryOutcome::NotDispatched, $now)];
            }
            $current = new VerificationLinkGeneration(
                $player->id, (int) $row['version'], (int) $row['algorithm'], $row['key_id'],
                $row['nonce'], (int) $row['expires_at'], $row['fingerprint'],
            );
            $link = (new VersionedVerificationLink($this->clock))->reproduce($current, $keys);
            // La résolution de clé peut avoir franchi l'échéance du bail.
            if ($this->expireLease($request)) {
                return ['result' => 'unknown'];
            }
            if ($link === null) {
                return ['result' => $this->finishRecovery($request, VerificationRecoveryOutcome::NotDispatched, $this->clock->now())];
            }

            // Point de remise durable, à usage unique. Le lien ne sort de la
            // transaction qu'après commit ; le port n'est appelé qu'ensuite.
            $db->update('verification_recovery_event', ['handoff_at' => $this->clock->now()->getTimestamp()], ['recovery_request_id' => $request->id]);
            return ['result' => 'handoff', 'player_id' => $player->id, 'link' => $link];
        });
    }

    public function completeRecoveryDelivery(
        int $requestId,
        #[\SensitiveParameter] string $claimToken,
        VerificationRecoveryOutcome $outcome,
    ): string {
        $result = $this->locked($requestId, function (VerificationDeliveryRequest $request) use ($claimToken, $outcome): array {
            if (!$this->ownsClaim($request, $claimToken) || $request->state !== 'recovery_dispatching') {
                return ['result' => 'unavailable'];
            }
            if ($this->expireLease($request)) {
                return ['result' => 'unknown'];
            }
            $event = $this->em->getConnection()->fetchAssociative(
                'SELECT handoff_at, delivery_outcome FROM verification_recovery_event WHERE recovery_request_id = ? FOR UPDATE',
                [$request->id],
            );
            if ($event === false || $event['handoff_at'] === null || $event['delivery_outcome'] !== null) {
                return ['result' => 'unavailable'];
            }

            return ['result' => $this->finishRecovery($request, $outcome, $this->clock->now())];
        });

        return $result['result'];
    }

    private function ownsClaim(VerificationDeliveryRequest $request, #[\SensitiveParameter] string $claimToken): bool
    {
        return preg_match('/\A[a-f0-9]{64}\z/', $claimToken) === 1
            && $request->claimHash !== null && hash_equals($request->claimHash, hash('sha256', $claimToken));
    }

    private function finishRecovery(VerificationDeliveryRequest $request, VerificationRecoveryOutcome $outcome, \DateTimeImmutable $now): string
    {
        $request->state = $outcome === VerificationRecoveryOutcome::NotDispatched ? 'cancelled' : $outcome->value;
        $request->resolvedAt = $now;
        $this->em->getConnection()->executeStatement(
            'UPDATE verification_recovery_event SET delivery_outcome = ?, delivery_resolved_at = ? WHERE recovery_request_id = ? AND delivery_outcome IS NULL',
            [$outcome->value, $now->getTimestamp(), $request->id],
        );

        return $outcome->value;
    }

    /** @return array{result: string, claim_token: string} */
    private function startClaim(VerificationDeliveryRequest $request, \DateTimeImmutable $leaseEnd, string $state = 'dispatching'): array
    {
        $token = bin2hex(random_bytes(32));
        $request->claimHash = hash('sha256', $token);
        $request->leaseExpiresAt = $leaseEnd;
        $request->state = $state;

        // Seul le propriétaire reçoit ce secret éphémère de possession.
        return ['result' => 'claimed', 'claim_token' => $token];
    }

    /** @return array{result: string} */
    private function cancelRecovery(VerificationDeliveryRequest $request, \DateTimeImmutable $now): array
    {
        // Avant toute possession, aucun transport n'a été possible dans ce circuit.
        $request->state = 'cancelled';
        $request->claimHash = null;
        $request->leaseExpiresAt = null;
        $request->resolvedAt = $now;

        return ['result' => 'cancelled'];
    }

    /** @return array{result: string} */
    public function complete(int $requestId, #[\SensitiveParameter] string $claimToken, string $outcome): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $claimToken) || !in_array($outcome, ['transport_accepted', 'cancelled', 'unknown'], true)) {
            throw new \InvalidArgumentException('Résultat de traitement invalide.');
        }
        return $this->locked($requestId, function (VerificationDeliveryRequest $request) use ($claimToken, $outcome): array {
            if ($request->state === 'recovery_dispatching' || !$this->ownsClaim($request, $claimToken)) {
                return ['result' => 'rejected'];
            }
            $this->expireLease($request);
            if ($request->state !== 'dispatching') {
                return ['result' => $request->state === $outcome ? 'replayed' : 'conflict'];
            }
            if ($request->versionedGenerationVersion !== null && $outcome === 'transport_accepted' && $request->emissionHandoffAt === null) {
                return ['result' => 'rejected'];
            }
            // Le demandeur devra attester l'acceptation effective du transport.
            // Ce service ne l'effectue pas et n'atteste jamais la réception.
            $request->state = $outcome;
            $request->resolvedAt = $this->clock->now();
            return ['result' => 'completed'];
        });
    }

    /** @return array{result: string} */
    public function expire(int $requestId): array
    {
        return $this->locked($requestId, function (VerificationDeliveryRequest $request): array {
            return ['result' => $this->expireLease($request) ? 'expired_unknown' : 'unchanged'];
        });
    }

    private function expireLease(VerificationDeliveryRequest $request): bool
    {
        if (!in_array($request->state, ['dispatching', 'recovery_dispatching'], true)
            || ($request->leaseExpiresAt !== null && $request->leaseExpiresAt > $this->clock->now())) {
            return false;
        }
        // Une lease expirée ne prouve pas l'absence d'envoi : jamais de remise
        // en file automatique. Un ancien worker ne peut plus publier un succès.
        if ($request->state === 'recovery_dispatching') {
            $this->finishRecovery($request, VerificationRecoveryOutcome::Unknown, $this->clock->now());
        } else {
            $request->state = 'unknown';
            $request->resolvedAt = $this->clock->now();
        }
        return true;
    }

    private function locked(int $requestId, callable $operation): array
    {
        if ($this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('Le cycle de reçu exige sa propre transaction.');
        }
        // Orientation hors transaction uniquement. Toutes les validations sont
        // répétées sous verrou, compte puis reçu, comme la réservation.
        $playerId = $this->em->getConnection()->fetchOne('SELECT player_id FROM verification_delivery_request WHERE id = ?', [$requestId]);
        if ($playerId === false) {
            return ['result' => 'missing'];
        }
        return $this->manager->transaction(function () use ($requestId, $playerId, $operation): array {
            $player = $this->em->find(Player::class, (int) $playerId, LockMode::PESSIMISTIC_WRITE);
            if (!$player instanceof Player) {
                return ['result' => 'missing'];
            }
            $this->em->refresh($player, LockMode::PESSIMISTIC_WRITE);
            $request = $this->em->find(VerificationDeliveryRequest::class, $requestId, LockMode::PESSIMISTIC_WRITE);
            if (!$request instanceof VerificationDeliveryRequest) {
                return ['result' => 'missing'];
            }
            $this->em->refresh($request, LockMode::PESSIMISTIC_WRITE);
            if ($request->player->id !== $player->id) {
                throw new \LogicException('Le compte du reçu a changé.');
            }
            return $operation($request, $player);
        });
    }
}
