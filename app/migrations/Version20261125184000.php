<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125184000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enregistrer séparément les fragments des points de reprise des grands combats.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE combat_round_checkpoint_part (
            combat_event_id INT NOT NULL, part_number INT NOT NULL, payload LONGTEXT NOT NULL,
            PRIMARY KEY (combat_event_id, part_number),
            FOREIGN KEY (combat_event_id) REFERENCES combat_event (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE combat_round_checkpoint_part');
    }
}
