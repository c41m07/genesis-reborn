<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{DailyRewardClaim, Galaxy, Planet, PlanetResource, Player, PlayerBoost, QueueItem, SolarSystem, Universe};
use App\Service\Player\{DailyRewardService, RegistrationService};
use App\Service\Boost\TimedBoostEffects;
use App\Service\Colony\ResourceService;
use App\Service\Queue\QueueService;
use App\Service\Universe\UniverseService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

final class DailyRewardTest extends WebTestCase
{
    private const NOW = '2026-12-01T22:59:00Z';
    private EntityManagerInterface $em;
    private MockClock $clock;
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);
        $tool->createSchema($metadata);
        $this->clock = new MockClock(self::NOW);
        self::getContainer()->set('clock', $this->clock);
    }

    public function testOnlyNewAccountFirstAssignmentGetsT1AfterActivationAndCanStartPower(): void
    {
        [$a, $aPlanet] = $this->map('first-starter');
        [$b, $bPlanet] = $this->map('second-starter');
        [$legacyUniverse, $legacyPlanet] = $this->map('legacy-starter');
        $legacy = $this->player('Legacy', false, false);
        $recent = $this->player('Recent', true, false);
        self::assertFalse($legacy->firstPlanetStarterPending);
        self::assertTrue($recent->firstPlanetStarterPending);
        self::assertNull($recent->emailVerifiedAt);
        // Une activation tardive ne change pas la date d’éligibilité, attachée à l’inscription.
        $this->clock->sleep(3 * 86400);
        $recent->emailVerifiedAt = $legacy->emailVerifiedAt = $this->clock->now();
        $this->em->flush();
        $this->select($legacy, $legacyUniverse);
        $this->select($recent, $a);
        foreach (['metal', 'nano', 'hydrogen', 'antimatter', 'electricity'] as $resource) {
            self::assertSame(10000.0, $this->stock($legacyPlanet, $resource)->currentStock);
            self::assertSame($resource === 'electricity' ? 0.0 : 5000.0, $this->stock($aPlanet, $resource)->currentStock);
        }
        self::assertFalse($recent->firstPlanetStarterPending);
        self::getContainer()->get(QueueService::class)->add($recent->currentPlayerUniverse, $aPlanet, 'buildings', 'power_plant', 1, str_repeat('1', 32));
        $item = $this->em->getRepository(QueueItem::class)->findOneBy(['planet' => $aPlanet, 'itemSlug' => 'power_plant']);
        self::assertSame(120, $item->durationSeconds);
        self::assertSame(0.0, $this->stock($aPlanet, 'electricity')->currentStock);
        $this->select($recent, $b);
        self::assertSame(10000.0, $this->stock($bPlanet, 'metal')->currentStock);
        self::assertSame(10000.0, $this->stock($bPlanet, 'electricity')->currentStock);
    }

    public function testParisMidnightSkippedDaysAndCycleAcrossUniverses(): void
    {
        [$a, $first] = $this->map('cycle-one');
        [$b, $second] = $this->map('cycle-two');
        $player = $this->player('Cycle', true);
        $this->select($player, $a);
        $memberA = $player->currentPlayerUniverse;
        $rewards = self::getContainer()->get(DailyRewardService::class);
        $firstDay = $rewards->status($player)['date'];
        self::assertSame('2026-12-01', $firstDay);
        self::assertSame(['state' => 'claimed', 'slot' => 1], $rewards->claim($player, $memberA, $first, $firstDay));
        self::assertSame(5000.0, $this->stock($first, 'metal')->currentStock);
        self::assertSame(5000.0, $this->stock($first, 'nano')->currentStock);
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count(['playerUniverse' => $memberA]));
        $this->select($player, $b);
        $memberB = $player->currentPlayerUniverse;
        self::assertSame(['state' => 'stale'], $rewards->claim($player, $memberA, $first, $firstDay));
        self::assertSame(['state' => 'already'], $rewards->claim($player, $memberB, $second, $firstDay));
        self::assertSame(10000.0, $this->stock($second, 'metal')->currentStock);
        $this->clock->sleep(60); // Toujours le 1/12 en UTC, mais le 2 à Paris.
        self::assertSame('2026-12-02', $rewards->status($player)['date']);
        self::assertSame(['state' => 'stale'], $rewards->claim($player, $memberB, $second, $firstDay));
        $this->clock->sleep(2 * 86400); // Deux jours sans retrait ne consomment aucun palier.
        foreach ([2 => TimedBoostEffects::CIVIL_FREIGHT, 3 => TimedBoostEffects::LOOT_SHIELD, 4 => TimedBoostEffects::PVP_DEFENSE,
            5 => TimedBoostEffects::PVP_ATTACK, 6 => TimedBoostEffects::QUEUE_SPEED, 7 => TimedBoostEffects::PRODUCTION, 1 => TimedBoostEffects::CIVIL_FUEL] as $slot => $effect) {
            $status = $rewards->status($player);
            self::assertSame($slot, $status['slot']);
            self::assertSame($effect, $status['reward']['effect']);
            $this->setStocks($second, 1000.0, 1000.0);
            self::assertSame(['state' => 'claimed', 'slot' => $slot], $rewards->claim($player, $memberB, $second, $status['date']));
            $this->clock->sleep(86400);
        }
        self::assertSame(8, $this->em->getRepository(DailyRewardClaim::class)->count(['player' => $player]));
    }

    public function testFullColonyDoesNotBlockTheDailyReward(): void
    {
        [$universe, $planet] = $this->map('capacity-reward');
        $player = $this->player('Capacity', true);
        $this->select($player, $universe);
        $current = $player->currentPlayerUniverse;
        $rewards = self::getContainer()->get(DailyRewardService::class);
        $this->setStocks($planet, 10000.0, 10000.0);
        self::assertSame(['state' => 'claimed', 'slot' => 1], $rewards->claim($player, $current, $planet, '2026-12-01'));
        self::assertSame(10000.0, $this->stock($planet, 'metal')->currentStock);
        self::assertSame(10000.0, $this->stock($planet, 'nano')->currentStock);
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count(['playerUniverse' => $current, 'kind' => TimedBoostEffects::KIND]));
    }

    public function testRewardFormShowsDestinationAndUpdatesWithoutNewLogin(): void
    {
        [$u, $planet] = $this->map('form-reward');
        $player = $this->player('FormReward', true);
        $this->select($player, $u);
        $this->client->loginUser($player);
        $this->client->request('GET', '/recompense-quotidienne?colony='.$planet->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Récompense quotidienne');
        self::assertSelectorTextContains('section[aria-labelledby="daily-reward-heading"]', 'Inventaire de votre empire');
        self::assertSelectorTextContains('section[aria-labelledby="daily-reward-heading"]', 'form-reward');
        $claimButtons = 'section[aria-labelledby="daily-reward-heading"] form button[type="submit"]';
        $describedClaim = $claimButtons.'[aria-describedby~="daily-reward-rules"][aria-describedby~="daily-reward-destination"]';
        self::assertSelectorTextContains($describedClaim, 'Récupérer');
        $this->client->submitForm('Récupérer');
        self::assertResponseRedirects('/recompense-quotidienne?colony='.$planet->id.'#daily-reward-heading', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('[role="status"].flash', 'Jour 1 récupéré');
        self::assertSelectorNotExists($claimButtons);
        $this->clock->sleep(60);
        $this->client->clickLink('Actualiser la récompense');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h2#daily-reward-heading', 'Jour 2 sur 7');
        self::assertSelectorTextContains($describedClaim, 'Récupérer');
    }

    public function testPanelReadsTheParisDateWithoutCachingAndOnlyForTheOwnedColony(): void
    {
        [$universe, $owned] = $this->map('panel-reward');
        [, $distant] = $this->map('panel-foreign');
        $player = $this->player('PanelReward', true);
        $this->select($player, $universe);
        $this->client->loginUser($player);

        $panel = '/recompense-quotidienne/panneau?colony=';
        $this->client->request('GET', $panel.$distant->id);
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', $panel.$owned->id);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertSelectorCount(7, '#daily-reward-dialog li');
        self::assertSame('2026-12-01', $crawler->filter('input[name="claim_date"]')->attr('value'));
        self::assertSame(0, $this->em->getRepository(DailyRewardClaim::class)->count());

        $this->clock->sleep(60);
        $crawler = $this->client->request('GET', $panel.$owned->id);
        self::assertSame('2026-12-02', $crawler->filter('input[name="claim_date"]')->attr('value'));
        self::assertSame(0, $this->em->getRepository(DailyRewardClaim::class)->count());
    }

    public function testConcurrentDailyClaimsUseOneReceiptAndOneInventoryItem(): void
    {
        [$u, $planet] = $this->map('race-reward');
        $p = $this->player('RaceReward', true);
        $this->select($p, $u);
        $params = ['action' => 'claim', 'player' => $p->id, 'membership' => $p->currentPlayerUniverse->id, 'planet' => $planet->id, 'date' => '2026-12-01'];
        $states = $this->race([$params, $params], 'SELECT id FROM player_universe WHERE id = ? FOR UPDATE', $p->currentPlayerUniverse->id);
        sort($states);
        self::assertSame(['already', 'claimed'], $states);
        $this->em->clear();
        $fresh = $this->em->find(Planet::class, $planet->id);
        self::assertSame(5000.0, $this->stock($fresh, 'metal')->currentStock);
        self::assertSame(5000.0, $this->stock($fresh, 'nano')->currentStock);
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count(['kind' => TimedBoostEffects::KIND]));
        self::assertSame(1, $this->em->getRepository(DailyRewardClaim::class)->count());
    }

    public function testConcurrentFirstUniverseSelectionsOnlyIssueOneT1Kit(): void
    {
        [$a, $aPlanet] = $this->map('race-first');
        [$b, $bPlanet] = $this->map('race-second');
        $p = $this->player('RaceStarter', true);
        $args = ['action' => 'select', 'player' => $p->id];
        self::assertSame(['selected', 'selected'], $this->race([$args + ['universe' => $a->id], $args + ['universe' => $b->id]], 'SELECT id FROM player WHERE id = ? FOR UPDATE', $p->id));
        $this->em->clear();
        $actual = [];
        foreach ([$aPlanet->id, $bPlanet->id] as $id) {
            $planet = $this->em->find(Planet::class, $id);
            self::assertSame($p->id, $planet->owner->player->id);
            $actual[] = [$this->stock($planet, 'metal')->currentStock, $this->stock($planet, 'electricity')->currentStock];
        }
        sort($actual);
        self::assertSame([[5000.0, 0.0], [10000.0, 10000.0]], $actual);
        self::assertFalse($this->em->find(Player::class, $p->id)->firstPlanetStarterPending);
    }

    public function testInventoryActivationCannotStackAndExpiresAtBothBoundaries(): void
    {
        [$universe, $planet] = $this->map('timed-reward');
        $player = $this->player('TimedReward', true);
        $this->select($player, $universe);
        $this->client->loginUser($player);
        $this->client->request('GET', '/recompense-quotidienne?colony='.$planet->id);
        $this->client->submitForm('Récupérer');

        $membership = $player->currentPlayerUniverse;
        $item = $this->em->getRepository(PlayerBoost::class)->findOneBy(['playerUniverse' => $membership, 'kind' => TimedBoostEffects::KIND]);
        self::assertSame('available', $item->status);
        $this->clock->sleep(TimedBoostEffects::SHELF_SECONDS - 1);
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($player);
        $this->client->request('GET', '/boosts');
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Activer Moteurs économes');
        self::assertResponseRedirects('/boosts', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#timed-boost-title + p + p', 'Moteurs économes');
        $effects = self::getContainer()->get(TimedBoostEffects::class);
        self::assertSame(0.8, $effects->factor($membership, TimedBoostEffects::CIVIL_FUEL));

        $this->client->request('GET', '/recompense-quotidienne?colony='.$planet->id);
        $this->client->submitForm('Récupérer');
        self::assertResponseStatusCodeSame(303);
        $this->client->request('GET', '/boosts');
        self::assertSelectorNotExists('form[action*="/boosts/temporaires/"]');
        $this->clock->sleep(TimedBoostEffects::ACTIVE_SECONDS);
        self::assertSame(1.0, $effects->factor($membership, TimedBoostEffects::CIVIL_FUEL));
        $this->client->request('GET', '/boosts');
        self::assertSelectorExists('form[action*="/boosts/temporaires/"]');
        $this->clock->sleep(TimedBoostEffects::SHELF_SECONDS - TimedBoostEffects::ACTIVE_SECONDS);
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($player);
        $this->client->request('GET', '/boosts');
        self::assertSelectorNotExists('form[action*="/boosts/temporaires/"]');
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count(['status' => 'expired']));
    }

    public function testTemporaryThroughputSchedulesActiveAndWaitingCommandsAtTheCorrectRate(): void
    {
        [$universe, $planet] = $this->map('timed-queue');
        $player = $this->player('TimedQueue', true);
        $this->select($player, $universe);
        $membership = $player->currentPlayerUniverse;
        $queues = self::getContainer()->get(QueueService::class);
        $queues->add($membership, $planet, 'buildings', 'power_plant', 1, str_repeat('a', 32));
        $queues->add($membership, $planet, 'buildings', 'power_plant', 1, str_repeat('b', 32));
        [$first, $next] = $this->em->getRepository(QueueItem::class)->findBy(['planet' => $planet], ['id' => 'ASC']);
        self::assertSame(120, $first->durationSeconds);
        $nextNominal = $next->durationSeconds;
        $firstToken = $first->dispatchToken;

        $this->clock->sleep(20);
        $this->activateFixture($player, TimedBoostEffects::QUEUE_SPEED);
        self::assertSame(100, $first->durationSeconds, '20 s avant puis 100 s de travail en 80 s.');
        self::assertNotSame($firstToken, $first->dispatchToken);
        $this->clock->sleep(80);
        $queues->complete($first->id, $firstToken);
        self::assertNotNull($this->em->find(QueueItem::class, $first->id));
        $queues->complete($first->id, $first->dispatchToken);
        self::assertNotNull($next->startedAt);
        self::assertSame((int) ceil($nextNominal / 1.25), $next->durationSeconds);
    }

    public function testProductionCatchupOnlyBoostsTheFiveHoursAndUpdatesProtectionDisplay(): void
    {
        [$universe, $planet] = $this->map('timed-production');
        $player = $this->player('TimedProduction', true);
        $this->select($player, $universe);
        $this->setStocks($planet, 100, 100);
        $resources = self::getContainer()->get(ResourceService::class);
        $base = $resources->snapshot($planet)['metal'];
        self::assertGreaterThan(0, $base['rate']);
        $this->clock->sleep(3600);
        $this->activateFixture($player, TimedBoostEffects::PRODUCTION);
        self::assertEqualsWithDelta($base['rate'] * 1.15, $resources->snapshot($planet)['metal']['rate'], 1e-8);
        $this->clock->sleep(6 * 3600);
        $credit = $resources->snapshot($planet)['metal'];
        self::assertEqualsWithDelta($base['rate'], $credit['rate'], 1e-8);
        self::assertEqualsWithDelta(100 + $base['rate'] * (2 + 5 * 1.15) * 3600, $credit['stock'], 1e-4);
        $this->activateFixture($player, TimedBoostEffects::LOOT_SHIELD);
        $shield = $resources->snapshot($planet)['metal'];
        self::assertEqualsWithDelta($base['protectedCapacity'] * 2, $shield['protectedCapacity'], 1e-7);
    }

    private function activateFixture(Player $player, string $effect): void
    {
        $item = new PlayerBoost();
        $item->playerUniverse = $player->currentPlayerUniverse;
        $item->sourceType = 'daily_reward';
        $item->sourceKey = 'fixture-'.$effect;
        $item->kind = TimedBoostEffects::KIND;
        $item->payload = ['effect' => $effect];
        $item->earnedAt = $this->clock->now();
        $this->em->persist($item);
        $this->em->flush();
        self::getContainer()->get(\App\Service\Boost\TimedBoostUseService::class)
            ->activate($item->playerUniverse, $item->id, bin2hex(random_bytes(16)));
    }

    private function player(string $name, bool $eligible, bool $verified = true): Player
    {
        $data = new RegistrationData(); $data->username = $name; $data->email = strtolower($name).'@example.test'; $data->plainPassword = 'Only-a-local-fixture-123!';
        $player = self::getContainer()->get(RegistrationService::class)->register($data, starterEligible: $eligible);
        if ($verified) {
            $player->emailVerifiedAt = $this->clock->now();
            $this->em->flush();
        }
        return $player;
    }

    private function map(string $slug): array
    {
        $u = new Universe(); $u->slug = $slug; $u->name = $slug;
        $g = new Galaxy(); $g->universe = $u; $g->name = 'Galaxie 1';
        $s = new SolarSystem(); $s->galaxy = $g; $s->name = 'Système de départ';
        $s->coordX = $s->coordY = 0;
        $p = new Planet(); $p->solarSystem = $s; $p->position = 5; $p->name = 'Test';
        foreach ([$u, $g, $s, $p] as $entity) {
            $this->em->persist($entity);
        }
        $this->em->flush();
        return [$u, $p];
    }

    private function select(Player $player, Universe $universe): void
    {
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
    }

    private function stock(Planet $planet, string $slug): PlanetResource
    {
        return $this->em->getRepository(PlanetResource::class)->findOneBy(['planet' => $planet, 'resourceType' => $slug]);
    }

    private function setStocks(Planet $planet, float $metal, float $nano): void
    {
        $stocks = ['metal' => $metal, 'nano' => $nano];
        // La projection commune de la planète utilise la date d’électricité comme ancre.
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $planet]) as $row) {
            $row->currentStock = $stocks[$row->resourceType] ?? $row->currentStock;
            $row->lastUpdatedAt = $this->clock->now();
        }
        $this->em->flush();
    }

    /** Les deux transactions sont prouvées concurrentes par leurs attentes SQL avant libération. */
    private function race(array $actions, string $lockSql, int $lockId): array
    {
        $directory = sys_get_temp_dir().'/genesis-daily-race-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $barrier = $directory.'/go';
        $connection = $this->em->getConnection();
        $children = [];
        $connection->beginTransaction();
        try {
            $connection->fetchOne($lockSql, [$lockId]);
            foreach ($actions as $index => $action) {
                $input = $action + ['now' => self::NOW, 'ready' => $directory.'/'.$index, 'barrier' => $barrier];
                $process = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_daily_reward.php', json_encode($input, JSON_THROW_ON_ERROR)]);
                $process->setTimeout(25);
                $process->start();
                $children[] = $process;
            }
            $deadline = microtime(true) + 12;
            do {
                $ready = glob($directory.'/[0-9]') ?: [];
                if (count($ready) === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertCount(2, $ready, implode('; ', array_map(static fn (Process $p): string => $p->getErrorOutput(), $children)));
            $ids = array_map(static fn(string $file): int => (int) file_get_contents($file), $ready);
            touch($barrier);
            do {
                $blocked = 0;
                foreach ($connection->fetchAllAssociative('SHOW FULL PROCESSLIST') as $row) {
                    if (in_array((int) $row['Id'], $ids, true) && str_contains(strtoupper($row['Info'] ?? ''), 'FOR UPDATE')) {
                        ++$blocked;
                    }
                }
                if ($blocked === 2) {
                    break;
                }
                usleep(10000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $blocked, 'Deux connexions doivent attendre simultanément.');
            $connection->commit();
            return array_map(static function (Process $process): string {
                self::assertSame(0, $process->wait(), $process->getOutput().$process->getErrorOutput());
                return trim($process->getOutput());
            }, $children);
        } finally {
            if ($connection->isTransactionActive()) {
                $connection->rollBack();
            }
            foreach ($children as $child) {
                if ($child->isRunning()) {
                    $child->stop();
                }
            }
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
