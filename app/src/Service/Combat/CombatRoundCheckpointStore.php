<?php

namespace App\Service\Combat;

use Doctrine\ORM\EntityManagerInterface;

/** Le verrou et la transaction du combat couvrent aussi ses fragments binaires encodés. */
final class CombatRoundCheckpointStore
{
    private const PART_BYTES = 262144;
    private const PARTS_PER_INSERT = 4;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    public function load(int $eventId, ?array $checkpoint): ?array
    {
        if ($checkpoint === null || !isset($checkpoint['payload_parts'])) {
            return $checkpoint;
        }

        $parts = $this->entityManager->getConnection()->fetchAllAssociative(
            'SELECT part_number, payload FROM combat_round_checkpoint_part WHERE combat_event_id = ? ORDER BY part_number',
            [$eventId],
        );
        if (count($parts) !== $checkpoint['payload_parts']) {
            throw new \LogicException('Point de reprise du combat incomplet.');
        }

        $payload = '';
        foreach ($parts as $index => $part) {
            if ((int) $part['part_number'] !== $index) {
                throw new \LogicException('Fragment du point de reprise hors séquence.');
            }
            $payload .= $part['payload'];
        }
        if (!hash_equals($checkpoint['payload_hash'], hash('sha256', $payload))) {
            throw new \LogicException('Les fragments du point de reprise ne correspondent pas au combat.');
        }

        unset($checkpoint['payload_parts'], $checkpoint['payload_hash']);
        $checkpoint['payload'] = $payload;

        return $checkpoint;
    }

    public function save(int $eventId, array $checkpoint): array
    {
        if (!isset($checkpoint['payload'])) {
            return $checkpoint;
        }

        // Un seul gros JSON de plusieurs Mio peut dépasser le paquet MariaDB ; les écritures restent atomiques mais bornées par requête.
        $payload = $checkpoint['payload'];
        if (!is_string($payload) || $payload === '') {
            throw new \LogicException('Point de reprise compact sans données.');
        }
        $this->delete($eventId);
        $connection = $this->entityManager->getConnection();
        $parts = str_split($payload, self::PART_BYTES);
        foreach (array_chunk($parts, self::PARTS_PER_INSERT, true) as $batch) {
            $params = [];
            foreach ($batch as $index => $part) {
                array_push($params, $eventId, $index, $part);
            }
            $connection->executeStatement(
                'INSERT INTO combat_round_checkpoint_part (combat_event_id, part_number, payload) VALUES '
                    . implode(', ', array_fill(0, count($batch), '(?, ?, ?)')),
                $params,
            );
        }

        unset($checkpoint['payload']);
        $checkpoint['payload_parts'] = count($parts);
        $checkpoint['payload_hash'] = hash('sha256', $payload);

        return $checkpoint;
    }

    public function delete(int $eventId): void
    {
        $this->entityManager->getConnection()->delete('combat_round_checkpoint_part', ['combat_event_id' => $eventId]);
    }
}
