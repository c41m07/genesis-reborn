<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PirateReconnaissanceDue;
use App\Service\PirateCampaign\PirateReconnaissanceService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final class PirateReconnaissanceDueHandler
{
    public function __construct(private PirateReconnaissanceService $reconnaissance)
    {
    }

    public function __invoke(PirateReconnaissanceDue $message): void
    {
        $delay = $this->reconnaissance->deliver($message->missionId, $message->dispatchToken);
        if ($delay !== null) {
            // Messenger conserve le jeton et porte l'attente ; ne pas générer de deuxième ligne d'outbox.
            throw new RecoverableMessageHandlingException('Échéance de reconnaissance non atteinte.', retryDelay: $delay);
        }
    }
}
