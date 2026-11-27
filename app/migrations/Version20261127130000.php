<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les rapports privés de campagne par reconnaissance, tentative et trajet du convoi.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pirate_mission_report (id INT AUTO_INCREMENT NOT NULL, reconnaissance_id INT NOT NULL, category VARCHAR(12) NOT NULL, sequence_number INT NOT NULL, created_at DATETIME NOT NULL, location VARCHAR(255) NOT NULL, status VARCHAR(24) NOT NULL, snapshot JSON NOT NULL, resolved_at DATETIME DEFAULT NULL, PRIMARY KEY(id), UNIQUE INDEX pirate_report_event (reconnaissance_id, category, sequence_number), INDEX pirate_report_recon (reconnaissance_id), CONSTRAINT pirate_report_recon_fk FOREIGN KEY (reconnaissance_id) REFERENCES pirate_reconnaissance (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE report_notice ADD pirate_mission_report_id INT DEFAULT NULL, DROP CONSTRAINT report_notice_one_report, ADD CONSTRAINT report_notice_one_report CHECK ((combat_log_id IS NOT NULL) + (espionage_report_id IS NOT NULL) + (transport_report_id IS NOT NULL) + (diplomatic_report_id IS NOT NULL) + (pirate_mission_report_id IS NOT NULL) = 1), ADD UNIQUE INDEX report_notice_campaign_unique (player_universe_id, pirate_mission_report_id), ADD INDEX report_notice_campaign_idx (pirate_mission_report_id), ADD CONSTRAINT report_notice_campaign_fk FOREIGN KEY (pirate_mission_report_id) REFERENCES pirate_mission_report (id) ON DELETE CASCADE');

        // Les reconnaissances déjà remises conservent leur lien et leur contenu. Aucun ancien rapport ne devient non lu.
        $this->addSql("INSERT INTO pirate_mission_report (reconnaissance_id, category, sequence_number, created_at, location, status, snapshot)
            SELECT r.id, 'espionage', 1, r.reported_at, s.name, 'reported', JSON_OBJECT()
            FROM pirate_reconnaissance r JOIN solar_system s ON s.id=r.system_id
            WHERE r.reported_at IS NOT NULL");
        // L’ancien dossier ne conservait que la dernière tentative résolue et le dernier trajet.
        // Reprendre ces preuves sans recréer de gain, de notification ni de rounds disparus.
        $this->addSql(<<<'SQL'
            INSERT INTO pirate_mission_report (reconnaissance_id, category, sequence_number, created_at, resolved_at, location, status, snapshot)
            SELECT r.id, 'combat', JSON_VALUE(a.timeline, '$.attempt'), REPLACE(LEFT(JSON_VALUE(a.timeline, '$.resolved'), 19), 'T', ' '),
                REPLACE(LEFT(JSON_VALUE(a.timeline, '$.resolved'), 19), 'T', ' '), s.name, JSON_VALUE(a.report, '$.outcome'),
                JSON_OBJECT('participants', JSON_ARRAY(
                    JSON_OBJECT('report_owner','escort','role','attacker','camp','escort','player_universe_id', r.membership_id,'units',JSON_EXTRACT(a.timeline,'$.units')),
                    JSON_OBJECT('report_owner','pirates','role','defender','camp','pirates','units',JSON_EXTRACT(a.scenario,'$.units'))),
                    'losses',JSON_EXTRACT(a.report,'$.losses'), 'rounds_played',JSON_EXTRACT(a.report,'$.rounds_played'),
                    'max_rounds',JSON_EXTRACT(a.scenario,'$.rounds'),
                    'location_code',CONCAT(REGEXP_REPLACE(g.name, '^Galaxie +', ''), ':', (s.coord_y DIV 3) * 10 + (s.coord_x DIV 3) + 1))
            FROM pirate_attack_campaign a JOIN pirate_reconnaissance r ON r.id=a.reconnaissance_id
                JOIN solar_system s ON s.id=r.system_id JOIN galaxy g ON g.id=s.galaxy_id
            WHERE JSON_VALUE(a.report,'$.outcome') IS NOT NULL AND JSON_VALUE(a.timeline,'$.resolved') IS NOT NULL
            SQL);

        // Pour un ancien trajet, ne reprendre que la destination attestée et la cargaison figée au départ.
        $amounts = [];
        foreach (['metal', 'nano', 'antimatter', 'hydrogen'] as $resource) {
            $amounts[] = "'$resource', CAST(JSON_VALUE(d.quote_parts, '$.$resource.whole') AS DECIMAL(20,1))"
                ." + IF(JSON_VALUE(d.quote_parts, '$.$resource.extra_half') IN ('true', '1'), 0.5, 0)";
        }
        $cargo = 'JSON_OBJECT('.implode(', ', $amounts).')';
        $this->addSql("INSERT INTO pirate_mission_report (reconnaissance_id, category, sequence_number, created_at, resolved_at, location, status, snapshot)
            SELECT r.id, 'economy', JSON_VALUE(d.checkpoint,'$.generation'), REPLACE(LEFT(JSON_VALUE(d.checkpoint,'$.departed_at'),19),'T',' '),
                COALESCE(d.settled_at, IF(JSON_VALUE(d.checkpoint,'$.state')='destination_lost',REPLACE(LEFT(JSON_VALUE(d.checkpoint,'$.transition_at'),19),'T',' '),NULL)),
                COALESCE(p.name, 'Ancienne destination'),
                CASE JSON_VALUE(d.checkpoint,'$.state') WHEN 'settled' THEN 'delivered' WHEN 'destination_lost' THEN 'destination_lost'
                    WHEN 'waiting_capacity' THEN 'waiting_capacity' WHEN 'arrived' THEN 'arrived' ELSE 'traveling' END,
                JSON_OBJECT('sender','Convoi de remerciement','recipient',account.username,'origin',IF(JSON_VALUE(d.checkpoint,'$.generation')=1,s.name,'Destination précédente du convoi'),
                    'destination',COALESCE(p.name,'Ancienne destination'),'sent',$cargo,'delivered',JSON_EXTRACT(IF(d.settled_at IS NULL,JSON_OBJECT(),$cargo),'$'))
            FROM pirate_convoy_delivery d JOIN pirate_attack_campaign a ON a.id=d.attack_id JOIN pirate_reconnaissance r ON r.id=a.reconnaissance_id
                JOIN solar_system s ON s.id=r.system_id JOIN player account ON account.id=d.owner_id
                LEFT JOIN planet p ON p.id=JSON_VALUE(d.checkpoint,'$.destination') AND p.owner_id=d.membership_id
            WHERE JSON_VALUE(d.checkpoint,'$.departed_at') IS NOT NULL");

    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les tentatives de combat et les reçus de livraison publiés ont un historique propre.');
    }
}
