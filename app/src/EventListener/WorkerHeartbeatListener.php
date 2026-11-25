<?php
namespace App\EventListener;
use App\Service\ProcessHeartbeat;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
#[AsEventListener]
final class WorkerHeartbeatListener
{
    public function __construct(private ProcessHeartbeat $heartbeat) {}
    public function __invoke(WorkerRunningEvent $event): void
    {
        // La file facultative de simulation ne doit pas masquer l’arrêt du consommateur du jeu.
        $transports = $event->getWorker()->getMetadata()->getTransportNames();
        if (in_array('async', $transports, true)) {
            $this->heartbeat->touch('worker');
        }
        if (in_array('combat_simulation', $transports, true)) {
            $this->heartbeat->touch('simulation-worker');
        }
    }
}
