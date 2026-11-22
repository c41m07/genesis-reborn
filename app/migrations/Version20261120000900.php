<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la reconnaissance privée de campagne sans créer ni initialiser de mission.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pirate_reconnaissance (
            id INT AUTO_INCREMENT NOT NULL,
            membership_id INT NOT NULL,
            owner_id INT NOT NULL,
            system_id INT NOT NULL,
            mission_key VARCHAR(64) NOT NULL,
            departed_at DATETIME NOT NULL,
            due_at DATETIME NOT NULL,
            dispatch_token VARCHAR(36) NOT NULL,
            detected TINYINT(1) DEFAULT NULL,
            reported_at DATETIME DEFAULT NULL,
            composition JSON NOT NULL,
            build_costs JSON NOT NULL,
            UNIQUE INDEX pirate_recon_participation_mission (membership_id, mission_key),
            INDEX pirate_recon_owner_idx (owner_id),
            INDEX pirate_recon_system_idx (system_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_PIRATE_RECON_MEMBER FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE,
            CONSTRAINT FK_PIRATE_RECON_OWNER FOREIGN KEY (owner_id) REFERENCES player (id) ON DELETE CASCADE,
            CONSTRAINT FK_PIRATE_RECON_SYSTEM FOREIGN KEY (system_id) REFERENCES solar_system (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La suppression des missions ne restituerait pas les sondes réservées.');
    }
}
