<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PirateSurvivorsDue;
use App\Service\PirateCampaign\PirateSurvivorRecoveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class PirateSurvivorsDueHandler
{
    public function __construct(private PirateSurvivorRecoveryService $recovery)
    {
    }

    public function __invoke(PirateSurvivorsDue $message): void
    {
        $delay = $this->recovery->processDue($message->campaignId, $message->dispatchToken);
        if ($delay !== null) {
            throw new RecoverableMessageHandlingException('Échéance du repli des survivants non atteinte.', retryDelay: $delay);
        }
    }
}
