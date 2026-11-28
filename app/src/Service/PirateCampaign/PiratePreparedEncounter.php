<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

/** L'instantané et le scénario appariés sont destinés au futur stockage serveur, jamais à une vue. */
final readonly class PiratePreparedEncounter
{
    private PirateBattleScenario $battle;

    public function __construct(
        private string $definitionKey,
        private PirateEncounterSnapshot $encounter,
        PirateBattleScenario $battle,
        private ?PirateConvoyQuote $reward = null,
    ) {
        if (!preg_match('/^[a-z][a-z0-9_]{0,63}$/D', $definitionKey)) {
            throw new \DomainException('Définition pirate invalide.');
        }
        // Refuser un scénario assemblé par erreur avec une composition différente.
        $this->battle = PirateBattleScenario::fromSnapshot($encounter, $battle->snapshot());
    }

    /** @return array{version: int, definition: string, scenario: array} */
    public function checkpoint(): array
    {
        return ['version' => 1, 'definition' => $this->definitionKey, 'scenario' => $this->battle->snapshot()] + ($this->reward ? ['reward'=>$this->reward->exactParts()] : []);
    }

    public static function restore(PirateEncounterSnapshot $encounter, array $checkpoint): self
    {
        if (($checkpoint['version'] ?? null) !== 1 || !is_string($checkpoint['definition'] ?? null)
            || !is_array($checkpoint['scenario'] ?? null)) {
            throw new \DomainException('Préparation de rencontre pirate illisible.');
        }

        return new self($checkpoint['definition'], $encounter, PirateBattleScenario::fromSnapshot($encounter, $checkpoint['scenario']),isset($checkpoint['reward']) ? PirateConvoyQuote::fromFrozenParts($checkpoint['reward']) : null);
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
