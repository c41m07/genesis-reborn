<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/** Identifiant canonique passé au gestionnaire CSRF Symfony, jamais un nouveau jeton ou reçu. */
#[Exclude]
final readonly class PiratePilotCommandIntent
{
    public function __construct(
        private int $accountId,
        private int $membershipId,
        private int $activeOriginId,
        private int $missionNumber,
        private int $expectedAttempt,
        private string $quoteVersion,
        private PiratePilotCommandOptions $options,
    ) {
        if ($accountId < 1 || $membershipId < 1 || $activeOriginId < 1 || $missionNumber < 1
            || $missionNumber > 999999999 || $expectedAttempt < 0 || $expectedAttempt > PiratePilotCommandOptions::MAX_QUANTITY
            || preg_match('/^[a-f0-9]{64}$/D', $quoteVersion) !== 1) {
            throw new \InvalidArgumentException('Contexte serveur de commande invalide.');
        }
    }

    /** Recalculer avec la même forme et les preuves courantes sous les verrous natifs. */
    public function csrfId(): string
    {
        return implode(':', ['pirate-pilot-v1', $this->accountId, $this->membershipId, $this->activeOriginId,
            $this->missionNumber, $this->options->action->value, $this->expectedAttempt, $this->quoteVersion, $this->options->fingerprint()]);
    }
}
