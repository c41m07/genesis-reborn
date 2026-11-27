<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildDockOrder, GuildHallBuilding, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Point d’entrée métier du Dock pour le futur formulaire de modernisation. */
final readonly class GuildDockService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildHallAccess $hallAccess,
        private GuildDockReservation $reservation,
    ) {
    }

    /** @param array{duration:int,costs:array<string,int>} $accepted */
    public function reserve(
        PlayerUniverse $membership,
        int $guildId,
        int $hallId,
        int $originPlanetId,
        string $requestKey,
        string $ship,
        string $sourceTier,
        int $quantity,
        array $accepted,
    ): GuildDockOrder {
        return $this->manager->transaction(function () use (
            $membership, $guildId, $hallId, $originPlanetId, $requestKey, $ship, $sourceTier, $quantity, $accepted,
        ): GuildDockOrder {
            $hall = $this->hallAccess->lockPlaced($membership, $guildId, $hallId);

            $building = $this->repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => GuildHallBuilding::DOCK]);
            if (!$building instanceof GuildHallBuilding) {
                throw new \DomainException('Le Dock de modernisation doit être construit avant de l’utiliser.');
            }
            $this->manager->lockCurrent($building);
            if ($building->level < 1) {
                throw new \DomainException('Le Dock de modernisation doit être construit avant de l’utiliser.');
            }

            // L’accès a déjà pris les verrous participation → univers → guilde → Hall.
            // Ne pas prendre de verrou sur une colonie tierce avant même le contrôle de portée.
            $origin = $this->repository->find(Planet::class, $originPlanetId);
            if (!$origin instanceof Planet || $origin->owner?->id !== $membership->id
                || $origin->solarSystem->galaxy->universe->id !== $membership->universe->id) {
                throw new \DomainException('Sélectionnez une colonie de votre empire.');
            }
            $this->manager->lockCurrent($origin);
            if ($origin->owner?->id !== $membership->id) {
                throw new \DomainException('Cette colonie ne vous appartient plus.');
            }

            return $this->reservation->reserveLocked(
                $membership, $hall, $origin, $requestKey, $ship, $sourceTier, $quantity, $accepted,
            );
        });
    }
}
