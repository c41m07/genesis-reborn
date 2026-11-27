<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126221500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distinguer les boîtes de l’Avent des réservations et avoirs déjà acquis.';
    }

    public function up(Schema $schema): void
    {
        // Ne pas attribuer rétroactivement d’échéance aux cadeaux existants.
        $this->addSql('ALTER TABLE advent_claim ADD box_expires_at DATETIME DEFAULT NULL, ADD box_universe_id INT DEFAULT NULL, ADD box_unread TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver le type, l’univers et l’échéance des cadeaux déjà acquis.');
    }
}
