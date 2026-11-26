<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261126124900 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Conserver les débits réels de carburant à chaque trajet et occupation.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE fleet_fuel_expense (
            transition_id VARCHAR(64) NOT NULL, player_universe_id INT NOT NULL, fleet_id INT NOT NULL,
            phase VARCHAR(32) NOT NULL, hydrogen DOUBLE PRECISION NOT NULL, burned_at DATETIME NOT NULL,
            INDEX fleet_fuel_expense_burned (player_universe_id, burned_at), PRIMARY KEY(transition_id),
            CONSTRAINT FK_FLEET_FUEL_EXPENSE_MEMBER FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $this->addSql("INSERT INTO expense_collection (source, available_since) VALUES ('fuel', UTC_TIMESTAMP())");
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Conserver les consommations de carburant lors d’un retour du code.');
    }
}
