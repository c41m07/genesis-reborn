<?php
namespace App\MessageHandler;

use App\Message\EmpireReconstructionDue;
use App\Service\Player\EmpireReconstructionService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class EmpireReconstructionDueHandler
{
    public function __construct(private EmpireReconstructionService $service) {}
    public function __invoke(EmpireReconstructionDue $message): void
    {
        $this->service->rebuild($message->reconstructionId,$message->dispatchToken);
    }
}
