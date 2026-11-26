<?php

declare(strict_types=1);

namespace App\Repository;

use App\Service\Catalog\GameCatalog;
use Doctrine\DBAL\Connection;

final readonly class AdminExpenseReadRepository
{
    public const RESOURCES = ['metal', 'nano', 'hydrogen', 'antimatter', 'electricity'];
    public const CATEGORIES = ['buildings', 'technologies', 'ships', 'defenses'];

    public function __construct(private Connection $connection)
    {
    }

    public function catalogueAvailableSince(): ?\DateTimeImmutable
    {
        return $this->availableSince('catalogue');
    }

    public function fuelAvailableSince(): ?\DateTimeImmutable
    {
        return $this->availableSince('fuel');
    }

    private function availableSince(string $source): ?\DateTimeImmutable
    {
        $value = $this->connection->fetchOne('SELECT available_since FROM expense_collection WHERE source = ?', [$source]);

        return $value === false ? null : new \DateTimeImmutable((string) $value, new \DateTimeZone('UTC'));
    }

    public function fuelForMembership(int $universeId, int $membershipId, \DateTimeImmutable $from, \DateTimeImmutable $until): float
    {
        $utc = new \DateTimeZone('UTC');
        return (float) $this->connection->fetchOne(
            'SELECT COALESCE(SUM(e.hydrogen), 0)
            FROM fleet_fuel_expense e JOIN player_universe u ON u.id = e.player_universe_id
            WHERE u.universe_id = ? AND e.player_universe_id = ? AND e.burned_at >= ? AND e.burned_at < ?',
            [$universeId, $membershipId, $from->setTimezone($utc)->format('Y-m-d H:i:s'), $until->setTimezone($utc)->format('Y-m-d H:i:s')],
        );
    }

    /** Les pseudos suffisent à la sélection ; aucune adresse ni donnée de compte ne sort. */
    public function commanders(int $universeId, string $prefix, int $selectedId): array
    {
        $source = ' FROM player_universe u JOIN player p ON p.id = COALESCE(u.player_id, u.former_player_id)
            WHERE u.universe_id = ?';
        $columns = 'SELECT u.id, p.username, (u.player_id IS NULL) AS former';
        $search = strtr($prefix, ['!' => '!!', '%' => '!%', '_' => '!_']);
        $options = $this->connection->fetchAllAssociative(
            $columns . $source . " AND p.username LIKE ? ESCAPE '!' ORDER BY p.username, u.id LIMIT 25",
            [$universeId, $search.'%'],
        );
        $selected = $selectedId > 0 ? $this->connection->fetchAssociative(
            $columns . $source . ' AND u.id = ?',
            [$universeId, $selectedId],
        ) : false;
        if ($selected && !in_array($selectedId, array_map('intval', array_column($options, 'id')), true)) {
            array_unshift($options, $selected);
        }

        return ['options' => $options, 'selected' => $selected ?: null];
    }

    /**
     * @return array<string, array{paid: array<string, float>, refunded: array<string, float>, net: array<string, float>, material_net: float}>
     */
    public function catalogueForMembership(int $universeId, int $membershipId, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        if ($until <= $from) {
            throw new \InvalidArgumentException('La période de dépenses doit avoir une durée positive.');
        }
        $utc = new \DateTimeZone('UTC');
        $parameters = [
            $universeId,
            $membershipId,
            $from->setTimezone($utc)->format('Y-m-d H:i:s'),
            $until->setTimezone($utc)->format('Y-m-d H:i:s'),
        ];
        $zeros = array_fill_keys(self::RESOURCES, 0.0);
        $totals = array_fill_keys(self::CATEGORIES, [
            'paid' => $zeros,
            'refunded' => $zeros,
            'net' => $zeros,
            'material_net' => 0.0,
        ]);

        // Deux dates indépendantes permettent de compter un remboursement sans réimporter l’achat.
        // Le filtre univers + participation s’applique aux deux lectures, même avec un ID fourni en URL.
        foreach (['paid', 'refunded'] as $kind) {
            $totalsByResource = [];
            foreach (self::RESOURCES as $resource) {
                // Colonnes et chemins JSON fixes ; les données de requête restent des paramètres SQL.
                $totalsByResource[] = "COALESCE(SUM(CAST(JSON_VALUE(e.{$kind}, '$.{$resource}') AS DECIMAL(30,9))), 0) AS {$resource}";
            }
            $rows = $this->connection->fetchAllAssociative(
                'SELECT e.category, '.implode(', ', $totalsByResource)."
                FROM queue_expense e JOIN player_universe u ON u.id = e.player_universe_id
                WHERE u.universe_id = ? AND e.player_universe_id = ? AND e.{$kind}_at >= ? AND e.{$kind}_at < ?
                GROUP BY e.category",
                $parameters,
            );
            foreach ($rows as $row) {
                $category = $row['category'];
                if (!isset($totals[$category])) {
                    throw new \UnexpectedValueException('Catégorie de dépense non reconnue.');
                }
                foreach (self::RESOURCES as $resource) {
                    $totals[$category][$kind][$resource] = (float) $row[$resource];
                }
            }
        }
        foreach ($totals as &$category) {
            foreach (self::RESOURCES as $resource) {
                $net = $category['paid'][$resource] - $category['refunded'][$resource];
                $category['net'][$resource] = $net;
                $category['material_net'] += $net * (GameCatalog::MATERIAL_WEIGHTS[$resource] ?? 0);
            }
        }
        unset($category);

        return $totals;
    }
}
