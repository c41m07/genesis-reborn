<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111010000 extends AbstractMigration
{
    public function getDescription(): string { return 'Conserve la référence et la reprise de reconstruction des nouveaux empires oubliés.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE empire_reconstruction (id INT AUTO_INCREMENT NOT NULL, reference JSON NOT NULL, next_at DATETIME DEFAULT NULL, dispatch_token VARCHAR(36) NOT NULL, planet_id INT NOT NULL, membership_id INT NOT NULL, queue_item_id INT DEFAULT NULL, INDEX IDX_C745BE92A25E9820 (planet_id), UNIQUE INDEX UNIQ_C745BE92F0EDC960 (queue_item_id), INDEX IDX_C745BE921FB354CD (membership_id), UNIQUE INDEX reconstruction_membership_planet (membership_id, planet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE empire_reconstruction ADD CONSTRAINT FK_C745BE92A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
        $this->addSql('ALTER TABLE empire_reconstruction ADD CONSTRAINT FK_C745BE921FB354CD FOREIGN KEY (membership_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE empire_reconstruction ADD CONSTRAINT FK_C745BE92F0EDC960 FOREIGN KEY (queue_item_id) REFERENCES queue_item (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM empire_reconstruction')>0,
            'Retour impossible : les références de reconstruction doivent être conservées.');
        $this->addSql('DROP TABLE empire_reconstruction');
    }
}
