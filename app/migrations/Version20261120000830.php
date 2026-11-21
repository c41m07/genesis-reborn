<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000830 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identifie les actions guidées et réserve le remboursement pilote une fois par compte et étape, sans reprise de l’existant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE queue_item ADD first_steps_guided TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('ALTER TABLE fleet ADD first_steps_spy TINYINT(1) DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE first_step_reward (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            source_membership_id INT DEFAULT NULL,
            step_key VARCHAR(24) NOT NULL,
            metal INT NOT NULL,
            nano INT NOT NULL,
            claimed_at DATETIME DEFAULT NULL,
            UNIQUE INDEX first_step_reward_player_key (player_id, step_key),
            INDEX first_step_reward_source_idx (source_membership_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_FIRST_STEP_REWARD_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE,
            CONSTRAINT FK_FIRST_STEP_REWARD_SOURCE FOREIGN KEY (source_membership_id) REFERENCES player_universe (id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les remboursements déjà réservés ou versés doivent être conservés.');
    }
}
