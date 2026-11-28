<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128153000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve la cible et les accords des propriétaires pour une sortie du Centre.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_command_plan (
            id INT AUTO_INCREMENT NOT NULL,
            universe_id INT NOT NULL,
            hall_id INT NOT NULL,
            guild_id INT NOT NULL,
            enemy_guild_id INT NOT NULL,
            target_colony_id INT NOT NULL,
            target_owner_id INT NOT NULL,
            created_by_id INT NOT NULL,
            created_at DATETIME NOT NULL,
            launched_at DATETIME DEFAULT NULL,
            closed_at DATETIME DEFAULT NULL,
            INDEX guild_command_plan_hall (hall_id, closed_at),
            PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");

        $this->addSql("CREATE TABLE guild_command_consent (
            id INT AUTO_INCREMENT NOT NULL,
            plan_id INT NOT NULL,
            fleet_id INT NOT NULL,
            owner_id INT NOT NULL,
            home_colony_id INT NOT NULL,
            berth_token VARCHAR(36) NOT NULL,
            consented_at DATETIME NOT NULL,
            withdrawn_at DATETIME DEFAULT NULL,
            INDEX guild_command_consent_fleet (fleet_id, withdrawn_at),
            UNIQUE INDEX guild_command_consent_plan_fleet (plan_id, fleet_id),
            PRIMARY KEY (id),
            CONSTRAINT guild_command_consent_plan_fk FOREIGN KEY (plan_id) REFERENCES guild_command_plan (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas supprimer les accords historiques du Centre.');
    }
}
