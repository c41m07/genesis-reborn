<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache un reçu de livraison de guilde au convoi sans effacer le fret par dissolution.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_hall_convoy (
            fleet_id INT NOT NULL,
            hall_id INT NOT NULL,
            guild_id INT NOT NULL,
            outbound_token VARCHAR(36) NOT NULL,
            settled_at DATETIME DEFAULT NULL,
            delivered JSON NOT NULL,
            PRIMARY KEY (fleet_id),
            CONSTRAINT guild_hall_convoy_fleet_fk FOREIGN KEY (fleet_id) REFERENCES fleet (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas effacer les reçus des convois de guilde.');
    }
}
