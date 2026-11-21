<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{OutboxMessage, PirateAttackCampaign, PirateConvoyDelivery, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Message\PirateConvoyDue;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ResourceService;
use App\Service\Fleet\{FleetNavigation, MovementCalculator};
use Psr\Clock\ClockInterface;

final readonly class PirateConvoyDeliveryService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private GameCatalog $catalog,
        private FleetNavigation $navigation,
        private MovementCalculator $movement,
        private ResourceService $resources,
        private ClockInterface $clock,
    ) {
    }

    /** Appel unique dans la transaction de publication de la victoire, après les verrous de campagne. */
    public function awardInVictoryTransaction(PirateAttackCampaign $attack, PlayerUniverse $owner, \DateTimeImmutable $at): void
    {
        if (($attack->timeline['convoy_pilot'] ?? null) !== true) {
            return;
        }
        if ($this->repository->one(PirateConvoyDelivery::class, ['attack' => $attack]) !== null) {
            return;
        }
        $right = PirateConvoyDelivery::forVictory($attack, $owner, $at);
        $this->manager->save($right);
        $planet = $this->ownedPlanetLocked($owner, $attack->originId);
        $timeline = $right->timeline();
        $source = $timeline->attestedPositionForDeparture();
        if ($planet === null || $source === null || $source[1] !== $planet->solarSystem->galaxy->id) {
            // Le droit subsiste sans destination joignable ; on ne suppose pas de voyage de remplacement.
            return;
        }
        $destination = $this->coordinates($planet);
        // Le profil de transport sert de référence de vitesse, sans créer de vaisseau ni débiter le joueur.
        try {
            $profile = $this->catalog->getTierForUniverse($owner->universe, 'ships', 'transport', 'basic') + ['quantity' => 1];
            $distances = $this->navigation->distances($this->navigation->point($source), $destination);
            $quote = $this->movement->quoteDistances($distances['normal'], $distances['hyper'], $distances['same'], [$profile], 1.0,
                $this->catalog->forUniverse($owner->universe)['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy']);
        } catch (\DomainException) {
            // Un devis impossible ne doit pas effacer la victoire ni forcer une destination inventée.
            return;
        }
        $token = PirateConvoyDispatchToken::generate(1);
        $due = $at->modify('+'.$quote['seconds'].' seconds');
        if (!$timeline->dispatch($owner->id, $owner->universe->id, $planet->id, $source, $destination, $token, $at, $due)) {
            throw new \LogicException('L’ancre du droit de convoi ne correspond pas au trajet.');
        }
        $right->replaceState($timeline);
        $this->manager->flush();

        $event = new OutboxMessage();
        $event->kind = PirateConvoyDue::KIND;
        $event->targetId = $right->id;
        $event->token = $token;
        $event->dueAt = $due;
        $this->manager->save($event);
    }

    /** Délai strictement positif pour un jeton courant reçu avant le véritable terme. */
    public function processDue(int $deliveryId, string $token): ?int
    {
        $candidate = $this->repository->find(PirateConvoyDelivery::class, $deliveryId);
        if (!$candidate instanceof PirateConvoyDelivery) {
            return null;
        }
        $owner = $this->repository->find(PlayerUniverse::class, $candidate->membershipId);
        if (!$owner instanceof PlayerUniverse) {
            return null;
        }

        return $this->manager->transaction(function () use ($owner, $deliveryId, $token): ?int {
            $now = $this->now();
            $right = $this->lockForOwner($owner, $deliveryId, $now);
            if ($right === null) {
                return null;
            }

            $timeline = $right->timeline();
            $phase = $timeline->checkpoint();
            if ($timeline->state() !== 'traveling' || !hash_equals($phase['token'], $token)) {
                return null;
            }
            $due = $timeline->chronology()['due_at'];
            if ($now < $due) {
                return max(1000, ($due->getTimestamp() - $now->getTimestamp()) * 1000);
            }
            if (!$timeline->arriveWhenDue($token, $now)) {
                throw new \LogicException('Le convoi ne peut pas attester cette arrivée.');
            }
            $this->settleOrKeepEntire($right, $timeline, $owner, $phase['destination'], $now);

            return null;
        });
    }

    /** Reprise privée au même dépôt seulement. Aucun trajet ni destination ne vient du demandeur. */
    public function retryDeposit(PlayerUniverse $owner, int $deliveryId): ?string
    {
        return $this->manager->transaction(function () use ($owner, $deliveryId): ?string {
            $now = $this->now();
            $right = $this->lockForOwner($owner, $deliveryId, $now);
            if ($right === null || $owner->player?->lockedAt !== null) {
                return null;
            }
            $timeline = $right->timeline();
            if ($timeline->state() === 'waiting_capacity') {
                $this->settleOrKeepEntire($right, $timeline, $owner, $timeline->checkpoint()['destination'], $now);
            }

            return $timeline->state();
        });
    }

    private function settleOrKeepEntire(
        PirateConvoyDelivery $right,
        PirateConvoyTimeline $timeline,
        PlayerUniverse $owner,
        int $destinationId,
        \DateTimeImmutable $at,
    ): void {
        if (!$timeline->mayTryDeposit($owner->id, $owner->universe->id, $destinationId, $at)) {
            throw new \LogicException('Le dépôt du convoi exige une arrivée réelle à cette destination.');
        }
        // Verrous dans l'ordre : participation, univers, droit, planète, puis stocks courants.
        $planet = $this->ownedPlanetLocked($owner, $destinationId);
        if ($planet === null) {
            $timeline->recordDestinationLoss($owner->id, $owner->universe->id, $destinationId, $at);
        } else {
            $cargo = $timeline->quote->depositAmounts();
            $result = $this->resources->deposit($planet, $cargo, allOrNothing: true);
            if (max([0.0, ...array_values($result['overflow'])]) > 0.0) {
                $timeline->recordCapacityShortage($owner->id, $owner->universe->id, $destinationId, $at);
            } else {
                if ($result['delivered'] !== $cargo
                    || !$timeline->recordSettlementAfterCredit($owner->id, $owner->universe->id, $destinationId, $at)) {
                    throw new \LogicException('Le lot et le reçu du convoi doivent être enregistrés intégralement.');
                }
            }
        }
        // Le dépôt entier, la réception et le marqueur durable sont flushés ensemble par GameManager.
        $right->replaceState($timeline);
    }

    private function lockForOwner(PlayerUniverse $owner, int $deliveryId, \DateTimeImmutable $at): ?PirateConvoyDelivery
    {
        if ($owner->id === null) {
            return null;
        }
        // Résoudre l'association avant le premier verrou du droit ; un propriétaire détaché ne doit rien recevoir.
        $this->manager->lockCurrent($owner);
        $this->manager->lockCurrent($owner->universe);
        if (!$owner->isPlayerControlled() || !$owner->universe->isAccessible($at)) {
            return null;
        }

        return $this->repository->lockPirateConvoy($deliveryId, $owner);
    }

    private function ownedPlanetLocked(PlayerUniverse $owner, int $destinationId): ?Planet
    {
        $planet = $this->repository->find(Planet::class, $destinationId);
        if (!$planet instanceof Planet) {
            return null;
        }
        $this->manager->lockCurrent($planet);
        if ($planet->owner?->id !== $owner->id || $planet->solarSystem->galaxy->universe->id !== $owner->universe->id) {
            return null;
        }

        return $planet;
    }

    private function coordinates(Planet $planet): array
    {
        $system = $planet->solarSystem;

        return [$system->id, $system->galaxy->id, $system->coordX, $system->coordY, $planet->position];
    }

    private function now(): \DateTimeImmutable
    {
        $now = $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));

        return $now->setTimestamp($now->getTimestamp());
    }
}
