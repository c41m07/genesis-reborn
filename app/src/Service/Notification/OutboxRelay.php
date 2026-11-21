<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Integration\Mercure\PublisherInterface;
use App\Message\{QueueItemFinished, FleetArrived, CombatEventDue, UniverseClosureDue, OccupationDue, EmpireReconstructionDue, GuildProjectRewardsDue};
use Symfony\Component\Messenger\{MessageBusInterface, Stamp\DelayStamp};
use App\Message\{PirateAttackDue, PirateConvoyDue, PirateReconnaissanceDue};
use Psr\Clock\ClockInterface;

/**
 * Relaye les événements enregistrés vers Messenger ou Mercure.
 * Un arrêt entre l’envoi et le retrait de l’outbox peut provoquer une nouvelle réception.
 */
final class OutboxRelay
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private MessageBusInterface $bus,
        private PublisherInterface $publisher,
        private ClockInterface $clock,
    )
    {
    }

    public function run(bool $notifications = false): int
    {
        $count = 0;
        foreach ($this->repo->outbox($notifications) as $outboxMessage) {
            $this->manager->transaction(function () use ($outboxMessage, $notifications) {
                $this->manager->lock($outboxMessage);
                if ($notifications) {
                    $notification = $this->repo->find(Notification::class, $outboxMessage->targetId);
                    if ($notification) {
                        $this->publisher->publish(NotificationService::topic($notification->recipient), ['message' => $notification->message, 'id' => $notification->id, 'journal' => $notification->journalKind !== null && $notification->recipient->journalStartedAt !== null], 'notification-' . $notification->id);
                    }
                } else {
                    $message = match ($outboxMessage->kind) {
                        'queue' => new QueueItemFinished($outboxMessage->targetId, $outboxMessage->token),
                        PirateReconnaissanceDue::KIND => new PirateReconnaissanceDue($outboxMessage->targetId, $outboxMessage->token),
                        PirateAttackDue::KIND => new PirateAttackDue($outboxMessage->targetId, $outboxMessage->token),
                        PirateConvoyDue::KIND => new PirateConvoyDue($outboxMessage->targetId, $outboxMessage->token),
                        'fleet' => new FleetArrived($outboxMessage->targetId, $outboxMessage->token),
                        'combat' => new CombatEventDue($outboxMessage->targetId, $outboxMessage->token),
                        'reconstruction' => new EmpireReconstructionDue($outboxMessage->targetId, $outboxMessage->token),
                        'guild_project' => new GuildProjectRewardsDue($outboxMessage->targetId, $outboxMessage->token),
                        'occupation' => new OccupationDue($outboxMessage->targetId, $outboxMessage->token),
                        'universe' => new UniverseClosureDue($outboxMessage->targetId, $outboxMessage->token),
                        default => throw new \LogicException('Événement inconnu.'),
                    };
                    // Messenger porte l’attente ; une échéance déjà dépassée est envoyée sans délai.
                    $delay = max(0, ($outboxMessage->dueAt->getTimestamp() - $this->clock->now()->getTimestamp()) * 1000);
                    $this->bus->dispatch($message, [new DelayStamp($delay)]);
                }
                // Un arrêt après l’envoi peut republier le même événement : conserver son jeton et l’identifiant de notification.
                $this->manager->remove($outboxMessage);
            });
            ++$count;
        }
        // Ne pas garder les entités de cette passe pour le prochain appel du relais.
        $this->manager->clear();
        return $count;
    }
}
