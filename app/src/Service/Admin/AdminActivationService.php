<?php

namespace App\Service\Admin;

use App\Entity\{AdminAuditLog, Player};
use App\Service\Player\{VerificationRequestLifecycle, VerificationRequestReservation};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class AdminActivationService
{
    public function __construct(
        private VerificationRequestReservation $reservations,
        private VerificationRequestLifecycle $lifecycle,
        private AdminActivationTransport $transport,
        private EntityManagerInterface $em,
        private ClockInterface $clock,
        #[Autowire('%env(bool:ADMIN_ACTIVATION_ENABLED)%')] private bool $enabled,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function request(Player $actor, int $playerId, string $key): string
    {
        if (!$this->enabled) {
            return 'disabled';
        }
        if ($actor->id === null) {
            throw new \DomainException('Action administrative non autorisée.');
        }
        $reservation = $this->reservations->reserve($playerId, $key, 300, $actor->id);
        if ($reservation['result'] !== 'reserved') {
            return $reservation['result'];
        }
        $id = $reservation['request_id'];
        $claim = $this->lifecycle->claim($id, 120);
        if ($claim['result'] !== 'claimed') {
            return $claim['result'];
        }
        // Aucune implémentation SMTP dans ce lot. Les tests injectent le seul
        // transport acceptant ; l'implémentation normale certifie aucun départ.
        try {
            $outcome = $this->transport->deliver($playerId);
        } catch (\Throwable) {
            // Une exception seule ne prouve jamais que le mail n'est pas parti.
            $outcome = 'unknown';
        }
        if (!in_array($outcome, ['transport_accepted', 'not_dispatched', 'unknown'], true)) {
            $outcome = 'unknown';
        }
        $terminal = $outcome === 'not_dispatched' ? 'cancelled' : $outcome;
        $completion = $this->lifecycle->complete($id, $claim['claim_token'], $terminal);
        if (!in_array($completion['result'], ['completed', 'replayed'], true)) {
            $outcome = 'unknown';
        }
        $audit = new AdminAuditLog();
        $audit->actor = $actor;
        $audit->action = 'admin_activation_result';
        $audit->targetType = 'player';
        $audit->targetId = $playerId;
        $audit->createdAt = $this->clock->now();
        $audit->details = ['request_id' => $id, 'result' => $outcome];
        $this->em->persist($audit);
        $this->em->flush();
        return $outcome;
    }
}
