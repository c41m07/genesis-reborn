<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102170000 extends AbstractMigration
{
    private const RENAMES = [
        'ground_turret' => 'ground_turret_anti_light',
        'laser_turret_interceptor' => 'ground_turret_anti_light',
        'laser_turret_fighter' => 'ground_turret_anti_light',
        'plasma_turret_heavy_fighter' => 'ground_turret_anti_light',
        'laser_turret_stealth_fighter' => 'ground_turret_anti_light',
        'plasma_turret_bomber' => 'ground_turret_anti_light',
        'laser_turret_corvette' => 'ground_turret_anti_escort',
        'laser_turret_frigate' => 'ground_turret_anti_escort',
        'plasma_turret_destroyer' => 'ground_turret_anti_escort',
        'plasma_turret_cruiser' => 'ground_turret_anti_heavy',
        'plasma_turret_battleship' => 'ground_turret_anti_heavy',
        'plasma_turret_capital' => 'ground_turret_anti_capital',
    ];

    public function getDescription(): string
    {
        return 'Regroupe les tourelles par classe de vaisseaux ciblée.';
    }

    public function up(Schema $schema): void
    {
        $cases = array_map(static fn (string $old, string $new): string => sprintf("WHEN '%s' THEN '%s'", $old, $new), array_keys(self::RENAMES), array_values(self::RENAMES));
        $case = 'CASE slug '.implode(' ', $cases).' ELSE slug END';
        $slugs = "'".implode("','", array_keys(self::RENAMES))."'";
        $newSlugs = "'ground_turret_anti_light','ground_turret_anti_escort','ground_turret_anti_heavy','ground_turret_anti_capital'";
        $this->addSql('CREATE TEMPORARY TABLE defense_slug_migration (planet_id BIGINT NOT NULL, slug VARCHAR(64) NOT NULL, tier VARCHAR(16) NOT NULL, quantity INT NOT NULL, PRIMARY KEY (planet_id, slug, tier)) ENGINE=InnoDB');
        $this->addSql("INSERT INTO defense_slug_migration (planet_id,slug,tier,quantity) SELECT planet_id,$case,tier,SUM(quantity) FROM planet_defense WHERE slug IN ($slugs,$newSlugs) GROUP BY planet_id,$case,tier");
        $this->addSql("DELETE FROM planet_defense WHERE slug IN ($slugs,$newSlugs)");
        $this->addSql('INSERT INTO planet_defense (planet_id,slug,tier,quantity) SELECT planet_id,slug,tier,quantity FROM defense_slug_migration');
        $this->addSql('DROP TEMPORARY TABLE defense_slug_migration');

        foreach (self::RENAMES as $old => $new) {
            $this->addSql("UPDATE queue_item SET item_slug=? WHERE queue_type='shipyard' AND item_slug=?", [$new, $old]);
            $this->addSql("UPDATE universe SET game_settings=JSON_REMOVE(game_settings, '$.catalog_overrides.defenses.$old') WHERE JSON_CONTAINS_PATH(game_settings,'one','$.catalog_overrides.defenses.$old')");
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les tourelles regroupées par classe ne peuvent pas être séparées sans perdre la correspondance des défenses déjà construites.');
    }
}
