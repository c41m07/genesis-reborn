<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GuildAtelierManufacturingDue;
use App\Service\Guild\GuildAtelierManufacturing;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GuildAtelierManufacturingDueHandler
{
    public function __construct(private GuildAtelierManufacturing $manufacturing)
    {
    }

    public function __invoke(GuildAtelierManufacturingDue $message): void
    {
        $this->manufacturing->complete($message->orderId, $message->dispatchToken);
    }
}
