<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126232000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Mémorise les nouveaux cadeaux à signaler dans l’inventaire sans réveiller les anciens objets.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_boost ADD gift_unread TINYINT DEFAULT 0 NOT NULL,
            ADD INDEX player_boost_gift_unread_idx (player_universe_id, gift_unread, status, earned_at, id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_boost DROP INDEX player_boost_gift_unread_idx, DROP gift_unread');
    }
}
