<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127130100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserve le reçu et les droits sur les coques immobilisées au Dock.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_dock_order (
            id INT AUTO_INCREMENT NOT NULL,
            universe_id INT NOT NULL,
            hall_id INT NOT NULL,
            membership_id INT NOT NULL,
            origin_planet_id INT NOT NULL,
            request_key VARCHAR(32) NOT NULL,
            ship_slug VARCHAR(64) NOT NULL,
            source_tier VARCHAR(16) NOT NULL,
            target_tier VARCHAR(16) NOT NULL,
            quantity INT NOT NULL,
            paid_costs JSON NOT NULL,
            dispatch_token VARCHAR(36) NOT NULL,
            started_at DATETIME NOT NULL,
            due_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX guild_dock_order_universe (universe_id),
            INDEX guild_dock_order_due (completed_at, due_at, id),
            INDEX guild_dock_order_hall (hall_id, completed_at, id),
            UNIQUE INDEX guild_dock_order_request (membership_id, request_key),
            CONSTRAINT guild_dock_order_universe_fk FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Ne pas perdre les coques en cours de modernisation ni les reçus empêchant un double débit.');
    }
}
