<?php

namespace App\Service\Admin;

use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

/** Lecture ciblée : aucune preuve locale ne vaut autorisation de rejouer un effet. */
final class OutboxRecoveryInspection
{
    public function __construct(private Connection $connection, private ClockInterface $clock)
    {
    }

    /** @return array{id: int, target_id: int, kind: string, state: string, explanation: string, replay_allowed: false}|null */
    public function inspect(int $id): ?array
    {
        // Une seule lecture bornée rapproche le jeton du relais de la commande.
        // Les secrets ne quittent jamais ce service, même pour une cible absente.
        $row = $this->connection->fetchAssociative(
            "SELECT o.id, o.kind, o.target_id, o.token, o.due_at,
                    q.id AS queue_id, q.dispatch_token, q.started_at, q.duration_seconds,
                    q.player_universe_id, p.owner_id
             FROM outbox_message o
             LEFT JOIN queue_item q ON o.kind = 'queue' AND q.id = o.target_id
             LEFT JOIN planet p ON p.id = q.planet_id
             WHERE o.id = ?",
            [$id],
        );
        if ($row === false) {
            return null;
        }

        [$state, $explanation] = $this->classify($row);

        return [
            'id' => (int) $row['id'],
            'target_id' => (int) $row['target_id'],
            'kind' => (string) $row['kind'],
            'state' => $state,
            'explanation' => $explanation,
            'replay_allowed' => false,
        ];
    }

    /** @param array<string, mixed> $row @return array{string, string} */
    private function classify(array $row): array
    {
        if ($row['kind'] !== 'queue') {
            return ['unsupported', 'Cette nature ne dispose pas encore d’une analyse de cible.'];
        }
        if ($row['queue_id'] === null) {
            return ['target_absent', 'Commande absente : elle peut être terminée ou annulée. Ne pas la recréer.'];
        }
        if (!hash_equals((string) $row['dispatch_token'], (string) $row['token'])) {
            return ['superseded', 'Le jeton de la commande a changé : cet ancien événement ne doit pas être réémis.'];
        }
        if ($row['owner_id'] === null || (int) $row['owner_id'] !== (int) $row['player_universe_id']) {
            return ['ownership_changed', 'Le propriétaire actuel ne correspond plus à la commande. Examen métier nécessaire.'];
        }
        if ($row['started_at'] === null) {
            return ['not_started', 'La commande n’est pas démarrée. Une dépendance ou une autre opération peut la retenir.'];
        }
        $utc = new \DateTimeZone('UTC');
        $finishAt = (new \DateTimeImmutable($row['started_at'], $utc))->modify('+'.(int) $row['duration_seconds'].' seconds');
        $relayDueAt = new \DateTimeImmutable($row['due_at'], $utc);
        if ($finishAt > $this->clock->now() || $relayDueAt > $this->clock->now()) {
            return ['not_due', 'L’échéance de la commande ou de l’événement est future. Ne pas forcer son achèvement.'];
        }

        // complete() réconcilie le membre entier. Ce contrôle ne prouve ni la
        // tête de file ni l’absence d’une exécution concurrente ou déjà publiée.
        return ['due_unproven', 'Échéance atteinte et jeton actuel, mais absence d’effet non prouvée. Le traitement réconcilie aussi les autres files du membre.'];
    }
}
