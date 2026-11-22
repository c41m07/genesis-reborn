<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261120000300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Index unique de l’empreinte versionnée pour le lecteur mixte interne.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE UNIQUE INDEX verification_generation_fingerprint_unique ON player_verification_generation (fingerprint)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX verification_generation_fingerprint_unique ON player_verification_generation');
    }
}
