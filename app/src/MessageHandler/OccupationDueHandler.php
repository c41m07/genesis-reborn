<?php
namespace App\MessageHandler;

use App\Message\OccupationDue;
use App\Service\Combat\{OccupationCaptureService,OccupationScheduler};
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class OccupationDueHandler
{
    public function __construct(private OccupationCaptureService $captures,private OccupationScheduler $scheduler) {}

    public function __invoke(OccupationDue $message): void
    {
        if(!$this->scheduler->isEnabled()) return;
        $result=$this->captures->capture($message->occupationId,$message->dispatchToken);
        if($result['status']==='waiting') $this->scheduler->enqueue($message->occupationId,$message->dispatchToken);
    }
}
