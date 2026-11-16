<?php
namespace App\Service\Colony;

use App\Entity\{Fleet, FleetShip, Planet};
use App\Manager\GameManager;
use App\Repository\GameRepository;

/** Transitions de réservation appelées dans une transaction, après verrouillage de participation/univers. */
final class EconomicUnitDeployment
{
    public function __construct(private GameRepository $repo, private GameManager $manager,
        private EconomicUnitCapacity $capacity, private ResourceService $resources) {}

    public function reserve(Fleet $fleet, Planet $target, bool $requireAll = false): void
    {
        if ($target->owner?->id !== $fleet->playerUniverse->id
            || $target->solarSystem->galaxy->universe->id !== $fleet->playerUniverse->universe->id) {
            if (!$requireAll) { $this->clear($fleet); return; }
            throw new \DomainException('L’affectation exige une colonie de votre empire et de cet univers.');
        }
        $composition = [];
        foreach ($this->repo->all(FleetShip::class, ['fleet'=>$fleet]) as $ship) {
            if ($ship->quantity > 0) $composition[$ship->slug][$ship->tier] = $ship->quantity;
        }
        $this->manager->lock($target);
        $allocated = $this->capacity->allocate($target, $composition, $fleet->id, $requireAll);
        $previous = $this->previousPlanet($fleet);
        if ($allocated === [] && $previous === null) return;
        // Libérer une réservation peut activer des unités déjà à quai : solder l'ancien état d'abord.
        if ($previous !== null) $this->resources->materialize($previous);
        if ($allocated !== [] && $previous?->id !== $target->id) $this->resources->materialize($target);
        unset($fleet->navigation['economic_slots']);
        if ($allocated !== []) {
            $fleet->navigation['economic_slots'] = ['planet_id'=>$target->id,
                'owner_id'=>$fleet->playerUniverse->id, 'quantities'=>$allocated];
        }
        $this->manager->flush();
        if ($previous !== null) $this->resources->refreshProduction($previous);
        if ($allocated !== [] && $previous?->id !== $target->id) $this->resources->refreshProduction($target);
    }

    public function clear(Fleet $fleet): void
    {
        if (!isset($fleet->navigation['economic_slots'])) return;
        $previous = $this->previousPlanet($fleet);
        if ($previous !== null) $this->resources->materialize($previous);
        unset($fleet->navigation['economic_slots']);
        $this->manager->flush();
        if ($previous !== null) $this->resources->refreshProduction($previous);
    }

    private function previousPlanet(Fleet $fleet): ?Planet
    {
        $reservation = $fleet->navigation['economic_slots'] ?? null;
        if (!is_array($reservation) || ($reservation['owner_id'] ?? null) !== $fleet->playerUniverse->id) return null;
        $planet = $this->repo->find(Planet::class, $reservation['planet_id'] ?? 0);
        // Une destination perdue ne réserve plus rien chez son nouveau propriétaire.
        if ($planet === null || $planet->owner?->id !== $fleet->playerUniverse->id
            || $planet->solarSystem->galaxy->universe->id !== $fleet->playerUniverse->universe->id) return null;
        $this->manager->lock($planet);
        return $planet;
    }
}
