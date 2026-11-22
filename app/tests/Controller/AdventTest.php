<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{AdventClaim, AdventEdition, Planet, Player, Universe};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Service\Player\RegistrationService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

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
        $originalMembership = $member->currentPlayerUniverse;
        $planet = $this->planet($member);
        $foreign = $this->planet($other);
        $this->client->loginUser($member);
        $this->client->request('GET', '/admin/events/avent');
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
        $url = '/evenements/avent/'.$edition.'/portes/1';
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $planet->id, '_token' => 'expired']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $foreign->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $this->em->getRepository(AdventClaim::class)->count());

        $db = $this->em->getConnection();
        $db->executeStatement("UPDATE planet_resource SET current_stock = 10000, last_updated_at = '2026-11-30 23:00:00' WHERE planet_id = ? AND resource_type = 'metal'", [$planet->id]);
        $this->client->request('POST', $url, ['choice' => '0', 'colony' => $planet->id, '_token' => $token]);
        self::assertResponseRedirects('/evenements/avent#advent-door-1', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('#advent-door-1', 'Réservé');
        $initial = $db->fetchAssociative('SELECT choice, resources, delivered_at, destination_id FROM advent_claim');
        self::assertSame(0, (int) $initial['choice']);
        self::assertSame($planet->id, (int) $initial['destination_id']);
        self::assertNull($initial['delivered_at']);

        // Aucun second droit ni changement de choix : même compte, autre univers.
        $this->select($member, 'turbo-1');
        $altPlanet = $this->planet($member);
        $this->client->loginUser($member);
        $this->client->request('GET', '/evenements/avent');
        self::assertSelectorTextContains('#advent-door-1', 'Réservé');
        self::assertSelectorNotExists('#advent-door-1 form');
        $this->client->request('POST', $url, ['choice' => '1', 'colony' => $altPlanet->id, '_token' => $token]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame($initial, $db->fetchAssociative('SELECT choice, resources, delivered_at, destination_id FROM advent_claim'));

        $this->select($member, $originalMembership->universe->slug);
        $this->client->loginUser($member);
        $crawler = $this->client->request('GET', '/evenements/avent');
        $token = $crawler->filter('#advent-door-1 input[name="_token"]')->attr('value');
        $db->executeStatement("UPDATE planet_resource SET current_stock = 500 WHERE planet_id = ? AND resource_type = 'metal'", [$planet->id]);
        for ($i = 0; $i < 2; ++$i) {
            $this->client->request('POST', $url, ['choice' => '0', 'colony' => $planet->id, '_token' => $token]);
            self::assertResponseRedirects('/evenements/avent#advent-door-1', 303);
        }
        self::assertSame(1, $this->em->getRepository(AdventClaim::class)->count());
        self::assertNotNull($db->fetchOne('SELECT delivered_at FROM advent_claim'));
        self::assertEqualsWithDelta(507, (float) $db->fetchOne("SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = 'metal'", [$planet->id]), .01);
        $this->client->request('GET', '/evenements/avent');
        self::assertSelectorTextContains('#advent-door-1', 'Versé');

        $this->clock->sleep(31 * 24 * 3600 - 1);
        $this->client->request('GET', '/evenements/avent');
        self::assertSelectorExists('#advent-door-24 form');
        $this->clock->sleep(1);
        $this->client->request('GET', '/evenements/avent');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(1, $this->em->getRepository(AdventClaim::class)->count());
    }

    private function publish(Player $admin): int
    {
        $this->client->loginUser($admin);
        $crawler = $this->client->request('GET', '/admin/events/avent');
        self::assertResponseIsSuccessful();
        self::assertCount(24, $crawler->filter('details'));
        $token = $crawler->filter('input[name="_token"]')->attr('value');
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
        $this->select($player, 'omega-1');

        return $player;
    }

    private function select(Player $player, string $slug): void
    {
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
        static::getContainer()->get(UniverseService::class)->select($player, $universe->id);
    }

    private function planet(Player $player): Planet
    {
        return $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);
    }
}
