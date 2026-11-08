<?php
namespace App\Tests\Integration;

use App\Entity\{Player, PlayerActivity, Universe};
use App\Service\Player\{PlayerActivityService, RegistrationService};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class PlayerActivityTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private $client;

    protected function setUp(): void
    {
        $this->client = self::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $schema = new SchemaTool($this->em);$metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->clock = new MockClock('2026-11-09T23:59:00Z');self::getContainer()->set('clock', $this->clock);
        $data = new RegistrationData();$data->email = 'activity@example.test';$data->username = 'Activity';$data->plainPassword = 'Browser-test-2026!';
        $this->player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);
        self::getContainer()->get(UniverseService::class)->select($this->player, $universe->id);
    }

    public function testIntervalsSplitAtMidnightAndDoNotAccumulateAcrossTabsOrAbsence(): void
    {
        $service = self::getContainer()->get(PlayerActivityService::class);
        $membership = $this->player->currentPlayerUniverse;
        $service->record($membership);
        $this->clock->sleep(120);$service->record($membership);
        $service->record($membership);
        $activity = $this->em->find(PlayerActivity::class, $membership->id);
        self::assertSame(120, $activity->totalSeconds);
        self::assertSame(['2026-11-09'=>60, '2026-11-10'=>60], $activity->dailySeconds);
        self::assertSame(60, $service->snapshot($activity)['today']);
        self::assertTrue($service->snapshot($activity)['online']);
        $this->clock->sleep(300);
        self::assertFalse($service->snapshot($activity)['online']);
        $this->clock->sleep(3600);$service->record($membership);
        self::assertSame(120, $activity->totalSeconds);
        self::assertTrue($service->snapshot($activity)['online']);
        $this->clock->sleep(8 * 86400);$service->record($membership);
        self::assertSame(120, $activity->totalSeconds);
        self::assertSame([], $activity->dailySeconds);
        self::assertSame(0, $service->snapshot($activity)['week']);
    }

    public function testNoHistoryBeforeFirstSampleAndInvalidIdleIsIgnored(): void
    {
        $service = self::getContainer()->get(PlayerActivityService::class);
        $membership = $this->player->currentPlayerUniverse;
        self::assertNull($service->snapshot(null)['tracked_since']);
        $service->record($membership, 300);
        self::assertNull($this->em->find(PlayerActivity::class, $membership->id));
        $service->record($membership);
        $this->clock->sleep(60);$service->record($membership, 60);
        $activity = $this->em->find(PlayerActivity::class, $membership->id);
        self::assertSame('2026-11-09T23:59:00+00:00', $activity->trackedSince->format(DATE_ATOM));
        self::assertEquals($activity->trackedSince, $activity->lastSeenAt);
        self::assertSame(60, $activity->totalSeconds);
    }

    public function testPreparedMigrationMatchesSchema(): void
    {
        $schema = new SchemaTool($this->em);
        $schema->dropSchema([$this->em->getClassMetadata(PlayerActivity::class)]);
        require_once dirname(__DIR__, 2).'/migrations/Version20261109010000.php';
        $migration = new \DoctrineMigrations\Version20261109010000($this->em->getConnection(), new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) $this->em->getConnection()->executeStatement($query->getStatement());
        self::assertSame([], $schema->getUpdateSchemaSql($this->em->getMetadataFactory()->getAllMetadata()));
    }
}
