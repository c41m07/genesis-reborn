<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130800 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve les livraisons du stock commun de l’Atelier indépendamment des flottes personnelles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_atelier_shipment (
            id INT AUTO_INCREMENT NOT NULL,
            hall_id INT NOT NULL, guild_id INT NOT NULL, universe_id INT NOT NULL,
            dispatched_by_id INT NOT NULL, recipient_id INT NOT NULL, destination_id INT NOT NULL,
            ships JSON NOT NULL, navigation JSON NOT NULL, reserved_fuel DOUBLE PRECISION NOT NULL,
            departure_at DATETIME NOT NULL, arrival_at DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL, arrival_token VARCHAR(36) NOT NULL,
            INDEX guild_atelier_shipment_due (status, arrival_at), PRIMARY KEY (id)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas effacer les livraisons de vaisseaux de l’Atelier.');
    }
}
