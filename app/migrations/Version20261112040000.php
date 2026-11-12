<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112040000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute la navigation figée et la colonie de retour, sans inventer les trajets anciens.'; }
    public function isTransactional(): bool { return false; }
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet ADD navigation JSON DEFAULT NULL, ADD return_planet_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE fleet ADD CONSTRAINT FK_A05E1E4757472690 FOREIGN KEY (return_planet_id) REFERENCES planet (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_A05E1E4757472690 ON fleet (return_planet_id)');
    }
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver la navigation et les retours des flottes ; aucun effacement automatique.');
    }
}
