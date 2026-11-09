<?php
namespace App\Tests\Integration;

use App\Command\EmpireInactivityWarningsCommand;
use App\Entity\{EmpireInactivityWarning, Player, PlayerUniverse, Universe};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Player\{EmpireActivityService, EmpireInactivityWarningService};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class EmpireInactivityWarningTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PlayerUniverse $membership;
    /** @var list<Email> */
    private array $messages = [];
    private ?\Closure $onSend = null;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-07T12:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $this->membership = $this->membership('warning', $this->clock->now()->modify('-14 days'));
    }

    public function testPreviewDoesNotWriteOrSendAndDefaultConfigurationBlocksSending(): void
    {
        $tester = new CommandTester(self::getContainer()->get(EmpireInactivityWarningsCommand::class));
        self::assertSame(Command::SUCCESS, $tester->execute([]));
        self::assertStringContainsString('Prévisualisation sans écriture ni envoi — éligibles : 1', $tester->getDisplay());
        self::assertSame(0, $this->em->getRepository(EmpireInactivityWarning::class)->count());
        self::assertSame(Command::INVALID, $tester->execute(['--send' => true]));
        self::assertStringContainsString('désactivés', $tester->getDisplay());
        self::assertSame(0, $this->em->getRepository(EmpireInactivityWarning::class)->count());
    }

    public function testExactThresholdsAndRepeatedRunsOnlySendOncePerStage(): void
    {
        $this->membership->lastActivityAt = $this->membership->lastActivityAt->modify('+1 second');
        $this->em->flush();
        self::assertSame(0, $this->service()->run(true)['sent']);
        $this->clock->sleep(1);
        self::assertSame(1, $this->service()->run(true)['sent']);
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(13 * 86400 - 1);
        self::assertSame(0, $this->service()->run(true)['sent']);
        $this->clock->sleep(1);
        self::assertSame(1, $this->service()->run(true)['sent']);
        self::assertSame(0, $this->service()->run(true)['eligible']);
        self::assertCount(2, $this->messages);
        self::assertStringContainsString('Dernier avertissement', $this->messages[1]->getSubject());
        self::assertStringContainsString('23/11/2026 à 23:00 UTC', $this->messages[0]->getTextBody());
        self::assertStringContainsString('https://game.example.test/univers', $this->messages[0]->getTextBody());
        self::assertStringContainsString('Warning warning', $this->messages[0]->getTextBody());
        self::assertSame('warning@example.test', $this->messages[0]->getTo()[0]->getAddress());
        self::assertSame('game@example.test', $this->messages[0]->getFrom()[0]->getAddress());
        self::assertSame(2, $this->em->getRepository(EmpireInactivityWarning::class)->count());
    }

    public function testLateProcessingSendsOnlyTheCurrentReminder(): void
    {
        $this->membership->lastActivityAt = $this->clock->now()->modify('-28 days');
        $this->em->flush();
        self::assertSame(1, $this->service()->run(true)['sent']);
        self::assertSame(0, $this->service()->run(true)['sent']);
        $warning = $this->em->getRepository(EmpireInactivityWarning::class)->findOneBy([]);
        self::assertSame(27, $warning->thresholdDays);
        self::assertStringContainsString('09/11/2026 à 23:00 UTC', $this->messages[0]->getTextBody());
    }

    public function testExpiredUnknownUnverifiedSuspendedAndClosedEmpiresAreExcluded(): void
    {
        $this->membership->lastActivityAt = $this->clock->now()->modify('-30 days');
        $this->membership('unknown', null);
        $unverified = $this->membership('unverified', $this->clock->now()->modify('-20 days'));
        $unverified->player->emailVerifiedAt = null;
        $suspended = $this->membership('suspended', $this->clock->now()->modify('-20 days'));
        $suspended->player->lockedAt = $this->clock->now();
        $closed = $this->membership('closed', $this->clock->now()->modify('-20 days'));
        $closed->universe->status = 'closed';
        $future = $this->membership('future', $this->clock->now()->modify('-20 days'));
        $future->universe->startsAt = $this->clock->now()->modify('+1 second');
        $ended = $this->membership('ended', $this->clock->now()->modify('-20 days'));
        $ended->universe->endsAt = $this->clock->now();
        $this->em->flush();
        self::assertSame(0, $this->service()->run(true)['eligible']);
        self::assertCount(0, $this->messages);
    }

    public function testAVisitStartsANewWarningCycleWithoutErasingHistory(): void
    {
        self::assertSame(1, $this->service()->run(true)['sent']);
        $this->clock->sleep(60);
        self::getContainer()->get(EmpireActivityService::class)->recordVisit($this->membership);
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(14 * 86400);
        self::assertSame(1, $this->service()->run(true)['sent']);
        self::assertSame(2, $this->em->getRepository(EmpireInactivityWarning::class)->count());
    }

    public function testTransportFailureRemainsRetryable(): void
    {
        $this->onSend = static function (): void { throw new TransportException('Transport de test indisponible'); };
        self::assertSame(1, $this->service()->run(true)['failed']);
        $warning = $this->em->getRepository(EmpireInactivityWarning::class)->findOneBy([]);
        self::assertNull($warning->sentAt);
        $this->onSend = null;
        self::assertSame(1, $this->service()->run(true)['sent']);
        self::assertNotNull($warning->sentAt);
        self::assertSame(1, $this->em->getRepository(EmpireInactivityWarning::class)->count());
        self::assertSame(0, $this->service()->run(true)['eligible']);
    }

    public function testBatchLimitAdvancesPastWarningsAlreadySent(): void
    {
        $this->membership('older', $this->clock->now()->modify('-15 days'));
        self::assertSame(1, $this->service()->run(true, 1)['sent']);
        self::assertSame(1, $this->service()->run(true, 1)['sent']);
        self::assertSame(0, $this->service()->run(true, 1)['eligible']);
        self::assertCount(2, $this->messages);
    }

    public function testAReconnectionAfterSelectionCancelsThePendingWarning(): void
    {
        $this->membership('older', $this->clock->now()->modify('-15 days'));
        $this->onSend = function (): void {
            $this->em->getConnection()->executeStatement(
                'UPDATE player_universe SET last_activity_at = ? WHERE id = ?',
                [$this->clock->now()->format('Y-m-d H:i:s'), $this->membership->id],
            );
        };
        $result = $this->service()->run(true);
        self::assertSame(2, $result['eligible']);
        self::assertSame(1, $result['sent']);
        self::assertSame(1, $result['skipped']);
        self::assertCount(1, $this->messages);
    }

    public function testCommandValidatesLimitsAndReportsTransportFailure(): void
    {
        $tester = new CommandTester(new EmpireInactivityWarningsCommand($this->service()));
        foreach (['zero', '0', '501'] as $limit) {
            self::assertSame(Command::INVALID, $tester->execute(['--limit' => $limit]));
        }
        $this->onSend = static function (): void { throw new TransportException('Indisponible'); };
        self::assertSame(Command::FAILURE, $tester->execute(['--send' => true]));
        self::assertStringContainsString('échecs : 1', $tester->getDisplay());
        self::assertStringNotContainsString('warning@example.test', $tester->getDisplay());
    }

    public function testAnotherSenderUpdatingTheJournalCancelsThePendingDuplicate(): void
    {
        $warning = new EmpireInactivityWarning();
        $warning->membership = $this->membership;
        $warning->activityAt = $this->membership->lastActivityAt;
        $warning->thresholdDays = 14;
        $warning->createdAt = $this->clock->now();
        $this->em->persist($warning);
        $this->em->flush();
        $this->membership('older', $this->clock->now()->modify('-15 days'));
        $this->onSend = function () use ($warning): void {
            $this->em->getConnection()->executeStatement(
                'UPDATE empire_inactivity_warning SET sent_at = ? WHERE id = ?',
                [$this->clock->now()->format('Y-m-d H:i:s'), $warning->id],
            );
        };
        $result = $this->service()->run(true);
        self::assertSame(2, $result['eligible']);
        self::assertSame(1, $result['sent']);
        self::assertSame(1, $result['skipped']);
        self::assertCount(1, $this->messages);
    }

    public function testWarningsWaitThroughANinetyDayVacationAndKeepTheAdjustedDeadline(): void
    {
        $lastActivity = $this->membership->lastActivityAt;
        $this->vacation($this->membership, $lastActivity->modify('+10 days'));
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(86 * 86400);
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(4 * 86400 - 1);
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(1);
        self::assertSame(1, $this->service()->run(true)['sent']);
        $deadline = \App\Service\Player\EmpireConversionSchedule::atOrAfter($lastActivity->modify('+120 days'))->format('d/m/Y à H:i') . ' UTC';
        self::assertStringContainsString($deadline, $this->messages[0]->getTextBody());
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(13 * 86400 - 1);
        self::assertSame(0, $this->service()->run(true)['eligible']);
        $this->clock->sleep(1);
        self::assertSame(1, $this->service()->run(true)['sent']);
        self::assertStringContainsString('Dernier avertissement', $this->messages[1]->getSubject());
        $this->clock->sleep(3 * 86400);
        self::assertSame(0, $this->service()->run(true)['eligible']);
    }

    public function testAVacationAfterTheFirstWarningDoesNotResendItAndDoesNotBlockOtherCandidates(): void
    {
        self::assertSame(1, $this->service()->run(true)['sent']);
        $start = $this->clock->now();
        $this->vacation($this->membership, $start);
        $other = $this->membership('other', $this->clock->now()->modify('-14 days'));
        self::assertSame(1, $this->service()->run(true, 1)['sent']);
        self::assertSame('other@example.test', $this->messages[1]->getTo()[0]->getAddress());
        $other->lastActivityAt = $this->clock->now();
        $this->em->flush();
        $this->clock->sleep(90 * 86400);
        self::assertSame(0, $this->service()->run(true, 1)['eligible']);
        $this->clock->sleep(13 * 86400);
        self::assertSame(1, $this->service()->run(true, 1)['sent']);
        self::assertStringContainsString('Dernier avertissement', $this->messages[2]->getSubject());
        self::assertSame(3, $this->em->getRepository(EmpireInactivityWarning::class)->count());
    }

    public function testAShortCompletedVacationDoesNotFillTheBatchBeforeItsAdjustedThreshold(): void
    {
        $lastActivity = $this->membership->lastActivityAt;
        $this->vacation($this->membership, $lastActivity->modify('+10 days'));
        $period = $this->em->getRepository(\App\Entity\VacationPeriod::class)->findOneBy([]);
        $period->endedAt = $lastActivity->modify('+12 days');
        $this->em->flush();
        $other = $this->membership('other-short', $lastActivity);
        self::assertSame(1, $this->service()->run(true, 1)['sent']);
        self::assertSame('other-short@example.test', $this->messages[0]->getTo()[0]->getAddress());
        $other->lastActivityAt = $this->clock->now();
        $this->em->flush();
        $this->clock->sleep(2 * 86400);
        self::assertSame(1, $this->service()->run(true, 1)['sent']);
        $deadline = \App\Service\Player\EmpireConversionSchedule::atOrAfter($lastActivity->modify('+32 days'))->format('d/m/Y à H:i') . ' UTC';
        self::assertStringContainsString($deadline, $this->messages[1]->getTextBody());
    }

    public function testEnteringVacationAfterSelectionCancelsThePendingWarning(): void
    {
        $this->membership('older', $this->clock->now()->modify('-15 days'));
        $this->onSend = function (): void { $this->vacation($this->membership, $this->clock->now()); };
        $result = $this->service()->run(true);
        self::assertSame(2, $result['eligible']);
        self::assertSame(1, $result['sent']);
        self::assertSame(1, $result['skipped']);
        self::assertCount(1, $this->messages);
    }

    public function testDailyCommandDrainsAllWarningBatchesWithoutDuplicates(): void
    {
        $this->membership('second',$this->clock->now()->modify('-15 days'));
        $this->membership('third',$this->clock->now()->modify('-28 days'));
        $tester=new CommandTester(new EmpireInactivityWarningsCommand($this->service()));
        self::assertSame(Command::INVALID,$tester->execute(['--all'=>true]));self::assertCount(0,$this->messages);
        self::assertSame(Command::SUCCESS,$tester->execute(['--send'=>true,'--all'=>true,'--limit'=>'1']));
        self::assertCount(3,$this->messages);self::assertStringContainsString('envoyés : 3',$tester->getDisplay());
        self::assertSame(Command::SUCCESS,$tester->execute(['--send'=>true,'--all'=>true,'--limit'=>'1']));self::assertCount(3,$this->messages);
    }

    public function testDailyCommandStopsAfterATransportFailure(): void
    {
        $this->membership('other',$this->clock->now()->modify('-15 days'));$attempts=0;
        $this->onSend=static function()use(&$attempts): void { ++$attempts;throw new TransportException('Panne simulée.'); };
        $tester=new CommandTester(new EmpireInactivityWarningsCommand($this->service()));
        self::assertSame(Command::FAILURE,$tester->execute(['--send'=>true,'--all'=>true,'--limit'=>'1']));self::assertSame(1,$attempts);
        self::assertCount(0,$this->messages);self::assertStringContainsString('échecs : 1',$tester->getDisplay());
    }

    private function vacation(PlayerUniverse $membership, \DateTimeImmutable $start): void
    {
        $period = new \App\Entity\VacationPeriod();
        $period->membership = $membership;
        $period->startedAt = $start;
        $period->scheduledEndAt = $start->modify('+90 days');
        $this->em->persist($period);
        $this->em->flush();
    }

    private function service(): EmpireInactivityWarningService
    {
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $message): void {
            if ($this->onSend !== null) ($this->onSend)();
            $this->messages[] = $message;
        });
        return new EmpireInactivityWarningService(
            self::getContainer()->get(GameManager::class), self::getContainer()->get(GameRepository::class),
            $mailer, self::getContainer()->get('router'), $this->clock, new NullLogger(),
            self::getContainer()->get(\App\Service\Player\EmpireActivityService::class),
            'https://game.example.test', 'game@example.test', true,
        );
    }

    private function membership(string $slug, ?\DateTimeImmutable $lastActivityAt): PlayerUniverse
    {
        $player = new Player();
        $player->username = 'Warning ' . $slug;
        $player->email = $slug . '@example.test';
        $player->password = 'not-used-in-test';
        $player->emailVerifiedAt = $this->clock->now();
        $universe = new Universe();
        $universe->slug = $slug;
        $universe->name = 'Warning ' . $slug;
        $membership = new PlayerUniverse();
        $membership->player = $player;
        $membership->universe = $universe;
        $membership->joinedAt = $this->clock->now()->modify('-60 days');
        $membership->lastActivityAt = $lastActivityAt;
        foreach ([$player, $universe, $membership] as $entity) $this->em->persist($entity);
        $this->em->flush();
        return $membership;
    }
}
