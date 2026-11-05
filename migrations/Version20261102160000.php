<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102160000 extends AbstractMigration
{
    private const RENAMES = [
        'basic_engineering' => 'engineering_foundations',
        'advanced_engineering' => 'naval_engineering',
        'robotics' => 'automated_defense_repair',
        'nano_construction' => 'nanomaterial_synthesis',
        'advanced_propulsion' => 'vector_propulsion',
        'precision_fire' => 'weapon_calibration',
        'advanced_targeting' => 'target_acquisition',
        'espionage_technology' => 'tactical_intelligence',
        'counter_espionage' => 'intelligence_countermeasures',
        'stealth_technology' => 'signature_masking',
        'weapon_technology' => 'weapons_engineering',
        'shield_technology' => 'shield_harmonics',
        'colonization' => 'colonial_expansion',
        'hull_reinforcement' => 'structural_armor',
        'evasion_systems' => 'evasive_navigation',
        'signature_dampening' => 'signature_reduction',
        'ballistic_overdrive' => 'rapid_fire_cycle',
        'auxiliary_fuel_tanks' => 'cryogenic_reserves',
        'cargo_optimization' => 'modular_cargo_bays',
    ];

    public function getDescription(): string
    {
        return 'Renomme les slugs de recherche sans perdre les niveaux, files ou surcharges d’univers.';
    }

    public function up(Schema $schema): void
    {
        foreach (self::RENAMES as $old => $new) {
            $this->abortIf(
                (int) $this->connection->fetchOne(
                    'SELECT COUNT(*) FROM player_technology old JOIN player_technology new ON new.player_universe_id=old.player_universe_id AND new.tier=old.tier AND new.slug=? WHERE old.slug=?',
                    [$new, $old],
                ) > 0,
                sprintf('Fusionner manuellement les recherches %s et %s avant migration.', $old, $new),
            );
            $this->addSql('UPDATE player_technology SET slug=? WHERE slug=?', [$new, $old]);
            $this->addSql("UPDATE queue_item SET item_slug=? WHERE queue_type='research' AND item_slug=?", [$new, $old]);
            $this->addSql(
                "UPDATE universe SET game_settings=JSON_SET(JSON_REMOVE(game_settings, '$.catalog_overrides.technologies.$old'), '$.catalog_overrides.technologies.$new', JSON_MERGE_PATCH(COALESCE(JSON_EXTRACT(game_settings, '$.catalog_overrides.technologies.$new'), JSON_OBJECT()), COALESCE(JSON_EXTRACT(game_settings, '$.catalog_overrides.technologies.$old'), JSON_OBJECT()))) WHERE JSON_CONTAINS_PATH(game_settings,'one','$.catalog_overrides.technologies.$old')",
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les recherches renommées peuvent maintenant avoir des progressions et surcharges distinctes; restaurer la sauvegarde pré-migration pour revenir en arrière.');
    }
}
