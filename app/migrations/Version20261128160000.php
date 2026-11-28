<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261128160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajouter les comptes commerciaux, les offres des joueurs et les opérations des convois à A:0:0.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE market_trade ADD operation VARCHAR(12) DEFAULT 'barter' NOT NULL, ADD credit_cents BIGINT DEFAULT 0 NOT NULL");
        $this->addSql('ALTER TABLE resource_market ADD last_player_prices JSON DEFAULT NULL');
        $this->addSql('CREATE TABLE market_account (membership_id INT NOT NULL, cents BIGINT NOT NULL, goods JSON NOT NULL, PRIMARY KEY (membership_id), FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE market_offer (id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, seller_id INT NOT NULL, resource VARCHAR(12) NOT NULL, remaining INT NOT NULL, unit_cents INT NOT NULL, INDEX IDX_E2F8384B8DE820D9 (seller_id), INDEX market_offer_book (universe_id, resource, unit_cents, id), PRIMARY KEY (id), FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE, FOREIGN KEY (seller_id) REFERENCES player_universe (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les crédits, les offres et les marchandises déposées doivent être conservés.');
    }
}
