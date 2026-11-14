<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261113220000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les compositions personnelles de flotte, par empire et univers.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fleet_composition (id INT AUTO_INCREMENT NOT NULL, owner_id INT NOT NULL, name VARCHAR(80) NOT NULL, ships JSON NOT NULL, request_key VARCHAR(32) NOT NULL, version INT DEFAULT 1 NOT NULL, INDEX IDX_COMPOSITION_OWNER (owner_id), UNIQUE INDEX composition_request_unique (owner_id, request_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE fleet_composition ADD CONSTRAINT FK_COMPOSITION_OWNER FOREIGN KEY (owner_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Préserver les compositions enregistrées ; aucun effacement automatique.');
    }
}
