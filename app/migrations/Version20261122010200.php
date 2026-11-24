<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la construction de Hall et sa revendication unique de système sans modifier les planètes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guild_hall (
            id INT AUTO_INCREMENT NOT NULL,
            guild_id INT NOT NULL,
            solar_system_id INT DEFAULT NULL,
            required_strength INT NOT NULL,
            strength INT NOT NULL,
            revision INT NOT NULL,
            started_at DATETIME NOT NULL,
            placed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX guild_hall_guild_unique (guild_id),
            UNIQUE INDEX guild_hall_system_unique (solar_system_id),
            CONSTRAINT guild_hall_valid_progress CHECK (required_strength > 0 AND strength >= 0 AND strength <= required_strength),
            CONSTRAINT guild_hall_valid_placement CHECK ((solar_system_id IS NULL AND placed_at IS NULL) OR (solar_system_id IS NOT NULL AND placed_at IS NOT NULL AND strength = required_strength)),
            CONSTRAINT FK_FADA0E915F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE,
            CONSTRAINT FK_FADA0E91E5C8C6D3 FOREIGN KEY (solar_system_id) REFERENCES solar_system (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas supprimer les contributions et revendications existantes en cas de retour du code.');
    }
}
