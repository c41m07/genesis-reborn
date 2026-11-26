<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261127002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Enregistre les réserves finies et les convois protégés de la Bourse.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE resource_market (universe_id INT NOT NULL, enabled TINYINT NOT NULL, stock JSON NOT NULL, targets JSON NOT NULL, pressure JSON NOT NULL, last_occurrence VARCHAR(10) NOT NULL, PRIMARY KEY(universe_id), CONSTRAINT market_universe_fk FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE market_trade (id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, membership_id INT NOT NULL, origin_id INT NOT NULL, return_planet_id INT NOT NULL, request_key VARCHAR(32) NOT NULL, status VARCHAR(18) NOT NULL, pay_resource VARCHAR(12) NOT NULL, pay_quantity INT NOT NULL, receive_resource VARCHAR(12) NOT NULL, receive_quantity INT NOT NULL, ships JSON NOT NULL, one_way_fuel DOUBLE PRECISION NOT NULL, reserved_fuel DOUBLE PRECISION NOT NULL, recalled TINYINT NOT NULL, departed_at DATETIME NOT NULL, exchanged_at DATETIME DEFAULT NULL, due_at DATETIME DEFAULT NULL, token VARCHAR(36) NOT NULL, INDEX market_trade_outbound (universe_id, status), INDEX market_trade_week (universe_id, exchanged_at), INDEX market_trade_owner (membership_id, id), UNIQUE INDEX market_trade_order (membership_id, request_key), PRIMARY KEY(id), CONSTRAINT market_trade_universe_fk FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql('CREATE TABLE market_merchant_delivery (id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, occurrence VARCHAR(10) NOT NULL, quantities JSON NOT NULL, applied_at DATETIME NOT NULL, UNIQUE INDEX market_merchant_occurrence (universe_id, occurrence), PRIMARY KEY(id), CONSTRAINT market_merchant_universe_fk FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Supprimer la Bourse ferait aussi disparaître le fret de convois encore en vol.');
    }
}
