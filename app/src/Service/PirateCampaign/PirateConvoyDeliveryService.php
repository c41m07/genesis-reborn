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
        if ($planet !== null) {
            $this->dispatchProtectedLeg($right, $right->timeline(), $owner, $planet, $at);
        }
    }

    /**
     * Réaffectation éventuelle au terme d'un trajet dont la destination fut réellement perdue,
     * ou premier choix quand l'origine n'était déjà plus possédée à la victoire.
     * Ce service ne signe aucun formulaire et ne crédite aucun stock.
     */
    public function chooseOwnedDestination(PlayerUniverse $owner, int $deliveryId, int $newDestinationId): ?string
    {
        $scope = $this->readScope($owner);
        if ($scope === null) {
            return null;
        }

        return $this->manager->transaction(function () use ($owner, $scope, $deliveryId, $newDestinationId): ?string {
            $now = $this->now();
            $right = $this->lockForOwner($owner, $scope, $deliveryId, $now);
            if ($right === null || $owner->player?->lockedAt !== null) {
                return null;
            }
            $timeline = $right->timeline();
            if (!in_array($timeline->state(), ['eligible', 'destination_lost'], true)) {
                // Notamment pas de détournement en vol ou pour un dépôt simplement plein.
                return $timeline->state();
            }
            $planet = $this->ownedPlanetLocked($owner, $newDestinationId);
            if ($planet === null) {
                return null;
            }
            $this->dispatchProtectedLeg($right, $timeline, $owner, $planet, $now);

            return $timeline->state();
        });
    }

    private function dispatchProtectedLeg(
        PirateConvoyDelivery $right,
        PirateConvoyTimeline $timeline,
        PlayerUniverse $owner,
        Planet $planet,
        \DateTimeImmutable $at,
    ): void {
        $source = $timeline->attestedPositionForDeparture();
        if ($source === null || $source[1] !== $planet->solarSystem->galaxy->id
            || $timeline->checkpoint()['destination'] === $planet->id) {
            // Le droit subsiste, même sans position ou destination joignable ; jamais de téléportation.
            return;
        }
        $destination = $this->coordinates($planet);
        // Le profil civil est une référence de vitesse, sans véritable Fleet et sans débit au joueur.
        try {
            $profile = $this->catalog->getTierForUniverse($owner->universe, 'ships', 'transport', 'basic') + ['quantity' => 1];
            $distances = $this->navigation->distances($this->navigation->point($source), $destination);
            $quote = $this->movement->quoteDistances($distances['normal'], $distances['hyper'], $distances['same'], [$profile], 1.0,
                $this->catalog->forUniverse($owner->universe)['multipliers']['travel_time'], $this->catalog->rules()['fuel_ratio_policy']);
        } catch (\DomainException) {
            // Un devis impossible ne doit pas effacer la victoire ni forcer une destination inventée.
            return;
        }
        $token = PirateConvoyDispatchToken::generate($timeline->checkpoint()['generation'] + 1);
        $due = $at->modify('+'.$quote['seconds'].' seconds');
        if (!$timeline->dispatch($owner->id, $owner->universe->id, $planet->id, $source, $destination, $token, $at, $due)) {
            throw new \LogicException('L’ancre du droit de convoi ne correspond pas au trajet.');
        }
        $right->replaceState($timeline);
        // Premier droit non flushé : il faut son ID pour l'outbox. La transaction reste ouverte.
        if ($right->id === null) {
            $this->manager->flush();
        }

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

        $scope = $this->readScope($owner);
        if ($scope === null) {
            return null;
        }

        return $this->manager->transaction(function () use ($owner, $scope, $deliveryId, $token): ?int {
            $now = $this->now();
            $right = $this->lockForOwner($owner, $scope, $deliveryId, $now);
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
        $scope = $this->readScope($owner);
        if ($scope === null) {
            return null;
        }

        return $this->manager->transaction(function () use ($owner, $scope, $deliveryId): ?string {
            $now = $this->now();
            $right = $this->lockForOwner($owner, $scope, $deliveryId, $now);
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

    /** @return array{account:int,universe:int}|null */
    private function readScope(PlayerUniverse $owner): ?array
    {
        // Résoudre les associations avant la transaction : éviter un instantané MariaDB avant le verrou d'entrée.
        $account = $owner->player?->id;
        $universe = $owner->universe->id;

        return $owner->id === null || $account === null || $universe === null
            ? null : ['account' => $account, 'universe' => $universe];
    }

    /** @param array{account:int,universe:int} $scope */
    private function lockForOwner(PlayerUniverse $owner, array $scope, int $deliveryId, \DateTimeImmutable $at): ?PirateConvoyDelivery
    {
        // Un changement d'identité ne requalifie pas une demande portée par l'ancien contexte.
        $this->manager->lockCurrent($owner);
        $this->manager->lockCurrent($owner->universe);
        if (!$owner->isPlayerControlled() || !$owner->universe->isAccessible($at)
            || $owner->player?->id !== $scope['account'] || $owner->universe->id !== $scope['universe']) {
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
