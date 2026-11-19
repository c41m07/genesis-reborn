<?php

namespace App\Service\Player;

use App\Entity\{Player, VerificationDeliveryRequest};
use App\Manager\GameManager;
use App\Repository\VerificationDeliveryRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;
use Psr\Clock\ClockInterface;

/** Fondation interne : ni route publique, ni autorisation admin, ni envoi. */
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
    public function reserve(int $playerId, string $requestKey, int $cooldownSeconds): array
    {
        // Le futur contrôleur fournit une clé aléatoire stable lors des doubles
        // soumissions ; ce n'est ni un jeton d'activation ni une autorisation.
        if (!preg_match('/^[a-f0-9]{64}$/D', $requestKey) || $cooldownSeconds < 1 || $cooldownSeconds > 86400) {
            throw new \InvalidArgumentException('Paramètres de réservation invalides.');
        }
        if ($this->em->getConnection()->isTransactionActive()) {
            throw new \LogicException('La réservation exige sa propre transaction.');
        }
        return $this->manager->transaction(function () use ($playerId, $requestKey, $cooldownSeconds): array {
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
            $request->requestedAt = $now;
            $request->cooldownUntil = $now->modify('+'.$cooldownSeconds.' seconds');
            $this->manager->save($request);
            $this->manager->flush();
            return $this->result('reserved', $request);
        });
    }

    /** @return array{result: string, request_id: ?int, state: ?string} */
    private function result(string $result, ?VerificationDeliveryRequest $request = null): array
    {
        return ['result' => $result, 'request_id' => $request?->id, 'state' => $request?->state];
    }
}
