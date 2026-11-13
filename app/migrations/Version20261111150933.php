<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111150933 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les projets de guilde, leurs paliers figés et les contributions persistantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guild_project (id INT AUTO_INCREMENT NOT NULL, guild_id INT NOT NULL, guild_name VARCHAR(120) NOT NULL, snapshot JSON NOT NULL, progress JSON NOT NULL, stage_index INT NOT NULL, status VARCHAR(16) NOT NULL, version INT DEFAULT 1 NOT NULL, started_at DATETIME NOT NULL, completed_at DATETIME DEFAULT NULL, eligible_ids JSON NOT NULL, reward_token VARCHAR(36) DEFAULT NULL, rewards_delivered_at DATETIME DEFAULT NULL, universe_id INT NOT NULL, definition_id INT NOT NULL, UNIQUE INDEX guild_project_once (guild_id, definition_id), INDEX IDX_1F091E715CD9AF2 (universe_id), INDEX IDX_1F091E71D11EA911 (definition_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guild_project_contribution (id INT AUTO_INCREMENT NOT NULL, player_universe_id INT NOT NULL, planet_id INT NOT NULL, resources JSON NOT NULL, updated_at DATETIME NOT NULL, project_id INT NOT NULL, UNIQUE INDEX guild_project_contributor (project_id, player_universe_id), INDEX IDX_9FCD03BF166D1F9C (project_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guild_project_definition (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, stages JSON NOT NULL, minimums JSON NOT NULL, enabled TINYINT NOT NULL, version INT DEFAULT 1 NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, universe_id INT NOT NULL, UNIQUE INDEX guild_project_definition_name (universe_id, name), INDEX IDX_48876145CD9AF2 (universe_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guild_project ADD CONSTRAINT FK_1F091E715CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_project ADD CONSTRAINT FK_1F091E71D11EA911 FOREIGN KEY (definition_id) REFERENCES guild_project_definition (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_project_contribution ADD CONSTRAINT FK_9FCD03BF166D1F9C FOREIGN KEY (project_id) REFERENCES guild_project (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_project_definition ADD CONSTRAINT FK_48876145CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM guild_project_definition') > 0 || (int) $this->connection->fetchOne('SELECT COUNT(*) FROM guild_project') > 0, 'Retour refusé : les modèles, contributions et récompenses doivent être conservés.');
        $this->addSql('ALTER TABLE guild_project DROP FOREIGN KEY FK_1F091E715CD9AF2');
        $this->addSql('ALTER TABLE guild_project DROP FOREIGN KEY FK_1F091E71D11EA911');
        $this->addSql('ALTER TABLE guild_project_contribution DROP FOREIGN KEY FK_9FCD03BF166D1F9C');
        $this->addSql('ALTER TABLE guild_project_definition DROP FOREIGN KEY FK_48876145CD9AF2');
        $this->addSql('DROP TABLE guild_project');
        $this->addSql('DROP TABLE guild_project_contribution');
        $this->addSql('DROP TABLE guild_project_definition');
    }
}
