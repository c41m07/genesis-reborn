<?php
declare(strict_types=1);
namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261129100000 extends AbstractMigration
{
    public function getDescription(): string { return 'Édition des contenus, médias, activité manuelle et échéance des offres.'; }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE challenge_cosmetic_award (event_id INT NOT NULL, membership_id INT NOT NULL, PRIMARY KEY(event_id,membership_id), INDEX IDX_F74523C41FB354CD (membership_id), FOREIGN KEY (event_id) REFERENCES boost_event (id) ON DELETE CASCADE, FOREIGN KEY (membership_id) REFERENCES player_universe (id) ON DELETE CASCADE) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE editorial_image (id INT AUTO_INCREMENT NOT NULL, encoded MEDIUMTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE editorial_revision (id INT AUTO_INCREMENT NOT NULL, universe_id INT DEFAULT NULL, author_id INT DEFAULT NULL, image_id INT DEFAULT NULL, kind VARCHAR(16) NOT NULL, code VARCHAR(40) NOT NULL, content JSON NOT NULL, created_at DATETIME NOT NULL, publish_at DATETIME DEFAULT NULL, INDEX IDX_46EC67205CD9AF2 (universe_id), INDEX IDX_46EC6720F675F31B (author_id), INDEX IDX_46EC67203DA5256D (image_id), INDEX editorial_publication_idx (kind, universe_id, code, publish_at, id), PRIMARY KEY(id), FOREIGN KEY (universe_id) REFERENCES universe (id) ON DELETE CASCADE, FOREIGN KEY (author_id) REFERENCES player (id) ON DELETE SET NULL, FOREIGN KEY (image_id) REFERENCES editorial_image (id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE player_universe ADD manual_inactive_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE market_offer ADD expires_at DATETIME DEFAULT NULL, ADD return_planet_id INT DEFAULT NULL, ADD INDEX market_offer_expiry (expires_at,id)');
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException('Les versions, médias, reçus et destinations des offres doivent être conservés.');
    }
}
