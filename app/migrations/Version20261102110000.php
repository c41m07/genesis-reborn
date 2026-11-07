<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les rangs parallèles aux bâtiments, recherches, vaisseaux et commandes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE planet_building DROP INDEX building_unique, ADD tier VARCHAR(16) NOT NULL DEFAULT 'basic', ADD UNIQUE INDEX building_unique (planet_id, slug, tier)");
        $this->addSql("ALTER TABLE player_technology DROP INDEX tech_unique, ADD tier VARCHAR(16) NOT NULL DEFAULT 'basic', ADD UNIQUE INDEX tech_unique (player_universe_id, slug, tier)");
        $this->addSql("ALTER TABLE fleet_ship DROP INDEX fleet_ship_unique, ADD tier VARCHAR(16) NOT NULL DEFAULT 'basic', ADD UNIQUE INDEX fleet_ship_unique (fleet_id, slug, tier)");
        $this->addSql("ALTER TABLE planet_npc_ship DROP INDEX planet_npc_ship_unique, ADD tier VARCHAR(16) NOT NULL DEFAULT 'basic', ADD UNIQUE INDEX planet_npc_ship_unique (planet_id, slug, tier)");
        $this->addSql("ALTER TABLE queue_item ADD tier VARCHAR(16) NOT NULL DEFAULT 'basic'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planet_building DROP INDEX building_unique, DROP tier, ADD UNIQUE INDEX building_unique (planet_id, slug)');
        $this->addSql('ALTER TABLE player_technology DROP INDEX tech_unique, DROP tier, ADD UNIQUE INDEX tech_unique (player_universe_id, slug)');
        $this->addSql('ALTER TABLE fleet_ship DROP INDEX fleet_ship_unique, DROP tier, ADD UNIQUE INDEX fleet_ship_unique (fleet_id, slug)');
        $this->addSql('ALTER TABLE planet_npc_ship DROP INDEX planet_npc_ship_unique, DROP tier, ADD UNIQUE INDEX planet_npc_ship_unique (planet_id, slug)');
        $this->addSql('ALTER TABLE queue_item DROP tier');
    }
}
