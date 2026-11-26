<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{Fleet, FleetShip, MarketTrade, Planet, PlanetResource, Player, Universe};
use App\Message\MarketConvoyDue;
use App\MessageHandler\MarketConvoyDueHandler;
use App\Repository\ResourceMarketRepository;
use App\Service\Colony\ResourceService;
use App\Service\Market\{MarketConvoys, MarketPricing, WeeklyMerchantDelivery};
use App\Service\Notification\OutboxRelay;
use App\Service\Player\{LeaderboardService, RegistrationService};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Stamp\DelayStamp;
use Symfony\Component\Process\Process;

final class ResourceMarketTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private MockClock $clock;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->clock = new MockClock('2026-11-26T18:40:00+00:00');
        self::getContainer()->set(ClockInterface::class, $this->clock);
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $tool = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        // Une base migrée et les fixtures ont des contraintes de noms différents.
        // Supprimer exclusivement les tables contrôlées dans cette base de test dédiée.
        $connection = $this->em->getConnection();
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($connection->createSchemaManager()->listTableNames() as $table) {
                $connection->executeStatement('DROP TABLE '.$connection->quoteSingleIdentifier($table));
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }
        $tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    public function testAdminOpeningAndPlayerRoundTripThroughOutboxAndHandler(): void
    {
        [$admin, $home, $transport] = $this->fixture('MarketAdmin', true);
        $member = $admin->currentPlayerUniverse;
        $universe = $member->universe;
        $this->client->loginUser($admin);
        $this->client->request('GET', '/bourse');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'n’accepte pas de nouveaux départs');
        $crawler = $this->client->request('GET', '/admin/univers/'.$universe->id.'/bourse');
        self::assertResponseIsSuccessful();
        $form = $crawler->selectButton('Enregistrer la configuration')->form($this->targets() + ['enabled' => '1']);
        $this->client->submit($form);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', '50 000');

        $crawler = $this->client->request('GET', '/bourse?planet='.$home->id);
        self::assertResponseIsSuccessful();
        $quote = $crawler->selectButton('Calculer le devis')->form(['quantity' => '2000']);
        $beforeMetal = $this->stock($home->id, 'metal');
        $beforePoints = self::getContainer()->get(LeaderboardService::class)->forUniverse($member)[0]['fleet_points'];
        $beforeNano = $this->stock($home->id, 'nano');
        $this->client->submit($quote);
        self::assertResponseRedirects('/bourse', 303);
        $crawler = $this->client->followRedirect();
        self::assertSelectorTextContains('.market-volumes', '2 000 Métal');
        self::assertSame($beforeMetal, $this->stock($home->id, 'metal'));

        $send = $crawler->selectButton('Confirmer et lancer les cargos')->form();
        $this->client->submit($send);
        self::assertResponseRedirects('/bourse', 303);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.market-trip', 'Vers la station');
        $first = $this->em->getRepository(MarketTrade::class)->findOneBy(['membershipId' => $member->id]);
        self::assertSame(1800, $first->dueAt->getTimestamp() - $first->departedAt->getTimestamp());
        self::assertSame($beforeMetal - 2000, $this->stock($home->id, 'metal'));
        self::assertSame($beforeNano, $this->stock($home->id, 'nano'));

        $markets = self::getContainer()->get(ResourceMarketRepository::class);
        self::assertSame(50000, $markets->find($universe)->stock['nano'], 'Le lot réservé est toujours physiquement à la station.');
        self::assertSame($first->receiveQuantity, $markets->reserved($universe)['outgoing']['nano']);
        self::assertSame(1, (int) $this->em->getConnection()->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$transport->id]));
        self::assertSame($beforePoints, self::getContainer()->get(LeaderboardService::class)->forUniverse($member)[0]['fleet_points']);
        self::assertSame(0, (int) $this->em->getConnection()->fetchOne("SELECT COUNT(*) FROM fleet WHERE status = 'traveling'"),
            'Le couloir dédié ne crée pas de cible d’attaque dans les flottes ordinaires.');
        $message = $this->relay($first->id);
        self::assertSame(1800000, $message['delay']);
        $handler = self::getContainer()->get(MarketConvoyDueHandler::class);
        $this->clock->sleep(1800);
        $handler($message['message']);
        $handler($message['message']);
        $this->em->clear();

        $return = $this->em->find(MarketTrade::class, $first->id);
        self::assertSame('returning', $return->status);
        self::assertSame(1800, $return->dueAt->getTimestamp() - $return->exchangedAt->getTimestamp());
        $market = $markets->find($return->universe);
        self::assertSame(50000 - $return->receiveQuantity, $market->stock['nano']);
        self::assertSame(52000, $market->stock['metal']);
        self::assertSame($return->receiveQuantity, $market->pressure['nano']);
        self::assertSame(-2000, $market->pressure['metal']);
        self::assertSame($beforeNano, $this->stock($home->id, 'nano'));
        $message = $this->relay($first->id);
        self::assertSame(1800000, $message['delay']);
        $this->clock->sleep(1800);
        $landingPlanet = $this->em->find(Planet::class, $home->id);
        $beforeDelivery = self::getContainer()->get(ResourceService::class)->snapshot($landingPlanet)['nano']['stock'];
        $handler($message['message']);
        $handler($message['message']);
        $this->em->clear();

        $closed = $this->em->find(MarketTrade::class, $first->id);
        self::assertSame('completed', $closed->status);
        self::assertEqualsWithDelta($beforeDelivery + $closed->receiveQuantity, $this->stock($home->id, 'nano'), .0001);
        self::assertSame(2, $this->em->find(FleetShip::class, $transport->id)->quantity);
    }

    public function testRecallAndOverflowPreserveAssetsWithoutNewTrade(): void
    {
        [$player, $planet, $transport] = $this->fixture('MarketRecall');
        $member = $player->currentPlayerUniverse;
        $admin = self::getContainer()->get(WeeklyMerchantDelivery::class);
        $admin->configure($player, $member->universe, true, $this->targets());
        $convoys = self::getContainer()->get(MarketConvoys::class);
        $before = $this->stock($planet->id, 'metal');
        $quote = $convoys->preview($member, $planet, ['pay' => 'metal', 'receive' => 'nano', 'quantity' => 300, 'rank' => 'basic', 'cargos' => 1]);
        $key = str_repeat('c', 32);
        $convoys->depart($member, $planet, $quote, $key);
        $convoys->depart($member, $planet, $quote, $key);
        $trades = $this->em->getRepository(MarketTrade::class)->findAll();
        self::assertCount(1, $trades);
        $trade = $trades[0];
        $oldToken = $trade->token;
        $this->clock->sleep(300);
        $convoys->recall($member, $trade->id);
        $this->clock->sleep(300);
        $convoys->processDue($trade->id, $oldToken);
        self::assertSame('returning', $trade->status);
        // Une autre activité remplit le stockage avant l’arrivée. Le lot non
        // déchargé doit rester disponible sur la flotte inactive.
        $metal = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet' => $planet, 'resourceType' => 'metal']);
        $metal->currentStock = $before + 10_000_000;
        $this->em->flush();
        $newToken = $trade->token;
        $convoys->processDue($trade->id, $newToken);
        $convoys->processDue($trade->id, $newToken);
        self::assertSame(2, $transport->quantity);
        self::assertEqualsWithDelta(300, $transport->fleet->cargo['metal'], 0.001);
        self::assertGreaterThan(0, $transport->fleet->storedFuel);
        self::assertNull($trade->exchangedAt);
        self::assertSame([], self::getContainer()->get(ResourceMarketRepository::class)->find($member->universe)->pressure);
    }

    public function testMerchantUsesClosedWeekPhysicalStockAndIsIdempotent(): void
    {
        [$player, $planet] = $this->fixture('MarketMerchant');
        $universe = $player->currentPlayerUniverse->universe;
        $delivery = self::getContainer()->get(WeeklyMerchantDelivery::class);
        $marketRepo = self::getContainer()->get(ResourceMarketRepository::class);
        $delivery->configure($player, $universe, true, $this->targets());
        $market = $marketRepo->find($universe);
        $market->stock['nano'] = 49000;
        $market->stock['antimatter'] = 40000;
        $market->pressure = ['nano' => 2000];
        $exchangeTimes = [
            ['2026-11-27T07:59:59Z', 'nano', 20000, 'completed'],
            ['2026-11-27T08:00:00Z', 'antimatter', 6000, 'completed'],
            [null, 'nano', 10000, 'outbound'],
        ];
        foreach ($exchangeTimes as $i => [$at, $resource, $quantity, $status]) {
            $trade = new MarketTrade();
            $trade->universe = $universe;
            $trade->membershipId = $player->currentPlayerUniverse->id;
            $trade->originId = $trade->returnPlanetId = $planet->id;
            $trade->requestKey = str_repeat(dechex($i + 1), 32);
            $trade->status = $status;
            $trade->payResource = 'metal';
            $trade->payQuantity = 1;
            $trade->receiveResource = $resource;
            $trade->receiveQuantity = $quantity;
            $trade->oneWayFuel = $trade->reservedFuel = 1.0;
            $trade->departedAt = new \DateTimeImmutable('2026-11-27T07:00:00Z');
            $trade->exchangedAt = $at ? new \DateTimeImmutable($at) : null;
            $this->em->persist($trade);
        }
        $this->em->flush();
        $now = new \DateTimeImmutable('2026-11-27T08:10:00Z');
        $this->clock->sleep($now->getTimestamp() - $this->clock->now()->getTimestamp());
        $command = (new Application(self::$kernel))->find('app:outbox:relay');
        $tester = new CommandTester($command);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $this->clock->sleep(31);
        $tester->execute([]);
        $tester->assertCommandIsSuccessful();
        $universe = $this->em->find(Universe::class, $universe->id);
        $market = $marketRepo->find($universe);
        self::assertSame(50000, $market->stock['nano']);
        self::assertSame(40000, $market->stock['antimatter']);
        self::assertSame(['nano' => 2000], $market->pressure);
        self::assertSame(10000, $marketRepo->reserved($universe)['outgoing']['nano']);
        $log = $marketRepo->merchantLog($universe);
        self::assertCount(1, $log);
        self::assertSame(['nano' => 1000], $log[0]->quantities);
        self::assertSame('2026-11-27', $log[0]->occurrence);
    }

    public function testLostOriginDoesNotDeliverToConquerorAndRedirectRequiresTravel(): void
    {
        [$player, $origin] = $this->fixture('MarketOwner');
        [$other] = $this->fixture('MarketConqueror');
        $member = $player->currentPlayerUniverse;
        $universe = $member->universe;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($player, $universe, true, $this->targets());
        $convoys = self::getContainer()->get(MarketConvoys::class);
        $resources = self::getContainer()->get(ResourceService::class);
        $input = ['pay' => 'metal', 'receive' => 'nano', 'quantity' => 1000, 'rank' => 'basic', 'cargos' => 1];
        $quote = $convoys->preview($member, $origin, $input);
        $convoys->depart($member, $origin, $quote, str_repeat('d', 32));
        $trade = $this->em->getRepository(MarketTrade::class)->findOneBy(['membershipId' => $member->id]);
        $this->clock->sleep(1800);
        $convoys->processDue($trade->id, $trade->token);
        $origin->owner = $other->currentPlayerUniverse;
        $this->em->flush();
        $conquerorStock = $this->stock($origin->id, 'nano');
        $this->clock->sleep(1800);
        $prior = $trade->token;
        $convoys->processDue($trade->id, $prior);
        self::assertSame('awaiting_colony', $trade->status);
        self::assertSame($conquerorStock, $this->stock($origin->id, 'nano'));

        $fallback = $this->em->getRepository(Planet::class)->findOneBy(['solarSystem' => $origin->solarSystem, 'owner' => null, 'npcPlanet' => false]);
        self::assertNotNull($fallback);
        $fallback->owner = $member;
        $resources->initialize($fallback, true);
        $this->em->flush();
        $convoys->reroute($member, $trade->id, $fallback);
        self::assertSame($fallback->id, $trade->returnPlanetId);
        self::assertSame(1800, $trade->dueAt->getTimestamp() - $this->clock->now()->getTimestamp());
        $convoys->processDue($trade->id, $prior);
        self::assertSame('returning', $trade->status);
        $this->clock->sleep(1800);
        $departureStock = $resources->snapshot($fallback)['nano']['stock'];
        $convoys->processDue($trade->id, $trade->token);
        self::assertSame('completed', $trade->status);
        self::assertEqualsWithDelta($departureStock + $quote['received'], $this->stock($fallback->id, 'nano'), .0001);
        self::assertSame($conquerorStock, $this->stock($origin->id, 'nano'));
    }

    public function testCompetingPlayersCannotReserveLastLotTwice(): void
    {
        [$first, $firstPlanet] = $this->fixture('MarketRaceFirst');
        [$second, $secondPlanet] = $this->fixture('MarketRaceSecond');
        $firstMember = $first->currentPlayerUniverse;
        $secondMember = $second->currentPlayerUniverse;
        $universe = $firstMember->universe;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($first, $universe, true, $this->targets());
        $markets = self::getContainer()->get(ResourceMarketRepository::class);
        $convoys = self::getContainer()->get(MarketConvoys::class);
        $input = ['pay' => 'metal', 'receive' => 'nano', 'quantity' => 1000, 'rank' => 'basic', 'cargos' => 1];
        $quotes = [$convoys->preview($firstMember, $firstPlanet, $input), $convoys->preview($secondMember, $secondPlanet, $input)];
        $market = $markets->find($universe);
        $market->stock['nano'] = $quotes[0]['received'];
        $this->em->flush();

        $barrier = tempnam(sys_get_temp_dir(), 'market-race-');
        unlink($barrier);
        $ready = [$barrier.'.first', $barrier.'.second'];
        $processes = [];
        try {
            foreach ([[$firstMember, $firstPlanet], [$secondMember, $secondPlanet]] as $i => [$member, $planet]) {
                $process = new Process([
                    PHP_BINARY, dirname(__DIR__).'/Support/concurrent_market_departure.php',
                    json_encode([
                        'member' => $member->id, 'planet' => $planet->id, 'quote' => $quotes[$i],
                        'key' => str_repeat((string) ($i + 1), 32), 'ready' => $ready[$i], 'barrier' => $barrier,
                    ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
                ], timeout: 20);
                $process->start();
                $processes[] = $process;
            }

            $deadline = microtime(true) + 12;
            while (!is_file($ready[0]) || !is_file($ready[1])) {
                if (microtime(true) >= $deadline) {
                    self::fail('Les deux connexions concurrentes ne sont pas prêtes.');
                }
                usleep(10000);
            }
            self::assertNotSame(file_get_contents($ready[0]), file_get_contents($ready[1]));
            touch($barrier);
            $answers = [];
            foreach ($processes as $process) {
                self::assertSame(0, $process->wait(), $process->getErrorOutput());
                $answers[] = trim($process->getOutput());
            }
            sort($answers);
            self::assertSame(['Le stock disponible de la station ne couvre pas ce lot. Réduisez votre échange.', 'accepted'], $answers);
            self::assertCount(1, $this->em->getRepository(MarketTrade::class)->findAll());
            self::assertSame($quotes[0]['received'], $markets->reserved($universe)['outgoing']['nano']);
            $paid = [$this->stock($firstPlanet->id, 'metal'), $this->stock($secondPlanet->id, 'metal')];
            self::assertLessThan(10000, min($paid), 'Un seul acheteur a payé son lot.');
            self::assertSame(10000.0, max($paid), 'L’acheteur refusé garde tout son fret.');
        } finally {
            foreach ($processes as $process) {
                $process->stop(0.5);
            }
            foreach ([$barrier, ...$ready] as $path) {
                if (is_file($path)) {
                    unlink($path);
                }
            }
        }
    }

    private function targets(): array
    {
        return array_fill_keys(array_keys(MarketPricing::WEIGHTS), 50_000);
    }

    private function fixture(string $name, bool $admin = false): array
    {
        $registration = new RegistrationData();
        $registration->username = $name;
        $registration->email = strtolower($name).'@example.test';
        $registration->plainPassword = bin2hex(random_bytes(16)).'Aa!';
        $player = self::getContainer()->get(RegistrationService::class)->register($registration);
        if ($admin) {
            $player->roles = ['ROLE_ADMIN'];
            $this->em->flush();
        }
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $member = $player->currentPlayerUniverse;
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $member]);
        $idle = new Fleet();
        $idle->playerUniverse = $member;
        $idle->homePlanet = $planet;
        $this->em->persist($idle);
        $ship = new FleetShip();
        $ship->fleet = $idle;
        $ship->slug = 'transport';
        $ship->quantity = 2;
        $this->em->persist($ship);
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $planet]) as $resource) {
            if (in_array($resource->resourceType, ['metal', 'hydrogen'], true)) {
                $resource->currentStock = 10000;
            } elseif ($resource->resourceType === 'nano') {
                $resource->currentStock = 1000;
            }
            $resource->lastUpdatedAt = $this->clock->now();
        }
        $this->em->flush();

        return [$player, $planet, $ship];
    }

    private function stock(int $planet, string $resource): float
    {
        return (float) $this->em->getConnection()->fetchOne('SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = ?', [$planet, $resource]);
    }

    /** Le relais public doit vraiment produire le message dans le transport async livré. */
    private function relay(int $tradeId): array
    {
        $transport = self::getContainer()->get('messenger.transport.async');
        $transport->reset();
        self::getContainer()->get(OutboxRelay::class)->run();
        $messages = array_filter($transport->getSent(), static fn ($envelope): bool => $envelope->getMessage() instanceof MarketConvoyDue
            && $envelope->getMessage()->tradeId === $tradeId);
        self::assertCount(1, $messages);
        $envelope = reset($messages);
        return ['message' => $envelope->getMessage(), 'delay' => $envelope->last(DelayStamp::class)->getDelay()];
    }
}
