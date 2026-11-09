<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261108040000 extends AbstractMigration
{
    public function getDescription(): string { return 'Lie les renforts au titulaire de leur occupation de départ.'; }
    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE occupation_deployment (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(36) NOT NULL, dispatched_at DATETIME NOT NULL, arrived_at DATETIME DEFAULT NULL, fleet_id INT NOT NULL, occupation_id INT NOT NULL, UNIQUE INDEX UNIQ_5DD5CFF54B061DF9 (fleet_id), INDEX IDX_5DD5CFF522C8FC20 (occupation_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE occupation_deployment ADD CONSTRAINT FK_5DD5CFF54B061DF9 FOREIGN KEY (fleet_id) REFERENCES fleet (id)');
        $this->addSql('ALTER TABLE occupation_deployment ADD CONSTRAINT FK_5DD5CFF522C8FC20 FOREIGN KEY (occupation_id) REFERENCES planet_occupation (id)');
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne('SELECT COUNT(*) FROM occupation_deployment')>0,
            'Retour impossible : les historiques des renforts doivent être conservés.');
        $this->addSql('DROP TABLE occupation_deployment');
    }
}
