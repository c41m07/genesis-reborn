<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Isoler l’univers de l’édition et conserver les lots et remises utilisés sur le reçu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE advent_edition
            ADD plan VARCHAR(24) DEFAULT 'custom' NOT NULL,
            ADD cohort_captured_at DATETIME DEFAULT NULL");
        $this->addSql('ALTER TABLE advent_claim ADD award JSON DEFAULT NULL, ADD credited_resources JSON DEFAULT NULL, ADD redeemed_queue_id INT DEFAULT NULL');
        $this->addSql('CREATE TABLE advent_progression (
            id INT AUTO_INCREMENT NOT NULL,
            advent_edition_id INT NOT NULL,
            player_id INT NOT NULL,
            universe_id INT NOT NULL,
            bracket VARCHAR(20) NOT NULL,
            observed_at DATETIME NOT NULL,
            origin VARCHAR(16) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX advent_progression_account_universe_unique (player_id, advent_edition_id, universe_id),
            INDEX advent_progression_edition_idx (advent_edition_id),
            CONSTRAINT advent_progression_edition_fk FOREIGN KEY (advent_edition_id) REFERENCES advent_edition (id) ON DELETE RESTRICT,
            CONSTRAINT advent_progression_account_fk FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE advent_binding (
            id INT AUTO_INCREMENT NOT NULL,
            advent_edition_id INT NOT NULL,
            player_id INT NOT NULL,
            universe_id INT NOT NULL,
            bracket VARCHAR(20) NOT NULL,
            joined_at DATETIME NOT NULL,
            bound_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX advent_binding_account_edition_unique (player_id, advent_edition_id),
            INDEX advent_binding_edition_idx (advent_edition_id),
            CONSTRAINT advent_binding_edition_fk FOREIGN KEY (advent_edition_id) REFERENCES advent_edition (id) ON DELETE RESTRICT,
            CONSTRAINT advent_binding_account_fk FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les restes de cadeaux et les preuves de consommation des remises.');
    }
}
