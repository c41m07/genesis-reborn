<?php

declare(strict_types=1);

namespace App\Service\Performance;

use Psr\Clock\ClockInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\RedeliveryStamp;

final class WorkerPerformanceListener
{
    public function __construct(
        private readonly InfrastructurePerformance $metrics,
        private readonly ClockInterface $clock,
    ) {
    }

    #[AsEventListener(priority: -128)]
    public function received(WorkerMessageReceivedEvent $event): void
    {
        $envelope = $event->getEnvelope();
        $eligible = $envelope->last(QueuePerfEligibleStamp::class);
        if (!$event->shouldHandle() || !$eligible instanceof QueuePerfEligibleStamp || !$this->metrics->enabled()) {
            return;
        }
        $wait = null;
        if (RedeliveryStamp::getRetryCountFromEnvelope($envelope) === 0) {
            $delay = (int) $this->clock->now()->format('Uv') - $eligible->eligibleAtMs;
            // Début de prise en charge moins l'échéance effective, hors attente volontaire.
            $wait = $delay >= 0 ? $delay : null;
        }
        $event->addStamps(new QueuePerfStartedStamp(hrtime(true), $wait));
    }

    #[AsEventListener(priority: 128)]
    public function finished(WorkerMessageHandledEvent|WorkerMessageFailedEvent $event): void
    {
        $started = $event->getEnvelope()->last(QueuePerfStartedStamp::class);
        if (!$started instanceof QueuePerfStartedStamp) {
            return;
        }
        $values = ['worker_execute' => InfrastructurePerformance::millisecondsSince($started->started)];
        if ($started->waitMs !== null) {
            $values['worker_wait'] = $started->waitMs;
        }
        if ($event instanceof WorkerMessageFailedEvent) {
            $values['worker_error'] = 0;
        }
        $this->metrics->record('worker', $values);
    }
}
