<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107230000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare l’historique des périodes de vacances par univers, sans activer le mode.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE vacation_period (id INT AUTO_INCREMENT NOT NULL, started_at DATETIME NOT NULL, scheduled_end_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, membership_id INT NOT NULL, INDEX vacation_membership_start_idx (membership_id, started_at), INDEX IDX_663C21151FB354CD (membership_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE vacation_period ADD CONSTRAINT FK_663C21151FB354CD FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE vacation_period');
    }
}
