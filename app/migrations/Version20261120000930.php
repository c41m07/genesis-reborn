<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000930 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare les tentatives privées et réservations de vaisseaux, sans activer la campagne.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE pirate_attack_campaign (
            id INT AUTO_INCREMENT NOT NULL,
            reconnaissance_id INT NOT NULL,
            origin_id INT NOT NULL,
            travel_seconds INT NOT NULL,
            round_seconds INT NOT NULL,
            scenario JSON NOT NULL,
            timeline JSON NOT NULL,
            reserved_ships JSON NOT NULL,
            report JSON DEFAULT NULL,
            departed_at DATETIME NOT NULL,
            arrival_at DATETIME NOT NULL,
            next_at DATETIME DEFAULT NULL,
            dispatch_token VARCHAR(36) DEFAULT NULL,
            return_blocked TINYINT(1) NOT NULL,
            UNIQUE INDEX pirate_attack_reconnaissance (reconnaissance_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_PIRATE_ATTACK_RECON FOREIGN KEY (reconnaissance_id) REFERENCES pirate_reconnaissance (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La suppression des réservations ferait perdre les vaisseaux en trajet.');
    }
}
