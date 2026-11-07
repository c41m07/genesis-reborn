<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261107150000 extends AbstractMigration
{
    public function getDescription(): string { return 'Ajoute les rapports et l’historique des transports de ressources.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("CREATE TABLE transport_report (id INT AUTO_INCREMENT NOT NULL, fleet_id INT NOT NULL, sender_id INT NOT NULL, recipient_id INT NOT NULL, status VARCHAR(20) NOT NULL, snapshot JSON NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, UNIQUE INDEX UNIQ_77D4020D4B061DF9 (fleet_id), INDEX IDX_77D4020DF624B39D (sender_id), INDEX IDX_77D4020DE92F8F78 (recipient_id), INDEX transport_created_idx (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB");
        $this->addSql('ALTER TABLE transport_report ADD CONSTRAINT FK_TRANSPORT_FLEET FOREIGN KEY (fleet_id) REFERENCES fleet (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transport_report ADD CONSTRAINT FK_TRANSPORT_SENDER FOREIGN KEY (sender_id) REFERENCES player_universe (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE transport_report ADD CONSTRAINT FK_TRANSPORT_RECIPIENT FOREIGN KEY (recipient_id) REFERENCES player_universe (id) ON DELETE CASCADE');
    }
    public function down(Schema $schema): void { $this->addSql('DROP TABLE transport_report'); }
}
