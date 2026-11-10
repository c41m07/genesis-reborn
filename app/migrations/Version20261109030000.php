<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261109030000 extends AbstractMigration
{
    public function getDescription(): string { return 'Étend l’inventaire partagé aux boîtes de ressources en conservant tous les boosts existants.'; }
    public function up(Schema $schema): void
    {
        $this->addSql("ALTER TABLE player_boost ADD kind VARCHAR(16) DEFAULT 'boost' NOT NULL, ADD payload JSON DEFAULT NULL");
    }
    public function down(Schema $schema): void
    {
        $this->abortIf((int)$this->connection->fetchOne("SELECT COUNT(*) FROM player_boost WHERE kind <> 'boost' OR payload IS NOT NULL")>0,'Les boîtes et leurs historiques doivent être conservés avant tout retour en arrière.');
        $this->addSql('ALTER TABLE player_boost DROP kind, DROP payload');
    }
}
