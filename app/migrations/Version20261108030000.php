<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108030000 extends AbstractMigration
{
    public function getDescription(): string { return 'Prépare les états et historiques d’occupation JcE, sans modifier la capture actuelle.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE occupation_event (id INT AUTO_INCREMENT NOT NULL, action VARCHAR(16) NOT NULL, occurred_at DATETIME NOT NULL, snapshot JSON NOT NULL, occupation_id INT NOT NULL, combat_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_3750F331FC7EEDB8 (combat_id), INDEX IDX_3750F33122C8FC20 (occupation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE planet_occupation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(16) NOT NULL, started_at DATETIME NOT NULL, capture_at DATETIME NOT NULL, ended_at DATETIME DEFAULT NULL, end_reason VARCHAR(40) DEFAULT NULL, dispatch_token VARCHAR(36) NOT NULL, planet_id INT NOT NULL, occupant_id INT NOT NULL, fleet_id INT NOT NULL, origin_combat_id INT NOT NULL, UNIQUE INDEX UNIQ_B11FCA20A25E9820 (planet_id), INDEX occupation_reservations_idx (occupant_id, status), INDEX IDX_B11FCA2067BAA0E5 (occupant_id), INDEX IDX_B11FCA204B061DF9 (fleet_id), INDEX IDX_B11FCA2041F0A9CE (origin_combat_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE occupation_event ADD CONSTRAINT FK_3750F33122C8FC20 FOREIGN KEY (occupation_id) REFERENCES planet_occupation (id)');
        $this->addSql('ALTER TABLE occupation_event ADD CONSTRAINT FK_3750F331FC7EEDB8 FOREIGN KEY (combat_id) REFERENCES combat_event (id)');
        $this->addSql('ALTER TABLE planet_occupation ADD CONSTRAINT FK_B11FCA20A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE planet_occupation ADD CONSTRAINT FK_B11FCA2067BAA0E5 FOREIGN KEY (occupant_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE planet_occupation ADD CONSTRAINT FK_B11FCA204B061DF9 FOREIGN KEY (fleet_id) REFERENCES fleet (id)');
        $this->addSql('ALTER TABLE planet_occupation ADD CONSTRAINT FK_B11FCA2041F0A9CE FOREIGN KEY (origin_combat_id) REFERENCES combat_event (id)');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM occupation_event')>0 || (int)$this->connection->fetchOne('SELECT COUNT(*) FROM planet_occupation')>0,
            'Retour impossible : des occupations ou leurs historiques doivent être conservés.');
        $this->addSql('DROP TABLE occupation_event');$this->addSql('DROP TABLE planet_occupation');
    }
}
