<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261102100000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les dates de protection de récupération après une défaite majeure.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE player_universe ADD recovery_protected_until DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', ADD last_recovery_protection_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_universe DROP recovery_protected_until, DROP last_recovery_protection_at');
    }
}
