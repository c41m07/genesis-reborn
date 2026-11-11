<?php
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261111173000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Réconcilie les historiques grades/reconstruction partageant anciennement le numéro 20261111010000, sans effacer leurs données.';
    }

    public function up(Schema $schema): void
    {
        $manager=$this->connection->createSchemaManager();
        $hasRanks=$manager->tablesExist(['guild_rank']);
        $member=$manager->introspectTable('guild_member');
        $this->abortIf($hasRanks!==$member->hasColumn('rank_id'), 'Schéma des grades incomplet : intervention explicite requise.');
        if($hasRanks) {
            $rank=$manager->introspectTable('guild_rank');
            foreach(['id','name','position','permissions','version','guild_id'] as $column) {
                $this->abortIf(!$rank->hasColumn($column), 'Schéma des grades incomplet : colonne '.$column.' absente.');
            }
            $this->abortIf(!$this->hasForeignKey($rank,'guild_id','guild') || !$this->hasForeignKey($member,'rank_id','guild_rank'), 'Contraintes des grades incomplètes : intervention explicite requise.');
        }
        $hasReconstruction=$manager->tablesExist(['empire_reconstruction']);
        if($hasReconstruction) {
            $table=$manager->introspectTable('empire_reconstruction');
            foreach(['id','reference','next_at','dispatch_token','planet_id','membership_id','queue_item_id'] as $column) {
                $this->abortIf(!$table->hasColumn($column), 'Schéma de reconstruction incomplet : colonne '.$column.' absente.');
            }
            foreach(['planet_id'=>'planet','membership_id'=>'player_universe','queue_item_id'=>'queue_item'] as $column=>$target) {
                $this->abortIf(!$this->hasForeignKey($table,$column,$target), 'Contraintes de reconstruction incomplètes : intervention explicite requise.');
            }
        }
        // Les deux historiques ont pu enregistrer le même numéro : vérifier le schéma réel.
        if(!$hasRanks) {
            $this->addSql('CREATE TABLE guild_rank (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(60) NOT NULL, position INT NOT NULL, permissions JSON NOT NULL, version INT DEFAULT 1 NOT NULL, guild_id INT NOT NULL, INDEX IDX_692C4E4B5F2131EF (guild_id), UNIQUE INDEX guild_rank_name_unique (guild_id, name), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE guild_rank ADD CONSTRAINT FK_692C4E4B5F2131EF FOREIGN KEY (guild_id) REFERENCES guild (id) ON DELETE CASCADE');
            $this->addSql('ALTER TABLE guild_member ADD rank_id INT DEFAULT NULL');
            $this->addSql('CREATE INDEX IDX_7FD58C977616678F ON guild_member (rank_id)');
            $this->addSql('ALTER TABLE guild_member ADD CONSTRAINT FK_7FD58C977616678F FOREIGN KEY (rank_id) REFERENCES guild_rank (id) ON DELETE SET NULL');
        }
        if(!$hasReconstruction) {
            $this->addSql('CREATE TABLE empire_reconstruction (id INT AUTO_INCREMENT NOT NULL, reference JSON NOT NULL, next_at DATETIME DEFAULT NULL, dispatch_token VARCHAR(36) NOT NULL, planet_id INT NOT NULL, membership_id INT NOT NULL, queue_item_id INT DEFAULT NULL, INDEX IDX_C745BE92A25E9820 (planet_id), UNIQUE INDEX UNIQ_C745BE92F0EDC960 (queue_item_id), INDEX IDX_C745BE921FB354CD (membership_id), UNIQUE INDEX reconstruction_membership_planet (membership_id, planet_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
            $this->addSql('ALTER TABLE empire_reconstruction ADD CONSTRAINT FK_C745BE92A25E9820 FOREIGN KEY (planet_id) REFERENCES planet (id)');
            $this->addSql('ALTER TABLE empire_reconstruction ADD CONSTRAINT FK_C745BE921FB354CD FOREIGN KEY (membership_id) REFERENCES player_universe (id)');
            $this->addSql('ALTER TABLE empire_reconstruction ADD CONSTRAINT FK_C745BE92F0EDC960 FOREIGN KEY (queue_item_id) REFERENCES queue_item (id) ON DELETE SET NULL');
        }
    }

    private function hasForeignKey(\Doctrine\DBAL\Schema\Table $table,string $column,string $target): bool
    {
        foreach($table->getForeignKeys() as $key) {
            if($key->getLocalColumns()===[$column] && $key->getForeignTableName()===$target && $key->getForeignColumns()===['id']) return true;
        }
        return false;
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Réconciliation irréversible : impossible de distinguer les structures préexistantes sans risquer leurs données.');
    }
}
