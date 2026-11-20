<?php

namespace App\Service\Player;

use App\Entity\{Player, VerificationDeliveryRequest};
use App\Manager\GameManager;
use App\Repository\VerificationDeliveryRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Psr\Clock\ClockInterface;

/** Réservation interne sans envoi ; acteur administratif revérifié sous verrou. */
final class VerificationRequestReservation
{
    public function __construct(
        private EntityManagerInterface $em,
        private GameManager $manager,
        private VerificationDeliveryRequestRepository $requests,
        private ClockInterface $clock,
    ) {
    }

    /** @return array{result: string, request_id: ?int, state: ?string} */
    public function reserve(int $playerId, string $requestKey, int $cooldownSeconds, ?int $adminId = null, ?int $generationVersion = null): array
    {
        // Le contrôleur fournit une clé aléatoire stable lors des doubles
        // soumissions ; ce n'est ni un jeton d'activation ni une autorisation.
        if (!preg_match('/^[a-f0-9]{64}$/D', $requestKey) || $cooldownSeconds < 1 || $cooldownSeconds > 86400) {
            throw new \InvalidArgumentException('Paramètres de réservation invalides.');
        }
        if ($this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('La réservation exige sa propre transaction.');
        }
        return $this->manager->transaction(function () use ($playerId, $requestKey, $cooldownSeconds, $adminId, $generationVersion): array {
            $admin = null;
            if ($adminId !== null) {
                (new VerificationAdminActorGuard())->lockAndCheck($this->em->getConnection(), $adminId, $playerId);
                $admin = $this->em->find(Player::class, $adminId);
                if (!$admin instanceof Player) {
                    throw new \DomainException('Action administrative non autorisée.');
                }
            }
            // Première lecture déjà verrouillée : ne pas créer un snapshot
            // périmé avant d’attendre le compte détenu par un autre demandeur.
            $player = $this->em->find(Player::class, $playerId, LockMode::PESSIMISTIC_WRITE);
            if (!$player instanceof Player) {
                return $this->result('ineligible');
            }
            // Tous les demandeurs futurs doivent utiliser ce même verrou.
            // La lecture verrouillée évite de décider sur un compte périmé.
            $this->em->refresh($player, LockMode::PESSIMISTIC_WRITE);
            if ($player->emailVerifiedAt !== null || $player->lockedAt !== null) {
                return $this->result('ineligible');
            }
            if ($generationVersion !== null) {
                $row = $this->em->getConnection()->fetchAssociative(
                    'SELECT version, state, expires_at, recipient_fingerprint, fingerprint FROM player_verification_generation WHERE player_id = ? FOR UPDATE',
                    [$playerId],
                );
                if ($row === false || (int) $row['version'] !== $generationVersion || $row['state'] !== 'active'
                    || (int) $row['expires_at'] <= $this->clock->now()->getTimestamp()
                    || !VerificationRecipientBinding::matches($row['recipient_fingerprint'], $player->email, $row['fingerprint'])) {
                    return $this->result('ineligible');
                }
            }
            $hash = hash('sha256', $requestKey);
            $existing = $this->requests->byKey($player, $hash);
            if ($existing !== null) {
                return $this->result('replayed', $existing);
            }
            $now = $this->clock->now();
            $blocking = $this->requests->blocking($player, $now);
            if ($blocking !== null) {
                return $this->result('blocked', $blocking);
            }
            $request = new VerificationDeliveryRequest();
            $request->player = $player;
            $request->requestKeyHash = $hash;
            $request->versionedGenerationVersion = $generationVersion;
            $request->requestedAt = $now;
            $request->cooldownUntil = $now->modify('+'.$cooldownSeconds.' seconds');
            $this->manager->save($request);
            $this->manager->flush();
            if ($admin !== null) {
                $audit = new \App\Entity\AdminAuditLog();
                $audit->actor = $admin;
                $audit->action = 'admin_activation_requested';
                $audit->targetType = 'player';
                $audit->targetId = $playerId;
                $audit->createdAt = $now;
                // Motif fermé : aucun texte libre pouvant contenir un secret.
                $audit->details = ['request_id' => $request->id, 'reason' => 'activation_support'];
                $this->manager->save($audit);
            }
            return $this->result('reserved', $request);
        });
    }

    /** @return array{result: string, request_id: ?int, state: ?string} */
    private function result(string $result, ?VerificationDeliveryRequest $request = null): array
    {
        return ['result' => $result, 'request_id' => $request?->id, 'state' => $request?->state];
    }
}
