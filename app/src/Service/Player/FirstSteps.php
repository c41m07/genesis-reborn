<?php

declare(strict_types=1);

namespace App\Service\Player;

use App\Entity\Fleet;
use App\Entity\PlayerDiscovery;
use App\Entity\PlayerUniverse;
use App\Entity\QueueItem;
use Doctrine\ORM\EntityManagerInterface;

final class FirstSteps
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return array<string, true> */
    public function completed(PlayerUniverse $membership): array
    {
        $keys = $this->entityManager->getConnection()->fetchFirstColumn(
            'SELECT step_key FROM first_step_completion WHERE player_universe_id = ?',
            [$membership->id],
        );

        // Une version antérieure du guide ne doit pas compter les jalons d’une version plus récente.
        return array_fill_keys(array_filter($keys, static fn (string $key): bool => FirstStep::tryFrom($key) !== null), true);
    }

    public function recordQueueCompletion(QueueItem $item, \DateTimeImmutable $finishedAt, bool $shipConstructed = false): void
    {
        $step = FirstStep::forCompletedQueue($item, $shipConstructed);
        if ($step !== null) {
            $this->record($item->playerUniverse, $step, $finishedAt);
        }
    }

    public function recordExplorationDiscovery(Fleet $fleet, PlayerDiscovery $discovery): void
    {
        $destination = $fleet->destinationSystem;
        $membership = $fleet->playerUniverse;
        if ($fleet->mission !== 'explore' || $fleet->status !== 'traveling' || $fleet->arrivalAt === null
            || $destination === null || $discovery->solarSystem !== $destination || $discovery->playerUniverse !== $membership
            || $destination->galaxy->universe->id !== $membership->universe->id
            || !$this->entityManager->getUnitOfWork()->isScheduledForInsert($discovery)) {
            return;
        }

        // La preuve accompagne la nouvelle découverte, pas un système déjà connu ni le départ.
        $this->record($membership, FirstStep::Exploration, $fleet->arrivalAt);
    }

    private function record(PlayerUniverse $membership, FirstStep $step, \DateTimeImmutable $finishedAt): void
    {
        if (!$membership->isPlayerControlled()) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('Le résultat de jeu doit porter la preuve dans sa transaction.');
        }

        // Le verrou de participation et la transaction viennent de l’action qui applique le résultat.
        // Une nouvelle fin valide ne doit ni modifier la première preuve ni créer un doublon.
        $connection->executeStatement(
            'INSERT INTO first_step_completion (player_universe_id, step_key, completed_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE step_key = VALUES(step_key)',
            [$membership->id, $step->value, $finishedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );
    }
}
