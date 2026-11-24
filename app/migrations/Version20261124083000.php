<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124083000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajouter les rapports privés de demandes d’orbite et de diplomatie, sans reprise des anciennes notifications.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE diplomatic_report (
            id INT AUTO_INCREMENT NOT NULL,
            recipient_id INT NOT NULL,
            counterpart_id INT DEFAULT NULL,
            subject VARCHAR(24) NOT NULL,
            outcome VARCHAR(12) NOT NULL,
            location VARCHAR(80) DEFAULT NULL,
            guild_name VARCHAR(60) DEFAULT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            INDEX diplomatic_report_recipient_date_idx (recipient_id, created_at),
            INDEX diplomatic_report_counterpart_idx (counterpart_id),
            CONSTRAINT diplomatic_report_recipient_fk FOREIGN KEY (recipient_id) REFERENCES player_universe(id) ON DELETE CASCADE,
            CONSTRAINT diplomatic_report_counterpart_fk FOREIGN KEY (counterpart_id) REFERENCES player_universe(id) ON DELETE SET NULL
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE report_notice ADD diplomatic_report_id INT DEFAULT NULL,
            ADD INDEX report_notice_diplomatic_idx (diplomatic_report_id),
            ADD UNIQUE INDEX report_notice_diplomatic_unique (player_universe_id, diplomatic_report_id),
            ADD CONSTRAINT report_notice_diplomatic_fk FOREIGN KEY (diplomatic_report_id) REFERENCES diplomatic_report(id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les rapports privés et leur état de lecture.');
    }
}
