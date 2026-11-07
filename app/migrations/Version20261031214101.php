<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031214101 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les rôles administrateur, la suspension des comptes, les signalements et les journaux d’audit.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE admin_audit_log (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(80) NOT NULL, target_type VARCHAR(80) NOT NULL, target_id INT NOT NULL, details JSON NOT NULL, created_at DATETIME NOT NULL, actor_id INT DEFAULT NULL, INDEX admin_audit_created_idx (created_at), INDEX IDX_1F16C5C710DAF24A (actor_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE message_report (id INT AUTO_INCREMENT NOT NULL, reason VARCHAR(500) NOT NULL, status VARCHAR(16) NOT NULL, reviewed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, message_id INT NOT NULL, reporter_id INT NOT NULL, reviewed_by_id INT DEFAULT NULL, INDEX message_report_status_created_idx (status, created_at), INDEX IDX_F308EA8B537A1329 (message_id), INDEX IDX_F308EA8BE1CFE6F5 (reporter_id), INDEX IDX_F308EA8BFC6B21F1 (reviewed_by_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE admin_audit_log ADD CONSTRAINT FK_1F16C5C710DAF24A FOREIGN KEY (actor_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE message_report ADD CONSTRAINT FK_F308EA8B537A1329 FOREIGN KEY (message_id) REFERENCES social_message (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_report ADD CONSTRAINT FK_F308EA8BE1CFE6F5 FOREIGN KEY (reporter_id) REFERENCES player_universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE message_report ADD CONSTRAINT FK_F308EA8BFC6B21F1 FOREIGN KEY (reviewed_by_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE player ADD roles JSON DEFAULT NULL, ADD locked_at DATETIME DEFAULT NULL');
        $this->addSql("UPDATE player SET roles = '[]' WHERE roles IS NULL");
        $this->addSql('ALTER TABLE player MODIFY roles JSON NOT NULL');
        $this->addSql('ALTER TABLE social_message ADD moderated_at DATETIME DEFAULT NULL, ADD moderated_by_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE social_message ADD CONSTRAINT FK_39C6E5E28EDA19B0 FOREIGN KEY (moderated_by_id) REFERENCES player (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_39C6E5E28EDA19B0 ON social_message (moderated_by_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_audit_log DROP FOREIGN KEY FK_1F16C5C710DAF24A');
        $this->addSql('ALTER TABLE message_report DROP FOREIGN KEY FK_F308EA8B537A1329');
        $this->addSql('ALTER TABLE message_report DROP FOREIGN KEY FK_F308EA8BE1CFE6F5');
        $this->addSql('ALTER TABLE message_report DROP FOREIGN KEY FK_F308EA8BFC6B21F1');
        $this->addSql('DROP TABLE admin_audit_log');
        $this->addSql('DROP TABLE message_report');
        $this->addSql('ALTER TABLE player DROP roles, DROP locked_at');
        $this->addSql('ALTER TABLE social_message DROP FOREIGN KEY FK_39C6E5E28EDA19B0');
        $this->addSql('DROP INDEX IDX_39C6E5E28EDA19B0 ON social_message');
        $this->addSql('ALTER TABLE social_message DROP moderated_at, DROP moderated_by_id');
    }
}
