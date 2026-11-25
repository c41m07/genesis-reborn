<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Integration\Mercure\PublisherInterface;
use App\Message\{AdventCaptureDue, QueueItemFinished, FleetArrived, CombatEventDue, UniverseClosureDue, OccupationDue, EmpireReconstructionDue, GuildProjectRewardsDue};
use Symfony\Component\Messenger\{Exception\TransportException, MessageBusInterface, Stamp\DelayStamp};
use App\Message\PlayerCombatSimulationDue;
use App\Message\{PirateAttackDue, PirateConvoyDue, PirateReconnaissanceDue, PirateSurvivorsDue};
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
        $failed = [];
        foreach ($this->repo->outbox($notifications, $this->clock->now()) as $outboxMessage) {
            $failure = $this->manager->transaction(function () use ($outboxMessage, $notifications): ?string {
                $this->manager->lock($outboxMessage);
                if ($notifications) {
                    $notification = $this->repo->find(Notification::class, $outboxMessage->targetId);
                    if ($notification) {
                        $this->publisher->publish(NotificationService::topic($notification->recipient), ['message' => $notification->message, 'id' => $notification->id, 'journal' => $notification->journalKind !== null && $notification->recipient->journalStartedAt !== null], 'notification-' . $notification->id);
                    }
                } else {
                    $message = match ($outboxMessage->kind) {
                        AdventCaptureDue::KIND => new AdventCaptureDue($outboxMessage->targetId, $outboxMessage->token === 'late'),
                        'queue' => new QueueItemFinished($outboxMessage->targetId, $outboxMessage->token),
                        PirateReconnaissanceDue::KIND => new PirateReconnaissanceDue($outboxMessage->targetId, $outboxMessage->token),
                        PirateAttackDue::KIND => new PirateAttackDue($outboxMessage->targetId, $outboxMessage->token),
                        PirateConvoyDue::KIND => new PirateConvoyDue($outboxMessage->targetId, $outboxMessage->token),
                        PirateSurvivorsDue::KIND => new PirateSurvivorsDue($outboxMessage->targetId, $outboxMessage->token),
                        'fleet' => new FleetArrived($outboxMessage->targetId, $outboxMessage->token),
                        'combat' => new CombatEventDue($outboxMessage->targetId, $outboxMessage->token),
                        PlayerCombatSimulationDue::KIND => new PlayerCombatSimulationDue($outboxMessage->targetId, $outboxMessage->token),
                        PlayerCombatSimulationDue::EXPIRY => new PlayerCombatSimulationDue($outboxMessage->targetId, $outboxMessage->token, true),
                        'reconstruction' => new EmpireReconstructionDue($outboxMessage->targetId, $outboxMessage->token),
                        'guild_project' => new GuildProjectRewardsDue($outboxMessage->targetId, $outboxMessage->token),
                        'occupation' => new OccupationDue($outboxMessage->targetId, $outboxMessage->token),
                        'universe' => new UniverseClosureDue($outboxMessage->targetId, $outboxMessage->token),
                        default => throw new \LogicException('Événement inconnu.'),
                    };
                    // Messenger porte l’attente ; une échéance déjà dépassée est envoyée sans délai.
                    $delay = max(0, ($outboxMessage->dueAt->getTimestamp() - $this->clock->now()->getTimestamp()) * 1000);
                    try {
                        $this->bus->dispatch($message, [new DelayStamp($delay)]);
                    } catch (TransportException) {
                        // Ce dispatch n’a pas de mutation SQL locale. Sortir sans retirer la ligne
                        // permet de poursuivre le lot sans fermer Doctrine ni affirmer que le broker n’a rien reçu.
                        return sprintf('%s#%d', $outboxMessage->kind, $outboxMessage->id);
                    }
                }
                // Un arrêt après l’envoi peut republier le même événement : conserver son jeton et l’identifiant de notification.
                $this->manager->remove($outboxMessage);
                return null;
            });
            if ($failure === null) {
                ++$count;
            } else {
                $failed[] = $failure;
            }
        }
        // Ne pas garder les entités de cette passe pour le prochain appel du relais.
        $this->manager->clear();
        if ($failed !== []) {
            throw new OutboxPublicationFailed($count, $failed);
        }
        return $count;
    }
}
