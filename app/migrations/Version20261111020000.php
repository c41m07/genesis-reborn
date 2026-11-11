<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111020000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Formalise les propositions et le journal des traités de guilde, avec contrôle de version.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE guild_relation ADD version INT DEFAULT 1 NOT NULL, ADD proposal VARCHAR(20) DEFAULT NULL');
        $this->addSql("UPDATE guild_relation SET proposal = 'allies' WHERE status = 'pending'");
        $this->addSql('CREATE TABLE guild_treaty_event (id INT AUTO_INCREMENT NOT NULL, universe_id INT NOT NULL, guild_a_id INT NOT NULL, guild_b_id INT NOT NULL, actor_id INT NOT NULL, actor_guild_id INT NOT NULL, action VARCHAR(24) NOT NULL, previous_status VARCHAR(20) NOT NULL, status VARCHAR(20) NOT NULL, proposal VARCHAR(20) DEFAULT NULL, occurred_at DATETIME NOT NULL, INDEX treaty_pair_history (universe_id, guild_a_id, guild_b_id, id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM guild_relation WHERE version > 1') > 0, 'Retour refusé : des relations versionnées doivent être conservées.');
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM guild_treaty_event') > 0, 'Retour refusé : le journal des traités doit être conservé.');
        $this->addSql('DROP TABLE guild_treaty_event');
        $this->addSql('ALTER TABLE guild_relation DROP version, DROP proposal');
    }
}
