<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126015000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mémoriser le retrait personnel des archives sans supprimer les rapports.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE report_archive ADD removed_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Le retour arrière ne doit pas effacer les retraits personnels enregistrés.');
    }
}
