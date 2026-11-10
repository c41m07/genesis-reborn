<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109040000 extends AbstractMigration
{
    public function getDescription(): string { return 'Prépare les défis collectifs guidés et leur récompense figée, sans modifier les rotations existantes.'; }
    public function up(Schema $schema): void { $this->addSql('ALTER TABLE boost_event ADD configuration JSON DEFAULT NULL'); }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM boost_event WHERE configuration IS NOT NULL')>0,'Les défis guidés et leurs récompenses doivent être conservés.');
        $this->addSql('ALTER TABLE boost_event DROP configuration');
    }
}
