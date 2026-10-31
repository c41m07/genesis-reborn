<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20261021011049 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE alliance_contract (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, player_a_id INT NOT NULL, player_b_id INT NOT NULL, proposer_id INT NOT NULL, UNIQUE INDEX alliance_pair_unique (player_a_id, player_b_id), INDEX IDX_4B37558699C4036B (player_a_id), INDEX IDX_4B3755868B71AC85 (player_b_id), INDEX IDX_4B375586B13FA634 (proposer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guild (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, universe_id INT NOT NULL, leader_id INT NOT NULL, UNIQUE INDEX guild_name_unique (universe_id, name), INDEX IDX_75407DAB5CD9AF2 (universe_id), INDEX IDX_75407DAB73154ED4 (leader_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guild_member (id INT AUTO_INCREMENT NOT NULL, guild_id INT NOT NULL, player_universe_id INT NOT NULL, UNIQUE INDEX UNIQ_7FD58C975DFA7367 (player_universe_id), INDEX IDX_7FD58C975F2131EF (guild_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE guild_relation (id INT AUTO_INCREMENT NOT NULL, status VARCHAR(20) NOT NULL, guild_a_id INT NOT NULL, guild_b_id INT NOT NULL, proposer_id INT NOT NULL, UNIQUE INDEX guild_relation_unique (guild_a_id, guild_b_id), INDEX IDX_2097352DA8BF5F6 (guild_a_id), INDEX IDX_2097352C83E5A18 (guild_b_id), INDEX IDX_2097352B13FA634 (proposer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE alliance_contract ADD CONSTRAINT FK_4B37558699C4036B FOREIGN KEY (player_a_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE alliance_contract ADD CONSTRAINT FK_4B3755868B71AC85 FOREIGN KEY (player_b_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE alliance_contract ADD CONSTRAINT FK_4B375586B13FA634 FOREIGN KEY (proposer_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE guild ADD CONSTRAINT FK_75407DAB5CD9AF2 FOREIGN KEY (universe_id) REFERENCES universe (id)');
        $this->addSql('ALTER TABLE guild ADD CONSTRAINT FK_75407DAB73154ED4 FOREIGN KEY (leader_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE guild_member ADD CONSTRAINT FK_7FD58C975F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id)');
        $this->addSql('ALTER TABLE guild_member ADD CONSTRAINT FK_7FD58C975DFA7367 FOREIGN KEY (player_universe_id) REFERENCES player_universe (id)');
        $this->addSql('ALTER TABLE guild_relation ADD CONSTRAINT FK_2097352DA8BF5F6 FOREIGN KEY (guild_a_id) REFERENCES guild (id)');
        $this->addSql('ALTER TABLE guild_relation ADD CONSTRAINT FK_2097352C83E5A18 FOREIGN KEY (guild_b_id) REFERENCES guild (id)');
        $this->addSql('ALTER TABLE guild_relation ADD CONSTRAINT FK_2097352B13FA634 FOREIGN KEY (proposer_id) REFERENCES guild (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE alliance_contract DROP FOREIGN KEY FK_4B37558699C4036B');
        $this->addSql('ALTER TABLE alliance_contract DROP FOREIGN KEY FK_4B3755868B71AC85');
        $this->addSql('ALTER TABLE alliance_contract DROP FOREIGN KEY FK_4B375586B13FA634');
        $this->addSql('ALTER TABLE guild DROP FOREIGN KEY FK_75407DAB5CD9AF2');
        $this->addSql('ALTER TABLE guild DROP FOREIGN KEY FK_75407DAB73154ED4');
        $this->addSql('ALTER TABLE guild_member DROP FOREIGN KEY FK_7FD58C975F2131EF');
        $this->addSql('ALTER TABLE guild_member DROP FOREIGN KEY FK_7FD58C975DFA7367');
        $this->addSql('ALTER TABLE guild_relation DROP FOREIGN KEY FK_2097352DA8BF5F6');
        $this->addSql('ALTER TABLE guild_relation DROP FOREIGN KEY FK_2097352C83E5A18');
        $this->addSql('ALTER TABLE guild_relation DROP FOREIGN KEY FK_2097352B13FA634');
        $this->addSql('DROP TABLE alliance_contract');
        $this->addSql('DROP TABLE guild');
        $this->addSql('DROP TABLE guild_member');
        $this->addSql('DROP TABLE guild_relation');
    }
}
