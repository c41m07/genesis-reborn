<?php

namespace App\Service\Colony;

use App\Entity\Planet;

/** État économique des cartes, limité à une préparation après synchronisation des files. */
final readonly class CatalogueProductionContext
{
    public function __construct(
        private Planet $planet,
        public bool $onVacation,
        public float $eventMultiplier,
    ) {}

    public function assertPlanet(Planet $planet): void
    {
        if ($planet !== $this->planet) {
            throw new \LogicException('Le contexte du catalogue appartient à une autre colonie.');
        }
    }
}
