<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021010146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE action_receipt (id INT AUTO_INCREMENT NOT NULL, request_key VARCHAR(64) NOT NULL, player_universe_id INT NOT NULL, UNIQUE INDEX request_unique (player_universe_id, request_key), INDEX IDX_893B3EA45DFA7367 (player_universe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fleet (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, mission VARCHAR(20) NOT NULL, speed_ratio DOUBLE PRECISION NOT NULL, cargo JSON NOT NULL, reserved_fuel DOUBLE PRECISION NOT NULL, departure_at DATETIME DEFAULT NULL, arrival_at DATETIME DEFAULT NULL, travel_seconds INT NOT NULL, dispatch_token VARCHAR(36) NOT NULL, player_universe_id INT NOT NULL, home_planet_id INT NOT NULL, destination_id INT DEFAULT NULL, INDEX IDX_A05E1E475DFA7367 (player_universe_id), INDEX IDX_A05E1E47DC8AE164 (home_planet_id), INDEX IDX_A05E1E47816C6140 (destination_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fleet_ship (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, quantity INT NOT NULL, fleet_id INT NOT NULL, UNIQUE INDEX fleet_ship_unique (fleet_id, slug), INDEX IDX_352580994B061DF9 (fleet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE galaxy (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, universe_id INT NOT NULL, UNIQUE INDEX galaxy_unique (universe_id, name), INDEX IDX_F6BB13765CD9AF2 (universe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE outbox_message (id INT AUTO_INCREMENT NOT NULL, kind VARCHAR(30) NOT NULL, target_id INT NOT NULL, token VARCHAR(36) NOT NULL, due_at DATETIME NOT NULL, PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planet (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, position INT NOT NULL, building_queue_started_at DATETIME DEFAULT NULL, research_queue_started_at DATETIME DEFAULT NULL, shipyard_queue_started_at DATETIME DEFAULT NULL, solar_system_id INT NOT NULL, owner_id INT DEFAULT NULL, UNIQUE INDEX planet_unique (solar_system_id, position), INDEX IDX_68136AA5E5C8C6D3 (solar_system_id), INDEX IDX_68136AA57E3C61F9 (owner_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planet_building (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, level INT NOT NULL, planet_id INT NOT NULL, UNIQUE INDEX building_unique (planet_id, slug), INDEX IDX_92265400A25E9820 (planet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planet_defense (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, quantity INT NOT NULL, planet_id INT NOT NULL, UNIQUE INDEX defense_unique (planet_id, slug), INDEX IDX_4E86A2F2A25E9820 (planet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planet_resource (id INT AUTO_INCREMENT NOT NULL, resource_type VARCHAR(20) NOT NULL, current_stock DOUBLE PRECISION NOT NULL, production_rate DOUBLE PRECISION NOT NULL, last_updated_at DATETIME NOT NULL, planet_id INT NOT NULL, UNIQUE INDEX resource_unique (planet_id, resource_type), INDEX IDX_CFD8C1C2A25E9820 (planet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(180) NOT NULL, username VARCHAR(40) NOT NULL, password VARCHAR(255) NOT NULL, current_player_universe_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_98197A65E7927C74 (email), UNIQUE INDEX UNIQ_98197A65F85E0677 (username), INDEX IDX_98197A6591A234EC (current_player_universe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player_discovery (id INT AUTO_INCREMENT NOT NULL, player_universe_id INT NOT NULL, solar_system_id INT NOT NULL, UNIQUE INDEX discovery_unique (player_universe_id, solar_system_id), INDEX IDX_F43439165DFA7367 (player_universe_id), INDEX IDX_F4343916E5C8C6D3 (solar_system_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player_technology (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, level INT NOT NULL, player_universe_id INT NOT NULL, UNIQUE INDEX tech_unique (player_universe_id, slug), INDEX IDX_ABC6D6705DFA7367 (player_universe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE player_universe (id INT AUTO_INCREMENT NOT NULL, joined_at DATETIME NOT NULL, player_id INT NOT NULL, universe_id INT NOT NULL, UNIQUE INDEX membership_unique (player_id, universe_id), INDEX IDX_67E279D599E6F5DF (player_id), INDEX IDX_67E279D55CD9AF2 (universe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE queue_item (id INT AUTO_INCREMENT NOT NULL, queue_type VARCHAR(16) NOT NULL, item_slug VARCHAR(64) NOT NULL, item_level INT DEFAULT NULL, quantity INT NOT NULL, duration_seconds INT NOT NULL, started_at DATETIME DEFAULT NULL, queued_at DATETIME NOT NULL, dispatch_token VARCHAR(36) NOT NULL, planet_id INT NOT NULL, player_universe_id INT NOT NULL, depends_on_id INT DEFAULT NULL, INDEX queue_order (planet_id, queue_type, id), INDEX IDX_BA4B6DE8A25E9820 (planet_id), INDEX IDX_BA4B6DE85DFA7367 (player_universe_id), INDEX IDX_BA4B6DE81E088F8 (depends_on_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE solar_system (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(80) NOT NULL, coord_x INT NOT NULL, coord_y INT NOT NULL, galaxy_id INT NOT NULL, UNIQUE INDEX system_unique (galaxy_id, coord_x, coord_y), INDEX IDX_28893917B61FAB2 (galaxy_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE universe (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, name VARCHAR(100) NOT NULL, status VARCHAR(16) NOT NULL, starts_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_61353835989D9B62 (slug), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE action_receipt ADD CONSTRAINT FK_893B3EA45DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE fleet ADD CONSTRAINT FK_A05E1E475DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE fleet ADD CONSTRAINT FK_A05E1E47DC8AE164 FOREIGN KEY (home_planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE fleet ADD CONSTRAINT FK_A05E1E47816C6140 FOREIGN KEY (destination_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE fleet_ship ADD CONSTRAINT FK_352580994B061DF9 FOREIGN KEY (fleet_id) REFERENCES fleet (id)');
        $this->addSql('ALTER TABLE galaxy ADD CONSTRAINT FK_F6BB13765CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id)');
        $this->addSql('ALTER TABLE planet ADD CONSTRAINT FK_68136AA5E5C8C6D3 FOREIGN KEY (solar_system_id) REFERENCES solar_system (id)');
        $this->addSql('ALTER TABLE planet ADD CONSTRAINT FK_68136AA57E3C61F9 FOREIGN KEY (owner_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE planet_building ADD CONSTRAINT FK_92265400A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE planet_defense ADD CONSTRAINT FK_4E86A2F2A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE planet_resource ADD CONSTRAINT FK_CFD8C1C2A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE player ADD CONSTRAINT FK_98197A6591A234EC FOREIGN KEY (current_player_universe_id) REFERENCES player_universe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE player_discovery ADD CONSTRAINT FK_F43439165DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE player_discovery ADD CONSTRAINT FK_F4343916E5C8C6D3 FOREIGN KEY (solar_system_id) REFERENCES solar_system (id)');
        $this->addSql('ALTER TABLE player_technology ADD CONSTRAINT FK_ABC6D6705DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE player_universe ADD CONSTRAINT FK_67E279D599E6F5DF FOREIGN KEY (player_id) REFERENCES player (id)');
        $this->addSql('ALTER TABLE player_universe ADD CONSTRAINT FK_67E279D55CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id)');
        $this->addSql('ALTER TABLE queue_item ADD CONSTRAINT FK_BA4B6DE8A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE queue_item ADD CONSTRAINT FK_BA4B6DE85DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE queue_item ADD CONSTRAINT FK_BA4B6DE81E088F8 FOREIGN KEY (depends_on_id) REFERENCES queue_item (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE solar_system ADD CONSTRAINT FK_28893917B61FAB2 FOREIGN KEY (galaxy_id) REFERENCES galaxy (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE action_receipt DROP FOREIGN KEY FK_893B3EA45DFA7367');
        $this->addSql('ALTER TABLE fleet DROP FOREIGN KEY FK_A05E1E475DFA7367');
        $this->addSql('ALTER TABLE fleet DROP FOREIGN KEY FK_A05E1E47DC8AE164');
        $this->addSql('ALTER TABLE fleet DROP FOREIGN KEY FK_A05E1E47816C6140');
        $this->addSql('ALTER TABLE fleet_ship DROP FOREIGN KEY FK_352580994B061DF9');
        $this->addSql('ALTER TABLE galaxy DROP FOREIGN KEY FK_F6BB13765CD9AF2');
        $this->addSql('ALTER TABLE planet DROP FOREIGN KEY FK_68136AA5E5C8C6D3');
        $this->addSql('ALTER TABLE planet DROP FOREIGN KEY FK_68136AA57E3C61F9');
        $this->addSql('ALTER TABLE planet_building DROP FOREIGN KEY FK_92265400A25E9820');
        $this->addSql('ALTER TABLE planet_defense DROP FOREIGN KEY FK_4E86A2F2A25E9820');
        $this->addSql('ALTER TABLE planet_resource DROP FOREIGN KEY FK_CFD8C1C2A25E9820');
        $this->addSql('ALTER TABLE player DROP FOREIGN KEY FK_98197A6591A234EC');
        $this->addSql('ALTER TABLE player_discovery DROP FOREIGN KEY FK_F43439165DFA7367');
        $this->addSql('ALTER TABLE player_discovery DROP FOREIGN KEY FK_F4343916E5C8C6D3');
        $this->addSql('ALTER TABLE player_technology DROP FOREIGN KEY FK_ABC6D6705DFA7367');
        $this->addSql('ALTER TABLE player_universe DROP FOREIGN KEY FK_67E279D599E6F5DF');
        $this->addSql('ALTER TABLE player_universe DROP FOREIGN KEY FK_67E279D55CD9AF2');
        $this->addSql('ALTER TABLE queue_item DROP FOREIGN KEY FK_BA4B6DE8A25E9820');
        $this->addSql('ALTER TABLE queue_item DROP FOREIGN KEY FK_BA4B6DE85DFA7367');
        $this->addSql('ALTER TABLE queue_item DROP FOREIGN KEY FK_BA4B6DE81E088F8');
        $this->addSql('ALTER TABLE solar_system DROP FOREIGN KEY FK_28893917B61FAB2');
        $this->addSql('DROP TABLE action_receipt');
        $this->addSql('DROP TABLE fleet');
        $this->addSql('DROP TABLE fleet_ship');
        $this->addSql('DROP TABLE galaxy');
        $this->addSql('DROP TABLE outbox_message');
        $this->addSql('DROP TABLE planet');
        $this->addSql('DROP TABLE planet_building');
        $this->addSql('DROP TABLE planet_defense');
        $this->addSql('DROP TABLE planet_resource');
        $this->addSql('DROP TABLE player');
        $this->addSql('DROP TABLE player_discovery');
        $this->addSql('DROP TABLE player_technology');
        $this->addSql('DROP TABLE player_universe');
        $this->addSql('DROP TABLE queue_item');
        $this->addSql('DROP TABLE solar_system');
        $this->addSql('DROP TABLE universe');
    }
}
