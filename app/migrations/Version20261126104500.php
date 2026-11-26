<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126104500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver les paiements réels de catalogue après résolution de la commande.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE expense_collection (
            source VARCHAR(16) NOT NULL, available_since DATETIME NOT NULL, PRIMARY KEY(source)
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        // Une période sans commande ne doit pas faire avancer le début de couverture.
        $this->addSql("INSERT INTO expense_collection (source, available_since) VALUES ('catalogue', UTC_TIMESTAMP())");
        $this->addSql('CREATE TABLE queue_expense (
            queue_item_id INT NOT NULL, player_universe_id INT NOT NULL, planet_id INT NOT NULL,
            category VARCHAR(16) NOT NULL, item_slug VARCHAR(64) NOT NULL, tier VARCHAR(16) NOT NULL,
            item_level INT DEFAULT NULL, quantity INT NOT NULL, paid JSON NOT NULL, paid_at DATETIME NOT NULL,
            refunded JSON DEFAULT NULL, refunded_at DATETIME DEFAULT NULL,
            INDEX queue_expense_paid (player_universe_id, paid_at),
            INDEX queue_expense_refunded (player_universe_id, refunded_at), PRIMARY KEY(queue_item_id),
            CONSTRAINT FK_QUEUE_EXPENSE_MEMBERSHIP FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Revenir au code précédent ne doit pas supprimer le relevé des dépenses réelles.');
    }
}
