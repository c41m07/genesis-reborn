<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111010000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Ajoute les grades de guilde et leur affectation facultative, sans changer les contrats personnels.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE guild_rank (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, position INT NOT NULL, permissions JSON NOT NULL, version INT DEFAULT 1 NOT NULL, guild_id INT NOT NULL, INDEX IDX_692C4E4B5F2131EF (guild_id), UNIQUE INDEX guild_rank_name_unique (guild_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE guild_rank ADD CONSTRAINT FK_692C4E4B5F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE guild_member ADD rank_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_7FD58C977616678F ON guild_member (rank_id)');
        $this->addSql('ALTER TABLE guild_member ADD CONSTRAINT FK_7FD58C977616678F FOREIGN KEY (rank_id) REFERENCES guild_rank (id) ON DELETE SET NULL');
    }

    public function down(Schema $schema): void
    {
        $this->abortIf((int) $this->connection->fetchOne('SELECT COUNT(*) FROM guild_rank') > 0, 'Retour refusé : des grades existants doivent être conservés.');
        $this->addSql('ALTER TABLE guild_member DROP FOREIGN KEY FK_7FD58C977616678F');
        $this->addSql('DROP INDEX IDX_7FD58C977616678F ON guild_member');
        $this->addSql('ALTER TABLE guild_member DROP rank_id');
        $this->addSql('DROP TABLE guild_rank');
    }
}
