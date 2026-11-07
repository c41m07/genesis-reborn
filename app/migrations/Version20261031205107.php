<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031205107 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les rapports privés de mission d’espionnage.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE espionage_report (id INT AUTO_INCREMENT NOT NULL, result VARCHAR(16) NOT NULL, created_at DATETIME NOT NULL, probes_sent INT NOT NULL, intel_categories INT NOT NULL, detection_chance INT NOT NULL, sender_espionage_level INT NOT NULL, target_espionage_level INT NOT NULL, target_counter_espionage_level INT NOT NULL, snapshot JSON NOT NULL, sender_id INT NOT NULL, target_id INT NOT NULL, INDEX espionage_sender_created_idx (sender_id, created_at), INDEX IDX_BDB3E420F624B39D (sender_id), INDEX IDX_BDB3E420158E0B66 (target_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE espionage_report ADD CONSTRAINT FK_BDB3E420F624B39D FOREIGN KEY (sender_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE espionage_report ADD CONSTRAINT FK_BDB3E420158E0B66 FOREIGN KEY (target_id) REFERENCES planet (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE espionage_report DROP FOREIGN KEY FK_BDB3E420F624B39D');
        $this->addSql('ALTER TABLE espionage_report DROP FOREIGN KEY FK_BDB3E420158E0B66');
        $this->addSql('DROP TABLE espionage_report');
    }
}
