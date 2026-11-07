<?php
namespace App\Service\Universe;

use App\Entity\OutboxMessage;
use App\Entity\Universe;
use App\Manager\GameManager;
use App\Repository\GameRepository;
use Psr\Clock\ClockInterface;

final class UniverseLifecycleService
{
    public function __construct(
        private GameManager $manager,
        private GameRepository $repo,
        private ClockInterface $clock,
    ) {}

    public function setEndDate(int $universeId, ?\DateTimeImmutable $endsAt): void
    {
        $this->manager->transaction(function () use ($universeId, $endsAt): void {
            $universe = $this->repo->find(Universe::class, $universeId);
            if (!$universe instanceof Universe) {
                throw new \DomainException('Univers introuvable.');
            }

            $this->manager->lock($universe);
            if ($universe->status === 'closed') {
                throw new \DomainException('Un univers fermé ne peut pas être réouvert.');
            }

            $universe->endsAt = $endsAt;
            $this->replaceClosureOutbox($universe);
        });
    }

    public function closeIfDue(int $universeId, string $scheduleToken): void
    {
        $this->manager->transaction(function () use ($universeId, $scheduleToken): void {
            $universe = $this->repo->find(Universe::class, $universeId);
            if (!$universe instanceof Universe) {
                return;
            }

            $this->manager->lock($universe);
            if (
                $universe->status === 'closed'
                || $universe->endsAt === null
                || $universe->endsAt > $this->clock->now()
                || !hash_equals(self::closureToken($universeId, $universe->endsAt), $scheduleToken)
            ) {
                return;
            }

            $universe->status = 'closed';
            $this->removePendingClosures($universeId);
        });
    }

    public static function closureToken(int $universeId, \DateTimeImmutable $endsAt): string
    {
        return substr(hash('sha256', $universeId . '|' . $endsAt->format('U')), 0, 36);
    }

    private function replaceClosureOutbox(Universe $universe): void
    {
        $this->removePendingClosures($universe->id);
        if ($universe->endsAt === null) {
            return;
        }

        $outbox = new OutboxMessage();
        $outbox->kind = 'universe';
        $outbox->targetId = $universe->id;
        $outbox->token = self::closureToken($universe->id, $universe->endsAt);
        $outbox->dueAt = $universe->endsAt;
        $this->manager->save($outbox);
    }

    private function removePendingClosures(int $universeId): void
    {
        foreach ($this->repo->all(OutboxMessage::class, ['kind' => 'universe', 'targetId' => $universeId]) as $outbox) {
            $this->manager->remove($outbox);
        }
    }
}
