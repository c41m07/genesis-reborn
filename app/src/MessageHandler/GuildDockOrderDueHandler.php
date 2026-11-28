<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GuildDockOrderDue;
use App\Service\Guild\GuildDockSettlement;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GuildDockOrderDueHandler
{
    public function __construct(private GuildDockSettlement $settlement)
    {
    }

    public function __invoke(GuildDockOrderDue $message): void
    {
        // Si le retour est impossible, le reçu conserve les coques pour la reprise.
        $this->settlement->finish($message->orderId, $message->dispatchToken);
    }
}
