<?php

namespace App\MessageHandler;

use App\Message\PlayerCombatSimulationDue;
use App\Service\Combat\PlayerCombatSimulationQueue;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;

#[AsMessageHandler]
final class PlayerCombatSimulationDueHandler
{
    public function __construct(private readonly PlayerCombatSimulationQueue $queue)
    {
    }

    public function __invoke(PlayerCombatSimulationDue $message): void
    {
        if ($message->expire) {
            $this->queue->expire($message);
        } else {
            $this->queue->run($message);
        }
    }

    // Laisser Messenger décider de ses tentatives avant de fermer un essai pour le joueur.
    #[AsEventListener(priority: -200)]
    public function onFinalFailure(WorkerMessageFailedEvent $event): void
    {
        $message = $event->getEnvelope()->getMessage();
        if (!$event->willRetry() && $message instanceof PlayerCombatSimulationDue && !$message->expire) {
            $this->queue->fail($message);
        }
    }
}
