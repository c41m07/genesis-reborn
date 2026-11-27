<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GuildHallConstructionDue;
use App\Service\Guild\GuildHallConstruction;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GuildHallConstructionDueHandler
{
    public function __construct(private GuildHallConstruction $construction)
    {
    }

    public function __invoke(GuildHallConstructionDue $message): void
    {
        $this->construction->complete($message->buildingId, $message->dispatchToken);
    }
}
