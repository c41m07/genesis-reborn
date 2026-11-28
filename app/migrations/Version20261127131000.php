<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127131000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Attache les Jump Points à une colonie, au Hall exact et à l’adhésion qui les a reçus.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE guild_jump_point (
            id INT AUTO_INCREMENT NOT NULL,
            hall_id INT NOT NULL,
            member_id INT DEFAULT NULL,
            planet_id INT NOT NULL,
            acquisition_at DATETIME DEFAULT NULL,
            ready_at DATETIME NOT NULL,
            original_owner_id INT NOT NULL,
            instability_seconds INT NOT NULL,
            unstable_until DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE INDEX guild_jump_colony (planet_id),
            INDEX guild_jump_hall (hall_id),
            INDEX guild_jump_member (member_id),
            CONSTRAINT guild_jump_hall_fk FOREIGN KEY (hall_id) REFERENCES guild_hall (id) ON DELETE CASCADE,
            CONSTRAINT guild_jump_member_fk FOREIGN KEY (member_id) REFERENCES guild_member (id) ON DELETE SET NULL,
            CONSTRAINT guild_jump_planet_fk FOREIGN KEY (planet_id) REFERENCES planet (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La suppression des Jump Points annulerait les installations de guilde.');
    }
}
