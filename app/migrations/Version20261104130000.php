<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les événements configurables par les administrateurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE game_event (id INT AUTO_INCREMENT NOT NULL, universe_id INT DEFAULT NULL, name VARCHAR(120) NOT NULL, description LONGTEXT NOT NULL, type VARCHAR(40) NOT NULL, status VARCHAR(20) NOT NULL, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, parameters JSON NOT NULL, visible_to_players TINYINT(1) NOT NULL, INDEX game_event_schedule_idx (status, starts_at, ends_at), INDEX IDX_GAME_EVENT_UNIVERSE (universe_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql('ALTER TABLE game_event ADD CONSTRAINT FK_GAME_EVENT_UNIVERSE FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE game_event DROP FOREIGN KEY FK_GAME_EVENT_UNIVERSE');
        $this->addSql('DROP TABLE game_event');
    }
}
