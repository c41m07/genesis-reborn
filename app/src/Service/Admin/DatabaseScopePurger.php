<?php

declare(strict_types=1);

namespace App\Service\Admin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;

/** Supprime un arbre de données rattaché à des enregistrements racines, à partir des clés étrangères. */
final class DatabaseScopePurger
{
    public function __construct(private readonly Connection $connection)
    {
    }

    /**
     * @param list<int> $rootIds
     * @param list<string> $preservedTables Tables à préserver si elles apparaissent comme dépendances.
     */
    public function purge(string $rootTable, array $rootIds, array $preservedTables = []): int
    {
        $rootIds = array_values(array_unique(array_map('intval', $rootIds)));
        if ($rootIds === []) {
            return 0;
        }

        $schemaManager = $this->connection->createSchemaManager();
        $tables = [];
        foreach ($schemaManager->listTables() as $table) {
            $tables[strtolower($table->getName())] = $table;
        }

        $rootTable = strtolower($rootTable);
        if (!isset($tables[$rootTable])) {
            throw new \InvalidArgumentException('Table racine inconnue.');
        }

        $preserved = array_fill_keys(array_map('strtolower', $preservedTables), true);
        $idsByTable = [$rootTable => $rootIds];
        $queue = [[$rootTable, $rootIds]];
        $playersToDetach = [];

        while ($queue !== []) {
            [$parentName, $parentIds] = array_shift($queue);
            foreach ($tables as $childName => $childTable) {
                foreach ($childTable->getForeignKeys() as $foreignKey) {
                    if (strtolower($foreignKey->getForeignTableName()) !== $parentName) {
                        continue;
                    }

                    $localColumns = $foreignKey->getLocalColumns();
                    $foreignColumns = $foreignKey->getForeignColumns();
                    if (count($localColumns) !== 1 || count($foreignColumns) !== 1 || strtolower($foreignColumns[0]) !== 'id') {
                        throw new \RuntimeException(sprintf('Clé étrangère composite non prise en charge (%s).', $foreignKey->getName()));
                    }

                    if (isset($preserved[$childName])) {
                        if ($rootTable === 'universe' && $childName === 'player' && $parentName === 'player_universe') {
                            $playersToDetach[$localColumns[0]] = array_values(array_unique([...( $playersToDetach[$localColumns[0]] ?? []), ...$parentIds]));
                        }
                        continue;
                    }

                    $primaryKey = $childTable->getPrimaryKey();
                    if ($primaryKey === null || count($primaryKey->getColumns()) !== 1) {
                        throw new \RuntimeException(sprintf('Clé primaire non prise en charge pour %s.', $childName));
                    }

                    $idColumn = $primaryKey->getColumns()[0];
                    $query = $this->connection->createQueryBuilder()
                        ->select($this->connection->quoteIdentifier($idColumn))
                        ->from($this->connection->quoteIdentifier($childTable->getName()))
                        ->where($this->connection->quoteIdentifier($localColumns[0]).' IN (:parent_ids)')
                        ->setParameter('parent_ids', $parentIds, ArrayParameterType::INTEGER);
                    $foundIds = array_map('intval', $query->executeQuery()->fetchFirstColumn());
                    $newIds = array_values(array_diff($foundIds, $idsByTable[$childName] ?? []));
                    if ($newIds === []) {
                        continue;
                    }

                    $idsByTable[$childName] = array_values(array_unique([...( $idsByTable[$childName] ?? []), ...$newIds]));
                    $queue[] = [$childName, $newIds];
                }
            }
        }

        $count = array_sum(array_map('count', $idsByTable));
        $this->connection->beginTransaction();
        $foreignKeysDisabled = false;
        try {
            if ($this->connection->getDatabasePlatform() instanceof AbstractMySQLPlatform) {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 0');
                $foreignKeysDisabled = true;
            }

            if (isset($tables['outbox_message'])) {
                $outboxTargets = ['queue_item' => 'queue', 'fleet' => 'fleet', 'combat_event' => 'combat', 'universe' => 'universe', 'notification' => 'notification', 'guild_project' => 'guild_project'];
                foreach ($outboxTargets as $targetTable => $kind) {
                    if (!isset($idsByTable[$targetTable])) {
                        continue;
                    }
                    $this->connection->executeStatement(
                        sprintf('DELETE FROM %s WHERE %s = ? AND %s IN (?)', $this->connection->quoteIdentifier($tables['outbox_message']->getName()), $this->connection->quoteIdentifier('kind'), $this->connection->quoteIdentifier('target_id')),
                        [$kind, $idsByTable[$targetTable]],
                        [\Doctrine\DBAL\ParameterType::STRING, ArrayParameterType::INTEGER],
                    );
                }
            }

            if ($playersToDetach !== []) {
                $playerTable = $this->connection->quoteIdentifier($tables['player']->getName());
                foreach ($playersToDetach as $column => $membershipIds) {
                    $this->connection->executeStatement(
                        sprintf('UPDATE %s SET %s = NULL WHERE %s IN (?)', $playerTable, $this->connection->quoteIdentifier($column), $this->connection->quoteIdentifier($column)),
                        [$membershipIds],
                        [ArrayParameterType::INTEGER],
                    );
                }
            }

            foreach ($idsByTable as $tableName => $ids) {
                $primaryKey = $tables[$tableName]->getPrimaryKey();
                if ($primaryKey === null || count($primaryKey->getColumns()) !== 1) {
                    throw new \RuntimeException(sprintf('Clé primaire non prise en charge pour %s.', $tableName));
                }
                $this->connection->executeStatement(
                    sprintf('DELETE FROM %s WHERE %s IN (?)', $this->connection->quoteIdentifier($tables[$tableName]->getName()), $this->connection->quoteIdentifier($primaryKey->getColumns()[0])),
                    [$ids],
                    [ArrayParameterType::INTEGER],
                );
            }

            $this->connection->commit();
        } catch (\Throwable $error) {
            $this->connection->rollBack();
            throw $error;
        } finally {
            if ($foreignKeysDisabled) {
                $this->connection->executeStatement('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        return $count;
    }
}
