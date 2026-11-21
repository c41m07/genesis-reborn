<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** L'instantané et le scénario appariés sont destinés au futur stockage serveur, jamais à une vue. */
final readonly class PiratePreparedEncounter
{
    public function __construct(
        private string $definitionKey,
        private PirateEncounterSnapshot $encounter,
        private PirateBattleScenario $battle,
    ) {
    }

    public function definitionKey(): string
    {
        return $this->definitionKey;
    }

    public function encounterForServer(): PirateEncounterSnapshot
    {
        return $this->encounter;
    }

    public function battleForServer(): PirateBattleScenario
    {
        return $this->battle;
    }
}
