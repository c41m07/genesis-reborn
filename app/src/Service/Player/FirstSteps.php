<?php

declare(strict_types=1);

namespace App\Service\Player;

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

        return array_fill_keys($keys, true);
    }

    public function recordQueueCompletion(QueueItem $item, \DateTimeImmutable $finishedAt): void
    {
        $step = FirstStep::forCompletedQueue($item);
        $membership = $item->playerUniverse;
        if ($step === null || !$membership->isPlayerControlled()) {
            return;
        }

        $connection = $this->entityManager->getConnection();
        if (!$connection->isTransactionActive()) {
            throw new \LogicException('La fin de file doit porter la preuve dans sa transaction.');
        }

        // Le verrou de participation et la transaction viennent de la file qui applique le résultat.
        // Une nouvelle fin valide ne doit ni modifier la première preuve ni créer un doublon.
        $connection->executeStatement(
            'INSERT INTO first_step_completion (player_universe_id, step_key, completed_at) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE step_key = VALUES(step_key)',
            [$membership->id, $step->value, $finishedAt->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')],
        );
    }
}
