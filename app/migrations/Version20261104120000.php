<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261104120000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les feedbacks utilisateurs et leur suivi administratif.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE user_feedback (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, universe_id INT DEFAULT NULL, type VARCHAR(20) NOT NULL, priority VARCHAR(20) NOT NULL, title VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, context_url VARCHAR(500) DEFAULT NULL, attachment_filename VARCHAR(120) DEFAULT NULL, status VARCHAR(20) NOT NULL, internal_note LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX IDX_FEEDBACK_PLAYER (player_id), INDEX IDX_FEEDBACK_UNIVERSE (universe_id), INDEX feedback_created_idx (created_at), INDEX feedback_status_idx (status), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE user_feedback ADD CONSTRAINT FK_FEEDBACK_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE user_feedback ADD CONSTRAINT FK_FEEDBACK_UNIVERSE FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE SET NULL');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE user_feedback'); }
}
