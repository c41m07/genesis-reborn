<?php
namespace App\Service\Boost;

use App\Entity\PlayerUniverse;
use App\Entity\QueueItem;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Colony\ActionGuard;
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
        private ClockInterface $clock,
        private BoostGrantService $grants,
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
            $this->takeAvailableBoost($membership, $queueId);
            $this->queues->applyPlayerBoost($item, $this->divisor());
            $this->manager->flush();
            $this->grants->promoteOldestPendingLocked($membership);
            $this->manager->flush();
        });
    }

    private function takeAvailableBoost(PlayerUniverse $membership, int $queueId): void
    {
        $boost = $this->repo->oldestBoost($membership, 'available', 'boost');
        if (!$boost) {
            throw new \DomainException('Aucun boost disponible dans votre inventaire.');
        }
        $this->manager->lock($boost);
        if ($boost->status !== 'available' || $boost->playerUniverse->id !== $membership->id) {
            throw new \DomainException('Ce boost n’est plus disponible.');
        }
        $boost->status = 'used';
        $boost->usedAt = $this->clock->now();
        $boost->operationType = 'queue';
        $boost->operationId = $queueId;
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
