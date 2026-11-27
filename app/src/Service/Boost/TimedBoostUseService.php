<?php

declare(strict_types=1);

namespace App\Service\Boost;

use App\Entity\PlayerBoost;
use App\Entity\PlayerUniverse;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Colony\ActionGuard;
use App\Service\Player\VacationCalendar;
use App\Service\Queue\QueueService;
use Psr\Clock\ClockInterface;

/** Active un objet d’inventaire identifié, sans consommer un ancien boost de file. */
final readonly class TimedBoostUseService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private ActionGuard $guard,
        private VacationCalendar $vacations,
        private ClockInterface $clock,
        private BoostGrantService $grants,
        private TimedBoostEffects $effects,
        private QueueService $queues,
    ) {}

    public function activate(PlayerUniverse $membership, int $id, string $requestKey): bool
    {
        return $this->manager->transaction(function () use ($membership, $id, $requestKey): bool {
            $this->guard->lockMembership($membership);
            $this->vacations->assertCanManageQueues($membership);
            if (!$this->guard->receipt($membership, $requestKey)) {
                return false;
            }
            $item = $this->repo->find(PlayerBoost::class, $id);
            if (!$item instanceof PlayerBoost || $item->playerUniverse->id !== $membership->id || $item->kind !== TimedBoostEffects::KIND) {
                throw new \DomainException('Boost temporaire introuvable dans cet univers.');
            }
            $this->manager->lockCurrent($item);
            $now = $this->clock->now();
            if ($item->status !== 'available' || TimedBoostEffects::expiresAt($item) <= $now) {
                throw new \DomainException('Ce boost n’est plus disponible.');
            }
            $effect = (string) ($item->payload['effect'] ?? '');
            TimedBoostEffects::definition($effect);
            // Les activations partagent le verrou de l’empire : même avec deux objets,
            // aucun second effet ne commence avant la fin du premier.
            if ($this->effects->windows($membership, $now, $now) !== []) {
                throw new \DomainException('Un boost temporaire est déjà actif. Attendez sa fin pour en activer un autre.');
            }
            if ($effect === TimedBoostEffects::QUEUE_SPEED) {
                $this->queues->synchronize($membership, $now);
            }
            $item->status = 'used';
            $item->usedAt = $now;
            $item->operationType = $effect;
            $this->manager->flush();
            if ($effect === TimedBoostEffects::QUEUE_SPEED) {
                $this->queues->activateTemporaryThroughputLocked($membership, $now, TimedBoostEffects::endsAt($item));
            }
            $this->grants->promoteOldestPendingLocked($membership);
            $this->manager->flush();

            return true;
        });
    }
}
