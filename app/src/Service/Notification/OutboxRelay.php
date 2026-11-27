<?php

namespace App\Service\Notification;

use App\Entity\Notification;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Integration\Mercure\PublisherInterface;
use App\Message\{AdventCaptureDue, QueueItemFinished, FleetArrived, CombatEventDue, UniverseClosureDue, OccupationDue, EmpireReconstructionDue, GuildProjectRewardsDue};
use Symfony\Component\Messenger\{Exception\TransportException, MessageBusInterface, Stamp\DelayStamp};
use App\Message\{GuildDockOrderDue, GuildHallConstructionDue, MarketConvoyDue, PlayerCombatSimulationDue};
use App\Message\{PirateAttackDue, PirateConvoyDue, PirateReconnaissanceDue, PirateSurvivorsDue};
use Psr\Clock\ClockInterface;
use App\Service\Performance\{InfrastructurePerformance, QueuePerfEligibleStamp};

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
        private ?InfrastructurePerformance $performance = null,
    )
    {
    }

    public function run(bool $notifications = false): int
    {
        $count = 0;
        $failed = [];
        foreach ($this->repo->outbox($notifications, $this->clock->now()) as $outboxMessage) {
            $measured = $this->performance?->sample() ?? false;
            $timings = [];
            try {
                $failure = $this->manager->transaction(function () use ($outboxMessage, $notifications, $measured, &$timings): ?string {
                    $this->manager->lock($outboxMessage);
                    if ($notifications) {
                        $notification = $this->repo->find(Notification::class, $outboxMessage->targetId);
                        if ($notification) {
                            $clock = $measured ? hrtime(true) : null;
                            try {
                                $this->publisher->publish(NotificationService::topic($notification->recipient), ['message' => $notification->message, 'id' => $notification->id, 'journal' => $notification->journalKind !== null && $notification->recipient->journalStartedAt !== null], 'notification-' . $notification->id);
                            } catch (\Throwable $error) {
                                if ($measured) {
                                    $timings['mercure_error'] = 0;
                                }
                                throw $error;
                            } finally {
                                if ($clock !== null) {
                                    $timings['mercure_publish'] = InfrastructurePerformance::millisecondsSince($clock);
                                }
                            }
                        }
                    } else {
                        $message = match ($outboxMessage->kind) {
                            AdventCaptureDue::KIND => new AdventCaptureDue($outboxMessage->targetId, $outboxMessage->token === 'late'),
                            'queue' => new QueueItemFinished($outboxMessage->targetId, $outboxMessage->token),
                            PirateReconnaissanceDue::KIND => new PirateReconnaissanceDue($outboxMessage->targetId, $outboxMessage->token),
                            PirateAttackDue::KIND => new PirateAttackDue($outboxMessage->targetId, $outboxMessage->token),
                            PirateConvoyDue::KIND => new PirateConvoyDue($outboxMessage->targetId, $outboxMessage->token),
                            PirateSurvivorsDue::KIND => new PirateSurvivorsDue($outboxMessage->targetId, $outboxMessage->token),
                            MarketConvoyDue::KIND => new MarketConvoyDue($outboxMessage->targetId, $outboxMessage->token),
                            GuildDockOrderDue::KIND => new GuildDockOrderDue($outboxMessage->targetId, $outboxMessage->token),
                            GuildHallConstructionDue::KIND => new GuildHallConstructionDue($outboxMessage->targetId, $outboxMessage->token),
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
                        $now = $this->clock->now();
                        $delay = max(0, ($outboxMessage->dueAt->getTimestamp() - $now->getTimestamp()) * 1000);
                        $stamps = [new DelayStamp($delay)];
                        $clock = $measured ? hrtime(true) : null;
                        if ($measured) {
                            $nowMs = (int) $now->format('Uv');
                            $timings['outbox_ready'] = max(0, $nowMs - (int) $outboxMessage->dueAt->format('Uv'));
                            $stamps[] = new QueuePerfEligibleStamp($nowMs + $delay);
                        }
                        try {
                            $this->bus->dispatch($message, $stamps);
                        } catch (TransportException) {
                            if ($measured) {
                                $timings['broker_error'] = 0;
                            }
                            // Ce dispatch n’a pas de mutation SQL locale. Sortir sans retirer la ligne
                            // permet de poursuivre le lot sans fermer Doctrine ni affirmer que le broker n’a rien reçu.
                            return sprintf('%s#%d', $outboxMessage->kind, $outboxMessage->id);
                        } finally {
                            if ($clock !== null) {
                                $timings['broker_publish'] = InfrastructurePerformance::millisecondsSince($clock);
                            }
                        }
                    }
                    // Un arrêt après l’envoi peut republier le même événement : conserver son jeton et l’identifiant de notification.
                    $this->manager->remove($outboxMessage);
                    return null;
                });
            } finally {
                if ($timings !== []) {
                    // Persister seulement après fermeture de la transaction de l'outbox.
                    $this->performance?->record($notifications ? 'mercure' : 'rabbitmq', $timings);
                }
            }
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
