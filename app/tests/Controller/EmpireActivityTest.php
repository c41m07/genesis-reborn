<?php
namespace App\Tests\Controller;

use App\Entity\{Player, PlayerUniverse, Universe, Planet};
use App\Service\Player\{EmpireActivityService, RegistrationService};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class EmpireActivityTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $player;
    private PlayerUniverse $selected;
    private PlayerUniverse $other;
    private Planet $planet;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);

        $this->clock = new MockClock('2026-11-07T12:00:00Z');
        static::getContainer()->set('clock', $this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        $data = new RegistrationData();
        $data->email = 'activity@example.test';
        $data->username = 'Activity';
        $data->plainPassword = 'A-test-password-2026';
        $this->player = static::getContainer()->get(RegistrationService::class)->register($data);
        $this->player->emailVerifiedAt = $this->clock->now();

        $universes = $this->em->getRepository(Universe::class);
        $service = static::getContainer()->get(UniverseService::class);
        $service->select($this->player, $universes->findOneBy(['slug' => 'omega-1'])->id);
        $this->other = $this->player->currentPlayerUniverse;
        $service->select($this->player, $universes->findOneBy(['slug' => 'turbo-1'])->id);
        $this->selected = $this->player->currentPlayerUniverse;
        $this->planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $this->selected]);
        $this->client->loginUser($this->player);
    }

    public function testNewEmpiresStartTrackedAndVisitsOnlyUpdateTheSelectedUniverse(): void
    {
        self::assertEquals($this->selected->joinedAt, $this->selected->lastActivityAt);
        $otherActivity = $this->other->lastActivityAt;
        $this->clock->sleep(100);
        $this->client->request('GET', '/planetes');
        self::assertResponseIsSuccessful();
        $this->em->refresh($this->selected);
        $this->em->refresh($this->other);
        self::assertEquals($this->clock->now(), $this->selected->lastActivityAt);
        self::assertEquals($otherActivity, $this->other->lastActivityAt);
    }

    public function testPollingAccountPagesAndFailedVisitsDoNotRenewActivity(): void
    {
        $lastActivityAt = $this->selected->lastActivityAt;
        $this->clock->sleep(100);
        $this->client->request('GET', '/planetes/' . $this->planet->id . '/ressources');
        self::assertResponseIsSuccessful();
        $this->assertActivity($lastActivityAt);
        $this->client->request('GET', '/profil/preferences');
        self::assertResponseIsSuccessful();
        $this->assertActivity($lastActivityAt);
        $this->client->request('GET', '/univers');
        self::assertResponseIsSuccessful();
        $this->assertActivity($lastActivityAt);
        $this->client->request('GET', '/planetes', [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
        self::assertResponseIsSuccessful();
        $this->assertActivity($lastActivityAt);
        $this->client->request('GET', '/planetes', [], [], ['HTTP_SEC_FETCH_DEST' => 'empty']);
        self::assertResponseIsSuccessful();
        $this->assertActivity($lastActivityAt);
        $this->client->request('GET', '/planetes/99999999');
        self::assertResponseStatusCodeSame(404);
        $this->assertActivity($lastActivityAt);
    }

    public function testAVisitReactivatesAnInactiveEmpireBeforeThirtyDays(): void
    {
        $this->selected->lastActivityAt = $this->clock->now()->modify('-20 days');
        $this->em->flush();
        $activity = static::getContainer()->get(EmpireActivityService::class);
        self::assertSame('inactive', $activity->snapshot($this->selected)['status']);
        $this->client->request('GET', '/planetes');
        self::assertResponseIsSuccessful();
        $this->assertActivity($this->clock->now());
        self::assertSame('active', $activity->snapshot($this->selected)['status']);
    }

    public function testAnOldEmpireWithoutKnownActivityStartsOnItsFirstVisit(): void
    {
        $this->selected->joinedAt = new \DateTimeImmutable('2000-01-01T00:00:00Z');
        $this->selected->lastActivityAt = null;
        $this->em->flush();
        $this->client->request('GET', '/planetes');
        self::assertResponseIsSuccessful();
        $this->assertActivity($this->clock->now());
    }

    public function testRecordingAnOlderVisitPreservesANewerPersistedVisit(): void
    {
        $newerVisit = $this->clock->now()->modify('+1 minute');
        $this->em->getConnection()->executeStatement(
            'UPDATE player_universe SET last_activity_at = ? WHERE id = ?',
            [$newerVisit->format('Y-m-d H:i:s'), $this->selected->id],
        );
        $this->selected->lastActivityAt = $this->clock->now()->modify('-1 minute');
        static::getContainer()->get(EmpireActivityService::class)->recordVisit($this->selected);
        $this->assertActivity($newerVisit);
    }

    public function testAnAnonymousVisitCannotRefreshTheEmpire(): void
    {
        $lastActivityAt = $this->selected->lastActivityAt;
        $this->clock->sleep(100);
        $this->client->restart();
        $this->client->request('GET', '/planetes');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $this->assertActivity($lastActivityAt);
    }

    public function testVisitsDuringVacationKeepTheCounterAndLongExpiryResumesIt(): void
    {
        $lastActivity = $this->clock->now()->modify('-10 days');
        $this->selected->lastActivityAt = $lastActivity;
        $this->em->flush();
        $this->vacation($this->selected, $this->clock->now());
        $this->clock->sleep(2 * 86400);
        $this->client->request('GET', '/planetes');
        self::assertResponseIsSuccessful();
        $this->assertActivity($lastActivity);
        $activity = static::getContainer()->get(\App\Service\Player\EmpireActivityService::class);
        self::assertSame('vacation', $activity->snapshot($this->selected)['status']);
        self::assertSame(10 * 86400, $activity->inactiveSeconds($this->selected));
        self::assertFalse($activity->snapshot($this->selected)['conversion_due']);
        $this->clock->sleep(88 * 86400);
        self::assertSame('active', $activity->snapshot($this->selected)['status']);
        self::assertSame(10 * 86400, $activity->inactiveSeconds($this->selected));
        $this->clock->sleep(4 * 86400);
        self::assertSame('inactive', $activity->snapshot($this->selected)['status']);
        self::assertSame(\App\Service\Player\EmpireConversionSchedule::atOrAfter($lastActivity->modify('+120 days'))->format(DATE_ATOM), $activity->snapshot($this->selected)['conversion_due_at']);
        $this->clock->sleep(16 * 86400);
        self::assertFalse($activity->snapshot($this->selected)['conversion_due']);
        $this->clock->sleep(11 * 3600);
        self::assertTrue($activity->snapshot($this->selected)['conversion_due']);
    }

    public function testSeveralCompletedVacationsAreCountedUntilARealReturn(): void
    {
        $lastActivity = $this->clock->now()->modify('-33 days');
        $this->selected->lastActivityAt = $lastActivity;
        $this->other->lastActivityAt = $lastActivity;
        $this->em->flush();
        $this->vacation($this->selected, $lastActivity->modify('+10 days'), $lastActivity->modify('+12 days'));
        $this->vacation($this->selected, $lastActivity->modify('+27 days'), $lastActivity->modify('+29 days'));
        $activity = static::getContainer()->get(\App\Service\Player\EmpireActivityService::class);
        self::assertSame(29 * 86400, $activity->inactiveSeconds($this->selected));
        self::assertSame(33 * 86400, $activity->inactiveSeconds($this->other));
        self::assertTrue($activity->snapshot($this->other)['conversion_due']);
        self::assertFalse($activity->snapshot($this->selected)['conversion_due']);
        self::assertSame(\App\Service\Player\EmpireConversionSchedule::atOrAfter($lastActivity->modify('+34 days'))->format(DATE_ATOM), $activity->snapshot($this->selected)['conversion_due_at']);
        self::assertSame(33 * 86400, $this->clock->now()->getTimestamp() - $lastActivity->getTimestamp());
        $activity->recordVisit($this->selected);
        self::assertSame(0, $activity->inactiveSeconds($this->selected));
        self::assertSame(\App\Service\Player\EmpireConversionSchedule::atOrAfter($this->clock->now()->modify('+30 days'))->format(DATE_ATOM), $activity->snapshot($this->selected)['conversion_due_at']);
        self::assertSame(2, $this->em->getRepository(\App\Entity\VacationPeriod::class)->count());
        self::assertEquals($lastActivity, $this->other->lastActivityAt);
    }

    public function testUnknownActivityIsNotInitializedByAVisitDuringVacation(): void
    {
        $this->selected->lastActivityAt = null;
        $this->em->flush();
        $this->vacation($this->selected, $this->clock->now());
        $this->clock->sleep(2 * 86400);
        $this->client->request('GET', '/planetes');
        self::assertResponseIsSuccessful();
        $this->assertActivity(null);
        $activity = static::getContainer()->get(\App\Service\Player\EmpireActivityService::class);
        self::assertSame('vacation', $activity->snapshot($this->selected)['status']);
        self::assertNull($activity->snapshot($this->selected)['conversion_due_at']);
        $this->clock->sleep(88 * 86400);
        $this->client->restart();
        $this->client->loginUser($this->em->find(Player::class, $this->player->id));
        $this->client->request('GET', '/planetes');
        self::assertResponseIsSuccessful();
        $this->assertActivity($this->clock->now());
    }

    private function vacation(PlayerUniverse $membership, \DateTimeImmutable $start, ?\DateTimeImmutable $end = null): void
    {
        $period = new \App\Entity\VacationPeriod();
        $period->membership = $membership;
        $period->startedAt = $start;
        $period->scheduledEndAt = $start->modify('+90 days');
        $period->endedAt = $end;
        $this->em->persist($period);
        $this->em->flush();
    }

    private function assertActivity(?\DateTimeImmutable $expected): void
    {
        $membershipId = $this->selected->id;
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->em->clear();
        $this->selected = $this->em->find(PlayerUniverse::class, $membershipId);
        self::assertEquals($expected, $this->selected->lastActivityAt);
    }
}
