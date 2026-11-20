<?php

namespace App\Service\Colony;

use App\Entity\{PlayerUniverse, Planet, ActionReceipt};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

/**
 * Verrouille le contexte d’une action avant de revérifier ses droits.
 * L’appelant doit déjà avoir ouvert la transaction qui portera l’action.
 */
final class ActionGuard
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private ClockInterface $clock,
        private \App\Service\Player\EmpireActivityService $activity,
    )
    {
    }

    public function lock(PlayerUniverse $membership, Planet $planet, bool $currentReads = false): void
    {
        $this->lockMembership($membership, $currentReads);
        $this->lockEntity($planet, $currentReads);
        if ($planet->owner?->id !== $membership->id || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            throw new \DomainException('Cette planète ne vous appartient pas dans cet univers.');
        }
    }

    public function lockMembership(PlayerUniverse $membership, bool $currentReads = false): void
    {
        $this->lockMembershipForProcessing($membership, $currentReads);
        $this->activity->assertCanControl($membership);
    }

    // Un empire oublié ne reçoit plus d’ordres du joueur, mais ses commandes déjà payées peuvent finir.
    public function lockMembershipForProcessing(PlayerUniverse $membership, bool $currentReads = false): void
    {
        // Garder cet ordre commun à toutes les colonies : participation, univers, puis planète chez l’appelant.
        $this->lockEntity($membership, $currentReads);
        $this->lockEntity($membership->universe, $currentReads);
        if (!$membership->universe->isAccessible($this->clock->now())) {
            throw new \DomainException('Univers inaccessible.');
        }
    }

    public function lockForCompletion(PlayerUniverse $membership, Planet $planet): void
    {
        $this->lockMembershipForProcessing($membership);
        $this->manager->lock($planet);
        if ($planet->owner?->id !== $membership->id || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
            throw new \DomainException('Cette planète ne correspond plus à la commande.');
        }
    }

    private function lockEntity(object $entity, bool $currentReads): void
    {
        if ($currentReads) {
            $this->manager->lockCurrent($entity);
            return;
        }
        $this->manager->lock($entity);
    }

    public function receipt(PlayerUniverse $membership, string $key): bool
    {
        if (!preg_match('/^[a-f0-9]{32}$/D', $key)) {
            throw new \DomainException('Identifiant de requête invalide.');
        }
        if ($this->repo->one(ActionReceipt::class, ['playerUniverse' => $membership, 'requestKey' => $key])) {
            return false;
        }
        // Le reçu partage la transaction de l’action : un rollback libère aussi cette clé de requête.
        $receipt = new ActionReceipt();
        $receipt->playerUniverse = $membership;
        $receipt->requestKey = $key;
        $this->manager->save($receipt);
        return true;
    }
}
