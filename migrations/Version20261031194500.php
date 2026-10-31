<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031194500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Persist the requested stationing duration for orbital fleets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet ADD orbit_duration_seconds INT NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet DROP orbit_duration_seconds');
    }
}
