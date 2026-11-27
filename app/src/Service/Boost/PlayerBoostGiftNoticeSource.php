<?php

declare(strict_types=1);

namespace App\Service\Boost;

use App\Entity\PlayerUniverse;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final readonly class PlayerBoostGiftNoticeSource implements InventoryGiftNoticeSource
{
    private const UNREAD_FILTER = "player_universe_id = :membership AND gift_unread = 1
        AND status IN ('available', 'pending') AND (kind <> 'timed_boost' OR earned_at > :cutoff)";

    public function __construct(private Connection $connection, private ClockInterface $clock)
    {
    }

    public function key(): string
    {
        // Les formulaires déjà ouverts portent cette clé dans leur limite signée.
        return 'daily';
    }

    public function summary(PlayerUniverse $membership): array
    {
        $data = $this->connection->fetchAssociative(
            "SELECT COUNT(*) AS total, MAX(id) AS through_id,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) AS pending,
                COUNT(CASE WHEN status = 'available' AND kind = 'resource_box' THEN 1 END) AS boxes
             FROM player_boost WHERE ".self::UNREAD_FILTER,
            $this->parameters($membership),
        );

        return ['count' => (int) $data['total'], 'pending' => (int) $data['pending'], 'boxes' => (int) $data['boxes'],
            'through' => $data['through_id'] === null ? null : (int) $data['through_id']];
    }

    public function acknowledge(PlayerUniverse $membership, int $through, bool $single = false): void
    {
        $this->connection->executeStatement(
            'UPDATE player_boost SET gift_unread = 0 WHERE id '.($single ? '=' : '<=').' :through AND '.self::UNREAD_FILTER,
            $this->parameters($membership) + ['through' => $through],
        );
    }

    private function parameters(PlayerUniverse $membership): array
    {
        // Le délai de sept jours concerne l'objet, même si une tâche de purge ne l'a pas encore marqué expiré.
        return [
            'membership' => $membership->id,
            'cutoff' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify('-'.TimedBoostEffects::SHELF_SECONDS.' seconds')->format('Y-m-d H:i:s'),
        ];
    }
}
