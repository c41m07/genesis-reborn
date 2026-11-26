<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\MarketConvoyDue;
use App\Service\Market\MarketConvoys;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;

#[AsMessageHandler]
final readonly class MarketConvoyDueHandler
{
    public function __construct(private MarketConvoys $convoys)
    {
    }

    public function __invoke(MarketConvoyDue $message): void
    {
        if (($delay = $this->convoys->processDue($message->tradeId, $message->token)) !== null) {
            throw new RecoverableMessageHandlingException('L’arrivée commerciale n’est pas encore due.', retryDelay: $delay);
        }
    }
}
