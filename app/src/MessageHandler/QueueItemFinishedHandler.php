<?php
namespace App\MessageHandler;
use App\Message\QueueItemFinished;
use App\Service\Queue\QueueService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
#[AsMessageHandler]
final class QueueItemFinishedHandler
{
    public function __construct(private QueueService $service) {}
    public function __invoke(QueueItemFinished $message): void { $this->service->complete($message->queueItemId,$message->dispatchToken); }
}
