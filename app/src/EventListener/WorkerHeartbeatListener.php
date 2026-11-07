<?php
namespace App\EventListener;
use App\Service\ProcessHeartbeat;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerRunningEvent;
#[AsEventListener]
final class WorkerHeartbeatListener
{
    public function __construct(private ProcessHeartbeat $heartbeat) {}
    public function __invoke(WorkerRunningEvent $event): void { $this->heartbeat->touch('worker'); }
}
