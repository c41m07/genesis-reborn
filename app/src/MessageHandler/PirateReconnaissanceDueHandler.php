<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\PirateReconnaissanceDue;
use App\Service\PirateCampaign\PirateReconnaissanceService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final class PirateReconnaissanceDueHandler
{
    public function __construct(private PirateReconnaissanceService $reconnaissance)
    {
    }

    public function __invoke(PirateReconnaissanceDue $message): void
    {
        $this->reconnaissance->deliver($message->missionId, $message->dispatchToken);
    }
}
