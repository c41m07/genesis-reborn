<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver le destinataire du rapport de détection des sondes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE espionage_report ADD detected_by_id INT DEFAULT NULL, ADD INDEX espionage_detector_idx (detected_by_id), ADD CONSTRAINT FK_ESPIONAGE_DETECTOR FOREIGN KEY (detected_by_id) REFERENCES player_universe (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE espionage_report DROP FOREIGN KEY FK_ESPIONAGE_DETECTOR, DROP detected_by_id');
    }
}
