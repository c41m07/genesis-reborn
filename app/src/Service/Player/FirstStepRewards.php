<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\{Fleet, FleetShip, Planet, PlayerUniverse, QueueItem};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\{ActionGuard, ResourceService};
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final class FirstStepRewards
{
    private Connection $db;

    public function __construct(
        EntityManagerInterface $entityManager,
        private GameManager $manager,
        private GameRepository $repo,
        private ActionGuard $guard,
        private ResourceService $resources,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
    ) {
        $this->db = $entityManager->getConnection();
    }

    public function recordPaidGuideOrder(QueueItem $order): void
    {
        if (!$order->firstStepsGuided || $order->paidCosts === null) {
            return;
        }
        $step = FirstStepRewardPolicy::queueStep($order->queueType, $order->itemSlug, $order->tier, $order->itemLevel);
        $refund = FirstStepRewardPolicy::paidRefund($order);
        if ($step !== null && array_sum($refund) > 0) {
            $this->reserve($order->playerUniverse, $step, $refund);
        }
    }

    /** Appelé sous les verrous de l’arrivée, après avoir consommé exactement la sonde basique. */
    public function replaceConsumedProbe(Fleet $flight): void
    {
        $membership = $flight->playerUniverse;
        $home = $flight->homePlanet;
        if (!$flight->firstStepsSpy || $home->owner !== $membership || !FirstStepRewardPolicy::isNativeNpc($flight->destination)) {
            return;
        }
        $this->reserve($membership, FirstStep::Espionage, ['metal' => 0, 'nano' => 0]);
        $reward = $this->pending($membership, FirstStep::Espionage);
        if ($reward === null) {
            return;
        }

        // Le hangar n’est pas un stock de ressources. Le retour et sa preuve partagent le commit
        // du vrai rapport ; on ne remet jamais de sonde dans le trajet qui vient de se terminer.
        $idle = $this->repo->one(Fleet::class, ['homePlanet' => $home, 'playerUniverse' => $membership, 'status' => 'idle']);
        if (!$idle) {
            $idle = new Fleet();
            $idle->homePlanet = $home;
            $idle->playerUniverse = $membership;
            $this->manager->save($idle);
            $this->manager->flush();
        }
        $ship = $this->repo->one(FleetShip::class, ['fleet' => $idle, 'slug' => 'probe', 'tier' => 'basic']) ?? new FleetShip();
        $ship->fleet = $idle;
        $ship->slug = 'probe';
        $ship->tier = 'basic';
        ++$ship->quantity;
        $this->manager->save($ship);
        $this->markClaimed((int) $reward['id']);
    }

    /** @return list<array{step_key: string, metal: int, nano: int}> */
    public function available(PlayerUniverse $membership): array
    {
        return $this->db->fetchAllAssociative(
            'SELECT step_key, metal, nano FROM first_step_reward
             WHERE player_id = ? AND source_membership_id = ? AND claimed_at IS NULL
             AND step_key IN (?, ?) ORDER BY id',
            [$membership->player?->id, $membership->id, FirstStep::MetalMine->value, FirstStep::Research->value],
        );
    }

    /** @return 'unavailable'|'full'|'claimed' */
    public function claim(PlayerUniverse $membership, Planet $planet, FirstStep $step): string
    {
        return $this->manager->transaction(function () use ($membership, $planet, $step): string {
            $this->guard->lock($membership, $planet);
            $this->vacations->assertCanManageQueues($membership);
            if ($step !== FirstStep::MetalMine && $step !== FirstStep::Research) {
                return 'unavailable';
            }
            $reward = $this->pending($membership, $step);
            if ($reward === null) {
                return 'unavailable';
            }

            $amounts = array_filter(['metal' => (int) $reward['metal'], 'nano' => (int) $reward['nano']]);
            $deposit = $this->resources->deposit($planet, $amounts, allOrNothing: true);
            if (max([0.0, ...array_values($deposit['overflow'])]) > 0.0) {
                // Pas de versement partiel ni de consommation de la preuve si la colonie est pleine.
                return 'full';
            }

            $this->markClaimed((int) $reward['id']);
            return 'claimed';
        });
    }

    /** @param array{metal: int, nano: int} $refund */
    private function reserve(PlayerUniverse $membership, FirstStep $step, array $refund): void
    {
        if (!$membership->isPlayerControlled()) {
            return;
        }
        if (!$this->db->isTransactionActive()) {
            throw new \LogicException('Le lot tutoriel doit suivre la transaction du résultat réel.');
        }

        // Les univers n’ont pas les mêmes verrous métier : l’unicité du compte en base
        // est le dernier arbitre et conserve le premier empire qui a gagné le droit.
        $this->db->executeStatement(
            'INSERT INTO first_step_reward (player_id, source_membership_id, step_key, metal, nano, claimed_at) VALUES (?, ?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE step_key = VALUES(step_key)',
            [$membership->player->id, $membership->id, $step->value, $refund['metal'], $refund['nano']],
        );
    }

    private function pending(PlayerUniverse $membership, FirstStep $step): ?array
    {
        $reward = $this->db->fetchAssociative(
            'SELECT id, metal, nano FROM first_step_reward
             WHERE player_id = ? AND source_membership_id = ? AND step_key = ? AND claimed_at IS NULL FOR UPDATE',
            [$membership->player?->id, $membership->id, $step->value],
        );

        return $reward === false ? null : $reward;
    }

    private function markClaimed(int $rewardId): void
    {
        $this->db->executeStatement(
            'UPDATE first_step_reward SET claimed_at = ? WHERE id = ?',
            [$this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'), $rewardId],
        );
    }
}
