<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{FleetShip, GuildHall, GuildHallBuilding, GuildHallConvoy};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Symfony\Component\Uid\Uuid;

/** Le don reste au joueur tant que le Hall exact n’a pas accepté ses unités. */
final readonly class GuildAtelierDonations
{
    public function __construct(
        private GameRepository $repository,
        private GameManager $manager,
        private GuildAtelierStockStore $stock,
    ) {
    }

    /** La même vérification sous verrou s’applique au départ et à l’arrivée. */
    public function isBuiltLocked(GuildHall $hall): bool
    {
        $building = $this->repository->one(GuildHallBuilding::class, ['hall' => $hall, 'slug' => GuildHallBuilding::ATELIER]);
        if (!$building instanceof GuildHallBuilding) {
            return false;
        }
        $this->manager->lockCurrent($building);

        return $building->level > 0;
    }

    /**
     * Sous les verrous du Hall et du convoi, créditer et fermer le reçu ensemble.
     * Un refus ne touche pas à la flotte : le worker programme le retour.
     */
    public function acceptLocked(GuildHallConvoy $receipt, GuildHall $hall, string $token, \DateTimeImmutable $now): bool
    {
        $fleet = $receipt->fleet;
        if (!$receipt->shipDonation || !$receipt->canSettle($token)
            || $fleet->status !== 'traveling' || !hash_equals($fleet->dispatchToken, $token)
            || $fleet->arrivalAt === null || $fleet->arrivalAt > $now
            || $hall->id !== $receipt->hallId || $hall->guild->id !== $receipt->guildId
            || $hall->guild->universe->id !== $fleet->playerUniverse->universe->id
            || $hall->solarSystem === null || $fleet->destinationSystem?->id !== $hall->solarSystem->id
            || !$this->isBuiltLocked($hall)) {
            return false;
        }

        $units = $this->repository->all(FleetShip::class, ['fleet' => $fleet], ['id' => 'ASC']);
        $composition = [];
        foreach ($units as $ship) {
            $this->manager->lockCurrent($ship);
            if ($ship->quantity > 0) {
                $composition[$ship->slug][$ship->tier] = $ship->quantity;
            }
        }
        if ($composition === []) {
            return false;
        }
        try {
            // Le stockage valide toute la composition avant de modifier sa ligne.
            $this->stock->receiveLocked($hall, $composition);
        } catch (\DomainException) {
            return false;
        }
        if (!$receipt->settle($token, $now, [])) {
            throw new \LogicException('Impossible d’acquitter le don courant.');
        }
        $receipt->deliveredShips = $composition;
        foreach ($units as $ship) {
            $this->manager->remove($ship);
        }

        // Les coques passent au Hall, pas leur carburant réservé et non brûlé.
        $fleet->discardDockedFuel();
        $fleet->cargo = [];
        $fleet->status = 'completed';
        $fleet->arrivalAt = null;
        $fleet->dispatchToken = Uuid::v4()->toRfc4122();

        return true;
    }
}
