<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare le détachement des empires oubliés et leur ancien propriétaire historique, sans convertir de données.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_universe ADD forgotten_at DATETIME DEFAULT NULL, ADD former_player_id INT DEFAULT NULL, MODIFY player_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX membership_former_player_idx ON player_universe (former_player_id)');
        $this->addSql('ALTER TABLE player_universe ADD CONSTRAINT FK_67E279D5F34C281E FOREIGN KEY (former_player_id) REFERENCES player (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM player_universe WHERE forgotten_at IS NOT NULL OR player_id IS NULL')>0,
            'Retour impossible : des empires oubliés doivent être conservés.');
        $this->addSql('ALTER TABLE player_universe DROP FOREIGN KEY FK_67E279D5F34C281E');
        $this->addSql('ALTER TABLE player_universe DROP INDEX membership_former_player_idx, DROP former_player_id, DROP forgotten_at, MODIFY player_id INT NOT NULL');
    }
}
