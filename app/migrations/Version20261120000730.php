<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000730 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Isole l’éligibilité au kit T1 et enregistre un retrait quotidien par compte et date civile.';
    }

    public function up(Schema $schema): void
    {
        // La valeur par défaut préserve tous les comptes déjà inscrits, même non activés.
        $this->addSql('ALTER TABLE player ADD first_planet_starter_pending TINYINT DEFAULT 0 NOT NULL');
        $this->addSql('CREATE TABLE daily_reward_claim (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, claim_date DATE NOT NULL, slot INT NOT NULL, UNIQUE INDEX daily_reward_account_day (player_id, claim_date), PRIMARY KEY(id), CONSTRAINT FK_DAILY_REWARD_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conservez les reçus quotidiens et l’éligibilité acquise lors d’un retour applicatif.');
    }
}
