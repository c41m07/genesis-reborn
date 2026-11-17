<?php

namespace App\Service\Colony;

use App\Entity\Planet;

/**
 * Évite de relire les événements, les vacances et les effectifs civils pour chaque carte.
 * Recréer ce contexte après une synchronisation ou une écriture économique.
 */
final readonly class CatalogueProductionContext
{
    public function __construct(
        private Planet $planet,
        public bool $onVacation,
        public float $eventMultiplier,
        public ?array $economicInventory = null,
    ) {}

    public function assertPlanet(Planet $planet): void
    {
        // L’identité PHP est volontaire : ne pas partager ce contexte avec une autre instance de planète.
        if ($planet !== $this->planet) {
            throw new \LogicException('Le contexte du catalogue appartient à une autre colonie.');
        }
    }
}
