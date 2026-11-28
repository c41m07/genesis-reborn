<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver le propriétaire observé et l’expéditeur identifié dans les rapports d’espionnage.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE espionage_report ADD target_owner_name VARCHAR(80) DEFAULT NULL, ADD identified_sender_name VARCHAR(80) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE espionage_report DROP target_owner_name, DROP identified_sender_name');
    }
}
