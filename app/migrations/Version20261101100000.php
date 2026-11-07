<?php
declare(strict_types=1);
namespace DoctrineMigrations;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
final class Version20261101100000 extends AbstractMigration
{
 public function getDescription():string{return 'Ajoute les préférences d’interface et de notifications des comptes.';}
 public function up(Schema $schema):void{$this->addSql("ALTER TABLE player ADD preferences JSON NOT NULL");}
 public function down(Schema $schema):void{$this->addSql('ALTER TABLE player DROP preferences');}
}
