<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Reserve separately the fuel allocated to combat or defense stationing.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet ADD orbit_fuel DOUBLE PRECISION NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet DROP orbit_fuel');
    }
}
