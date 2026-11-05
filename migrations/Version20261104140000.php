<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104140000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les patchnotes administrables.'; }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE patch_note (id INT AUTO_INCREMENT NOT NULL, title VARCHAR(160) NOT NULL, version VARCHAR(40) NOT NULL, summary VARCHAR(500) NOT NULL, content LONGTEXT NOT NULL, status VARCHAR(16) NOT NULL, published_at DATETIME DEFAULT NULL COMMENT '(DC2Type:datetime_immutable)', created_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', updated_at DATETIME NOT NULL COMMENT '(DC2Type:datetime_immutable)', INDEX patch_note_status_published_idx (status, published_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB");
    }

    public function down(Schema $schema): void { $this->addSql('DROP TABLE patch_note'); }
}
