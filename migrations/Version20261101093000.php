<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101093000 extends AbstractMigration
{
    public function getDescription():string
    {
        return 'Aligne les types des colonnes du jeton avec le mapping Doctrine.';
    }

    public function up(Schema $schema):void
    {
        $this->addSql('ALTER TABLE player_password_reset_token CHANGE token_hash token_hash VARCHAR(64) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE expires_at expires_at DATETIME NOT NULL');
    }

    public function down(Schema $schema):void
    {
        $this->addSql('ALTER TABLE player_password_reset_token CHANGE token_hash token_hash CHAR(64) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE expires_at expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
    }
}
