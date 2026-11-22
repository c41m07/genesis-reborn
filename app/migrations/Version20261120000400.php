<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000400 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Relie une intention de récupération à une réservation distincte et à la version de génération appelée.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_recovery_event ADD recovery_request_id INT DEFAULT NULL, ADD generation_version INT DEFAULT NULL, ADD UNIQUE INDEX verification_recovery_request_unique (recovery_request_id), ADD CONSTRAINT verification_recovery_request_fk FOREIGN KEY (recovery_request_id) REFERENCES verification_delivery_request (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE verification_recovery_event DROP FOREIGN KEY verification_recovery_request_fk, DROP INDEX verification_recovery_request_unique, DROP recovery_request_id, DROP generation_version');
    }
}
