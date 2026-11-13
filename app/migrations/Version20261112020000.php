<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112020000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les archives personnelles restaurables sans modifier les rapports conservés.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE report_archive (id INT AUTO_INCREMENT NOT NULL, player_universe_id INT NOT NULL, kind VARCHAR(16) NOT NULL, report_id INT NOT NULL, archived_at DATETIME DEFAULT NULL, INDEX IDX_B565209C5DFA7367 (player_universe_id), UNIQUE INDEX report_archive_unique (player_universe_id, kind, report_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE report_archive ADD CONSTRAINT FK_B565209C5DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les choix personnels d’archivage ; aucun effacement automatique.');
    }
}
