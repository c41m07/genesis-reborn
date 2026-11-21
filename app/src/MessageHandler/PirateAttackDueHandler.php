<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PirateAttackDue;
use App\Service\PirateCampaign\PirateAttackService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final class PirateAttackDueHandler
{
    public function __construct(private PirateAttackService $attacks)
    {
    }

    public function __invoke(PirateAttackDue $message): void
    {
        $delay = $this->attacks->processDue($message->campaignId, $message->dispatchToken);
        if ($delay !== null) {
            // Rejouer strictement la même phase ; aucun jeton ni inventaire n'a changé avant le terme.
            throw new RecoverableMessageHandlingException('Échéance de campagne non atteinte.', retryDelay: $delay);
        }
    }
}
