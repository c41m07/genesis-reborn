<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114133852 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les présentations et images privées des guildes';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guild_profile (id INT AUTO_INCREMENT NOT NULL, description LONGTEXT NOT NULL, logo_filename VARCHAR(64) DEFAULT NULL, banner_filename VARCHAR(64) DEFAULT NULL, version INT NOT NULL, guild_id INT NOT NULL, UNIQUE INDEX guild_profile_guild_unique (guild_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guild_profile ADD CONSTRAINT FK_B1ED64905F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_profile DROP FOREIGN KEY FK_B1ED64905F2131EF');
        $this->addSql('DROP TABLE guild_profile');
    }
}
