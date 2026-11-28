<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127131100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distingue les flottes privées stationnées au Centre des dons de vaisseaux.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_hall_convoy ADD command_station TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Une flotte privée stationnée ne peut pas être reconvertie en convoi de don.');
    }
}
