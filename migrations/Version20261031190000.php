<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajouter les événements de combat, les participants et les rapports reproductibles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE combat_event (
            id INT AUTO_INCREMENT NOT NULL,
            planet_id INT NOT NULL,
            status VARCHAR(20) NOT NULL,
            started_at DATETIME NOT NULL,
            resolve_at DATETIME NOT NULL,
            seed BIGINT NOT NULL,
            dispatch_token VARCHAR(36) NOT NULL,
            INDEX combat_event_planet_status_idx (planet_id, status),
            INDEX IDX_B58B2F0EA25E9820 (planet_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('CREATE TABLE combat_participant (
            id INT AUTO_INCREMENT NOT NULL,
            combat_event_id INT NOT NULL,
            fleet_id INT NOT NULL,
            player_universe_id INT NOT NULL,
            role VARCHAR(20) NOT NULL,
            arrival_at DATETIME NOT NULL,
            arrival_round INT DEFAULT NULL,
            UNIQUE INDEX combat_event_fleet_unique (combat_event_id, fleet_id),
            INDEX combat_participant_event_role_idx (combat_event_id, role),
            INDEX IDX_9CEAE2C54A98A4A (combat_event_id),
            INDEX IDX_9CEAE2C54B061DF9 (fleet_id),
            INDEX IDX_9CEAE2C55DFA7367 (player_universe_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE combat_log (
            id INT AUTO_INCREMENT NOT NULL,
            combat_event_id INT NOT NULL,
            outcome VARCHAR(30) NOT NULL,
            rounds JSON NOT NULL,
            snapshot JSON NOT NULL,
            resolved_at DATETIME NOT NULL,
            UNIQUE INDEX combat_log_event_unique (combat_event_id),
            PRIMARY KEY(id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE combat_event ADD CONSTRAINT FK_B58B2F0EA25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE combat_participant ADD CONSTRAINT FK_9CEAE2C54A98A4A FOREIGN KEY (combat_event_id) REFERENCES combat_event (id)');
        $this->addSql('ALTER TABLE combat_participant ADD CONSTRAINT FK_9CEAE2C54B061DF9 FOREIGN KEY (fleet_id) REFERENCES fleet (id)');
        $this->addSql('ALTER TABLE combat_participant ADD CONSTRAINT FK_9CEAE2C55DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE combat_log ADD CONSTRAINT FK_21AD45054A98A4A FOREIGN KEY (combat_event_id) REFERENCES combat_event (id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE combat_participant DROP FOREIGN KEY FK_9CEAE2C54A98A4A');
        $this->addSql('ALTER TABLE combat_participant DROP FOREIGN KEY FK_9CEAE2C54B061DF9');
        $this->addSql('ALTER TABLE combat_participant DROP FOREIGN KEY FK_9CEAE2C55DFA7367');
        $this->addSql('ALTER TABLE combat_log DROP FOREIGN KEY FK_21AD45054A98A4A');
        $this->addSql('ALTER TABLE combat_event DROP FOREIGN KEY FK_B58B2F0EA25E9820');
        $this->addSql('DROP TABLE combat_log');
        $this->addSql('DROP TABLE combat_participant');
        $this->addSql('DROP TABLE combat_event');
    }
}
