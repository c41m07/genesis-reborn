<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

final class CatalogHistoryRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** Lecture bornée et filtrée en SQL : ne charge pas le journal administratif global. */
    public function page(int $universeId, string $group, string $slug, int $before): array
    {
        $rows = $this->connection->fetchAllAssociative(
            "SELECT id, created_at, details FROM admin_audit_log
             WHERE action = :action AND target_type = :target AND target_id = :universe
               AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.group')) = :family
               AND JSON_UNQUOTE(JSON_EXTRACT(details, '$.slug')) = :slug
               AND (:cursor = 0 OR id < :cursor)
             ORDER BY id DESC LIMIT 21",
            ['action' => 'universe_catalog_balance', 'target' => 'universe',
             'universe' => $universeId, 'family' => $group, 'slug' => $slug, 'cursor' => $before]
        );
        $more = count($rows) > 20;
        $rows = array_slice($rows, 0, 20);
        foreach ($rows as &$row) {
            $row['details'] = json_decode($row['details'], true, 512, JSON_THROW_ON_ERROR);
        }
        unset($row);
        return ['rows' => $rows, 'next' => $more ? (int) end($rows)['id'] : null];
    }
}
