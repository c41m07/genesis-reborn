<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sépare le stock de guilde livré du financement initial en force de construction.';
    }

    public function up(Schema $schema): void
    {
        // Aucun crédit rétroactif aux Halls financés ou posés avant cette migration.
        $this->addSql("CREATE TABLE guild_hall_stock (
            hall_id INT NOT NULL,
            materials JSON NOT NULL,
            PRIMARY KEY (hall_id),
            CONSTRAINT guild_hall_stock_hall_fk FOREIGN KEY (hall_id) REFERENCES guild_hall (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas supprimer les matériaux reçus des joueurs.');
    }
}
