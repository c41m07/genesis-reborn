<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{AdventClaim, AdventEdition, AdventWindow, Planet, Player, Universe};
use App\Service\Advent\{AdventCohortCapture, AdventEditions, AdventParticipation, AdventQueueCredits, AdventRewards};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Service\Queue\QueueService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Process\Process;

final class AdventTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient(['environment' => 'test'], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->clock = new MockClock('2026-11-30T15:00:00Z');
        static::getContainer()->set('clock', $this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
    }

    public function testAdministrationPreviewAndCalendarClaimAreSeparatedAndScoped(): void
    {
        $member = $this->player('AdventMember');
        $other = $this->player('AdventNeighbor');
        $admin = $this->player('AdventAdmin', admin: true);
        $planet = $this->planet($member);
        $foreign = $this->planet($other);
        $this->client->loginUser($member);
        $this->client->request('GET', '/admin/events/avent');
        self::assertResponseRedirects('/acces-refuse');
        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/evenements/avent');
        self::assertResponseStatusCodeSame(404);

        $edition = $this->publish($admin);
        $this->client->loginUser($member);
        $this->client->request('GET', '/evenements');
        self::assertSelectorExists('header.topbar a[href="/evenements/avent"]');
        self::assertSelectorNotExists('main a[href="/evenements/avent"]');
        $crawler = $this->client->request('GET', '/evenements/avent');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertCount(24, $crawler->filter('.advent-door'));
        self::assertSelectorNotExists('.advent-board--history');
        self::assertSelectorNotExists('#advent-door-1 form');

        // À minuit à Paris, seule la première porte est récupérable.
        $this->clock->sleep(8 * 3600);
        $crawler = $this->client->request('GET', '/evenements/avent');
        self::assertSelectorExists('#advent-door-1 form');
        self::assertSelectorNotExists('#advent-door-2 form');
        $first = $crawler->filter('#advent-door-1 form');
        $token = $first->filter('input[name="_token"]')->attr('value');
        $editionUrl = '/evenements/avent/'.$edition;
        $url = $editionUrl.'/portes/1';
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $planet->id, '_token' => 'expired']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $foreign->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->em->getRepository(AdventClaim::class)->count());

        $db = $this->em->getConnection();
        $this->fixtureStocks($planet->id, '2026-11-30 23:00:00', ['metal' => 10000]);
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $planet->id, '_token' => $token]);
        self::assertResponseRedirects($editionUrl.'#advent-door-1', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#advent-door-1', 'Dans l’inventaire');
        $initial = $db->fetchAssociative('SELECT choice, resources, delivered_at, original_destination_id, delivered_destination_id, box_expires_at FROM advent_claim');
        self::assertSame(0, (int) $initial['choice']);
        self::assertSame($planet->id, (int) $initial['original_destination_id']);
        self::assertNull($initial['delivered_at']);
        self::assertSame('2026-12-31 23:00:00', $initial['box_expires_at']);
        // Simuler un reçu déjà existant au moment de la migration.
        $db->executeStatement('UPDATE advent_claim SET box_expires_at = NULL, box_universe_id = NULL, box_unread = 0');
        $this->em->clear();

        // Le calendrier et un formulaire mémorisé sont inaccessibles dans un autre univers.
        $member = $this->select($member, 'turbo-1');
        $this->client->loginUser($member);
        $this->client->request('GET', $editionUrl);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $foreign->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        $member = $this->select($member, 'omega-1');
        $this->client->loginUser($member);
        $this->clock->sleep(31 * 86400 - 1);
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->em->find(Player::class, $member->id));
        $crawler = $this->client->request('GET', $editionUrl);
        self::assertSelectorExists('#advent-door-24 form');
        self::assertSelectorExists('#advent-door-2 form');
        $this->clock->sleep(1);
        $this->client->request('GET', '/evenements');
        self::assertSelectorNotExists('header a[href="/evenements/avent"]');
        foreach (['/evenements/avent', $editionUrl] as $address) {
            $this->client->request('GET', $address);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $planet->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        $this->fixtureStocks($planet->id, '2026-12-31 23:00:00', ['metal' => 500]);
        $crawler = $this->client->request('GET', '/inventaire/recompenses');
        $form = $crawler->selectButton('Retenter le versement')->form();
        $inventoryUrl = $form->getUri();
        $this->client->submit($form);
        self::assertResponseRedirects('/inventaire/recompenses', 303);
        $this->client->followRedirect();
        self::assertSelectorNotExists('form[action*="/recompenses/avent/"]');
        self::assertSame('2026-12-31 23:00:00', $db->fetchOne('SELECT delivered_at FROM advent_claim'));
        self::assertEqualsWithDelta(507, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$planet->id]), .01);
        $this->client->loginUser($other);
        $this->client->request('POST', $inventoryUrl);
        self::assertResponseStatusCodeSame(404);
    }

    public function testResourceBoxChoosesColonyAtOpeningPreservesOverflowAndRejectsAtParisMidnight(): void
    {
        $admin = $this->player('AdventBoxAdmin', admin: true);
        $player = $this->player('AdventBoxMember');
        $neighbor = $this->player('AdventBoxNeighbor');
        $planet = $this->planet($player);
        $foreignId = $this->planet($neighbor)->id;
        $editionId = $this->publish($admin);
        $edition = $this->em->find(AdventEdition::class, $editionId);
        $doors = $edition->doors;
        $doors[1]['options'][0] = ['metal' => 20000];
        $edition->doors = $doors;
        $this->em->flush();
        $db = $this->em->getConnection();
        $planetId = $planet->id;
        $this->clock->modify('2026-12-31T22:59:58Z');
        $this->client->loginUser($this->em->find(Player::class, $player->id));
        $url = '/evenements/avent/'.$editionId;
        foreach ([1, 2] as $door) {
            $crawler = $this->client->request('GET', $url);
            $form = $crawler->filter('#advent-door-'.$door.' form')->form(['choice' => '0']);
            self::assertArrayNotHasKey('colony', $form->getPhpValues());
            $this->client->submit($form);
            self::assertResponseRedirects($url.'#advent-door-'.$door, 303);
        }
        $membership = $this->em->find(Player::class, $player->id)->currentPlayerUniverse;
        $notices = static::getContainer()->get(AdventRewards::class);
        $firstBoxId = (int) $db->fetchOne('SELECT id FROM advent_claim WHERE door = 1');
        $lastBoxId = (int) $db->fetchOne('SELECT id FROM advent_claim WHERE door = 2');
        self::assertSame(['count' => 2, 'through' => $lastBoxId], $notices->giftNoticeSummary($membership));
        $notices->acknowledgeGiftNotices($membership, $firstBoxId);
        self::assertSame(['count' => 1, 'through' => $lastBoxId], $notices->giftNoticeSummary($membership));

        $bounds = static::getContainer()->get(ResourceService::class);
        $capacity = $bounds->capacity($planet, 'metal', []);
        $preBox = $capacity - 500.0;
        $this->fixtureStocks($planetId, '2026-12-31 22:59:59', ['metal' => (int) $preBox]);
        $crawler = $this->client->request('GET', '/inventaire/boites');
        self::assertSelectorTextContains('#advent-box-1', 'Boîte de ressources');
        self::assertSelectorTextContains('#advent-box-1', '01/01/2027 à 00:00');
        $first = $crawler->filter('#advent-box-1 form')->form(['colony' => $planetId]);
        $second = $crawler->filter('#advent-box-2 form')->form(['colony' => $planetId]);
        $this->client->request('POST', $first->getUri(), array_replace($first->getPhpValues(), ['colony' => $foreignId]));
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NOT NULL'));
        $this->clock->sleep(1);
        $this->client->submit($first);
        self::assertResponseRedirects('/inventaire/boites', 303);
        self::assertSame('2026-12-31 22:59:59', $db->fetchOne('SELECT delivered_at FROM advent_claim WHERE door = 1'));
        $stock = fn (): float => (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$planetId]);
        self::assertEqualsWithDelta($preBox + 20000, $stock(), .01);
        $this->client->submit($first);
        self::assertEqualsWithDelta($preBox + 20000, $stock(), .01);

        $this->clock->sleep(1);
        $this->client->submit($second);
        self::assertResponseRedirects('/inventaire/boites', 303);
        $this->client->followRedirect();
        self::assertSelectorNotExists('#advent-box-2');
        self::assertSelectorTextContains('body', 'L’échéance de cette boîte de l’Avent est passée.');
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE door = 2'));
        $membership = $this->em->find(Player::class, $player->id)->currentPlayerUniverse;
        self::assertSame(['count' => 0, 'through' => null], $notices->giftNoticeSummary($membership));
        $this->em->clear();
        $planet = $this->em->find(Planet::class, $planetId);
        $this->clock->sleep(600);
        $bounds->consume($planet, ['metal' => 10000]);
        $ordinaryDeposit = $bounds->deposit($planet, ['metal' => 1]);
        $this->em->flush();
        self::assertSame(1.0, $ordinaryDeposit['overflow']['metal']);
        self::assertEqualsWithDelta($preBox + 10000, $stock(), .01);
    }

    public function testInventoryBadgeCountsAdventAndDailyWithoutConsumingEither(): void
    {
        $admin = $this->player('MixedGiftAdmin', admin: true);
        $player = $this->player('MixedGiftMember');
        $editionId = $this->publish($admin);
        $this->clock->modify('2026-12-01T10:00:00Z');
        $this->client->loginUser($this->em->find(Player::class, $player->id));

        // Récupérer les deux cadeaux depuis leurs pages, avec les vrais formulaires et jetons.
        $url = '/evenements/avent/'.$editionId;
        $calendar = $this->client->request('GET', $url);
        $this->client->submit($calendar->filter('#advent-door-1 form')->form(['choice' => '0']));
        self::assertResponseRedirects($url.'#advent-door-1', 303);
        $this->client->request('GET', '/recompense-quotidienne');
        $this->client->submitForm('Récupérer');
        self::assertResponseStatusCodeSame(303);

        $count = function (): int {
            $this->client->request('GET', '/messages/non-lus');
            self::assertResponseIsSuccessful();
            $body = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);

            return $body['inventoryGiftCount'];
        };
        self::assertSame(2, $count());
        $this->client->request('GET', '/inventaire/boites');
        self::assertSelectorTextContains('.inventory-gift-notice', '1 nouveau cadeau');
        self::assertSelectorExists('[data-page="boxes"][hidden]');
        self::assertSelectorTextContains('#advent-box-1 .inventory-new-label', 'Nouveau');
        $this->client->submitForm('Marquer les cadeaux comme vus');
        self::assertResponseRedirects('/inventaire/boites', 303);
        self::assertSame(0, $count());

        $this->client->request('GET', '/inventaire/boites');
        self::assertSelectorExists('#advent-box-1');
        $this->client->request('GET', '/boosts');
        self::assertSelectorTextContains('main', 'Moteurs économes');
        $db = $this->em->getConnection();
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE advent_edition_id = ? AND door = 1', [$editionId]));
        self::assertSame('available', $db->fetchOne("SELECT status FROM player_boost WHERE source_type = 'daily_reward'"));
    }

    public function testActivationMidMonthAndIndependentWindowsForTheSameSeason(): void
    {
        $admin = $this->player('AdventWindowAdmin', admin: true);
        $player = $this->player('AdventWindowMember');
        $editions = static::getContainer()->get(AdventEditions::class);
        $omega = $admin->currentPlayerUniverse->universe;
        $turbo = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        $this->clock->modify('2026-12-10T12:00:00Z');
        $window = $editions->publish($admin, $editions->prepare('Avent Omega', 2026, $omega->id, '2026-12-10T13:01', '2026-12-31T00:00'));
        $otherWindow = $editions->publish($admin, $editions->prepare('Avent Turbo', 2026, $turbo->id, '2026-12-20T18:00', '2027-01-01T00:00'));
        self::assertSame($window->edition->id, $otherWindow->edition->id);
        $this->client->loginUser($player);
        $uri = '/evenements/avent/'.$window->edition->id;
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
        $this->clock->sleep(60);
        $handler = static::getContainer()->get(\App\MessageHandler\AdventCaptureDueHandler::class);
        $handler(new \App\Message\AdventCaptureDue($window->edition->id, true));
        $crawler = $this->client->request('GET', $uri);
        self::assertSelectorTextContains('h1', 'Avent Omega');
        $this->client->submit($crawler->filter('form[action$="/univers"]')->form());
        $this->client->followRedirect();
        self::assertSelectorExists('#advent-door-1 form');
        self::assertSelectorExists('#advent-door-10 form');
        self::assertSelectorNotExists('#advent-door-11 form');
        $this->clock->modify('2026-12-30T23:00:00Z');
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->em->find(Player::class, $player->id));
        $this->client->request('GET', $uri);
        self::assertResponseStatusCodeSame(404);
        $player = $this->select($player, 'turbo-1');
        $this->client->loginUser($player);
        $this->client->request('GET', $uri);
        self::assertSelectorTextContains('h1', 'Avent Turbo');
        $this->clock->modify('2027-12-10T12:00:00Z');
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->em->find(Player::class, $player->id));
        $this->client->request('GET', '/evenements/avent');
        self::assertResponseStatusCodeSame(404);
    }

    public function testInvalidAdminDateReturnsValidationWithoutPublication(): void
    {
        $admin = $this->player('AdventInvalidAdmin', admin: true);
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/events/avent');
        $token = $crawler->filter('.admin-event-form input[name="_token"]')->attr('value');
        foreach (['2026-11-31', "2026-12-01\0"] as $invalidDate) {
            $this->client->request('POST', '/admin/events/avent', [
                '_token' => $token, 'name' => 'Avent invalide', 'season' => 2026, 'universe' => $admin->currentPlayerUniverse->universe->id,
                'from' => $invalidDate, 'until' => '2026-12-31T00:00',
                'options' => array_fill(1, 24, [['metal' => '7']]),
            ]);
            self::assertResponseStatusCodeSame(422);
            self::assertSelectorTextContains('main', 'Saisissez une date et une heure valides');
        }
        self::assertSame(0, $this->em->getRepository(AdventEdition::class)->count());
    }

    #[DataProvider('races')]
    public function testCompetingOrdersFromSeparateProcessesCreditOneLot(bool $retry): void
    {
        $member = $this->player('AdventRacer');
        $admin = $this->player('AdventRaceAdmin', admin: true);
        $membership = $member->currentPlayerUniverse->id;
        $origin = $this->planet($member);
        $edition = $this->publish($admin);
        $db = $this->em->getConnection();
        $planets = [$origin->id, $origin->id];
        $choices = [0, 1];
        $when = '2026-11-30 23:00:00';
        if ($retry) {
            $currentMember = $this->em->find(Player::class, $member->id)->currentPlayerUniverse;
            $origin = $this->em->find(Planet::class, $origin->id);
            $spare = $this->em->getRepository(Planet::class)->findOneBy([
                'owner' => null, 'npcPlanet' => false, 'solarSystem' => $origin->solarSystem,
            ]);
            self::assertNotNull($spare);
            $spare->acquireBy($currentMember, $this->clock->now());
            static::getContainer()->get(ResourceService::class)->initialize($spare, colony: true);
            $claim = new AdventClaim();
            $claim->adventEdition = $this->em->find(AdventEdition::class, $edition);
            $claim->player = $currentMember->player;
            $claim->originalDestinationId = $origin->id;
            $claim->door = 1;
            $claim->choice = 0;
            $claim->resources = ['metal' => 7];
            $claim->openedAt = new \DateTimeImmutable('2026-11-30T23:00:00Z');
            $this->em->persist($claim);
            $this->em->flush();
            $planets[1] = $spare->id;
            $choices = [0, 0];
            $when = '2027-01-02 15:00:00';
        }
        foreach (array_unique($planets) as $planet) {
            $this->fixtureStocks($planet, $when, ['metal' => 500, 'nano' => 500]);
        }

        $directory = sys_get_temp_dir().'/genesis-advent-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $workers = [];
        $db->beginTransaction();
        try {
            // Une vraie transaction retient la participation. Les deux choix
            // préparent leurs propres connexions puis attendent la même ligne.
            $db->fetchOne('SELECT id FROM player_universe WHERE id = ? FOR UPDATE', [$membership]);
            foreach ($choices as $index => $choice) {
                $input = ['player' => $member->id, 'membership' => $membership, 'planet' => $planets[$index],
                    'edition' => $edition, 'choice' => $choice, 'reservation' => $retry, 'when' => $when, 'ready' => $directory.'/'.$index, 'go' => $directory.'/go'];
                $worker = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_advent_open.php', json_encode($input, JSON_THROW_ON_ERROR)]);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 8;
            do {
                clearstatcache();
                if (is_file($directory.'/0') && is_file($directory.'/1')) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline && $workers[0]->isRunning() && $workers[1]->isRunning());
            foreach ($workers as $choice => $worker) {
                self::assertFileExists($directory.'/'.$choice, $worker->getErrorOutput());
            }
            $connections = [(int) file_get_contents($directory.'/0'), (int) file_get_contents($directory.'/1')];
            self::assertNotSame($connections[0], $connections[1]);
            touch($directory.'/go');

            $waiting = 0;
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) $db->fetchOne(
                    "SELECT COUNT(*) FROM information_schema.PROCESSLIST WHERE ID IN (?, ?) AND INFO LIKE '%player_universe%' AND INFO LIKE '%FOR UPDATE%'",
                    $connections,
                );
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline && $workers[0]->isRunning() && $workers[1]->isRunning());
            self::assertSame(2, $waiting, 'Les deux traitements doivent réellement attendre le même verrou.');
            $db->commit();

            $results = [];
            foreach ($workers as $worker) {
                self::assertSame(0, $worker->wait(), $worker->getErrorOutput());
                $results[] = trim($worker->getOutput());
            }
            if (!$retry) {
                self::assertEqualsCanonicalizing(['box_ready', 'reserved'], $results);
                self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NULL AND box_expires_at IS NOT NULL'));
                self::assertEqualsWithDelta(500, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$origin->id]), .01);
                return;
            }

            self::assertEqualsCanonicalizing(['delivered', 'already'], $results);
            self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_claim WHERE delivered_at IS NOT NULL'));
            $receipt = $db->fetchAssociative('SELECT choice, original_destination_id, delivered_destination_id FROM advent_claim');
            $choice = (int) $receipt['choice'];
            $destination = (int) $receipt['delivered_destination_id'];
            self::assertSame($origin->id, (int) $receipt['original_destination_id']);
            self::assertContains($destination, $planets);
            foreach (array_unique($planets) as $planet) {
                $stocks = $db->fetchAllKeyValue("SELECT resource_type, current_stock FROM planet_resource WHERE planet_id = ? AND resource_type IN ('metal', 'nano')", [$planet]);
                self::assertEqualsWithDelta($planet === $destination && $choice === 0 ? 507 : 500, (float) $stocks['metal'], .01);
                self::assertEqualsWithDelta($planet === $destination && $choice === 1 ? 503 : 500, (float) $stocks['nano'], .01);
            }
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop(0);
            }
            foreach (['0', '1', 'go'] as $part) {
                $file = $directory.'/'.$part;
                if (is_file($file)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }

    public static function races(): iterable
    {
        yield 'deux choix le jour de l’ouverture' => [false];
        yield 'deux destinations de reprise après clôture' => [true];
    }

    /** @param array<string, int> $stocks */
    private function fixtureStocks(int $planet, string $at, array $stocks): void
    {
        $db = $this->em->getConnection();
        // La matérialisation de toutes les ressources part de l’horloge de
        // l’électricité. Neutraliser le temps simulé dans cette fixture seulement.
        $db->executeStatement('UPDATE planet_resource SET last_updated_at = ? WHERE planet_id = ?', [$at, $planet]);
        foreach ($stocks as $resource => $quantity) {
            $db->executeStatement('UPDATE planet_resource SET current_stock = ? WHERE planet_id = ? AND resource_type = ?', [$quantity, $planet, $resource]);
        }
    }

    public function testCohortIsFrozenBeforeLaterUnlockAndOpeningAndInitialCapKeepsRest(): void
    {
        $senior = $this->player('AdventEstablished');
        $secondary = $this->player('AdventLateUnlock');
        $admin = $this->player('AdventCohortAdmin', admin: true);
        $colony = $this->planet($senior)->id;
        $secondaryColony = $this->planet($secondary)->id;
        $omegaMembership = $senior->currentPlayerUniverse->id;
        $senior = $this->select($senior, 'turbo-1');
        $alternate = $senior->currentPlayerUniverse->id;
        $senior = $this->select($senior, 'omega-1');
        $db = $this->em->getConnection();
        $setUnlock = static function (int $member, int $planet, string $slug, string $tier) use ($db): void {
            $db->executeStatement('UPDATE player_universe SET catalog_unlocks = ? WHERE id = ?', [
                json_encode(['_requirements_version' => 2, 'v2:planet:'.$planet.':ships:'.$slug.':'.$tier => true], JSON_THROW_ON_ERROR), $member,
            ]);
        };
        $setUnlock($omegaMembership, $colony, 'fighter', 'intermediate');
        $id = $this->publishConsolidated($admin);
        $this->clock->sleep(8 * 3600);
        $capture = static::getContainer()->get(AdventCohortCapture::class);
        $report = $capture->capture($id);
        self::assertSame('captured', $report['state']);
        self::assertSame('2026-11-30 23:00:00', $report['observed_at']);
        self::assertSame(4, $report['profiles']);
        self::assertSame('already', $capture->capture($id)['state']);
        $byUniverse = $db->fetchFirstColumn('SELECT bracket FROM advent_progression WHERE player_id = ? ORDER BY universe_id', [$senior->id]);
        self::assertEqualsCanonicalizing(['intermediate', 'beginner'], $byUniverse);
        $setUnlock($secondary->currentPlayerUniverse->id, $secondaryColony, 'capital', 'superior');
        self::assertSame('beginner', $db->fetchOne('SELECT bracket FROM advent_progression WHERE player_id = ?', [$secondary->id]));
        $new = $this->player('AdventNewcomer');
        self::assertSame('arrival', $db->fetchOne('SELECT origin FROM advent_progression WHERE player_id = ?', [$new->id]));
        self::assertSame('beginner', $db->fetchOne('SELECT bracket FROM advent_progression WHERE player_id = ?', [$new->id]));

        $this->client->loginUser($this->em->find(Player::class, $senior->id));
        $uri = '/evenements/avent/'.$id;
        $crawler = $this->client->request('GET', $uri);
        self::assertSelectorNotExists('#advent-door-1 form');
        $this->client->submit($crawler->filter('form[action$="/univers"]')->form());
        self::assertResponseRedirects($uri, 303);
        self::assertSame($omegaMembership, $senior->currentPlayerUniverse->id);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.advent-universe', 'Intermédiaire ×6');
        $token = $crawler->filter('#advent-door-1 input[name="_token"]')->attr('value');
        $this->fixtureStocks($colony, '2026-11-30 23:00:00', ['metal' => 0, 'nano' => 0]);
        $this->client->request('POST', $uri.'/portes/1', ['_token' => $token, 'choice' => '0', 'colony' => $colony]);
        self::assertResponseRedirects($uri.'#advent-door-1', 303);
        $crawler = $this->client->request('GET', '/inventaire/boites');
        $this->client->submit($crawler->filter('#advent-box-1 form')->form(['colony' => $colony]));
        self::assertResponseRedirects('/inventaire/boites', 303);
        self::assertSame(['metal' => 2000, 'nano' => 1000], json_decode($db->fetchOne('SELECT credited_resources FROM advent_claim'), true));
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim'));

        $senior = $this->select($senior, 'turbo-1');
        self::assertSame($alternate, $senior->currentPlayerUniverse->id);
        $this->client->loginUser($senior);
        $this->client->request('GET', $uri);
        self::assertSelectorNotExists('#advent-door-1 form');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_binding WHERE player_id = ?', [$senior->id]));
        $senior = $this->select($senior, 'omega-1');
        $this->clock->sleep(49 * 3600);
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($senior);
        $this->fixtureStocks($colony, '2026-12-03 00:00:00', ['metal' => 0, 'nano' => 0]);
        $crawler = $this->client->request('GET', '/inventaire/boites');
        $this->client->submit($crawler->filter('#advent-box-1 form')->form(['colony' => $colony]));
        self::assertResponseRedirects('/inventaire/boites', 303);
        self::assertSame(['metal' => 3600, 'nano' => 1800], json_decode($db->fetchOne('SELECT credited_resources FROM advent_claim'), true));
        self::assertNotNull($db->fetchOne('SELECT delivered_at FROM advent_claim'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_claim'));

        // Un ancien reçu partiellement payé ne disparaît pas au réveillon ;
        // le montant déjà payé ne doit pas être rejoué.
        $this->em->clear();
        $legacy = new AdventClaim();
        $legacy->player = $this->em->find(Player::class, $senior->id);
        $legacy->adventEdition = $this->em->find(AdventEdition::class, $id);
        $legacy->door = 2;
        $legacy->choice = 0;
        $legacy->originalDestinationId = $colony;
        $legacy->resources = ['metal' => 3600, 'nano' => 1800];
        $legacy->creditedResources = ['metal' => 2000, 'nano' => 1000];
        $legacy->openedAt = $this->clock->now();
        $this->em->persist($legacy);
        $this->em->flush();
        $this->clock->modify('2027-01-01T00:00:00Z');
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($legacy->player);
        $this->fixtureStocks($colony, '2027-01-01 00:00:00', ['metal' => 500, 'nano' => 500]);
        $crawler = $this->client->request('GET', '/inventaire/recompenses');
        $legacyForm = $crawler->selectButton('Retenter le versement')->form(['colony' => $colony]);
        $this->client->submit($legacyForm);
        self::assertResponseRedirects('/inventaire/recompenses', 303);
        $this->client->submit($legacyForm);
        self::assertResponseRedirects('/inventaire/recompenses', 303);
        self::assertEqualsWithDelta(2100, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$colony]), .01);
        self::assertEqualsWithDelta(1300, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'nano'", [$colony]), .01);
    }

    public function testCaptureDefersWithoutTakingTheEditionWhileAccountIsChangingUniverse(): void
    {
        $member = $this->player('AdventCohortBusyMember');
        $admin = $this->player('AdventCohortBusyAdmin', admin: true);
        $edition = $this->publishConsolidated($admin);
        $this->clock->sleep(8 * 3600);
        $db = $this->em->getConnection();
        $args = ['action' => 'capture', 'edition' => $edition, 'when' => '2026-11-30 23:00:00'];
        $worker = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_advent_open.php', json_encode($args, JSON_THROW_ON_ERROR)]);
        $worker->setTimeout(15);
        try {
            $db->beginTransaction();
            $db->fetchOne('SELECT id FROM player WHERE id = ? FOR UPDATE', [$member->id]);
            // Un deuxième processus tente réellement de publier pendant que
            // le verrou compte est tenu, comme lors du choix d’un univers.
            self::assertSame(0, $worker->run(), $worker->getErrorOutput());
            self::assertSame('deferred', trim($worker->getOutput()));
            self::assertNull($db->fetchOne('SELECT cohort_captured_at FROM advent_edition WHERE id = ? LOCK IN SHARE MODE NOWAIT', [$edition]));
            self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_progression WHERE advent_edition_id = ?', [$edition]));
            $db->commit();
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            $worker->stop(0);
        }

        $report = static::getContainer()->get(AdventCohortCapture::class)->capture($edition);
        self::assertSame('captured', $report['state']);
        self::assertSame(2, $report['profiles']);
        self::assertSame('already', static::getContainer()->get(AdventCohortCapture::class)->capture($edition)['state']);
    }

    public function testTwoUniverseTabsCannotElectTwoProfilesForOneAccount(): void
    {
        $member = $this->player('AdventRaceElection');
        $omega = $member->currentPlayerUniverse;
        $colony = $this->planet($member)->id;
        $member = $this->select($member, 'turbo-1');
        $turbo = $member->currentPlayerUniverse;
        $member = $this->select($member, 'omega-1');
        $db = $this->em->getConnection();
        $db->executeStatement('UPDATE player_universe SET catalog_unlocks = ? WHERE id = ?', [
            json_encode(['_requirements_version' => 2, 'v2:planet:'.$colony.':ships:fighter:intermediate' => true], JSON_THROW_ON_ERROR), $omega->id,
        ]);
        $edition = $this->publishConsolidated($this->player('AdventElectionAdmin', admin: true));
        $this->clock->sleep(8 * 3600);
        $report = static::getContainer()->get(AdventCohortCapture::class)->capture($edition);
        self::assertSame('captured', $report['state']);
        self::assertEqualsCanonicalizing(['beginner', 'intermediate'], $db->fetchFirstColumn(
            'SELECT bracket FROM advent_progression WHERE player_id = ? AND advent_edition_id = ?', [$member->id, $edition],
        ));

        $directory = sys_get_temp_dir().'/genesis-advent-election-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $workers = [];
        try {
            $db->beginTransaction();
            $db->fetchOne('SELECT id FROM player WHERE id = ? FOR UPDATE', [$member->id]);
            foreach ([$omega->id, $turbo->id] as $index => $membership) {
                $input = ['action' => 'bind', 'player' => $member->id, 'membership' => $membership,
                    'edition' => $edition, 'when' => '2026-12-06 12:00:00', 'ready' => $directory.'/'.$index, 'go' => $directory.'/go'];
                $worker = new Process([PHP_BINARY, dirname(__DIR__).'/Support/concurrent_advent_open.php', json_encode($input, JSON_THROW_ON_ERROR)]);
                $worker->setTimeout(20);
                $worker->start();
                $workers[] = $worker;
            }
            $deadline = microtime(true) + 8;
            while (microtime(true) < $deadline && (!is_file($directory.'/0') || !is_file($directory.'/1'))) {
                clearstatcache();
                usleep(20000);
            }
            foreach ($workers as $index => $worker) {
                self::assertFileExists($directory.'/'.$index, $worker->getErrorOutput());
            }
            $connections = [(int) file_get_contents($directory.'/0'), (int) file_get_contents($directory.'/1')];
            self::assertNotSame($connections[0], $connections[1]);
            touch($directory.'/go');
            $deadline = microtime(true) + 5;
            do {
                $waiting = (int) $db->fetchOne("SELECT COUNT(*) FROM information_schema.PROCESSLIST
                    WHERE ID IN (?, ?) AND INFO LIKE '%player%' AND INFO LIKE '%FOR UPDATE%'", $connections);
                if ($waiting === 2) {
                    break;
                }
                usleep(20000);
            } while (microtime(true) < $deadline);
            self::assertSame(2, $waiting, 'Les deux élections attendent le compte, pas deux verrous indépendants.');
            $db->commit();
            foreach (['bound', 'stale'] as $index => $state) {
                self::assertSame(0, $workers[$index]->wait(), $workers[$index]->getErrorOutput());
                self::assertSame($state, trim($workers[$index]->getOutput()));
            }
            $bindings = $db->fetchAllAssociative('SELECT universe_id, bracket FROM advent_binding WHERE player_id = ?', [$member->id]);
            self::assertCount(1, $bindings);
            self::assertSame($omega->universe->id, (int) $bindings[0]['universe_id']);
            self::assertSame('intermediate', $bindings[0]['bracket']);
        } finally {
            if ($db->isTransactionActive()) {
                $db->rollBack();
            }
            foreach ($workers as $worker) {
                $worker->stop(0);
            }
            foreach (['0', '1', 'go'] as $part) {
                if (is_file($file = $directory.'/'.$part)) {
                    unlink($file);
                }
            }
            rmdir($directory);
        }
    }

    public function testLockedHullPackCanBeDeliveredLaterAndCosmeticsDoNotDependOnAHeavyHull(): void
    {
        $admin = $this->player('AdventShipAdmin', admin: true);
        $id = $this->publishConsolidated($admin);
        $this->clock->sleep(8 * 3600);
        $member = $this->player('AdventShipPlayer');
        $membership = $member->currentPlayerUniverse;
        $planet = $this->planet($member);
        $edition = $this->em->find(AdventEdition::class, $id);
        static::getContainer()->get(AdventParticipation::class)->bind($member, $membership, $edition);
        $this->clock->sleep(5 * 86400);
        $rewards = static::getContainer()->get(AdventRewards::class);
        $db = $this->em->getConnection();
        self::assertSame('box_ready', $rewards->open($member, $membership, $planet, $edition, 6, 0)['state']);
        self::assertSame('ships_reserved', $rewards->open($member, $membership, $planet, $edition, 6, 0, reservationOnly: true)['state']);
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet_ship WHERE slug = 'probe'"));
        $membership->catalogUnlocks['v2:planet:'.$planet->id.':ships:probe:basic'] = true;
        $this->em->flush();
        self::assertSame('delivered', $rewards->open($member, $membership, $planet, $edition, 6, 0, reservationOnly: true)['state']);
        self::assertSame('already', $rewards->open($member, $membership, $planet, $edition, 6, 0)['state']);
        self::assertSame(12, (int) $db->fetchOne("SELECT quantity FROM fleet_ship WHERE slug = 'probe' AND tier = 'basic'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM queue_item'));

        $this->clock->sleep(18 * 86400);
        self::assertSame('credit_ready', $rewards->open($member, $membership, $planet, $edition, 24, 2)['state']);
        self::assertSame(['guardian_of_stars', 'polar_night'], $db->fetchFirstColumn('SELECT code FROM profile_cosmetic ORDER BY code'));
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet_ship WHERE slug = 'capital'"));
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE door = 24'));
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($member);
        $crawler = $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#profile-cosmetics', 'Apparence du profil');
        foreach ([['guardian_of_stars', 'polar_night'], []] as $codes) {
            $this->client->request('POST', '/profil/cosmetiques', [
                '_token' => $crawler->filter('.profile-cosmetics input[name="_token"]')->attr('value'),
                'equipped' => $codes,
            ]);
            self::assertResponseRedirects('/profil#profile-cosmetics', 303);
            self::assertSame(count($codes), (int) $db->fetchOne('SELECT COUNT(*) FROM profile_cosmetic WHERE equipped = 1'));
            $crawler = $this->client->followRedirect();
        }
        // Deux boîtes de la même coque permettent de contrôler la seconde
        // exacte de fermeture indépendamment des prérequis d’une autre coque.
        $this->em->clear();
        $edition = $this->em->find(AdventEdition::class, $id);
        $doors = $edition->doors;
        foreach ([18, 23] as $door) {
            $doors[$door]['options']['beginner'][0] = $doors[6]['options']['beginner'][0];
        }
        $edition->doors = $doors;
        $this->em->flush();
        $planetId = $planet->id;
        $this->clock->modify('2026-12-31T22:59:59Z');
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->em->find(Player::class, $member->id));
        foreach ([18, 23] as $door) {
            $crawler = $this->client->request('GET', '/evenements/avent/'.$id);
            $this->client->submit($crawler->filter('#advent-door-'.$door.' form')->form(['choice' => '0']));
            self::assertResponseStatusCodeSame(303);
        }
        $crawler = $this->client->request('GET', '/inventaire/boites');
        self::assertSelectorTextContains('#advent-box-18', 'Boîte de vaisseaux');
        $valid = $crawler->filter('#advent-box-18 form')->form(['colony' => $planetId]);
        $expired = $crawler->filter('#advent-box-23 form')->form(['colony' => $planetId]);
        $this->client->submit($valid);
        self::assertResponseRedirects('/inventaire/boites', 303);
        self::assertSame(24, (int) $db->fetchOne("SELECT quantity FROM fleet_ship WHERE slug = 'probe' AND tier = 'basic'"));
        $this->clock->sleep(1);
        $this->client->submit($expired);
        self::assertResponseRedirects('/inventaire/boites', 303);
        self::assertSame(24, (int) $db->fetchOne("SELECT quantity FROM fleet_ship WHERE slug = 'probe' AND tier = 'basic'"));
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE door = 23'));
    }

    public function testResearchAndHullVouchersUseTheNativeQueueAndNeverRefundTheDiscount(): void
    {
        $admin = $this->player('AdventCreditAdmin', admin: true);
        $id = $this->publishConsolidated($admin);
        $this->clock->sleep(8 * 3600);
        $member = $this->player('AdventCreditPlayer');
        $membership = $member->currentPlayerUniverse;
        $planet = $this->planet($member);
        $edition = $this->em->find(AdventEdition::class, $id);
        static::getContainer()->get(AdventParticipation::class)->bind($member, $membership, $edition);
        $this->clock->sleep(11 * 86400);
        $rewards = static::getContainer()->get(AdventRewards::class);
        self::assertSame('credit_ready', $rewards->open($member, $membership, $planet, $edition, 6, 1)['state']);
        self::assertSame('credit_ready', $rewards->open($member, $membership, $planet, $edition, 12, 0)['state']);
        $membership->catalogUnlocks['v2:planet:'.$planet->id.':ships:transport:basic'] = true;
        $membership->catalogUnlocks['v2:universe:technologies:engineering_foundations:basic'] = true;
        $membership->catalogUnlocks['v2:universe:technologies:colonial_expansion:basic'] = true;
        $lab = new \App\Entity\PlanetBuilding();
        $lab->planet = $planet;
        $lab->slug = 'lab';
        $lab->tier = 'basic';
        $lab->level = 1;
        $this->em->persist($lab);
        $this->em->flush();
        $colony = $planet->id;
        $db = $this->em->getConnection();
        $researchId = (int) $db->fetchOne('SELECT id FROM advent_claim WHERE door = 12');
        $shipId = (int) $db->fetchOne('SELECT id FROM advent_claim WHERE door = 6');
        $this->fixtureStocks($colony, '2026-12-11 23:00:00', ['metal' => 9900, 'nano' => 9900, 'antimatter' => 9900, 'hydrogen' => 9900, 'electricity' => 9900]);

        $this->client->getCookieJar()->clear();
        $this->client->loginUser($member);
        $url = '/evenements/avent/'.$id.'/avoirs/'.$researchId;
        $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('[data-advent-credit-day="12"]');
        self::assertSelectorNotExists('#advent-credit-quote-title');
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE id = ?', [$researchId]));
        $this->client->request('GET', $url.'?colony='.$colony.'&item=colonial_expansion%3Abasic');
        self::assertResponseStatusCodeSame(422);
        self::assertNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE id = ?', [$researchId]));
        $crawler = $this->client->request('GET', $url.'?colony='.$colony.'&item=engineering_foundations%3Abasic');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'La remise est utilisée à l’ajout en file');
        self::assertSelectorExists('.advent-credit-grid table');
        self::assertSelectorExists('button[aria-describedby="advent-credit-usage"]');
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM queue_item'));
        $control = $this->em->find(Player::class, $member->id)->currentPlayerUniverse;
        $target = $this->em->find(Planet::class, $colony);
        $offer = static::getContainer()->get(AdventQueueCredits::class)->findRedeemableOffer($control, $id, $researchId)['award'];
        $queue = static::getContainer()->get(QueueService::class);
        $quote = $queue->adventPreview($control, $target, 'technologies', 'engineering_foundations', 'basic', $offer);
        $before = $db->fetchAllKeyValue('SELECT resource_type, current_stock FROM planet_resource WHERE planet_id = ?', [$colony]);
        $this->client->submit($crawler->selectButton('Confirmer et utiliser la remise')->form());
        self::assertResponseRedirects('/evenements/avent/'.$id, 303);
        $command = $db->fetchAssociative("SELECT id, paid_costs, duration_seconds FROM queue_item WHERE queue_type = 'research'");
        self::assertSame($quote['paid'], json_decode($command['paid_costs'], true));
        self::assertSame($quote['duration'], (int) $command['duration_seconds']);
        self::assertSame((int) $command['id'], (int) $db->fetchOne('SELECT redeemed_queue_id FROM advent_claim WHERE id = ?', [$researchId]));
        $this->client->request('GET', $url.'?colony='.$colony.'&item=engineering_foundations%3Abasic');
        self::assertResponseStatusCodeSame(404);
        $control = $this->em->find(Player::class, $member->id)->currentPlayerUniverse;
        $queue->cancel($control, (int) $command['id'], bin2hex(random_bytes(16)));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM queue_item'));
        self::assertNotNull($db->fetchOne('SELECT delivered_at FROM advent_claim WHERE id = ?', [$researchId]));
        foreach ($db->fetchAllKeyValue('SELECT resource_type, current_stock FROM planet_resource WHERE planet_id = ?', [$colony]) as $resource => $after) {
            self::assertLessThanOrEqual((float) $before[$resource] + 0.01, (float) $after);
        }

        $crawler = $this->client->request('GET', '/evenements/avent/'.$id.'/avoirs/'.$shipId.'?colony='.$colony);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'équivalents métal');
        self::assertSelectorExists('[data-advent-credit-day="6"]');
        self::assertSelectorNotExists('#advent-credit-technology');
        $this->client->submit($crawler->selectButton('Confirmer et utiliser la remise')->form());
        self::assertResponseRedirects('/evenements/avent/'.$id, 303);
        $ship = $db->fetchAssociative("SELECT item_slug, tier, quantity FROM queue_item WHERE queue_type = 'shipyard'");
        self::assertSame('transport', $ship['item_slug']);
        self::assertSame('basic', $ship['tier']);
        self::assertSame(1, (int) $ship['quantity']);
        self::assertSame(0, (int) $db->fetchOne("SELECT COUNT(*) FROM fleet_ship WHERE slug = 'transport'"));
    }

    public function testAdminShowsScheduledNormalWindowAndActualOnTimeReadout(): void
    {
        $admin = $this->player('AdventReadoutAdmin', admin: true);
        $id = $this->publishConsolidated($admin);
        $selector = '[data-advent-readout="'.$id.'"]';
        $this->client->request('GET', '/admin/events/avent');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains($selector, 'Relevé prévu.');
        self::assertSelectorTextContains($selector, '01/12/2026 00:00:00');
        self::assertSelectorNotExists($selector.' code');

        $this->clock->sleep(8 * 3600 + 120);
        $this->client->request('GET', '/admin/events/avent');
        self::assertSelectorTextContains($selector, 'Fenêtre normale ouverte.');
        self::assertSelectorNotExists($selector.' code');
        $this->assertCohortReadoutMissing($id);

        $command = $this->readoutCommand();
        self::assertSame(0, $command->execute(['edition' => (string) $id]));
        self::assertStringContainsString('captured ; observation UTC 2026-11-30 23:02:00', $command->getDisplay());
        $this->client->request('GET', '/admin/events/avent');
        self::assertSelectorTextContains($selector, 'Relevé effectué');
        self::assertSelectorTextContains($selector, '01/12/2026 00:02:00');
        self::assertSelectorTextNotContains($selector, 'tardivement');
        self::assertSelectorNotExists($selector.' code');
    }

    public function testAdminOnlyOffersExplicitLateCaptureBeforeClosureAndDoesNotRepeatIt(): void
    {
        $this->player('AdventReadoutMember');
        $admin = $this->player('AdventLateReadoutAdmin', admin: true);
        $id = $this->publishConsolidated($admin);
        $selector = '[data-advent-readout="'.$id.'"]';
        $this->clock->sleep(8 * 3600 + 121);
        $this->client->request('GET', '/admin/events/avent');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains($selector, 'Relevé d’ouverture manqué.');
        self::assertSelectorTextSame($selector.' code', 'php bin/console app:advent:capture '.$id.' --accept-late');
        self::assertSelectorNotExists($selector.' form');
        $this->assertCohortReadoutMissing($id);

        $command = $this->readoutCommand();
        self::assertSame(1, $command->execute(['edition' => (string) $id]));
        self::assertStringContainsString('Relevé de départ manqué', $command->getDisplay());
        $this->assertCohortReadoutMissing($id);
        self::assertSame(0, $command->execute(['edition' => (string) $id, '--accept-late' => true]));
        self::assertStringContainsString('late ; observation UTC 2026-11-30 23:02:01', $command->getDisplay());
        $this->client->request('GET', '/admin/events/avent');
        self::assertSelectorTextContains($selector, 'Relevé effectué tardivement');
        self::assertSelectorTextContains($selector, '01/12/2026 00:02:01');
        self::assertSelectorTextContains($selector, 'Cet horaire ne représente pas l’ouverture.');
        self::assertSelectorNotExists($selector.' code');

        $db = $this->em->getConnection();
        $profiles = (int) $db->fetchOne('SELECT COUNT(*) FROM advent_progression');
        self::assertGreaterThan(0, $profiles);
        $this->clock->sleep(3600);
        self::assertSame(0, $command->execute(['edition' => (string) $id, '--accept-late' => true]));
        self::assertStringContainsString('already ; observation UTC 2026-11-30 23:02:01', $command->getDisplay());
        self::assertSame($profiles, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_progression'));
    }

    public function testAdminShowsClosedMissingReadoutWithoutOfferingRecovery(): void
    {
        $admin = $this->player('AdventClosedReadoutAdmin', admin: true);
        // Une courte période publiée par le formulaire natif isole la clôture
        // sans introduire de saut de temps qui invaliderait la session.
        $id = $this->publishConsolidated($admin, '2026-12-01T00:03');
        $edition = $this->em->find(AdventEdition::class, $id);
        self::assertInstanceOf(AdventEdition::class, $edition);
        $this->clock->sleep($edition->closesAt->getTimestamp() - $this->clock->now()->getTimestamp());
        $this->client->request('GET', '/admin/events/avent');
        self::assertResponseIsSuccessful();
        $selector = '[data-advent-readout="'.$id.'"]';
        self::assertSelectorTextContains($selector, 'Édition clôturée sans relevé.');
        self::assertSelectorTextContains($selector, 'Aucun rattrapage possible après la clôture.');
        self::assertSelectorNotExists($selector.' code');

        $command = $this->readoutCommand();
        self::assertSame(1, $command->execute(['edition' => (string) $id, '--accept-late' => true]));
        self::assertStringContainsString('Le relevé doit commencer pendant l’édition', $command->getDisplay());
        $this->assertCohortReadoutMissing($id);
    }

    private function readoutCommand(): CommandTester
    {
        return new CommandTester((new Application(self::$kernel))->find('app:advent:capture'));
    }

    private function assertCohortReadoutMissing(int $id): void
    {
        $db = $this->em->getConnection();
        self::assertNull($db->fetchOne('SELECT cohort_captured_at FROM advent_edition WHERE id = ?', [$id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_progression WHERE advent_edition_id = ?', [$id]));
    }

    public function testLateCaptureNeverPretendsToBeMidnight(): void
    {
        $this->player('AdventLateCohort');
        $admin = $this->player('AdventLateCohortAdmin', admin: true);
        $id = $this->publishConsolidated($admin);
        $this->clock->sleep(8 * 3600 + 121);
        $capture = static::getContainer()->get(AdventCohortCapture::class);
        try {
            $capture->capture($id);
            self::fail('Un relevé passé ne doit pas être reconstitué.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('Relevé de départ manqué', $error->getMessage());
        }
        $db = $this->em->getConnection();
        self::assertNull($db->fetchOne('SELECT cohort_captured_at FROM advent_edition WHERE id = ?', [$id]));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM advent_progression'));
        $result = $capture->capture($id, acceptLate: true);
        self::assertSame('late', $result['state']);
        self::assertSame('2026-11-30 23:02:01', $result['observed_at']);
        self::assertSame(['late_cohort'], array_unique($db->fetchFirstColumn('SELECT origin FROM advent_progression')));
    }

    /** Anciennes fixtures de retenue : les montants synthétiques ne transitent plus par l’admin. */
    private function publish(Player $admin): int
    {
        $edition = new AdventEdition();
        $edition->season = 2026;
        $edition->name = 'Avent synthétique';
        $edition->publishedAt = $this->clock->now();
        $dates = (new \App\Service\Advent\AdventCalendar())->schedule('2026-12-01', '2026-12-31');
        $edition->startsAt = $dates['startsAt'];
        $edition->closesAt = $dates['closesAt'];
        foreach ($dates['doors'] as $day => $date) {
            $edition->doors[$day] = ['opens_at' => $date->format(DATE_ATOM), 'options' => [['metal' => 7], ['nano' => 3]]];
        }
        $window = new AdventWindow();
        $window->universe = $this->em->find(Universe::class, $admin->currentPlayerUniverse->universe->id);
        $window->name = $edition->name;
        $window->edition = $edition;
        $window->visibleFrom = $edition->publishedAt;
        $window->visibleUntil = $edition->closesAt;
        $this->em->persist($edition);
        $this->em->persist($window);
        $this->em->flush();
        return $edition->id;
    }

    private function publishConsolidated(Player $admin, string $until = '2027-01-01T00:00'): int
    {
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/events/avent');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Barème de l’édition');
        $token = $crawler->filter('.admin-event-form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/events/avent', [
            '_token' => $token, 'name' => 'Avent synthétique', 'season' => 2026, 'universe' => $admin->currentPlayerUniverse->universe->id,
            'from' => '2026-12-01T00:00', 'until' => $until,
            'options' => array_fill(1, 24, [['metal' => '7'], ['nano' => '3']]),
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->em->getRepository(AdventEdition::class)->count());
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(24, $crawler->filter('ol.event-list > li'));
        self::assertSelectorTextContains('main', 'Débutant ×1');
        self::assertSelectorTextContains('main', 'Vétéran ×24');
        self::assertSelectorTextContains('main', 'Gardien des étoiles');
        self::assertSelectorTextNotContains('main', 'Collecteur');

        $url = $this->client->getRequest()->getUri();
        $this->client->submit($crawler->selectButton('Confirmer et publier')->form());
        self::assertResponseRedirects('/admin/events/avent', 303);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->em->getRepository(AdventEdition::class)->count());
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM outbox_message WHERE kind = 'advent_capture'"));
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'advent_edition_published'"));

        return (int) $this->em->getConnection()->fetchOne('SELECT id FROM advent_edition');
    }

    private function player(string $name, bool $admin = false): Player
    {
        $data = new RegistrationData();
        $data->email = strtolower($name).'@example.test';
        $data->username = $name;
        $data->plainPassword = bin2hex(random_bytes(16)).'aA1!';
        $player = static::getContainer()->get(RegistrationService::class)->register($data);
        $player->emailVerifiedAt = $this->clock->now();
        if ($admin) {
            $player->roles = ['ROLE_ADMIN'];
        }
        $this->em->flush();
        return $this->select($player, 'omega-1');
    }

    private function select(Player $player, string $slug): Player
    {
        $player = $this->em->find(Player::class, $player->id);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
        static::getContainer()->get(UniverseService::class)->select($player, $universe->id);

        return $player;
    }

    private function planet(Player $player): Planet
    {
        return $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);
    }
}
