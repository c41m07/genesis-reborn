<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261105181020 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Aligne les dates et les noms des index des retours et notes de version sur le mapping Doctrine.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patch_note CHANGE published_at published_at DATETIME DEFAULT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_feedback CHANGE created_at created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE user_feedback RENAME INDEX idx_feedback_player TO IDX_32A4A05899E6F5DF');
        $this->addSql('ALTER TABLE user_feedback RENAME INDEX idx_feedback_universe TO IDX_32A4A0585CD9AF2');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE patch_note CHANGE published_at published_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', CHANGE updated_at updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_feedback CHANGE created_at created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE user_feedback RENAME INDEX idx_32a4a05899e6f5df TO IDX_FEEDBACK_PLAYER');
        $this->addSql('ALTER TABLE user_feedback RENAME INDEX idx_32a4a0585cd9af2 TO IDX_FEEDBACK_UNIVERSE');
    }
}
