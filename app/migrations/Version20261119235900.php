<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261119235900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare les reçus de réservation de confirmation, sans activer de transport.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE verification_delivery_request (id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, request_key_hash VARCHAR(64) NOT NULL, state VARCHAR(24) NOT NULL, requested_at DATETIME NOT NULL, cooldown_until DATETIME NOT NULL, INDEX verification_request_player_idx (player_id, state), UNIQUE INDEX verification_request_key_unique (player_id, request_key_hash), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql('ALTER TABLE verification_delivery_request ADD CONSTRAINT FK_VERIFICATION_REQUEST_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Le retour arrière détruit les reçus : ne jamais le lancer sans
        // sauvegarde et décision explicite sur une base existante.
        $this->addSql('DROP TABLE verification_delivery_request');
    }
}
