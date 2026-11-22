<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’état privé des nouveaux rapports sans reprise de l’historique.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE report_notice (
            id INT AUTO_INCREMENT NOT NULL,
            player_universe_id INT NOT NULL,
            combat_log_id INT DEFAULT NULL,
            espionage_report_id INT DEFAULT NULL,
            transport_report_id INT DEFAULT NULL,
            consulted_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX report_notice_combat_unique (player_universe_id, combat_log_id),
            UNIQUE INDEX report_notice_espionage_unique (player_universe_id, espionage_report_id),
            UNIQUE INDEX report_notice_transport_unique (player_universe_id, transport_report_id),
            INDEX report_notice_unread_idx (player_universe_id, consulted_at),
            INDEX report_notice_combat_idx (combat_log_id),
            INDEX report_notice_espionage_idx (espionage_report_id),
            INDEX report_notice_transport_idx (transport_report_id),
            CONSTRAINT report_notice_one_report CHECK ((combat_log_id IS NOT NULL) + (espionage_report_id IS NOT NULL) + (transport_report_id IS NOT NULL) = 1),
            CONSTRAINT FK_REPORT_NOTICE_MEMBER FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE,
            CONSTRAINT FK_REPORT_NOTICE_COMBAT FOREIGN KEY (combat_log_id) REFERENCES combat_log (id) ON DELETE CASCADE,
            CONSTRAINT FK_REPORT_NOTICE_ESPIONAGE FOREIGN KEY (espionage_report_id) REFERENCES espionage_report (id) ON DELETE CASCADE,
            CONSTRAINT FK_REPORT_NOTICE_TRANSPORT FOREIGN KEY (transport_report_id) REFERENCES transport_report (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver l’état de lecture des rapports existants en cas de retour du code.');
    }
}
