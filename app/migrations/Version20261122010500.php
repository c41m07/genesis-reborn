<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Titres et bannières de profil acquis, sans effet sur le jeu.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE profile_cosmetic (
            id INT AUTO_INCREMENT NOT NULL,
            player_id INT NOT NULL,
            code VARCHAR(40) NOT NULL,
            awarded_at DATETIME NOT NULL,
            equipped TINYINT(1) NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX profile_cosmetic_player_code_unique (player_id, code),
            CONSTRAINT profile_cosmetic_player_fk FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les cosmétiques déjà acquis.');
    }
}
