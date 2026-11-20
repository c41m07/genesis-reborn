<?php

namespace App\Service\Player;

use App\Entity\{Player, VerificationDeliveryRequest};
use App\Manager\GameManager;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

/** Cycle interne sans transport : ne remplace pas les droits d'une route admin. */
final class VerificationRequestLifecycle
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
            return $this->startClaim($request, $leaseEnd) + ['generation_version' => (int) $generation['version']];
        });
    }

    /** @return array{result: string, claim_token: string} */
    private function startClaim(VerificationDeliveryRequest $request, \DateTimeImmutable $leaseEnd): array
    {
        $token = bin2hex(random_bytes(32));
        $request->claimHash = hash('sha256', $token);
        $request->leaseExpiresAt = $leaseEnd;
        $request->state = 'dispatching';

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
            if ($request->claimHash === null || !hash_equals($request->claimHash, hash('sha256', $claimToken))) {
                return ['result' => 'rejected'];
            }
            $this->expireLease($request);
            if ($request->state !== 'dispatching') {
                return ['result' => $request->state === $outcome ? 'replayed' : 'conflict'];
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
        if ($request->state !== 'dispatching' || ($request->leaseExpiresAt !== null && $request->leaseExpiresAt > $this->clock->now())) {
            return false;
        }
        // Une lease expirée ne prouve pas l'absence d'envoi : jamais de remise
        // en file automatique. Un ancien worker ne peut plus publier un succès.
        $request->state = 'unknown';
        $request->resolvedAt = $this->clock->now();
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
