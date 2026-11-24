<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261124014300 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver le travail restant des commandes dont le rythme suit les bonus.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE queue_item ADD live_timing JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // Le délai autoritaire reste inscrit dans les colonnes historiques.
        $this->addSql('ALTER TABLE queue_item DROP live_timing');
    }
}
