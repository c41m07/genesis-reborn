<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{Fleet, FleetShip, MarketAccount, MarketOffer, PlayerUniverse, MarketTrade, Planet, PlanetResource, Player, PlayerBoost, Universe};
use App\Message\MarketConvoyDue;
use App\MessageHandler\MarketConvoyDueHandler;
use App\Repository\ResourceMarketRepository;
use App\Service\Colony\ResourceService;
use App\Service\Market\{MarketConvoys, MarketPresentation, MarketPricing, PlayerMarket, WeeklyMerchantDelivery};
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
        $this->client->request('GET', '/flottes/empire');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.fleet-composition-table td', '2');
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
        $this->client->request('GET', '/flottes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-market-trade="'.$first->id.'"]', 'Aller');
        $this->client->request('GET', '/flottes/surveillance?galaxy='.$home->solarSystem->galaxy->id);
        self::assertResponseIsSuccessful();
        $traffic = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($first->id, $traffic['marketTraffic'][0]['id']);
        self::assertSame('Bourse · A:0:0', $traffic['marketTraffic'][0]['to']);
        self::assertStringContainsString('Convoi de Bourse', $traffic['fleetLog']);
        $message = $this->relay($first->id);
        self::assertSame(1800000, $message['delay']);
        $handler = self::getContainer()->get(MarketConvoyDueHandler::class);
        $this->clock->sleep(1800);
        $handler($message['message']);
        $handler($message['message']);
        $this->em->clear();

        $return = $this->em->find(MarketTrade::class, $first->id);
        self::assertSame('returning', $return->status);
        $traffic = self::getContainer()->get(\App\Service\Market\MarketTraffic::class)->forViewer($this->em->find(Planet::class, $home->id)->owner);
        self::assertSame('Bourse · A:0:0', $traffic[0]['from']);
        self::assertSame('Retour', $traffic[0]['label']);
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
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count(['sourceType' => 'market_overflow']));
        $this->client->request('GET', '/flottes');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-market-trade]');
        self::assertEqualsWithDelta($beforeDelivery + $closed->receiveQuantity, $this->stock($home->id, 'nano'), .0001);
        self::assertSame(2, $this->em->find(FleetShip::class, $transport->id)->quantity);
    }

    public function testTradeCanIncreaseStationStockPastTheMaximumMerchantTarget(): void
    {
        [$player, $planet] = $this->fixture('MarketBeyondTarget');
        $member = $player->currentPlayerUniverse;
        $universe = $member->universe;
        $limit = \App\Service\Market\MarketPricing::MAX_TARGET;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($player, $universe, true, [
            'metal' => $limit, 'nano' => $limit, 'hydrogen' => $limit, 'antimatter' => $limit,
        ]);
        $markets = self::getContainer()->get(ResourceMarketRepository::class);
        $convoys = self::getContainer()->get(MarketConvoys::class);
        $quote = $convoys->preview($member, $planet, [
            'pay' => 'metal', 'receive' => 'nano', 'quantity' => 300, 'rank' => 'basic', 'cargos' => 1,
        ]);
        $convoys->depart($member, $planet, $quote, str_repeat('a', 32));
        $trade = $this->em->getRepository(MarketTrade::class)->findOneBy(['membershipId' => $member->id]);
        self::assertNotNull($trade);
        self::assertSame($limit, $markets->find($universe)->stock['metal'], 'Le stock ne bouge pas avant livraison.');
        $this->clock->sleep(1800);
        $token = $trade->token;
        $convoys->processDue($trade->id, $token);
        $convoys->processDue($trade->id, $token);
        $market = $markets->find($universe);
        self::assertSame($limit + 300, $market->stock['metal']);
        self::assertSame($limit - $quote['received'], $market->stock['nano']);
        self::assertSame(-300, $market->pressure['metal']);
        self::assertSame($quote['received'], $market->pressure['nano']);
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
        $traffic = self::getContainer()->get(\App\Service\Market\MarketTraffic::class);
        $projection = $traffic->forViewer($member, $planet->solarSystem->galaxy);
        self::assertSame('Rappel', $projection[0]['label']);
        self::assertSame('Position du rappel', $projection[0]['from']);
        self::assertEqualsWithDelta(1 / 6, $projection[0]['returnFraction'], .000001);
        [$other] = $this->fixture('MarketPrivate');
        self::assertSame([], $traffic->forViewer($other->currentPlayerUniverse, $planet->solarSystem->galaxy));
        $this->clock->sleep(300);
        $convoys->processDue($trade->id, $oldToken);
        self::assertSame('returning', $trade->status);
        // Un entrepôt déjà plein envoie tout le fret rappelé dans une seule boîte.
        $metal = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet' => $planet, 'resourceType' => 'metal']);
        $metal->currentStock = $before + 10_000_000;
        $this->em->flush();
        $newToken = $trade->token;
        $convoys->processDue($trade->id, $newToken);
        $convoys->processDue($trade->id, $newToken);
        self::assertSame(2, $transport->quantity);
        self::assertArrayNotHasKey('metal', $transport->fleet->cargo);
        $boxes = $this->em->getRepository(PlayerBoost::class)->findBy(['sourceType' => 'market_overflow']);
        self::assertCount(1, $boxes);
        self::assertSame($member->id, $boxes[0]->playerUniverse->id);
        self::assertTrue($boxes[0]->giftUnread);
        self::assertEquals(['metal' => 300], $boxes[0]->payload['resources']);
        self::assertSame(0.0, $transport->fleet->storedFuel);
        self::assertNull($trade->exchangedAt);
        self::assertSame([], self::getContainer()->get(ResourceMarketRepository::class)->find($member->universe)->pressure);
    }

    public function testPartialUnloadBoxesOnlyTheRemainderAndThePlayerCanRedeemIt(): void
    {
        [$player, $planet, $ship] = $this->fixture('MarketBox');
        $member = $player->currentPlayerUniverse;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($player, $member->universe, true, $this->targets());
        $convoys = self::getContainer()->get(MarketConvoys::class);
        $quote = $convoys->preview($member, $planet, [
            'pay' => 'metal', 'receive' => 'nano', 'quantity' => 300, 'rank' => 'basic', 'cargos' => 1,
        ]);
        $convoys->depart($member, $planet, $quote, str_repeat('b', 32));
        $trade = $this->em->getRepository(MarketTrade::class)->findOneBy(['membershipId' => $member->id]);
        $handler = self::getContainer()->get(MarketConvoyDueHandler::class);
        $outbound = $this->relay($trade->id)['message'];
        $this->clock->sleep(1800);
        $handler($outbound);
        $message = $this->relay($trade->id)['message'];
        $this->clock->sleep(1800);

        // La production fractionnaire a laissé exactement 10,25 unités libres à la livraison.
        $resources = self::getContainer()->get(ResourceService::class);
        $capacity = $resources->capacity($planet, 'nano', []);
        $stock = $resources->materialize($planet)['nano'];
        $stock->currentStock = $capacity - 10.25;
        $idle = $this->em->find(Fleet::class, $ship->fleet->id);
        $idle->cargo = ['metal' => 8];
        $this->em->flush();
        $handler($message);
        $handler($message);
        $this->em->clear();

        $boxes = $this->em->getRepository(PlayerBoost::class)->findBy(['sourceType' => 'market_overflow']);
        self::assertCount(1, $boxes);
        $remainder = $trade->receiveQuantity - 10.25;
        self::assertEquals(['nano' => $remainder], $boxes[0]->payload['resources']);
        self::assertEquals($capacity, $this->stock($planet->id, 'nano'));
        self::assertEquals(['metal' => 8], $this->em->find(Fleet::class, $ship->fleet->id)->cargo);

        // Ouvrir la boîte depuis l’inventaire réel après avoir libéré de la place.
        $stock = $this->em->find(PlanetResource::class, $stock->id);
        $stock->currentStock = 0;
        $this->em->flush();
        $this->client->loginUser($this->em->find(Player::class, $player->id));
        $page = $this->client->request('GET', '/inventaire/boites');
        self::assertResponseIsSuccessful();
        $card = $page->filter('[data-box-id="'.$boxes[0]->id.'"]');
        self::assertSame('Boîte de surplus de la Bourse', $card->filter('h3')->text());
        $this->client->submit($card->selectButton('Ouvrir cette boîte')->form(['planet_id' => $planet->id]));
        self::assertResponseRedirects('/inventaire/boites');
        $this->client->followRedirect();
        self::assertSelectorNotExists('[data-box-id="'.$boxes[0]->id.'"]');
        self::assertEqualsWithDelta($remainder, $this->stock($planet->id, 'nano'), 0.000001);
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
        $presentation = self::getContainer()->get(MarketPresentation::class);
        $previousIndex = $presentation->rows($market)['nano']['index'];
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
        self::assertLessThan($previousIndex, $presentation->rows($market)['nano']['index']);
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
        $market = $markets->find($universe);
        // À ce niveau de rareté, 400 unités couvrent un seul des deux convois.
        $market->stock['nano'] = 400;
        $this->em->flush();
        $quotes = [$convoys->preview($firstMember, $firstPlanet, $input), $convoys->preview($secondMember, $secondPlanet, $input)];
        self::assertGreaterThan(200, $quotes[0]['received']);

        $answers = $this->raceMarketOrders([
            ['member' => $firstMember->id, 'planet' => $firstPlanet->id, 'quote' => $quotes[0]],
            ['member' => $secondMember->id, 'planet' => $secondPlanet->id, 'quote' => $quotes[1]],
        ]);
        self::assertSame(['Le stock disponible de la station ne couvre pas ce lot. Réduisez votre échange.', 'accepted'], $answers);
        self::assertCount(1, $this->em->getRepository(MarketTrade::class)->findAll());
        self::assertSame($quotes[0]['received'], $markets->reserved($universe)['outgoing']['nano']);
        $paid = [$this->stock($firstPlanet->id, 'metal'), $this->stock($secondPlanet->id, 'metal')];
        self::assertLessThan(10000, min($paid), 'Un seul acheteur a payé son lot.');
        self::assertSame(10000.0, max($paid), 'L’acheteur refusé garde tout son fret.');
    }

    public function testTwoBuyersCompeteForTheSameLastUnits(): void
    {
        [$seller] = $this->fixture('RaceSeller');
        [$first] = $this->fixture('RaceBuyerOne');
        [$second] = $this->fixture('RaceBuyerTwo');
        $member = $seller->currentPlayerUniverse;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($seller, $member->universe, true, $this->targets());
        $this->fundMarketAccount($member, 100, 5);
        $this->fundMarketAccount($first->currentPlayerUniverse, 1000);
        $this->fundMarketAccount($second->currentPlayerUniverse, 1000);
        self::getContainer()->get(PlayerMarket::class)->order($member, bin2hex(random_bytes(16)), 'list', 'metal', 5, '2.00', 0);
        $offer = $this->em->getRepository(MarketOffer::class)->findOneBy(['seller' => $member]);
        $answers = $this->raceMarketOrders([
            ['member' => $first->currentPlayerUniverse->id, 'offer' => $offer->id],
            ['member' => $second->currentPlayerUniverse->id, 'offer' => $offer->id],
        ]);
        self::assertSame(['Offre indisponible.', 'accepted'], $answers);
        $this->em->clear();
        $firstAccount = $this->em->find(MarketAccount::class, $first->currentPlayerUniverse->id);
        $secondAccount = $this->em->find(MarketAccount::class, $second->currentPlayerUniverse->id);
        self::assertSame(5, $firstAccount->goods['metal'] + $secondAccount->goods['metal']);
        self::assertSame(1000, $firstAccount->cents + $secondAccount->cents);
        self::assertSame(1080, $this->em->find(MarketAccount::class, $member->id)->cents);
    }

    private function raceMarketOrders(array $orders): array
    {
        $barrier = tempnam(sys_get_temp_dir(), 'market-race-');
        unlink($barrier);
        $ready = [$barrier.'.first', $barrier.'.second'];
        $processes = [];
        try {
            foreach ($orders as $i => $order) {
                $process = new Process([
                    PHP_BINARY, dirname(__DIR__).'/Support/concurrent_market_departure.php',
                    json_encode($order + [
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
            return $answers;
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

    public function testStationCargoSalesPurchasesAndPrivateDepositsUseTheirOwnAssets(): void
    {
        [$seller, $home] = $this->fixture('MarketCredits');
        $member = $seller->currentPlayerUniverse;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($seller, $member->universe, true, $this->targets());
        $playerMarket = self::getContainer()->get(PlayerMarket::class);
        $convoys = self::getContainer()->get(MarketConvoys::class);
        $station = self::getContainer()->get(ResourceMarketRepository::class)->find($member->universe);

        foreach (['sell', 'buy', 'deposit', 'collect'] as $operation) {
            $quantity = match ($operation) {
                'sell' => 200, 'buy' => 30, 'deposit' => 17, 'collect' => 5,
            };
            $input = ['operation' => $operation, 'pay' => 'metal', 'receive' => 'metal', 'quantity' => $quantity, 'rank' => 'basic', 'cargos' => 1];
            $quote = $convoys->preview($member, $home, $input);
            $beforeStock = $station->stock['metal'];
            $beforeCredits = $playerMarket->account($member)?->cents ?? 0;
            $beforeDepot = $playerMarket->account($member)?->goods['metal'] ?? 0;
            $this->clock->sleep(1);
            $key = bin2hex(random_bytes(16));
            $convoys->depart($member, $home, $quote, $key);
            $trade = $this->em->getRepository(MarketTrade::class)->findOneBy(['requestKey' => $key]);
            self::assertSame($beforeStock, $station->stock['metal']);
            self::assertSame($beforeCredits - ($operation === 'buy' ? $quote['creditCents'] : 0), $playerMarket->account($member)?->cents ?? 0);
            $this->clock->sleep(1800);
            $token = $trade->token;
            $convoys->processDue($trade->id, $token);
            $convoys->processDue($trade->id, $token);
            self::assertSame($beforeStock + match ($operation) { 'sell' => $quantity, 'buy' => -$quantity, default => 0 }, $station->stock['metal']);
            self::assertSame($beforeCredits + match ($operation) {
                'sell' => $quote['creditCents'], 'buy' => -$quote['creditCents'], default => 0,
            }, $playerMarket->account($member)->cents);
            self::assertSame($beforeDepot + match ($operation) { 'deposit' => $quantity, 'collect' => -$quantity, default => 0 },
                $playerMarket->account($member)->goods['metal'] ?? 0);
            $this->clock->sleep(1800);
            $convoys->processDue($trade->id, $trade->token);
            self::assertSame('completed', $trade->status);
        }

        foreach (['buy', 'collect'] as $operation) {
            $account = $playerMarket->account($member);
            $balance = $account->cents;
            $goods = $account->goods;
            $quote = $convoys->preview($member, $home, ['operation' => $operation, 'pay' => 'metal', 'receive' => 'metal',
                'quantity' => 1, 'rank' => 'basic', 'cargos' => 1]);
            $key = bin2hex(random_bytes(16));
            $convoys->depart($member, $home, $quote, $key);
            $trade = $this->em->getRepository(MarketTrade::class)->findOneBy(['requestKey' => $key]);
            $convoys->recall($member, $trade->id);
            $this->clock->sleep(2);
            $convoys->processDue($trade->id, $trade->token);
            self::assertSame($balance, $account->cents);
            self::assertSame($goods, $account->goods);
        }
    }

    public function testPublicListingChargesBeforePublicationThenPaysEveryPurchasedUnitInFull(): void
    {
        [$seller] = $this->fixture('MarketListingSeller');
        [$buyer] = $this->fixture('MarketListingBuyer');
        $seller->emailVerifiedAt = $this->clock->now();
        $this->em->flush();
        $member = $seller->currentPlayerUniverse;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($seller, $member->universe, true, $this->targets());
        $sellerAccount = $this->fundMarketAccount($member, 2000, 100);
        $buyerAccount = $this->fundMarketAccount($buyer->currentPlayerUniverse, 50000);
        $this->client->loginUser($seller);
        $page = $this->client->request('GET', '/bourse/joueurs?resource=metal');
        self::assertResponseIsSuccessful();
        $this->client->submit($page->selectButton('Vérifier la taxe et le produit de la vente')->form(['quantity' => 100, 'price' => '3.50']));
        $page = $this->client->followRedirect();
        self::assertSelectorTextContains('main', '350,00 crédits');
        self::assertSelectorTextContains('main', '7,00 crédits');
        self::assertSame(2000, $sellerAccount->cents);
        $this->client->submit($page->selectButton('Payer la taxe et publier')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('main', 'Retirer les invendus');
        $sellerAccount = $this->em->find(MarketAccount::class, $member->id);
        self::assertSame(1300, $sellerAccount->cents);
        self::assertSame(0, $sellerAccount->goods['metal']);

        $this->client->loginUser($buyer);
        $page = $this->client->request('GET', '/bourse/joueurs?resource=metal');
        $buy = $page->selectButton('Acheter pour mon dépôt')->form(['quantity' => 30]);
        $this->client->submit($buy);
        $this->client->submit($buy);
        $this->client->followRedirect();
        $sellerAccount = $this->em->find(MarketAccount::class, $member->id);
        $buyerAccount = $this->em->find(MarketAccount::class, $buyer->currentPlayerUniverse->id);
        self::assertSame(1300 + 10500, $sellerAccount->cents);
        self::assertSame(39500, $buyerAccount->cents);
        self::assertSame(30, $buyerAccount->goods['metal']);
        $this->client->loginUser($seller);
        $page = $this->client->request('GET', '/bourse/joueurs?resource=metal');
        $this->client->submit($page->selectButton('Retirer les invendus')->form());
        $this->client->followRedirect();
        $sellerAccount = $this->em->find(MarketAccount::class, $member->id);
        self::assertSame(70, $sellerAccount->goods['metal']);
        self::assertSame(11800, $sellerAccount->cents);
        self::assertSame(0, $this->em->getRepository(MarketOffer::class)->count([]));
        $this->clock->sleep(6 * 3600);
        $this->client->loginUser($seller);
        $this->client->request('GET', '/classement?category=credits');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.ranking-table--players tbody tr:first-child', 'MarketListingBuyer');
        self::assertSelectorTextContains('[data-category="credits"]', '395,00');
    }

    public function testInsufficientListingCreditsLeaveGoodsAndOrdersIntact(): void
    {
        [$seller] = $this->fixture('MarketListingRefused');
        $member = $seller->currentPlayerUniverse;
        self::getContainer()->get(WeeklyMerchantDelivery::class)->configure($seller, $member->universe, true, $this->targets());
        $this->fundMarketAccount($member, 699, 100);
        $db = $this->em->getConnection();
        try {
            self::getContainer()->get(PlayerMarket::class)->order($member, bin2hex(random_bytes(16)), 'list', 'metal', 100, '3.50', 0);
            self::fail('Le paiement des frais doit précéder la publication.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('crédits', $error->getMessage());
        }
        self::assertSame(699, (int) $db->fetchOne('SELECT cents FROM market_account WHERE membership_id = ?', [$member->id]));
        self::assertSame(100, json_decode($db->fetchOne('SELECT goods FROM market_account WHERE membership_id = ?', [$member->id]), true)['metal']);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM market_offer'));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('forbiddenOrders')]
    public function testOfferOwnershipAndUniverseAreChecked(string $action, bool $foreignUniverse): void
    {
        [$seller] = $this->fixture('OrderSeller');
        [$other] = $this->fixture('OrderVisitor');
        $member = $seller->currentPlayerUniverse;
        $stations = self::getContainer()->get(WeeklyMerchantDelivery::class);
        $stations->configure($seller, $member->universe, true, $this->targets());
        $this->fundMarketAccount($member, 100, 5);
        $players = self::getContainer()->get(PlayerMarket::class);
        $players->order($member, bin2hex(random_bytes(16)), 'list', 'metal', 5, '2.00', 0);
        $markets = self::getContainer()->get(ResourceMarketRepository::class);
        $offer = $markets->offers($member, 'metal', 1)[0];
        if ($foreignUniverse) {
            $world = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
            $world->startsAt = $world->endsAt = null;
            $this->em->flush();
            self::getContainer()->get(UniverseService::class)->select($other, $world->id);
            $stations->configure($other, $world, true, $this->targets());
            self::assertSame([], $markets->offers($other->currentPlayerUniverse, 'metal', 1));
        }
        $visitor = $other->currentPlayerUniverse;
        $this->fundMarketAccount($visitor, 1000);
        $db = $this->em->getConnection();
        try {
            $players->order($visitor, bin2hex(random_bytes(16)), $action, 'metal', 1, '', $offer->id);
            self::fail('L’ordre aurait dû être refusé.');
        } catch (\DomainException $error) {
            self::assertStringContainsString('indisponible', $error->getMessage());
        }
        self::assertSame(5, (int) $db->fetchOne('SELECT remaining FROM market_offer WHERE id = ?', [$offer->id]));
        self::assertSame(1000, (int) $db->fetchOne('SELECT cents FROM market_account WHERE membership_id = ?', [$visitor->id]));
    }

    public static function forbiddenOrders(): array
    {
        return [['cancel', false], ['purchase', true]];
    }

    private function fundMarketAccount(PlayerUniverse $member, int $cents, int $metal = 0): MarketAccount
    {
        $account = new MarketAccount();
        $account->membership = $member;
        $account->cents = $cents;
        $account->goods = ['metal' => $metal];
        $this->em->persist($account);
        $this->em->flush();
        return $account;
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
