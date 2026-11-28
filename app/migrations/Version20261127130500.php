<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Sépare les vaisseaux donnés à l’Atelier des biens personnels encore en vol ou stationnés.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_atelier_stock (
            hall_id INT NOT NULL,
            ships JSON NOT NULL,
            PRIMARY KEY (hall_id),
            CONSTRAINT guild_atelier_stock_hall_fk FOREIGN KEY (hall_id) REFERENCES guild_hall (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas effacer les vaisseaux déjà donnés à l’Atelier.');
    }
}
