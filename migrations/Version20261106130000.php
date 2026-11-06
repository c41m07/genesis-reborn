<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261106130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la vérification des adresses email des comptes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player ADD email_verified_at DATETIME DEFAULT NULL');
        $this->addSql('UPDATE player SET email_verified_at = CURRENT_TIMESTAMP');
        $this->addSql("CREATE TABLE player_email_verification_token (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, token_hash VARCHAR(64) NOT NULL, expires_at DATETIME NOT NULL, UNIQUE INDEX email_verification_hash_unique (token_hash), INDEX email_verification_player_idx (player_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE player_email_verification_token ADD CONSTRAINT FK_EMAIL_VERIFICATION_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_email_verification_token DROP FOREIGN KEY FK_EMAIL_VERIFICATION_PLAYER');
        $this->addSql('DROP TABLE player_email_verification_token');
        $this->addSql('ALTER TABLE player DROP email_verified_at');
    }
}
