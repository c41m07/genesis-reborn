<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, FleetShip, GuildDockOrder, GuildHall, GuildHallBuilding, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Point d’entrée métier du Dock pour le devis et la confirmation de modernisation. */
final readonly class GuildDockService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GuildHallAccess $hallAccess,
        private GuildDockReservation $reservation,
        private GuildDockQuote $quotes,
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
            [$hall, $origin] = $this->lockScope($membership, $guildId, $hallId, $originPlanetId);

            return $this->reservation->reserveLocked(
                $membership, $hall, $origin, $requestKey, $ship, $sourceTier, $quantity, $accepted,
            );
        });
    }

    /**
     * Valide le même Hall et la même colonie que le lancement, sans réserver ni débiter.
     *
     * @return array{ship:string,source_tier:string,target_tier:string,quantity:int,costs:array<string,int>,duration:int,source_duration:int,target_duration:int,source_costs:array<string,int>,target_costs:array<string,int>}
     */
    public function preview(
        PlayerUniverse $membership,
        int $guildId,
        int $hallId,
        int $originPlanetId,
        string $ship,
        string $sourceTier,
        int $quantity,
    ): array {
        return $this->manager->transaction(function () use (
            $membership, $guildId, $hallId, $originPlanetId, $ship, $sourceTier, $quantity,
        ): array {
            [, $origin] = $this->lockScope($membership, $guildId, $hallId, $originPlanetId);

            $quote = $this->quotes->forColony($membership, $origin, $ship, $sourceTier, $quantity);
            $idle = $this->repository->one(Fleet::class, ['playerUniverse' => $membership, 'homePlanet' => $origin, 'status' => 'idle']);
            $stock = $idle instanceof Fleet
                ? $this->repository->one(FleetShip::class, ['fleet' => $idle, 'slug' => $ship, 'tier' => $sourceTier])
                : null;
            if (!$stock instanceof FleetShip || $stock->quantity < $quantity) {
                throw new \DomainException('Quantité de vaisseaux insuffisante sur cette colonie.');
            }

            return $quote;
        });
    }

    /** @return array{GuildHall,Planet} */
    private function lockScope(PlayerUniverse $membership, int $guildId, int $hallId, int $originPlanetId): array
    {
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

        return [$hall, $origin];
    }
}
