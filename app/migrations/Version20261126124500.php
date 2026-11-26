<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126124500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Identifier la formule utilisée pour les points des classements quotidiens.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE leaderboard_snapshot ADD score_version INT DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Laisser le repère de formule lors d’un retour du code à la version précédente.');
    }
}
