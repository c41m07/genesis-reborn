<?php
declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261101110000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute l’inventaire persistant des boosts joueurs.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE player_boost (id INT AUTO_INCREMENT NOT NULL, player_universe_id INT NOT NULL, source_type VARCHAR(20) NOT NULL, source_key VARCHAR(120) NOT NULL, status VARCHAR(16) NOT NULL, earned_at DATETIME NOT NULL, used_at DATETIME DEFAULT NULL, operation_type VARCHAR(16) DEFAULT NULL, operation_id INT DEFAULT NULL, INDEX player_boost_inventory_idx (player_universe_id, status, earned_at, id), INDEX player_boost_daily_risk_idx (player_universe_id, source_type, earned_at), UNIQUE INDEX player_boost_source_unique (source_key), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 ENGINE = InnoDB");
        $this->addSql('ALTER TABLE player_boost ADD CONSTRAINT FK_PLAYER_BOOST_MEMBERSHIP FOREIGN KEY (player_universe_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE player_boost DROP FOREIGN KEY FK_PLAYER_BOOST_MEMBERSHIP');
        $this->addSql('DROP TABLE player_boost');
    }
}
