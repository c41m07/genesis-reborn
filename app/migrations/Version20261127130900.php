<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réserve au dépôt de l’Atelier les places de retour des vaisseaux en cours de livraison.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_atelier_stock ADD return_capacity JSON DEFAULT NULL');
        $this->addSql("UPDATE guild_atelier_stock SET return_capacity = '{}' WHERE return_capacity IS NULL");
        $this->addSql('ALTER TABLE guild_atelier_stock MODIFY return_capacity JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas effacer les réservations des livraisons en cours.');
    }
}
