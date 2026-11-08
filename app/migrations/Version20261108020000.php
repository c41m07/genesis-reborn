<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare le journal unique des conversions et l’archivage des guildes, sans convertir d’empire.';
    }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE empire_conversion (id INT AUTO_INCREMENT NOT NULL, activity_at DATETIME NOT NULL, due_at DATETIME NOT NULL, converted_at DATETIME NOT NULL, effects JSON NOT NULL, membership_id INT NOT NULL, UNIQUE INDEX UNIQ_857C9B231FB354CD (membership_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE empire_conversion ADD CONSTRAINT FK_857C9B231FB354CD FOREIGN KEY (membership_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE guild ADD archived_at DATETIME DEFAULT NULL');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM empire_conversion')>0 || (int)$this->connection->fetchOne('SELECT COUNT(*) FROM guild WHERE archived_at IS NOT NULL')>0,
            'Retour impossible : des conversions ou guildes archivées doivent être conservées.');
        $this->addSql('DROP TABLE empire_conversion');
        $this->addSql('ALTER TABLE guild DROP archived_at');
    }
}
