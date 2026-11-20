<?php

namespace App\Repository;

use Doctrine\DBAL\Connection;

/** Projections minimales, sans chargement des messages, courriels ou jetons. */
final class AdminGuildReadRepository
{
    private const PAGE_SIZE = 25;

    public function __construct(private Connection $connection)
    {
    }

    public function universes(): array
    {
        return $this->connection->fetchAllAssociative('SELECT id, name FROM universe ORDER BY name, id');
    }

    public function listing(int $universeId, string $search, int $after): array
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT g.id, g.name, g.tag, g.universe_id, u.name AS universe_name
             FROM guild g JOIN universe u ON u.id=g.universe_id
             WHERE g.archived_at IS NULL AND g.id > :after
             AND (:universe = 0 OR g.universe_id = :universe)
             AND LOCATE(:search, LOWER(CONCAT(g.name, \' \', COALESCE(g.tag, \'\')))) > 0
             ORDER BY g.id LIMIT 26',
            ['after' => max(0, $after), 'universe' => max(0, $universeId), 'search' => mb_strtolower($search)],
        );
        return $this->page($rows);
    }

    public function guild(int $universeId, int $id): array|false
    {
        return $this->connection->fetchAssociative(
            'SELECT g.id, g.name, g.tag, g.universe_id, g.leader_id,
                    u.name AS universe_name, p.username AS leader_name
             FROM guild g JOIN universe u ON u.id=g.universe_id
             JOIN player_universe membership ON membership.id=g.leader_id AND membership.universe_id=g.universe_id
             LEFT JOIN player p ON p.id=membership.player_id
             WHERE g.id=:guild AND g.universe_id=:universe AND g.archived_at IS NULL',
            ['guild' => $id, 'universe' => $universeId],
        );
    }

    public function section(array $guild, string $section, int $after): array
    {
        // Chaque section filtre de nouveau l'univers ; aucune association incohérente
        // ne peut faire apparaître une autre communauté dans cette fiche.
        $sql = match ($section) {
            'members' => 'SELECT gm.id, membership.id AS membership_id, p.username, guildRank.name AS rank_name
                FROM guild_member gm
                JOIN player_universe membership ON membership.id=gm.player_universe_id AND membership.universe_id=:universe
                LEFT JOIN player p ON p.id=membership.player_id
                LEFT JOIN guild_rank guildRank ON guildRank.id=gm.rank_id AND guildRank.guild_id=gm.guild_id
                WHERE gm.guild_id=:guild AND gm.id>:after ORDER BY gm.id LIMIT 26',
            'diplomacy' => 'SELECT relation.id, peer.name, peer.tag, relation.status, relation.proposal
                FROM guild_relation relation
                JOIN guild peer ON peer.id=CASE WHEN relation.guild_a_id=:guild THEN relation.guild_b_id ELSE relation.guild_a_id END
                WHERE (relation.guild_a_id=:guild OR relation.guild_b_id=:guild)
                AND peer.universe_id=:universe AND peer.archived_at IS NULL
                AND relation.id>:after ORDER BY relation.id LIMIT 26',
            'projects' => 'SELECT id, JSON_UNQUOTE(JSON_EXTRACT(snapshot, \'$.name\')) AS name,
                status, stage_index, JSON_LENGTH(JSON_EXTRACT(snapshot, \'$.stages\')) AS stage_count,
                started_at, completed_at, rewards_delivered_at
                FROM guild_project WHERE guild_id=:guild AND universe_id=:universe
                AND id>:after ORDER BY id LIMIT 26',
            default => throw new \InvalidArgumentException('Rubrique de guilde invalide.'),
        };
        return $this->page($this->connection->fetchAllAssociative($sql, [
            'guild' => (int) $guild['id'], 'universe' => (int) $guild['universe_id'], 'after' => max(0, $after),
        ]));
    }

    private function page(array $rows): array
    {
        $hasNext = count($rows) > self::PAGE_SIZE;
        $rows = array_slice($rows, 0, self::PAGE_SIZE);
        return ['rows' => $rows, 'next' => $hasNext ? (int) $rows[array_key_last($rows)]['id'] : null];
    }
}
