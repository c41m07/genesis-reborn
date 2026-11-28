<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enregistre les modules réellement construits pour chaque installation du Hall.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_hall_building (
            id INT AUTO_INCREMENT NOT NULL,
            hall_id INT NOT NULL,
            slug VARCHAR(32) NOT NULL,
            level INT NOT NULL,
            finishes_at DATETIME DEFAULT NULL,
            dispatch_token VARCHAR(36) DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX guild_hall_building_slot (hall_id, slug),
            CONSTRAINT guild_hall_building_hall_fk FOREIGN KEY (hall_id) REFERENCES guild_hall (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas effacer la progression acquise des bâtiments de guilde.');
    }
}
