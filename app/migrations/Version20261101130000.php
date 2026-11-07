<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101130000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persiste la progression et les instantanés des rounds de combat.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE combat_event ADD current_round INT NOT NULL DEFAULT 0, ADD combat_snapshot JSON DEFAULT NULL');
        $this->addSql("UPDATE combat_event SET combat_snapshot = '[]' WHERE combat_snapshot IS NULL");
        $this->addSql('ALTER TABLE combat_event MODIFY combat_snapshot JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE combat_event DROP current_round, DROP combat_snapshot');
    }
}
