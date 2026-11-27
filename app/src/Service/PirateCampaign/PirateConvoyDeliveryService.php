<?php

declare(strict_types=1);

namespace App\Service\PirateCampaign;

use App\Entity\{OutboxMessage, PirateAttackCampaign, PirateConvoyDelivery, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Message\PirateConvoyDue;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Boost\BoostGrantService;
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
        private BoostGrantService $inventory,
        private PirateMissionReports $reports,
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
            $this->dispatchProtectedLeg($right, $right->timeline(), $owner, $planet);
        }
    }

    /**
     * Réaffectation éventuelle au terme d'un trajet dont la destination fut réellement perdue,
     * ou premier choix quand l'origine n'était déjà plus possédée à la victoire.
     * Ce service ne signe aucun formulaire et ne crédite aucun stock.
     */
    public function chooseOwnedDestination(PlayerUniverse $owner, int $deliveryId, int $newDestinationId, ?string $approvedDigest = null): ?string
    {
        $scope = $this->readScope($owner);
        if ($scope === null) {
            return null;
        }

        return $this->manager->transaction(function () use ($owner, $scope, $deliveryId, $newDestinationId, $approvedDigest): ?string {
            $right = $this->lockForOwner($owner, $scope, $deliveryId);
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
            $this->dispatchProtectedLeg($right, $timeline, $owner, $planet, $approvedDigest);

            return $timeline->state();
        });
    }

    /** Devis privé en lecture seule, à recalculer sous verrou lors de la confirmation. */
    public function previewOwnedDestination(PlayerUniverse $owner, PirateConvoyDelivery $right, Planet $target): ?array
    {
        if ($owner->id !== $right->membershipId || $owner->player?->id !== $right->ownerId
            || $owner->universe->id !== $right->universeId || $target->owner?->id !== $owner->id
            || $target->npcPlanet || $target->solarSystem->galaxy->universe->id !== $right->universeId) {
            return null;
        }
        $plan = $this->protectedLegPlan($right->timeline(), $owner, $target);

        return $plan === null ? null : ['seconds' => $plan['seconds'], 'digest' => $plan['digest']];
    }

    private function protectedLegPlan(PirateConvoyTimeline $timeline, PlayerUniverse $owner, Planet $planet): ?array
    {
        $source = $timeline->attestedPositionForDeparture();
        if ($source === null || $source[1] !== $planet->solarSystem->galaxy->id
            || $timeline->checkpoint()['destination'] === $planet->id) {
            // Le droit subsiste, même sans position ou destination joignable ; jamais de téléportation.
            return null;
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
            return null;
        }
        $seconds = $quote['seconds'];
        $digest = hash('sha256', json_encode([
            $timeline->checkpoint(), $timeline->quote->exactParts(), $planet->id, $destination, $seconds,
        ], JSON_THROW_ON_ERROR));

        return ['source' => $source, 'destination' => $destination, 'seconds' => $seconds, 'digest' => $digest];
    }

    private function dispatchProtectedLeg(
        PirateConvoyDelivery $right,
        PirateConvoyTimeline $timeline,
        PlayerUniverse $owner,
        Planet $planet,
        ?string $approvedDigest = null,
    ): void {
        $plan = $this->protectedLegPlan($timeline, $owner, $planet);
        if ($plan === null || ($approvedDigest !== null && !hash_equals($plan['digest'], $approvedDigest))) {
            return;
        }
        $source = $plan['source'];
        $destination = $plan['destination'];
        $token = PirateConvoyDispatchToken::generate($timeline->checkpoint()['generation'] + 1);
        // Ni l'attente du verrou, ni la préparation du devis ne comptent comme temps de trajet.
        $at = $this->now();
        if (!$owner->universe->isAccessible($at) || !$owner->isPlayerControlled() || $planet->owner?->id !== $owner->id) {
            return;
        }
        $due = $at->modify('+'.$plan['seconds'].' seconds');
        if (!$timeline->dispatch($owner->id, $owner->universe->id, $planet->id, $source, $destination, $token, $at, $due)) {
            throw new \LogicException('L’ancre du droit de convoi ne correspond pas au trajet.');
        }
        $right->replaceState($timeline);
        // Premier droit non flushé : il faut son ID pour l'outbox. La transaction reste ouverte.
        if ($right->id === null) {
            $this->manager->flush();
        }

        $this->reports->startTransport($right, $timeline, $planet, $owner, $at);

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
            $right = $this->lockForOwner($owner, $scope, $deliveryId);
            if ($right === null) {
                return null;
            }

            $timeline = $right->timeline();
            $phase = $timeline->checkpoint();
            if ($timeline->state() !== 'traveling' || !hash_equals($phase['token'], $token)) {
                return null;
            }
            $due = $timeline->chronology()['due_at'];
            $now = $this->now();
            if ($now < $due) {
                return max(1000, ($due->getTimestamp() - $now->getTimestamp()) * 1000);
            }
            $planet = $this->ownedPlanetLocked($owner, $phase['destination']);
            $now = $this->now();
            if (!$owner->universe->isAccessible($now)) {
                return null;
            }
            if ($now < $due) {
                return max(1000, ($due->getTimestamp() - $now->getTimestamp()) * 1000);
            }
            if (!$timeline->arriveWhenDue($token, $now)) {
                throw new \LogicException('Le convoi ne peut pas attester cette arrivée.');
            }
            $this->settleOrKeepEntire($right, $timeline, $owner, $phase['destination'], $planet, $now);

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
            $right = $this->lockForOwner($owner, $scope, $deliveryId);
            if ($right === null || $owner->player?->lockedAt !== null) {
                return null;
            }
            $timeline = $right->timeline();
            if ($timeline->state() === 'waiting_capacity') {
                $destination = $timeline->checkpoint()['destination'];
                $planet = $this->ownedPlanetLocked($owner, $destination);
                $now = $this->now();
                if (!$owner->universe->isAccessible($now)) {
                    return null;
                }
                $this->settleOrKeepEntire($right, $timeline, $owner, $destination, $planet, $now);
            }

            return $timeline->state();
        });
    }

    private function settleOrKeepEntire(
        PirateConvoyDelivery $right,
        PirateConvoyTimeline $timeline,
        PlayerUniverse $owner,
        int $destinationId,
        ?Planet $planet,
        \DateTimeImmutable $at,
    ): void {
        if (!$timeline->mayTryDeposit($owner->id, $owner->universe->id, $destinationId, $at)) {
            throw new \LogicException('Le dépôt du convoi exige une arrivée réelle à cette destination.');
        }
        if ($planet === null) {
            $timeline->recordDestinationLoss($owner->id, $owner->universe->id, $destinationId, $at);
        } else {
            $cargo = $timeline->quote->depositAmounts();
            // Le contenu reste groupé dans l’inventaire jusqu’au choix de la colonie à l’ouverture.
            if (!$this->inventory->awardConvoyBoxLocked($owner, $right->id, $cargo)
                || !$timeline->recordSettlementAfterCredit($owner->id, $owner->universe->id, $destinationId, $at)) {
                throw new \LogicException('La boîte et le reçu du convoi doivent être enregistrés ensemble.');
            }
        }
        $this->reports->finishTransport($right, $timeline, $planet, $owner, $at);
        $right->replaceState($timeline, deliveredAsBox: $planet !== null);
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
    private function lockForOwner(PlayerUniverse $owner, array $scope, int $deliveryId): ?PirateConvoyDelivery
    {
        // Un changement d'identité ne requalifie pas une demande portée par l'ancien contexte.
        $this->manager->lockCurrent($owner);
        $this->manager->lockCurrent($owner->universe);
        if (!$owner->isPlayerControlled()
            || $owner->player?->id !== $scope['account'] || $owner->universe->id !== $scope['universe']) {
            return null;
        }

        $right = $this->repository->lockPirateConvoy($deliveryId, $owner);

        return $owner->universe->isAccessible($this->now()) ? $right : null;
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
