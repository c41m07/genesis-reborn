<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Fleet, FleetShip, Galaxy, PirateAttackCampaign, Planet, Player, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Message\PirateAttackDue;
use App\MessageHandler\PirateAttackDueHandler;
use App\Service\Combat\CombatSimulator;
use App\Service\Notification\OutboxRelay;
use App\Service\PirateCampaign\{PirateAttackService, PirateBattleScenario, PirateDistressSite, PirateEncounterSnapshot, PirateReconnaissanceService};
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Messenger\Exception\RecoverableMessageHandlingException;
use Symfony\Component\Process\Process;

final class PirateAttackPersistenceTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private PirateAttackService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-21T09:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $this->service = self::getContainer()->get(PirateAttackService::class);
    }

    public function testNoPersistentAttackBeforeReport(): void
    {
        [$member, $home, $fighter] = $this->fixture();
        $scenario = $this->scenario();
        $db = $this->em->getConnection();
        try {
            $this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], $scenario, 3);
            self::fail('La preuve de reconnaissance doit être reçue.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('rapport', $error->getMessage());
        }
        self::assertSame(3, (int) $db->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$fighter->id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testReservationRealLossAndSurvivorsReturnAtSeparateDeadlines(): void
    {
        [$member, $home, $stock, $probe, $ally] = $this->fixture();
        $this->finishRecon();
        $scenario = $this->scenario(400);
        $db = $this->em->getConnection();
        $fleet = ['fighter' => ['basic' => 2]];
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, $fleet, $scenario, 3));
        self::assertFalse($this->service->launch($member, $home, 'signal', 0, $fleet, $scenario, 3));
        self::assertSame(1, $this->stock($stock->id));
        self::assertSame(1, $this->stock($probe->id));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet WHERE status <> 'idle'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM combat_event'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM notification'));
        self::assertNull($this->service->statusFor($ally, 'signal'));

        // Utiliser les profils réellement saisis au départ, avec une graine figée pour vérifier les pertes exactes.
        $campaign = $this->campaign();
        $campaign->timeline['seed'] = $this->seedWithOneLoss($campaign, $scenario);
        $this->em->flush();
        $due = $campaign->nextAt;
        $travelSeconds = $campaign->travelSeconds;
        $messageId = $campaign->id;
        $token = $campaign->dispatchToken;
        self::assertGreaterThan($campaign->departedAt->getTimestamp(), $campaign->arrivalAt->getTimestamp());
        self::assertGreaterThan($campaign->arrivalAt->getTimestamp(), $due->getTimestamp());
        self::assertSame(1, self::getContainer()->get(OutboxRelay::class)->run());
        $member = $this->em->find(PlayerUniverse::class, $member->id);
        $home = $this->em->find(Planet::class, $home->id);
        $message = new PirateAttackDue($messageId, $token);
        $handler = self::getContainer()->get(PirateAttackDueHandler::class);
        $this->advanceTo($due->modify('-1 second'));
        try {
            $handler($message);
            self::fail('Le bon événement reçu trop tôt doit être replanifié.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(1000, $error->getRetryDelay());
        }
        self::assertNull($this->service->statusFor($member, 'signal')['report']);
        self::assertSame(1, $this->stock($stock->id));

        $this->advanceTo($due);
        $handler($message);
        $handler($message);
        $campaign = $this->campaign();
        self::assertSame('returning', $this->service->statusFor($member, 'signal')['state']);
        self::assertSame('defender_wins', $campaign->report['outcome']);
        self::assertSame(['escort:fighter' => 1], $campaign->report['losses']);
        self::assertSame(['fighter' => ['basic' => 1]], $campaign->reservedShips);
        self::assertSame(1, $this->stock($stock->id));
        $returnDue = $campaign->nextAt;
        self::assertSame($travelSeconds, $returnDue->getTimestamp() - $due->getTimestamp());

        $return = new PirateAttackDue($campaign->id, $campaign->dispatchToken);
        $this->advanceTo($returnDue->modify('-1 second'));
        $handler($message); // Ancien message du combat : aucune nouvelle tentative ni aucun nouveau report.
        try {
            $handler($return);
            self::fail('Le retour précoce doit être replanifié sans remettre la réserve.');
        } catch (RecoverableMessageHandlingException $error) {
            self::assertSame(1000, $error->getRetryDelay());
        }
        self::assertSame(1, $this->stock($stock->id));
        $this->advanceTo($returnDue);
        $handler($return);
        $handler($return);
        self::assertSame(2, $this->stock($stock->id));
        self::assertSame('retryable', $this->service->statusFor($member, 'signal')['state']);
        self::assertFalse($this->service->launch($member, $home, 'signal', 0, $fleet));
        self::assertTrue($this->service->launch($member, $home, 'signal', 1, $fleet));
        self::assertSame(2, $this->service->statusFor($member, 'signal')['attempt']);
        self::assertSame($scenario->snapshot(), $this->campaign()->scenario);
        self::assertSame(1, $this->stock($probe->id));
    }

    public function testInvalidDestinationKeepsSurvivorsReservedAndVictoryTerminal(): void
    {
        [$member, $home, $stock, $probe, $ally] = $this->fixture();
        $this->finishRecon();
        $win = $this->scenario(enemyHp: 1);
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], $win, 5));
        $campaign = $this->campaign();
        $campaign->timeline['seed'] = $this->seedWithVictory($campaign, $win);
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        self::assertSame('attacker_wins', $this->service->statusFor($member, 'signal')['report']['outcome']);
        $home->owner = $ally;
        $this->em->flush();
        $return = $campaign->dispatchToken;
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $return);
        $this->service->processDue($campaign->id, $return);
        self::assertSame('return_blocked', $this->service->statusFor($member, 'signal')['state']);
        self::assertNull($this->service->statusFor($ally, 'signal'));
        self::assertSame(['fighter' => ['basic' => 2]], $campaign->reservedShips);
        self::assertSame(1, $this->stock($stock->id));
        self::assertSame(1, $this->stock($probe->id));
    }

    public function testFailedAttackOutboxRollsBackReservedForces(): void
    {
        [$member, $home, $fighter] = $this->fixture();
        $this->finishRecon();
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_attack_test_reject_outbox BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture attack outbox failure'");
        try {
            $this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], $this->scenario(), 3);
            self::fail('La réservation sans phase planifiée doit être annulée.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture attack outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_attack_test_reject_outbox');
        }
        self::assertSame(3, $this->stock($fighter->id));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM pirate_attack_campaign'));
    }

    public function testFailedReturnOutboxLeavesCombatUnresolvedAndReservationIntact(): void
    {
        [$member, $home, $stock] = $this->fixture();
        $this->finishRecon();
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], $this->scenario(), 3));
        $campaign = $this->campaign();
        $token = $campaign->dispatchToken;
        $target = $campaign->id;
        $this->advanceTo($campaign->nextAt);
        $db = $this->em->getConnection();
        $db->executeStatement("CREATE TRIGGER pirate_attack_test_reject_return BEFORE INSERT ON outbox_message FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'fixture return outbox failure'");
        try {
            $this->service->processDue($target, $token);
            self::fail('Le combat ne doit pas être enregistré sans la phase de retour de ses survivants.');
        } catch (DriverException $error) {
            self::assertStringContainsString('fixture return outbox failure', $error->getMessage());
        } finally {
            $db->executeStatement('DROP TRIGGER IF EXISTS pirate_attack_test_reject_return');
        }
        $row = $db->fetchAssociative('SELECT report, dispatch_token, reserved_ships FROM pirate_attack_campaign WHERE id = ?', [$target]);
        self::assertNull($row['report']);
        self::assertSame($token, $row['dispatch_token']);
        self::assertSame(['fighter' => ['basic' => 2]], json_decode($row['reserved_ships'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame(1, $this->stock($stock->id));

        self::ensureKernelShutdown();
        self::bootKernel();
        self::getContainer()->set('clock', $this->clock);
        $service = self::getContainer()->get(PirateAttackService::class);
        $service->processDue($target, $token);
        $service->processDue($target, $token);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('defender_wins', $this->campaign()->report['outcome']);
        self::assertSame(2, (int) $this->em->getConnection()->fetchOne('SELECT COUNT(*) FROM outbox_message'));
    }

    public function testConcurrentReturnDoesNotInstallSurvivorsTwice(): void
    {
        [$member, $home, $stock] = $this->fixture();
        $this->finishRecon();
        $win = $this->scenario(enemyHp: 1);
        self::assertTrue($this->service->launch($member, $home, 'signal', 0, ['fighter' => ['basic' => 2]], $win, 5));
        $campaign = $this->campaign();
        $campaign->timeline['seed'] = $this->seedWithVictory($campaign, $win);
        $this->em->flush();
        $this->advanceTo($campaign->nextAt);
        $this->service->processDue($campaign->id, $campaign->dispatchToken);
        $this->advanceTo($campaign->nextAt);

        $db = $this->em->getConnection();
        $directory = sys_get_temp_dir().'/ges-pirate-return-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $barrier = $directory.'/go';
        $workers = [];
        $db->beginTransaction();
        try {
            // Tenir le verrou de l'empire jusqu'à l'arrivée simultanée des deux copies de message.
            $db->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$member->id]);
            $common = ['now' => $this->clock->now()->format(DATE_ATOM), 'campaign' => $campaign->id,
                'token' => $campaign->dispatchToken, 'barrier' => $barrier];
            foreach ([0, 1] as $index) {
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_pirate_attack.php',
                    json_encode($common + ['ready' => $directory.'/'.$index], JSON_THROW_ON_ERROR)]);
                $process->setTimeout(25);
                $process->start();
                $workers[] = $process;
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = glob($directory.'/[0-9]') ?: [];
                if (count($ready) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $ready, implode('; ', array_map(static fn (Process $p): string => $p->getErrorOutput(), $workers)));
            $ids = array_map(static fn (string $file): int => (int) file_get_contents($file), $ready);
            touch($barrier);
            do {
                $blocked = 0;
                foreach ($db->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $blocked, 'Les deux workers doivent attendre en même temps.');
            $db->commit();
            foreach ($workers as $worker) {
                self::assertSame(0, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
                self::assertSame('processed', trim($worker->getOutput()));
            }
            $this->em->refresh($campaign);
            self::assertSame('won', $campaign->timeline['state']);
            self::assertSame(3, $this->stock($stock->id));
            self::assertSame([], $campaign->reservedShips);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($workers as $worker) {
                if ($worker->isRunning()) {
                    $worker->stop();
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }

    private function campaign(): PirateAttackCampaign
    {
        return $this->em->getRepository(PirateAttackCampaign::class)->findOneBy([]);
    }

    private function stock(int $id): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$id]);
    }

    private function scenario(int $enemyAttack = 0, int $enemyHp = 100000000): PirateBattleScenario
    {
        return PirateBattleScenario::freeze($this->encounter(), [[
            'slug' => 'fixture_raider', 'tier' => 'basic', 'quantity' => 1,
            'hp' => $enemyHp, 'shield' => 0, 'precision' => 300, 'tracking' => 300,
            'evasion' => 0, 'signature' => 20, 'base_attack' => $enemyAttack,
            'shots_min' => 1, 'shots_max' => 1,
        ]], [], 1, 0);
    }

    private function encounter(): PirateEncounterSnapshot
    {
        return PirateEncounterSnapshot::freeze(['fixture_raider' => ['basic' => 1]], ['metal' => 42]);
    }

    private function seedWithOneLoss(PirateAttackCampaign $campaign, PirateBattleScenario $scenario): int
    {
        return $this->findSeed($campaign, $scenario, static fn (array $result): bool => $result['losses'] === ['escort:fighter' => 1]);
    }

    private function seedWithVictory(PirateAttackCampaign $campaign, PirateBattleScenario $scenario): int
    {
        return $this->findSeed($campaign, $scenario, static fn (array $result): bool => $result['outcome'] === 'attacker_wins');
    }

    private function findSeed(PirateAttackCampaign $campaign, PirateBattleScenario $scenario, \Closure $expected): int
    {
        for ($seed = 0; $seed < 100; ++$seed) {
            if ($expected($scenario->resolve(new CombatSimulator(), $campaign->timeline['units'], $seed))) {
                return $seed;
            }
        }

        self::fail('Le profil adverse de test ne peut pas produire le résultat voulu.');
    }

    private function advanceTo(\DateTimeImmutable $due): void
    {
        $this->clock->sleep($due->getTimestamp() - $this->clock->now()->getTimestamp());
    }

    private function finishRecon(): void
    {
        $event = $this->em->getConnection()->fetchAssociative("SELECT target_id, due_at, token FROM outbox_message WHERE kind = 'pirate_reconnaissance'");
        $this->advanceTo(new \DateTimeImmutable($event['due_at'], new \DateTimeZone('UTC')));
        self::getContainer()->get(PirateReconnaissanceService::class)->deliver((int) $event['target_id'], $event['token']);
        $this->em->getConnection()->executeStatement("DELETE FROM outbox_message WHERE kind = 'pirate_reconnaissance'");
    }

    private function fixture(): array
    {
        $u = new Universe();
        $u->slug = $u->name = 'pirate-attack-test';
        $g = new Galaxy();
        $g->universe = $u;
        $g->name = 'Galaxie synthétique';
        $s = new SolarSystem();
        $s->galaxy = $g;
        $s->name = 'Système synthétique';
        $s->coordX = $s->coordY = 0;
        $member = $this->member($u, 'attack-owner');
        $other = $this->member($u, 'attack-other');
        $p = new Planet();
        $p->solarSystem = $s;
        $p->position = 4;
        $p->name = 'Colonie synthétique';
        $p->owner = $member;
        $d = new PlayerDiscovery();
        $d->playerUniverse = $member;
        $d->solarSystem = $s;
        $idle = new Fleet();
        $idle->playerUniverse = $member;
        $idle->homePlanet = $p;
        foreach ([$u, $g, $s, $p, $d, $idle] as $entity) {
            $this->em->persist($entity);
        }
        $fighter = $this->addShips($idle, 'fighter', 3);
        $probe = $this->addShips($idle, 'probe', 2);
        $this->em->flush();
        self::getContainer()->get(PirateReconnaissanceService::class)
            ->launch($member, $p, $s, PirateDistressSite::inExistingSystem($member, $s), 'signal', $this->encounter());

        return [$member, $p, $fighter, $probe, $other];
    }

    private function member(Universe $universe, string $name): PlayerUniverse
    {
        $player = new Player();
        $player->username = $name;
        $player->email = $name.'@example.test';
        $player->password = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);
        $player->emailVerifiedAt = $this->clock->now();
        $member = new PlayerUniverse();
        $member->player = $player;
        $member->universe = $universe;
        $member->joinedAt = $this->clock->now();
        $this->em->persist($player);
        $this->em->persist($member);

        return $member;
    }

    private function addShips(Fleet $fleet, string $type, int $quantity): FleetShip
    {
        $stock = new FleetShip();
        $stock->fleet = $fleet;
        $stock->slug = $type;
        $stock->quantity = $quantity;
        $this->em->persist($stock);

        return $stock;
    }
}
