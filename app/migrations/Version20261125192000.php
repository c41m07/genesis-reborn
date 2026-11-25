<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125192000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajouter des compteurs de performance agrégés, sans stocker de visites individuelles.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE performance_bucket (
            hour_utc DATETIME NOT NULL, scope VARCHAR(40) NOT NULL,
            device VARCHAR(8) NOT NULL, metric VARCHAR(32) NOT NULL,
            ceiling_ms INT NOT NULL, observations BIGINT NOT NULL,
            PRIMARY KEY (hour_utc, scope, device, metric, ceiling_ms),
            INDEX performance_scope_hour_idx (scope, hour_utc)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE performance_receipt (
            receipt CHAR(64) NOT NULL, expires_at DATETIME NOT NULL,
            PRIMARY KEY (receipt), INDEX performance_receipt_expiry_idx (expires_at)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE performance_receipt');
        $this->addSql('DROP TABLE performance_bucket');
    }
}
