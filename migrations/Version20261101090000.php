<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101090000 extends AbstractMigration
{
    public function getDescription():string
    {
        return 'Ajoute les jetons temporaires de réinitialisation du mot de passe.';
    }

    public function up(Schema $schema):void
    {
        $this->addSql('CREATE TABLE player_password_reset_token (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, token_hash CHAR(64) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', expires_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', UNIQUE INDEX password_reset_hash_unique (token_hash), INDEX password_reset_player_idx (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE player_password_reset_token ADD CONSTRAINT FK_95B408A099E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema):void
    {
        $this->addSql('DROP TABLE player_password_reset_token');
    }
}
