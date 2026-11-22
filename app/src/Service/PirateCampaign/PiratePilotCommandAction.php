<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** Intention choisie par le futur adaptateur, jamais par un champ action de la requête. */
enum PiratePilotCommandAction: string
{
    case Reconnaissance = 'reconnaissance';
    case Attack = 'attack';
    case RetryDeposit = 'retry_deposit';
    case ChooseConvoyDestination = 'choose_convoy_destination';
    case SurvivorFallback = 'survivor_fallback';

    public function requiresDestination(): bool
    {
        return in_array($this, [self::ChooseConvoyDestination, self::SurvivorFallback], true);
    }
}
