<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105012000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute la référence de pièce jointe aux tables de retours déjà créées.';
    }

    public function up(Schema $schema): void
    {
        if (!$schema->getTable('user_feedback')->hasColumn('attachment_filename')) {
            $this->addSql('ALTER TABLE user_feedback ADD attachment_filename VARCHAR(120) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('La référence de pièce jointe est conservée pour éviter toute perte de données.');
    }
}
