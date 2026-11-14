<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114164227 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la réserve de carburant embarquée des flottes à quai.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet ADD stored_fuel DOUBLE PRECISION DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE fleet DROP stored_fuel');
    }
}
