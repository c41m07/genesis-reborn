<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261031214752 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les réglages de jeu persistants par univers.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE universe ADD game_settings JSON DEFAULT NULL');
        $this->addSql("UPDATE universe SET game_settings = '{}' WHERE game_settings IS NULL");
        $this->addSql('ALTER TABLE universe MODIFY game_settings JSON NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE universe DROP game_settings');
    }
}
