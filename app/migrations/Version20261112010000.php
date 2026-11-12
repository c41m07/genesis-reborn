<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261112010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare les dates d’acquisition et la consultation explicite du journal, sans dater l’historique.';
    }

    public function isTransactional(): bool { return false; }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE planet ADD acquired_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX planet_acquisition_idx ON planet (owner_id, acquired_at)');
        $this->addSql('ALTER TABLE player_universe ADD journal_started_at DATETIME DEFAULT NULL, ADD overview_visited_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE notification ADD journal_kind VARCHAR(20) DEFAULT NULL, ADD journal_available_at DATETIME DEFAULT NULL, ADD consulted_at DATETIME DEFAULT NULL');
        $this->addSql('CREATE INDEX notification_journal_idx ON notification (recipient_id, consulted_at, journal_available_at)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les dates d’acquisition et de consultation : aucun effacement automatique de cet historique.');
    }
}
