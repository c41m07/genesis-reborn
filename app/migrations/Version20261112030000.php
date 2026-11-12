<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112030000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les tags de guilde et les signalements de joueurs, sans inventer de tag historique.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild ADD tag VARCHAR(5) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX guild_tag_unique ON guild (universe_id, tag)');
        $this->addSql('CREATE TABLE player_report (id INT AUTO_INCREMENT NOT NULL, request_key VARCHAR(32) NOT NULL, reason VARCHAR(32) NOT NULL, comment VARCHAR(1000) NOT NULL, context JSON NOT NULL, status VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, reviewed_at DATETIME DEFAULT NULL, universe_id INT NOT NULL, reporter_id INT DEFAULT NULL, target_id INT DEFAULT NULL, reviewed_by_id INT DEFAULT NULL, INDEX player_report_status_created_idx (status, created_at), UNIQUE INDEX player_report_request_unique (reporter_id, request_key), INDEX IDX_5B2FE15A5CD9AF2 (universe_id), INDEX IDX_5B2FE15AE1CFE6F5 (reporter_id), INDEX IDX_5B2FE15A158E0B66 (target_id), INDEX IDX_5B2FE15AFC6B21F1 (reviewed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE player_report ADD CONSTRAINT FK_5B2FE15A5CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE player_report ADD CONSTRAINT FK_5B2FE15AE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES player_universe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE player_report ADD CONSTRAINT FK_5B2FE15A158E0B66 FOREIGN KEY (target_id) REFERENCES player_universe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE player_report ADD CONSTRAINT FK_5B2FE15AFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES player (id) ON DELETE SET NULL');
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les tags et les preuves des signalements ; aucun effacement automatique.');
    }
}
