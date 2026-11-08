<?php
namespace App\Service\Admin;

use App\Entity\Player;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

final class AdminNotificationService
{
    private const SOURCES = [
        'reports' => ['message_report', "n.status = 'open'"],
        'security' => ['admin_audit_log', "n.action = 'admin_access_denied' AND NOT EXISTS (SELECT 1 FROM admin_audit_log r WHERE r.action = 'admin_access_reviewed' AND r.target_type = 'admin_access_incident' AND r.target_id = n.id)"],
        'feedbacks' => ['user_feedback', "n.status <> 'archived'"],
    ];

    public function __construct(private EntityManagerInterface $em) {}

    public function counts(?Player $player): array
    {
        $counts = ['reports' => 0, 'security' => 0, 'feedbacks' => 0, 'total' => 0];
        if (!$player || !in_array('ROLE_ADMIN', $player->getRoles(), true)) return $counts;
        $seen = $player->preferences['admin_last_seen'] ?? [];
        foreach (self::SOURCES as $category => [$table, $condition]) {
            $counts[$category] = (int) $this->em->getConnection()->fetchOne(
                "SELECT COUNT(*) FROM $table n WHERE $condition AND n.id > :seen",
                ['seen' => max(0, (int) ($seen[$category] ?? 0))],
            );
            $counts['total'] += $counts[$category];
        }
        return $counts;
    }

    public function markVisited(Player $player, array $latestIds): void
    {
        if (!in_array('ROLE_ADMIN', $player->getRoles(), true)) return;
        $latestIds = array_intersect_key($latestIds, self::SOURCES);
        $seen = $player->preferences['admin_last_seen'] ?? [];
        if (!array_filter($latestIds, static fn (int $id, string $category): bool => $id > (int) ($seen[$category] ?? 0), ARRAY_FILTER_USE_BOTH)) return;
        $this->em->wrapInTransaction(function () use ($player, $latestIds): void {
            $this->em->refresh($player, LockMode::PESSIMISTIC_WRITE);
            $seen = $player->preferences['admin_last_seen'] ?? [];
            foreach ($latestIds as $category => $id) $seen[$category] = max((int) ($seen[$category] ?? 0), $id);
            $player->preferences['admin_last_seen'] = $seen;
        });
    }
}
