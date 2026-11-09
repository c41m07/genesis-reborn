<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la dernière activité par univers sans déduire une activité historique.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_universe ADD last_activity_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_universe DROP last_activity_at');
    }
}
