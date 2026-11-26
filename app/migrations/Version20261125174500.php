<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261125174500 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Stocke la dernière simulation de combat différée privée, sans écrire dans les flottes.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE player_combat_simulation_job (
            id INT AUTO_INCREMENT NOT NULL, player_id INT NOT NULL, membership_id INT NOT NULL,
            public_key VARCHAR(36) NOT NULL, dispatch_token VARCHAR(36) NOT NULL,
            status VARCHAR(12) NOT NULL, scenario JSON NOT NULL, trials JSON NOT NULL, expires_at DATETIME NOT NULL,
            UNIQUE INDEX combat_sim_job_player (player_id), UNIQUE INDEX combat_sim_job_key (public_key),
            INDEX combat_sim_job_expiry (expires_at), INDEX combat_sim_job_membership (membership_id),
            PRIMARY KEY (id),
            CONSTRAINT FK_COMBAT_SIM_JOB_PLAYER FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE,
            CONSTRAINT FK_COMBAT_SIM_JOB_MEMBERSHIP FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE
        ) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        if ((int) $this->connection->fetchOne('SELECT COUNT(*) FROM player_combat_simulation_job') !== 0) {
            $this->throwIrreversibleMigrationException('Laissez expirer les résultats privés avant de supprimer leur table.');
        }
        $this->addSql('DROP TABLE player_combat_simulation_job');
    }
}
