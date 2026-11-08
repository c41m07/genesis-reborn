<?php
namespace App\Service\Boost;

use App\Entity\Fleet;
use App\Entity\PlayerBoost;
use App\Entity\PlayerUniverse;
use App\Entity\QueueItem;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
use App\Service\Fleet\FleetService;
use App\Service\Queue\QueueService;
use Psr\Clock\ClockInterface;

final class BoostUseService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private GameCatalog $catalog,
        private ActionGuard $guard,
        private QueueService $queues,
        private FleetService $fleets,
        private ClockInterface $clock,
        private \App\Service\Player\VacationCalendar $vacations,
    ) {}

    public function useOnQueue(PlayerUniverse $membership, int $queueId, string $requestKey): void
    {
        $this->manager->transaction(function () use ($membership, $queueId, $requestKey): void {
            $this->guard->lockMembership($membership);
            if (!$this->guard->receipt($membership, $requestKey)) {
                return;
            }
            $this->queues->synchronize($membership);
            $item = $this->repo->find(QueueItem::class, $queueId);
            if (!$item instanceof QueueItem || $item->playerUniverse->id !== $membership->id || !$item->startedAt || !$item->finishAt() || $item->finishAt() <= $this->clock->now()) {
                throw new \DomainException('Opération active introuvable.');
            }
            $this->manager->lock($item);
            $boost = $this->takeAvailableBoost($membership, 'queue', $queueId);
            $this->queues->applyPlayerBoost($item, $this->divisor());
            $this->manager->flush();
            $this->promoteOldestPending($membership);
            $this->manager->flush();
        });
    }

    public function useOnFleet(PlayerUniverse $membership, int $fleetId, string $requestKey): void
    {
        $this->manager->transaction(function () use ($membership, $fleetId, $requestKey): void {
            $this->guard->lockMembership($membership);
            $this->vacations->assertCanSendFleet($membership);
            if (!$this->guard->receipt($membership, $requestKey)) {
                return;
            }
            $fleet = $this->repo->find(Fleet::class, $fleetId);
            $allowed = ['traveling', 'returning', 'defending', 'orbiting'];
            if (!$fleet instanceof Fleet || $fleet->playerUniverse->id !== $membership->id || !in_array($fleet->status, $allowed, true) || !$fleet->arrivalAt || $fleet->arrivalAt <= $this->clock->now()) {
                throw new \DomainException('Mission chronométrée active introuvable.');
            }
            $this->manager->lock($fleet);
            $boost = $this->takeAvailableBoost($membership, 'fleet', $fleetId);
            $remaining = max(1, $fleet->arrivalAt->getTimestamp() - $this->clock->now()->getTimestamp());
            $newRemaining = max(1, (int) ceil($remaining * (1 - $this->ratio())));
            $newArrivalAt = $this->clock->now()->modify('+' . $newRemaining . ' seconds');
            $this->fleets->reschedulePlayerBoost($fleet, $newArrivalAt);
            $this->promoteOldestPending($membership);
            $this->manager->flush();
        });
    }

    private function takeAvailableBoost(PlayerUniverse $membership, string $operationType, int $operationId): PlayerBoost
    {
        $boost = $this->repo->oldestBoost($membership, 'available');
        if (!$boost) {
            throw new \DomainException('Aucun boost disponible dans votre inventaire.');
        }
        $this->manager->lock($boost);
        if ($boost->status !== 'available' || $boost->playerUniverse->id !== $membership->id) {
            throw new \DomainException('Ce boost n’est plus disponible.');
        }
        $boost->status = 'used';
        $boost->usedAt = $this->clock->now();
        $boost->operationType = $operationType;
        $boost->operationId = $operationId;

        return $boost;
    }

    private function promoteOldestPending(PlayerUniverse $membership): void
    {
        if ($this->repo->countBoosts($membership, 'available') >= $this->inventoryCapacity()) {
            return;
        }
        $pending = $this->repo->oldestBoost($membership, 'pending');
        if ($pending) {
            $this->manager->lock($pending);
            if ($pending->status === 'pending') {
                $pending->status = 'available';
            }
        }
    }

    private function inventoryCapacity(): int
    {
        return max(1, min(100, (int) ($this->catalog->all('boosts')['inventory_capacity'] ?? 100)));
    }

    private function ratio(): float
    {
        $ratio = (float) ($this->catalog->all('boosts')['duration_reduction_ratio'] ?? 0.25);
        if (!is_finite($ratio) || $ratio <= 0 || $ratio >= 1) {
            throw new \LogicException('Ratio de réduction des boosts invalide.');
        }

        return $ratio;
    }

    private function divisor(): float
    {
        return 1 / (1 - $this->ratio());
    }
}
