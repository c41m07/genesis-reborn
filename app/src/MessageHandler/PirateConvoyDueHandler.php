<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PirateConvoyDue;
use App\Service\PirateCampaign\PirateConvoyDeliveryService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class PirateConvoyDueHandler
{
    public function __construct(private PirateConvoyDeliveryService $convoys)
    {
    }

    public function __invoke(PirateConvoyDue $message): void
    {
        $delay = $this->convoys->processDue($message->deliveryId, $message->dispatchToken);
        if ($delay !== null) {
            throw new RecoverableMessageHandlingException('L’arrivée du convoi n’est pas encore due.', retryDelay: $delay);
        }
    }
}
