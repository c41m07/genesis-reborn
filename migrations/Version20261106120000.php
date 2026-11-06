<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106120000 extends AbstractMigration
{
    public function getDescription():string
    {
        return 'Ajoute le registre des déblocages permanents, sans modifier la progression existante.';
    }
    public function up(Schema $schema):void
    {
        $this->addSql('ALTER TABLE player_universe ADD catalog_unlocks JSON DEFAULT NULL');
    }
    public function down(Schema $schema):void
    {
        $this->throwIrreversibleMigrationException('Le registre des accès acquis doit être conservé.');
    }
}
