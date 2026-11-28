<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130700 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve le reliquat fractionnaire d’hydrogène du Hall après réservation de carburant.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_hall_stock ADD hydrogen_fraction DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas perdre le reliquat d’hydrogène du Hall.');
    }
}
