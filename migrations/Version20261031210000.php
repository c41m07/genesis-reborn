<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261031210000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les messages privés et le canal de guilde.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE social_message (id INT AUTO_INCREMENT NOT NULL, content LONGTEXT NOT NULL, created_at DATETIME NOT NULL, read_at DATETIME DEFAULT NULL, sender_id INT NOT NULL, recipient_id INT DEFAULT NULL, guild_id INT DEFAULT NULL, INDEX social_private_thread_idx (sender_id, recipient_id, id), INDEX social_guild_created_idx (guild_id, created_at), INDEX IDX_39C6E5E2F624B39D (sender_id), INDEX IDX_39C6E5E2E92F8F78 (recipient_id), INDEX IDX_39C6E5E25F2131EF (guild_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE social_message ADD CONSTRAINT FK_811D5A6CF624B39D FOREIGN KEY (sender_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE social_message ADD CONSTRAINT FK_811D5A6C158E0B66 FOREIGN KEY (recipient_id) REFERENCES player_universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE social_message ADD CONSTRAINT FK_811D5A6C1B716A3B FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE social_message DROP FOREIGN KEY FK_811D5A6CF624B39D');
        $this->addSql('ALTER TABLE social_message DROP FOREIGN KEY FK_811D5A6C158E0B66');
        $this->addSql('ALTER TABLE social_message DROP FOREIGN KEY FK_811D5A6C1B716A3B');
        $this->addSql('DROP TABLE social_message');
    }
}
