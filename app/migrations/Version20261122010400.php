<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Éditions de l’Avent et droit unique par compte, édition et porte ; aucune édition activée.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE advent_edition (
            id INT AUTO_INCREMENT NOT NULL,
            season INT NOT NULL,
            name VARCHAR(120) NOT NULL,
            starts_at DATETIME NOT NULL,
            closes_at DATETIME NOT NULL,
            doors JSON NOT NULL,
            published_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX advent_edition_season_unique (season),
            INDEX advent_edition_schedule_idx (closes_at, starts_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE advent_claim (
            id INT AUTO_INCREMENT NOT NULL,
            advent_edition_id INT NOT NULL,
            player_id INT NOT NULL,
            original_destination_id INT NOT NULL,
            delivered_destination_id INT DEFAULT NULL,
            door INT NOT NULL,
            choice INT NOT NULL,
            resources JSON NOT NULL,
            opened_at DATETIME NOT NULL,
            delivered_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX advent_claim_account_door_unique (player_id, advent_edition_id, door),
            INDEX advent_claim_edition_idx (advent_edition_id),
            CONSTRAINT advent_claim_edition_fk FOREIGN KEY (advent_edition_id) REFERENCES advent_edition (id) ON DELETE RESTRICT,
            CONSTRAINT advent_claim_player_fk FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE,
            CONSTRAINT advent_claim_door_range CHECK (door BETWEEN 1 AND 24),
            CONSTRAINT advent_claim_choice_range CHECK (choice BETWEEN 0 AND 2),
            CONSTRAINT advent_claim_delivery_consistency CHECK ((delivered_destination_id IS NULL) = (delivered_at IS NULL))
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les droits persistants et éviter de recréditer les cadeaux en cas de retour du code.');
    }
}
