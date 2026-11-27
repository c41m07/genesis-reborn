<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{GuildDockOrder, Planet, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Combat\LocalGarrison;
use App\Service\PirateCampaign\PirateRecoveryHangar;
use Psr\Clock\ClockInterface;

/** Remet les coques personnelles immobilisées. La planification des reprises reste à raccorder. */
final readonly class GuildDockSettlement
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repository,
        private ActionGuard $guard,
        private PirateRecoveryHangar $hangar,
        private LocalGarrison $garrison,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return 'completed'|'ignored'|'waiting' Waiting garde les coques sur le reçu, jamais chez le nouveau propriétaire.
     */
    public function finish(int $orderId, string $token): string
    {
        return $this->manager->transaction(function () use ($orderId, $token): string {
            $order = $this->repository->find(GuildDockOrder::class, $orderId);
            if (!$order instanceof GuildDockOrder || !$order->isDue($token, $this->clock->now())) {
                return 'ignored';
            }

            // Le reçu ne dépend pas de la guilde actuelle : quitter ou dissoudre la guilde
            // ne transfère pas un lot déjà payé. Prendre les mêmes verrous que les actions de colonie.
            $membership = $this->repository->find(PlayerUniverse::class, $order->membershipId);
            if (!$membership instanceof PlayerUniverse || $membership->universe->id !== $order->universe->id) {
                return 'waiting';
            }
            $this->guard->lockMembershipForProcessing($membership, currentReads: true);
            $planet = $this->repository->find(Planet::class, $order->originPlanetId);
            if ($planet instanceof Planet) {
                $this->manager->lockCurrent($planet);
            }
            $this->manager->lockCurrent($order);

            $now = $this->clock->now();
            if (!$order->isDue($token, $now)) {
                return 'ignored';
            }
            if (!$planet instanceof Planet || $planet->owner?->id !== $membership->id
                || $planet->solarSystem->galaxy->universe->id !== $membership->universe->id) {
                // Pas de livraison implicite ailleurs : la reprise devra vérifier une destination choisie.
                return 'waiting';
            }

            $ships = [$order->shipSlug => [$order->targetTier => $order->quantity]];
            if (!$this->hangar->install($membership, $planet, $ships, 0.0)) {
                return 'waiting';
            }
            // Le stock et l'acquittement partagent la transaction. Un rejeu ne peut pas recréditer le stock.
            $order->completedAt = $now->setTimezone(new \DateTimeZone('UTC'));
            $this->manager->flush();
            $this->garrison->enlist($planet, $now);

            return 'completed';
        });
    }
}
