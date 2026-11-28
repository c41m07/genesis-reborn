<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127131200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve le paiement partagé et le reçu de chaque fabrication de l’Atelier.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_atelier_manufacturing_order (
            id INT AUTO_INCREMENT NOT NULL,
            hall_id INT NOT NULL,
            sponsor_id INT NOT NULL,
            colony_id INT NOT NULL,
            request_key VARCHAR(32) NOT NULL,
            ship_slug VARCHAR(64) NOT NULL,
            tier VARCHAR(16) NOT NULL,
            quantity INT NOT NULL,
            paid_materials JSON NOT NULL,
            paid_electricity BIGINT NOT NULL,
            confirmation VARCHAR(64) NOT NULL,
            dispatch_token VARCHAR(36) NOT NULL,
            started_at DATETIME NOT NULL,
            due_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            INDEX guild_atelier_mfg_active (hall_id, completed_at),
            UNIQUE INDEX guild_atelier_mfg_request (sponsor_id, request_key),
            PRIMARY KEY (id),
            CONSTRAINT guild_atelier_mfg_hall_fk FOREIGN KEY (hall_id) REFERENCES guild_hall (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas supprimer les commandes payées et leurs reçus.');
    }
}
