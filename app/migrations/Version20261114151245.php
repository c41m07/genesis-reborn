<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261114151245 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Distingue le format des nouveaux messages et protège les soumissions répétées.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE social_message ADD content_format VARCHAR(16) DEFAULT 'plain' NOT NULL, ADD submission_id VARCHAR(36) DEFAULT NULL");
        $this->addSql('CREATE UNIQUE INDEX social_submission_unique ON social_message (sender_id, submission_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX social_submission_unique ON social_message');
        $this->addSql('ALTER TABLE social_message DROP content_format, DROP submission_id');
    }
}
