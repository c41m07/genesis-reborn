<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Programmer explicitement la visibilité de l’Avent par univers, sans activer les éditions existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE advent_window (
            id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, edition_id INT NOT NULL,
            name VARCHAR(120) NOT NULL, visible_from DATETIME NOT NULL, visible_until DATETIME NOT NULL,
            PRIMARY KEY (id), INDEX edition_id (edition_id), INDEX advent_window_lookup (universe_id, visible_until, visible_from),
            FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE,
            FOREIGN KEY (edition_id) REFERENCES advent_edition (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE advent_window');
    }
}
