<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107220000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le journal des avertissements d’inactivité par univers et période.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE empire_inactivity_warning (id INT AUTO_INCREMENT NOT NULL, activity_at DATETIME NOT NULL, threshold_days SMALLINT NOT NULL, created_at DATETIME NOT NULL, sent_at DATETIME DEFAULT NULL, membership_id INT NOT NULL, UNIQUE INDEX inactivity_warning_cycle_unique (membership_id, activity_at, threshold_days), INDEX IDX_E0C566761FB354CD (membership_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE empire_inactivity_warning ADD CONSTRAINT FK_E0C566761FB354CD FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX membership_activity_idx ON player_universe (last_activity_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE empire_inactivity_warning');
        $this->addSql('DROP INDEX membership_activity_idx ON player_universe');
    }
}
