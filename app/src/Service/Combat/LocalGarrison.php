<?php
namespace App\Service\Combat;

use App\Entity\{CombatEvent, Fleet, FleetShip, Planet};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireServiceClosure;

/** Raccorde les livraisons au sol aux renforts existants, sans déplacer ni dédoubler leurs unités. */
final class LocalGarrison
{
    public function __construct(private GameRepository $repo, private GameManager $manager,
        private ClockInterface $clock,
        // Le service de combat utilise aussi les files : le résoudre seulement lors de la livraison.
        #[AutowireServiceClosure(CombatEventService::class)] private \Closure $combats) {}

    /** Participation, univers et planète déjà verrouillés par la résolution de la livraison. */
    public function enlist(Planet $planet, ?\DateTimeImmutable $deliveredAt = null): void
    {
        if ($planet->owner === null) return;
        $deliveredAt ??= $this->clock->now();
        $event = $this->repo->one(CombatEvent::class, ['planet'=>$planet, 'status'=>'pending']);
        if ($event === null || $event->resolveAt <= $this->clock->now()) return;
        $fleets = $this->repo->all(Fleet::class, ['homePlanet'=>$planet, 'playerUniverse'=>$planet->owner, 'status'=>'idle']);
        $ready = [];
        foreach ($fleets ? $this->repo->all(FleetShip::class, ['fleet'=>$fleets]) : [] as $ship) {
            if ($ship->quantity > 0) $ready[$ship->fleet->id] = true;
        }
        // Une file livrée en retard garde sa fin payée ; le calcul de round existant
        // empêche toutefois toute réintégration dans un round déjà résolu.
        foreach ($fleets as $fleet) {
            if (!isset($ready[$fleet->id])) continue;
            $previous = [$fleet->destination, $fleet->destinationSystem, $fleet->departureAt];
            $fleet->destination = $planet;$fleet->destinationSystem = $planet->solarSystem;
            $fleet->departureAt = $deliveredAt ?? $this->clock->now();
            if (($this->combats)()->joinOpenDefense($fleet,$deliveredAt)) {
                // La flotte reste physiquement à quai : aucun trajet fictif pour un satellite.
                $fleet->mission = 'defend';$fleet->departureAt = null;
            } else {
                [$fleet->destination, $fleet->destinationSystem, $fleet->departureAt] = $previous;
            }
        }
        $this->manager->flush();
    }
}
