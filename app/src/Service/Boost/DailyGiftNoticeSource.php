<?php

declare(strict_types=1);

namespace App\Service\Boost;

use App\Entity\PlayerUniverse;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;

final readonly class DailyGiftNoticeSource implements InventoryGiftNoticeSource
{
    private const PENDING_FILTER = "player_universe_id = :membership AND gift_unread = 1
        AND kind = 'timed_boost' AND status IN ('available', 'pending') AND earned_at > :cutoff";

    public function __construct(private Connection $connection, private ClockInterface $clock)
    {
    }

    public function key(): string
    {
        return 'daily';
    }

    public function summary(PlayerUniverse $membership): array
    {
        $data = $this->connection->fetchAssociative(
            'SELECT COUNT(*) AS total, MAX(id) AS through_id FROM player_boost WHERE '.self::PENDING_FILTER,
            $this->parameters($membership),
        );

        return ['count' => (int) $data['total'], 'through' => $data['through_id'] === null ? null : (int) $data['through_id']];
    }

    public function acknowledge(PlayerUniverse $membership, int $through): void
    {
        $this->connection->executeStatement(
            'UPDATE player_boost SET gift_unread = 0 WHERE id <= :through AND '.self::PENDING_FILTER,
            $this->parameters($membership) + ['through' => $through],
        );
    }

    private function parameters(PlayerUniverse $membership): array
    {
        // Le délai de sept jours concerne l'objet, même si une tâche de purge ne l'a pas encore marqué expiré.
        return [
            'membership' => $membership->id,
            'cutoff' => $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->modify('-604800 seconds')->format('Y-m-d H:i:s'),
        ];
    }
}
