<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261110010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Conserve les classements quotidiens par univers et les évolutions du dernier calcul.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE leaderboard_snapshot (id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, updated_at DATETIME NOT NULL, comparison_at DATETIME DEFAULT NULL, players JSON NOT NULL, guilds JSON NOT NULL, UNIQUE INDEX UNIQ_LEADERBOARD_UNIVERSE (universe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE leaderboard_snapshot ADD CONSTRAINT FK_LEADERBOARD_UNIVERSE FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM leaderboard_snapshot') > 0, 'Conserver les classements et leurs évolutions avant tout retour en arrière.');
        $this->addSql('DROP TABLE leaderboard_snapshot');
    }
}
