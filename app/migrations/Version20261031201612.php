<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031201612 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le niveau fixe de conquête JcE et les flottes PNJ persistantes.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE planet_npc_ship (id INT AUTO_INCREMENT NOT NULL, slug VARCHAR(64) NOT NULL, quantity INT NOT NULL, planet_id INT NOT NULL, UNIQUE INDEX planet_npc_ship_unique (planet_id, slug), INDEX IDX_D03599C9A25E9820 (planet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE planet_npc_ship ADD CONSTRAINT FK_D03599C9A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE planet ADD conquest_level INT NOT NULL DEFAULT 1, ADD npc_garrison_initialized TINYINT NOT NULL DEFAULT 0');
        $this->addSql('UPDATE planet SET conquest_level = CASE WHEN position <= 2 THEN 1 WHEN position <= 4 THEN 2 WHEN position <= 6 THEN 3 WHEN position <= 8 THEN 4 ELSE 5 END');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE planet_npc_ship DROP FOREIGN KEY FK_D03599C9A25E9820');
        $this->addSql('DROP TABLE planet_npc_ship');
        $this->addSql('ALTER TABLE planet DROP conquest_level, DROP npc_garrison_initialized');
    }
}
