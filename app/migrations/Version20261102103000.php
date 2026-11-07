<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102103000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les colonnes de protection de récupération sur le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_universe CHANGE recovery_protected_until recovery_protected_until DATETIME DEFAULT NULL, CHANGE last_recovery_protection_at last_recovery_protection_at DATETIME DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
ALTER TABLE player_universe CHANGE recovery_protected_until recovery_protected_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', CHANGE last_recovery_protection_at last_recovery_protection_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'
SQL);
    }
}
