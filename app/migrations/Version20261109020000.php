<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Prépare les dossiers, notes et instantanés des futurs départs de flotte pour la surveillance.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_case (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, status VARCHAR(20) NOT NULL, alert_key VARCHAR(120) DEFAULT NULL, creation_key VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, universe_id INT DEFAULT NULL, membership_id INT DEFAULT NULL, fleet_id INT DEFAULT NULL, created_by_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_58111BE45FDCD075 (alert_key), UNIQUE INDEX UNIQ_58111BE4F1AA46C6 (creation_key), INDEX admin_case_state_idx (status, updated_at), INDEX IDX_58111BE45CD9AF2 (universe_id), INDEX IDX_58111BE41FB354CD (membership_id), INDEX IDX_58111BE44B061DF9 (fleet_id), INDEX IDX_58111BE4B03A8386 (created_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE admin_case_note (id INT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, request_key VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL, case_file_id INT NOT NULL, author_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_A752259547BAEAE5 (request_key), INDEX IDX_A75225953C688C67 (case_file_id), INDEX IDX_A7522595F675F31B (author_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE fleet_mission_trace (id INT AUTO_INCREMENT NOT NULL, mission VARCHAR(20) NOT NULL, target_was_player TINYINT NOT NULL, departed_at DATETIME NOT NULL, snapshot JSON NOT NULL, fleet_id INT NOT NULL, universe_id INT NOT NULL, sender_id INT DEFAULT NULL, recipient_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_403F0EB34B061DF9 (fleet_id), INDEX mission_trace_window_idx (universe_id, mission, departed_at), INDEX IDX_403F0EB35CD9AF2 (universe_id), INDEX IDX_403F0EB3F624B39D (sender_id), INDEX IDX_403F0EB3E92F8F78 (recipient_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin_case ADD CONSTRAINT FK_58111BE45CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE admin_case ADD CONSTRAINT FK_58111BE41FB354CD FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE admin_case ADD CONSTRAINT FK_58111BE44B061DF9 FOREIGN KEY (fleet_id) REFERENCES fleet (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE admin_case ADD CONSTRAINT FK_58111BE4B03A8386 FOREIGN KEY (created_by_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE admin_case_note ADD CONSTRAINT FK_A75225953C688C67 FOREIGN KEY (case_file_id) REFERENCES admin_case (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE admin_case_note ADD CONSTRAINT FK_A7522595F675F31B FOREIGN KEY (author_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fleet_mission_trace ADD CONSTRAINT FK_403F0EB34B061DF9 FOREIGN KEY (fleet_id) REFERENCES fleet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fleet_mission_trace ADD CONSTRAINT FK_403F0EB35CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE fleet_mission_trace ADD CONSTRAINT FK_403F0EB3F624B39D FOREIGN KEY (sender_id) REFERENCES player_universe (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE fleet_mission_trace ADD CONSTRAINT FK_403F0EB3E92F8F78 FOREIGN KEY (recipient_id) REFERENCES player_universe (id) ON DELETE SET NULL');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT (SELECT COUNT(*) FROM admin_case)+(SELECT COUNT(*) FROM admin_case_note)+(SELECT COUNT(*) FROM fleet_mission_trace)')>0,'Les dossiers et traces existants doivent être sauvegardés avant tout retour en arrière.');
        $this->addSql('DROP TABLE admin_case_note');$this->addSql('DROP TABLE admin_case');$this->addSql('DROP TABLE fleet_mission_trace');
    }
}
