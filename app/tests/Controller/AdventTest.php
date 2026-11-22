<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{AdventClaim, AdventEdition, Planet, Player, Universe};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Process\Process;

final class AdventTest extends WebTestCase
{
    private ?string $savedServer;
    private ?string $savedEnv;
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->savedServer = $_SERVER['ADVENT_ENABLED'] ?? null;
        $this->savedEnv = $_ENV['ADVENT_ENABLED'] ?? null;
        $_SERVER['ADVENT_ENABLED'] = $_ENV['ADVENT_ENABLED'] = '1';
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

    protected function tearDown(): void
    {
        parent::tearDown();
        unset($_SERVER['ADVENT_ENABLED'], $_ENV['ADVENT_ENABLED']);
        if ($this->savedServer !== null) {
            $_SERVER['ADVENT_ENABLED'] = $this->savedServer;
        }
        if ($this->savedEnv !== null) {
            $_ENV['ADVENT_ENABLED'] = $this->savedEnv;
        }
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
        self::assertSelectorExists('a[href="/evenements/avent"]');
        $crawler = $this->client->request('GET', '/evenements/avent');
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('no-store', $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertCount(24, $crawler->filter('.advent-door'));
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
        self::assertSelectorTextContains('#advent-door-1', 'Réservé');
        $initial = $db->fetchAssociative('SELECT choice, resources, delivered_at, original_destination_id, delivered_destination_id FROM advent_claim');
        self::assertSame(0, (int) $initial['choice']);
        self::assertSame($planet->id, (int) $initial['original_destination_id']);
        self::assertNull($initial['delivered_at']);

        // L’ancien formulaire se ferme après changement d’univers même si
        // une nouvelle destination contrôlée peut ensuite être choisie.
        $member = $this->select($member, 'turbo-1');
        $altPlanet = $this->planet($member);
        $this->client->loginUser($member);
        $this->client->request('GET', $editionUrl);
        self::assertSelectorTextContains('#advent-door-1', 'Réservé');
        self::assertSelectorExists('#advent-door-1 form');
        $this->client->request('POST', $url, ['choice' => '1', 'colony' => $altPlanet->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(403);

        // Le joueur revient avec une nouvelle session jusqu’à la dernière
        // seconde civile, puis reste connecté au passage de la clôture.
        $this->clock->sleep(31 * 24 * 3600 - 1);
        $this->client->getCookieJar()->clear();
        $this->client->loginUser($this->em->find(Player::class, $member->id));
        $crawler = $this->client->request('GET', $editionUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#advent-door-24 form');
        $unopenedToken = $crawler->filter('#advent-door-2 input[name="_token"]')->attr('value');
        $this->clock->sleep(1);
        $this->client->request('GET', '/evenements');
        self::assertSelectorExists('a[href="/evenements/avent"]');
        $crawler = $this->client->request('GET', $editionUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#advent-door-2', 'Expiré');
        self::assertSelectorNotExists('#advent-door-2 form');
        $token = $crawler->filter('#advent-door-1 input[name="_token"]')->attr('value');
        $this->client->request('POST', $editionUrl.'/portes/2', ['choice' => '0', 'colony' => $altPlanet->id, '_token' => $unopenedToken]);
        self::assertResponseRedirects($editionUrl.'#advent-door-2', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Cette porte ou ce choix de cadeau n’est pas disponible.');
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $foreign->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('POST', $url, ['choice' => '1', 'colony' => $altPlanet->id, '_token' => $token]);
        self::assertResponseRedirects($editionUrl.'#advent-door-1', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Le choix du cadeau ne peut plus être modifié.');
        self::assertSame($initial, $db->fetchAssociative('SELECT choice, resources, delivered_at, original_destination_id, delivered_destination_id FROM advent_claim'));
        $this->fixtureStocks($altPlanet->id, '2026-12-31 23:00:00', ['metal' => 500]);
        for ($i = 0; $i < 2; ++$i) {
            $this->client->request('POST', $url, ['choice' => '0', 'colony' => $altPlanet->id, '_token' => $token]);
            self::assertResponseRedirects($editionUrl.'#advent-door-1', 303);
        }
        self::assertSame(1, $this->em->getRepository(AdventClaim::class)->count());
        self::assertSame($planet->id, (int) $db->fetchOne('SELECT original_destination_id FROM advent_claim'));
        self::assertSame($altPlanet->id, (int) $db->fetchOne('SELECT delivered_destination_id FROM advent_claim'));
        self::assertSame('2026-12-31 23:00:00', $db->fetchOne('SELECT delivered_at FROM advent_claim'));
        self::assertEqualsWithDelta(507, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$altPlanet->id]), .01);
        self::assertEqualsWithDelta(10000, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$planet->id]), .01);
        $this->client->request('GET', $editionUrl);
        self::assertSelectorTextContains('#advent-door-1', 'Versé');
        self::assertSelectorTextContains('#advent-door-24', 'Expiré');
        $this->client->request('GET', '/evenements');
        self::assertSelectorNotExists('a[href="/evenements/avent"]');
        $this->client->loginUser($this->em->find(Player::class, $other->id));
        $this->client->request('GET', $editionUrl);
        self::assertResponseStatusCodeSame(404);
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
                    'edition' => $edition, 'choice' => $choice, 'when' => $when, 'ready' => $directory.'/'.$index, 'go' => $directory.'/go'];
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

    private function publish(Player $admin): int
    {
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/events/avent');
        self::assertResponseIsSuccessful();
        self::assertCount(24, $crawler->filter('.admin-event-form details'));
        $token = $crawler->filter('.admin-event-form input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/events/avent', [
            '_token' => $token, 'name' => 'Avent synthétique', 'first' => '2026-12-01', 'last' => '2026-12-31',
            'options' => array_fill(1, 24, [['metal' => '7'], ['nano' => '3']]),
        ]);
        self::assertResponseStatusCodeSame(303);
        self::assertSame(0, $this->em->getRepository(AdventEdition::class)->count());
        $crawler = $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertCount(24, $crawler->filter('ol.event-list > li'));
        $url = $this->client->getRequest()->getUri();
        $this->client->submit($crawler->selectButton('Confirmer et publier')->form());
        self::assertResponseRedirects('/admin/events/avent', 303);
        $this->client->request('GET', $url);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->em->getRepository(AdventEdition::class)->count());
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
