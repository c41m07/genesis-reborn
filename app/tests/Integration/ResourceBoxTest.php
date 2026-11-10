<?php
namespace App\Tests\Integration;

use App\Entity\{Player, PlayerBoost, PlayerUniverse, Universe, Planet, QueueItem, BoostEvent, Fleet};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\QueueService;
use App\Service\Boost\{BoostGrantService, BoostUseService, ResourceBoxPolicy, ResourceBoxUseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class ResourceBoxTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        self::getContainer()->set('clock',new MockClock('2026-11-09T12:00:00Z'));
    }

    private function playerAndPlanet(string $name = 'BoostTester'): array
    {
        $data = new RegistrationData();
        $data->email = strtolower($name) . '@example.test';
        $data->username = $name;
        $data->plainPassword = 'A-test-password-2026';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);

        foreach($this->em->getRepository(\App\Entity\PlanetResource::class)->findBy(['planet'=>$planet]) as $row)$row->currentStock=1000;
        $this->em->flush();
        return [$player, $planet];
    }

    private function box(PlayerUniverse $membership,string $key='box-one',string $format='small'): PlayerBoost
    {
        $payload=self::getContainer()->get(ResourceBoxPolicy::class)->snapshot($membership->universe,$format);
        self::getContainer()->get(BoostGrantService::class)->awardEvent($membership,$key,$payload);
        return $this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceKey'=>'event-'.$key.'-player-'.$membership->id]);
    }

    public function testFixedContentsDepositExactlyOnceAndOneEventCannotGrantTwoObjects(): void
    {
        [$player,$planet]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);
        $stock=(float)$this->em->getConnection()->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id=? AND resource_type='metal'",[$planet->id]);
        self::assertFalse(self::getContainer()->get(BoostGrantService::class)->awardEvent($membership,'box-one'));
        $service=self::getContainer()->get(ResourceBoxUseService::class);
        self::assertTrue($service->open($membership,$box->id,$planet->id,str_repeat('b',32)));
        self::assertFalse($service->open($membership,$box->id,$planet->id,str_repeat('b',32)));
        self::assertEqualsWithDelta($stock+4000,(float)$this->em->getConnection()->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id=? AND resource_type='metal'",[$planet->id]),.001);
        self::assertSame('used',$box->status);self::assertSame($planet->id,$box->operationId);
    }

    public function testFullStorageRollsBackAllDepositsAndKeepsBoxIntact(): void
    {
        [$player,$planet]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);
        $connection=$this->em->getConnection();$connection->executeStatement("UPDATE planet_resource SET current_stock=200000 WHERE planet_id=? AND resource_type='metal'",[$planet->id]);
        $this->em->clear();$membership=$this->em->find(PlayerUniverse::class,$membership->id);
        $before=$connection->fetchAllAssociative('SELECT resource_type,current_stock,last_updated_at FROM planet_resource WHERE planet_id=? ORDER BY resource_type',[$planet->id]);
        $this->expectException(\DomainException::class);
        try { self::getContainer()->get(ResourceBoxUseService::class)->open($membership,$box->id,$planet->id,str_repeat('c',32)); }
        finally {
            self::assertSame($before,$connection->fetchAllAssociative('SELECT resource_type,current_stock,last_updated_at FROM planet_resource WHERE planet_id=? ORDER BY resource_type',[$planet->id]));
            self::assertSame('available',$connection->fetchOne('SELECT status FROM player_boost WHERE id=?',[$box->id]));
        }
    }

    public function testBoxesAndBoostsShareCapacityAndPromotePendingInFifoOrder(): void
    {
        [$player,$planet]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);$grants=self::getContainer()->get(BoostGrantService::class);
        for($i=1;$i<100;$i++) $grants->awardEvent($membership,'fill-'.$i);
        $grants->awardEvent($membership,'pending-boost');$pendingBox=$this->box($membership,'pending-box');
        self::assertSame('pending',$pendingBox->status);self::assertSame(2,$grants->pendingCount($membership));
        self::getContainer()->get(ResourceBoxUseService::class)->open($membership,$box->id,$planet->id,str_repeat('b',32));
        self::assertSame('available',$this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceKey'=>'event-pending-boost-player-'.$membership->id])->status);
        self::assertSame('pending',$pendingBox->status);
        self::getContainer()->get(QueueService::class)->add($membership,$planet,'buildings','mine_metal',1,str_repeat('c',32));
        $queue=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$planet]);
        self::getContainer()->get(BoostUseService::class)->useOnQueue($membership,$queue->id,str_repeat('d',32));
        self::assertSame('available',$pendingBox->status);self::assertCount(100,$grants->inventory($membership));self::assertSame(0,$grants->pendingCount($membership));
    }

    public function testBoostUseSkipsAnOlderResourceBox(): void
    {
        [$player,$planet]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);
        self::getContainer()->get(BoostGrantService::class)->awardEvent($membership,'real-boost');
        self::getContainer()->get(QueueService::class)->add($membership,$planet,'buildings','mine_metal',1,str_repeat('b',32));
        $queue=$this->em->getRepository(QueueItem::class)->findOneBy(['planet'=>$planet]);
        self::getContainer()->get(BoostUseService::class)->useOnQueue($membership,$queue->id,str_repeat('c',32));
        self::assertSame('available',$box->status);
        self::assertSame('used',$this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceKey'=>'event-real-boost-player-'.$membership->id])->status);
    }

    public function testAnotherPlayersColonyCannotReceiveTheBox(): void
    {
        [$player]=$this->playerAndPlanet();[, $other]=$this->playerAndPlanet('OtherPlayer');$box=$this->box($player->currentPlayerUniverse);
        $this->expectException(\DomainException::class);
        self::getContainer()->get(ResourceBoxUseService::class)->open($player->currentPlayerUniverse,$box->id,$other->id,str_repeat('b',32));
    }

    public function testVacationBlocksOpeningWithoutConsuming(): void
    {
        [$player,$planet]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);
        $period=new \App\Entity\VacationPeriod();$period->membership=$membership;$period->startedAt=new \DateTimeImmutable('2026-11-09T00:00:00Z');$period->scheduledEndAt=new \DateTimeImmutable('2026-11-10T00:00:00Z');$this->em->persist($period);$this->em->flush();
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('L’ouverture des boîtes est bloquée');
        try { self::getContainer()->get(ResourceBoxUseService::class)->open($membership,$box->id,$planet->id,str_repeat('b',32)); }
        finally { self::assertSame('available',$this->em->getConnection()->fetchOne('SELECT status FROM player_boost WHERE id=?',[$box->id])); }
    }

    public function testModelChangesDoNotModifyEarnedBoxes(): void
    {
        [$player]=$this->playerAndPlanet();$player->roles=['ROLE_ADMIN'];$this->em->flush();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);$policy=self::getContainer()->get(ResourceBoxPolicy::class);
        $settings=$policy->settings($membership->universe);$settings['templates']['small']['metal']=5000;
        $policy->saveSettings($player,$membership->universe,$settings,'Ajustement des futures récompenses');
        self::assertEquals(4000,$box->payload['resources']['metal']);self::assertEquals(5000,$policy->snapshot($membership->universe,'small')['resources']['metal']);
        self::assertSame(\App\Service\Boost\ResourceBoxPolicy::AMOUNTS['large']['nano'],$settings['caps']['nano']);
    }

    public function testPendingBoxCannotBeOpenedDirectly(): void
    {
        [$player,$planet]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);$box->status='pending';$this->em->flush();
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('Cette boîte n’est pas disponible');
        self::getContainer()->get(ResourceBoxUseService::class)->open($membership,$box->id,$planet->id,str_repeat('b',32));
    }

    public function testAnotherUniverseOfSamePlayerCannotReceiveBox(): void
    {
        [$player]=$this->playerAndPlanet();$membership=$player->currentPlayerUniverse;$box=$this->box($membership);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);self::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        $planet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse]);
        $this->expectException(\DomainException::class);
        self::getContainer()->get(ResourceBoxUseService::class)->open($membership,$box->id,$planet->id,str_repeat('b',32));
    }

    public function testPreparedMigrationPreservesExistingBoosts(): void
    {
        [$player]=$this->playerAndPlanet();self::getContainer()->get(BoostGrantService::class)->awardEvent($player->currentPlayerUniverse,'legacy');
        $connection=$this->em->getConnection();$connection->executeStatement('ALTER TABLE player_boost DROP kind, DROP payload');
        require_once dirname(__DIR__,2).'/migrations/Version20261109030000.php';
        $migration=new \DoctrineMigrations\Version20261109030000($connection,new \Psr\Log\NullLogger());$migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach($migration->getSql() as $query) $connection->executeStatement($query->getStatement());
        self::assertSame('boost',$connection->fetchOne('SELECT kind FROM player_boost LIMIT 1'));
        self::assertSame([], (new SchemaTool($this->em))->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
    }
}
