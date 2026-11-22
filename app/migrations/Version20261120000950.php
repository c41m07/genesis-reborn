<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000950 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Associe le scénario de combat figé au renseignement privé, sans reconstituer les anciens profils.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE pirate_reconnaissance ADD prepared_encounter JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Le scénario figé des reconnaissances ne doit pas être perdu.');
    }
}
