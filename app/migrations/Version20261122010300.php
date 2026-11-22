<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261122010300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les critères de participation du Hall et le cumul des dons par participation.';
    }

    public function up(Schema $schema): void
    {
        // Aucun Hall n'a été ouvert hors recette. Un ancien pilote ne connaît pas
        // ses donateurs : ne pas lui fabriquer une qualification ni le rebloquer.
        $this->abortIf($this->connection->fetchOne('SELECT 1 FROM guild_hall LIMIT 1') !== false,
            'Des Halls préexistent sans reçus individuels ; réconcilier leurs données avant activation.');
        $this->addSql('ALTER TABLE guild_hall
            ADD required_donors INT NOT NULL,
            ADD minimum_donor_strength INT NOT NULL');
        $this->addSql('CREATE TABLE guild_hall_contribution (
            id INT AUTO_INCREMENT NOT NULL,
            hall_id INT NOT NULL,
            member_id INT NOT NULL,
            strength INT NOT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX guild_hall_donor_unique (hall_id, member_id),
            INDEX guild_hall_donor_strength_idx (hall_id, strength),
            CONSTRAINT guild_hall_donor_positive CHECK (strength > 0),
            CONSTRAINT FK_7A3A598552AFCFD6 FOREIGN KEY (hall_id) REFERENCES guild_hall (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas supprimer les contributions déjà enregistrées en cas de retour du code.');
    }
}
