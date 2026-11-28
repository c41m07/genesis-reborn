<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distingue les reçus de don de vaisseaux des dépôts de matériaux du Hall.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_hall_convoy ADD ship_donation TINYINT(1) DEFAULT 0 NOT NULL, ADD delivered_ships JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas perdre la preuve des dons de vaisseaux.');
    }
}
