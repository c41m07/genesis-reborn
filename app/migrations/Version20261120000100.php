<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Prépare la revendication et l’expiration des reçus, sans transport.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_delivery_request ADD claim_hash VARCHAR(64) DEFAULT NULL, ADD lease_expires_at DATETIME DEFAULT NULL, ADD resolved_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_delivery_request DROP claim_hash, DROP lease_expires_at, DROP resolved_at');
    }
}
