<?php
namespace App\MessageHandler;

use App\Message\UniverseClosureDue;
use App\Service\Universe\UniverseLifecycleService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class UniverseClosureDueHandler
{
    public function __construct(private UniverseLifecycleService $lifecycle) {}

    public function __invoke(UniverseClosureDue $message): void
    {
        $this->lifecycle->closeIfDue($message->universeId, $message->scheduleToken);
    }
}
