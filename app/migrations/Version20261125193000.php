<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125193000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver les repères de périodes de performance sans effacer les mesures.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE performance_period (starts_at DATETIME NOT NULL, PRIMARY KEY (starts_at)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE performance_period');
    }
}
