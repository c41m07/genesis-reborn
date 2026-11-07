<?php
namespace App\MessageHandler;

use App\Message\CombatEventDue;
use App\Service\Combat\CombatEventService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class CombatEventDueHandler
{
    public function __construct(private CombatEventService $service) {}

    public function __invoke(CombatEventDue $message): void
    {
        $this->service->resolve($message->combatEventId, $message->dispatchToken);
    }
}
