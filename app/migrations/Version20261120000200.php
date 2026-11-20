<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute une génération de confirmation par joueur et un événement de récupération par reçu, sans toucher aux liens historiques.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE player_verification_generation (player_id INT NOT NULL, version INT NOT NULL, algorithm INT NOT NULL, key_id VARCHAR(32) NOT NULL, nonce VARCHAR(64) NOT NULL, expires_at BIGINT NOT NULL, fingerprint VARCHAR(64) NOT NULL, state VARCHAR(16) NOT NULL, PRIMARY KEY(player_id), CONSTRAINT verification_generation_player_fk FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
        $this->addSql("CREATE TABLE verification_recovery_event (original_request_id INT NOT NULL, request_key_hash VARCHAR(64) NOT NULL, recorded_at BIGINT NOT NULL, PRIMARY KEY(original_request_id), CONSTRAINT verification_recovery_original_fk FOREIGN KEY (original_request_id) REFERENCES verification_delivery_request (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void
    {
        // Vérifié sur la base jetable seulement. Garder l'audit en rollback applicatif.
        $this->addSql('DROP TABLE verification_recovery_event');
        $this->addSql('DROP TABLE player_verification_generation');
    }
}
