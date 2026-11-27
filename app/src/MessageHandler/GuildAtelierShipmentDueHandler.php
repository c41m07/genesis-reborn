<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\GuildAtelierShipmentDue;
use App\Service\Guild\GuildAtelierShipmentSettlement;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GuildAtelierShipmentDueHandler
{
    public function __construct(private GuildAtelierShipmentSettlement $settlement)
    {
    }

    public function __invoke(GuildAtelierShipmentDue $message): void
    {
        $this->settlement->finish($message->shipmentId, $message->token);
    }
}
