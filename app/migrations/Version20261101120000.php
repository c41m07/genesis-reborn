<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les événements de boosts et leurs contributions idempotentes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE boost_event (id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, event_key VARCHAR(120) NOT NULL, template_key VARCHAR(40) NOT NULL, status VARCHAR(16) NOT NULL, format VARCHAR(16) NOT NULL, reward_mode VARCHAR(20) NOT NULL, contribution_metric VARCHAR(40) NOT NULL, duration_seconds INT NOT NULL, scheduled_at DATETIME NOT NULL, started_at DATETIME DEFAULT NULL, ends_at DATETIME DEFAULT NULL, closed_at DATETIME DEFAULT NULL, threshold_value DOUBLE PRECISION DEFAULT NULL, global_goal DOUBLE PRECISION DEFAULT NULL, total_contribution DOUBLE PRECISION NOT NULL, INDEX boost_event_schedule_idx (status, scheduled_at, id), INDEX IDX_FAA5AA4E5CD9AF2 (universe_id), UNIQUE INDEX boost_event_key_unique (event_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql('ALTER TABLE boost_event ADD CONSTRAINT FK_BOOST_EVENT_UNIVERSE FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE');
        $this->addSql("CREATE TABLE boost_event_contribution (id INT AUTO_INCREMENT NOT NULL, event_id INT NOT NULL, player_universe_id INT NOT NULL, source_key VARCHAR(120) NOT NULL, value DOUBLE PRECISION NOT NULL, INDEX boost_event_score_idx (event_id, player_universe_id, id), INDEX IDX_E280B7845DFA7367 (player_universe_id), UNIQUE INDEX boost_event_source_unique (event_id, source_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql('ALTER TABLE boost_event_contribution ADD CONSTRAINT FK_BOOST_CONTRIBUTION_EVENT FOREIGN KEY (event_id) REFERENCES boost_event (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE boost_event_contribution ADD CONSTRAINT FK_BOOST_CONTRIBUTION_MEMBERSHIP FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE boost_event_contribution DROP FOREIGN KEY FK_BOOST_CONTRIBUTION_EVENT');
        $this->addSql('ALTER TABLE boost_event_contribution DROP FOREIGN KEY FK_BOOST_CONTRIBUTION_MEMBERSHIP');
        $this->addSql('DROP TABLE boost_event_contribution');
        $this->addSql('ALTER TABLE boost_event DROP FOREIGN KEY FK_BOOST_EVENT_UNIVERSE');
        $this->addSql('DROP TABLE boost_event');
    }
}
