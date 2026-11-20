<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000600 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rattache la génération à son destinataire et permet une remise versionnée à usage unique.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_verification_generation ADD recipient_fingerprint VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE verification_delivery_request ADD versioned_generation_version INT DEFAULT NULL, ADD emission_handoff_at BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Préférer conserver ces champs lors d'un rollback applicatif.
        $this->addSql('ALTER TABLE verification_delivery_request DROP versioned_generation_version, DROP emission_handoff_at');
        $this->addSql('ALTER TABLE player_verification_generation DROP recipient_fingerprint');
    }
}
