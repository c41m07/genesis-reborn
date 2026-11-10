<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Prépare la présence et le temps de jeu estimé par participation, sans historique reconstruit.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE player_activity (membership_id INT NOT NULL, tracked_since DATETIME NOT NULL, sampled_at DATETIME NOT NULL, last_seen_at DATETIME NOT NULL, total_seconds INT NOT NULL, daily_seconds JSON NOT NULL, PRIMARY KEY (membership_id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE player_activity ADD CONSTRAINT FK_ACTIVITY_MEMBERSHIP FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM player_activity') > 0, 'Le suivi existant doit être sauvegardé avant tout retour en arrière.');
        $this->addSql('DROP TABLE player_activity');
    }
}
