<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Renomme carbone et énergie en nano et électricité en conservant les stocks et files.';
    }

    public function up(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM planet_resource old JOIN planet_resource new ON new.planet_id=old.planet_id AND new.resource_type=CASE old.resource_type WHEN 'carbon' THEN 'nano' ELSE 'electricity' END WHERE old.resource_type IN ('carbon','energy')") > 0, 'Fusionner manuellement les stocks nano/électricité en doublon avant migration.');
        $this->abortIf((int) $this->connection->fetchOne("SELECT COUNT(*) FROM planet_building old JOIN planet_building new ON new.planet_id=old.planet_id AND new.tier=old.tier AND new.slug=CASE old.slug WHEN 'mine_carbon' THEN 'mine_nano' WHEN 'storage_carbon' THEN 'storage_nano' ELSE 'storage_electricity' END WHERE old.slug IN ('mine_carbon','storage_carbon','storage_energy')") > 0, 'Fusionner les bâtiments nano/électricité en doublon avant migration.');
        $this->addSql("ALTER TABLE planet_defense DROP INDEX defense_unique, ADD tier VARCHAR(16) NOT NULL DEFAULT 'basic', ADD UNIQUE INDEX defense_unique (planet_id, slug, tier)");
        $this->addSql("UPDATE planet_resource SET resource_type=CASE resource_type WHEN 'carbon' THEN 'nano' WHEN 'energy' THEN 'electricity' ELSE resource_type END WHERE resource_type IN ('carbon','energy')");
        $this->addSql("UPDATE planet_building SET slug=CASE slug WHEN 'mine_carbon' THEN 'mine_nano' WHEN 'storage_carbon' THEN 'storage_nano' WHEN 'storage_energy' THEN 'storage_electricity' ELSE slug END WHERE slug IN ('mine_carbon','storage_carbon','storage_energy')");
        $this->addSql("UPDATE queue_item SET item_slug=CASE item_slug WHEN 'mine_carbon' THEN 'mine_nano' WHEN 'storage_carbon' THEN 'storage_nano' WHEN 'storage_energy' THEN 'storage_electricity' ELSE item_slug END WHERE item_slug IN ('mine_carbon','storage_carbon','storage_energy')");
        $this->addSql("UPDATE queue_item SET paid_costs=JSON_SET(JSON_REMOVE(paid_costs,'$.energy','$.carbon'),'$.electricity',COALESCE(JSON_EXTRACT(paid_costs,'$.electricity'),0)+COALESCE(JSON_EXTRACT(paid_costs,'$.energy'),0),'$.nano',COALESCE(JSON_EXTRACT(paid_costs,'$.nano'),0)+COALESCE(JSON_EXTRACT(paid_costs,'$.carbon'),0)) WHERE paid_costs IS NOT NULL AND JSON_CONTAINS_PATH(paid_costs,'one','$.energy','$.carbon')");
        $this->addSql("UPDATE fleet SET cargo=JSON_SET(JSON_REMOVE(cargo,'$.energy','$.carbon'),'$.electricity',COALESCE(JSON_EXTRACT(cargo,'$.electricity'),0)+COALESCE(JSON_EXTRACT(cargo,'$.energy'),0),'$.nano',COALESCE(JSON_EXTRACT(cargo,'$.nano'),0)+COALESCE(JSON_EXTRACT(cargo,'$.carbon'),0)) WHERE JSON_CONTAINS_PATH(cargo,'one','$.energy','$.carbon')");
        foreach ([['mine_carbon','mine_nano'],['storage_carbon','storage_nano'],['storage_energy','storage_electricity']] as [$old,$new]) {
            $this->addSql("UPDATE universe SET game_settings=JSON_SET(JSON_REMOVE(game_settings, '$.catalog_overrides.buildings.$old'), '$.catalog_overrides.buildings.$new', JSON_MERGE_PATCH(COALESCE(JSON_EXTRACT(game_settings, '$.catalog_overrides.buildings.$new'), JSON_OBJECT()), COALESCE(JSON_EXTRACT(game_settings, '$.catalog_overrides.buildings.$old'), JSON_OBJECT()))) WHERE JSON_CONTAINS_PATH(game_settings,'one','$.catalog_overrides.buildings.$old')");
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les stocks convertis et les files peuvent avoir évolué; la migration inverse doit utiliser une sauvegarde prise avant bascule.');
    }
}
