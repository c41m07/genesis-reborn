<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les preuves d’achèvement du guide Premiers pas, sans pré-cocher l’existant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE first_step_completion (
            id INT AUTO_INCREMENT NOT NULL,
            player_universe_id INT NOT NULL,
            step_key VARCHAR(24) NOT NULL,
            completed_at DATETIME NOT NULL,
            UNIQUE INDEX first_step_membership_key (player_universe_id, step_key),
            PRIMARY KEY (id),
            CONSTRAINT FK_FIRST_STEP_COMPLETION_MEMBERSHIP FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La progression acquise dans le guide doit être conservée.');
    }
}
