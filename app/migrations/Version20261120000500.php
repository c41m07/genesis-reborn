<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute le point de remise unique et l’issue auditée au journal de récupération.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_recovery_event ADD handoff_at BIGINT DEFAULT NULL, ADD delivery_outcome VARCHAR(24) DEFAULT NULL, ADD delivery_resolved_at BIGINT DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_recovery_event DROP handoff_at, DROP delivery_outcome, DROP delivery_resolved_at');
    }
}
