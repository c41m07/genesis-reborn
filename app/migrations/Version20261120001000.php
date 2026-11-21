<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare un droit de convoi privé unique par victoire, sans migration de données ni activation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pirate_convoy_delivery (
            id INT AUTO_INCREMENT NOT NULL,
            attack_id INT NOT NULL,
            membership_id INT NOT NULL,
            universe_id INT NOT NULL,
            owner_id INT NOT NULL,
            winning_attempt INT NOT NULL,
            victory_at DATETIME NOT NULL,
            quote_parts JSON NOT NULL,
            checkpoint JSON NOT NULL,
            dispatch_token VARCHAR(36) DEFAULT NULL,
            next_at DATETIME DEFAULT NULL,
            settled_at DATETIME DEFAULT NULL,
            UNIQUE INDEX pirate_convoy_attack_unique (attack_id),
            UNIQUE INDEX pirate_convoy_phase_token_unique (dispatch_token),
            PRIMARY KEY (id),
            CONSTRAINT FK_PIRATE_CONVOY_ATTACK FOREIGN KEY (attack_id) REFERENCES pirate_attack_campaign (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Supprimer les droits ferait perdre les convois en attente et leurs reçus.');
    }
}
