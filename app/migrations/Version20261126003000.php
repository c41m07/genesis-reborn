<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver les demandes et invitations de guilde sans modifier les adhésions existantes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guild_recruitment (id INT AUTO_INCREMENT NOT NULL, guild_id INT NOT NULL, candidate_id INT NOT NULL, kind VARCHAR(12) NOT NULL, INDEX IDX_81C2B0485F2131EF (guild_id), INDEX IDX_81C2B04891BD8781 (candidate_id), UNIQUE INDEX guild_recruitment_pair (guild_id, candidate_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE guild_recruitment ADD CONSTRAINT FK_81C2B0485F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_recruitment ADD CONSTRAINT FK_81C2B04891BD8781 FOREIGN KEY (candidate_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Revenir au code précédent ne doit pas supprimer les recrutements en attente.');
    }
}
