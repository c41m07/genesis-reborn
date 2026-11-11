<?php
namespace App\Tests\Integration;

use App\Entity\{Planet,Universe};
use App\Service\Player\{DiplomacyService,RegistrationService,RelationResolver};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\Exception\{AbortMigration,IrreversibleMigration};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\{SchemaTool,SchemaValidator};
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class MigrationHistoryCompatibilityTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();$this->em=self::getContainer()->get(EntityManagerInterface::class);
        $params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test'||($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base non isolée : test refusé.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        require_once dirname(__DIR__,2).'/migrations/Version20261111010000.php';
        require_once dirname(__DIR__,2).'/migrations/Version20261111173000.php';
    }

    private function migration(): \DoctrineMigrations\Version20261111173000
    {
        return new \DoctrineMigrations\Version20261111173000($this->em->getConnection(),new NullLogger());
    }

    private function apply(): void
    {
        $migration=$this->migration();$migration->up(new Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement(),$query->getParameters(),$query->getTypes());
        self::assertTrue((new SchemaValidator($this->em))->schemaInSyncWithMetadata());
    }

    private function removeRanks(): void
    {
        $migration=new \DoctrineMigrations\Version20261111010000($this->em->getConnection(),new NullLogger());
        $migration->down(new Schema());
        foreach($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement());
    }

    private function fixture(): array
    {
        self::getContainer()->get(UniverseImporter::class)->import();
        $data=new RegistrationData();$data->email='migration@example.test';$data->username='Migration';$data->plainPassword='Migration-tests-2026!';
        $player=self::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        $member=$player->currentPlayerUniverse;
        self::getContainer()->get(DiplomacyService::class)->create($member,'Historique');
        return [$member,self::getContainer()->get(RelationResolver::class)->guild($member),$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$member])];
    }

    public function testGradesHistoryPreservesAssignedRankAndCreatesReconstruction(): void
    {
        [$member,$guild]=$this->fixture();$connection=$this->em->getConnection();
        $connection->insert('guild_rank',['name'=>'Diplomate','position'=>1,'permissions'=>'["diplomacy.alliance"]','version'=>4,'guild_id'=>$guild->id]);
        $rankId=(int)$connection->lastInsertId();
        $connection->executeStatement('UPDATE guild_member SET rank_id = ? WHERE player_universe_id = ?',[$rankId,$member->id]);
        $before=$connection->fetchAllAssociative('SELECT * FROM guild_rank');
        $connection->executeStatement('DROP TABLE empire_reconstruction');
        $this->apply();
        self::assertSame($before,$connection->fetchAllAssociative('SELECT * FROM guild_rank'));
        self::assertSame($rankId,(int)$connection->fetchOne('SELECT rank_id FROM guild_member WHERE player_universe_id = ?',[$member->id]));
        self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM empire_reconstruction'));
    }

    public function testReconstructionHistoryPreservesReferencesAndCreatesGrades(): void
    {
        [$member,$guild,$planet]=$this->fixture();$connection=$this->em->getConnection();
        $connection->insert('empire_reconstruction',['reference'=>'{"ships":{"fighter":{"basic":17}}}','next_at'=>'2026-11-12 12:00:00','dispatch_token'=>'preserved-token','planet_id'=>$planet->id,'membership_id'=>$member->id,'queue_item_id'=>null]);
        $before=$connection->fetchAllAssociative('SELECT * FROM empire_reconstruction');
        $this->removeRanks();$this->apply();
        self::assertSame($before,$connection->fetchAllAssociative('SELECT * FROM empire_reconstruction'));
        self::assertSame('Historique',$connection->fetchOne('SELECT name FROM guild WHERE id = ?',[$guild->id]));
        self::assertNull($connection->fetchOne('SELECT rank_id FROM guild_member WHERE player_universe_id = ?',[$member->id]));
    }

    public function testBothSchemasAlreadyPresentProducesNoSqlEvenOnReplay(): void
    {
        $this->apply();$migration=$this->migration();$migration->up(new Schema());self::assertSame([],$migration->getSql());
    }

    public function testMissingSchemasAreCreatedWithoutChangingMembers(): void
    {
        [$member]=$this->fixture();$connection=$this->em->getConnection();
        $this->removeRanks();$connection->executeStatement('DROP TABLE empire_reconstruction');$this->apply();
        self::assertSame(0,(int)$connection->fetchOne('SELECT COUNT(*) FROM guild_rank'));
        self::assertNull($connection->fetchOne('SELECT rank_id FROM guild_member WHERE player_universe_id = ?',[$member->id]));
    }

    public function testPartialGradesAreRejectedBeforeAnySql(): void
    {
        $connection=$this->em->getConnection();$connection->executeStatement('ALTER TABLE guild_member DROP FOREIGN KEY FK_7FD58C977616678F');
        $connection->executeStatement('ALTER TABLE guild_member DROP rank_id');
        $migration=$this->migration();
        try {$migration->up(new Schema());self::fail('Le schéma partiel doit être refusé.');}
        catch(AbortMigration $error) {self::assertStringContainsString('Schéma des grades incomplet',$error->getMessage());self::assertSame([],$migration->getSql());}
    }

    public function testPartialReconstructionIsRejectedBeforeAnySql(): void
    {
        $this->removeRanks();$this->em->getConnection()->executeStatement('ALTER TABLE empire_reconstruction DROP reference');
        $migration=$this->migration();
        try {$migration->up(new Schema());self::fail('Le schéma partiel doit être refusé.');}
        catch(AbortMigration $error) {self::assertStringContainsString('Schéma de reconstruction incomplet',$error->getMessage());self::assertSame([],$migration->getSql());}
    }

    public function testRollbackNeverDeletesPreexistingStructures(): void
    {
        $this->expectException(IrreversibleMigration::class);$this->migration()->down(new Schema());
    }
}
