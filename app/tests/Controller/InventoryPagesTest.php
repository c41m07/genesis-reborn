<?php

namespace App\Tests\Controller;

use App\Entity\{Fleet, Planet, PlanetResource, Player, PlayerBoost, Universe};
use App\Service\Boost\InventoryPageView;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class InventoryPagesTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Player $owner;
    private Planet $home;
    private Player $other;
    private Player $foreign;
    private array $boxes = [];
    private array $pending = [];

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);$schema->createSchema($metadata);
        static::getContainer()->set('clock', new MockClock('2026-11-16T12:00:00Z'));
        static::getContainer()->get(UniverseImporter::class)->import();
        $this->owner = $this->player('InventoryOwner');
        $this->other = $this->player('InventoryOther');
        $this->foreign = $this->player('InventoryForeign', 'turbo-1');
        $this->home = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $this->owner->currentPlayerUniverse]);
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $this->home]) as $resource) {
            $resource->currentStock = 0;$resource->lastUpdatedAt = new \DateTimeImmutable('2026-11-16T12:00:00Z');
        }
        $payload = ['format' => 'small', 'label' => 'Boîte privée', 'resources' => ['metal' => 4000, 'nano' => 0, 'hydrogen' => 0, 'antimatter' => 0, 'electricity' => 0]];
        for ($i=0;$i<2;$i++) $this->item($this->owner, 'available');
        for ($i=0;$i<3;$i++) $this->boxes[] = $this->item($this->owner, 'available', $payload);
        for ($i=0;$i<28;$i++) $this->pending[] = $this->item($this->owner, 'pending', $i===0 ? $payload : null);
        for ($i=0;$i<5;$i++) $this->item($this->owner, 'used', ['label' => 'Historique utilisé']);
        $this->item($this->other, 'pending', ['label' => 'Autre commandant']);
        $this->item($this->foreign, 'available', ['label' => 'Autre univers']);
        $this->em->flush();$this->client->loginUser($this->owner);
    }

    private function player(string $name, string $slug = 'omega-1'): Player
    {
        $data = new RegistrationData();$data->username = $name;$data->email = strtolower($name).'@example.test';$data->plainPassword = 'Inventory-test-2026!';
        $player = static::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
        static::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        return $player;
    }

    private function item(Player $owner, string $status, ?array $payload = null): PlayerBoost
    {
        $item = new PlayerBoost();$item->playerUniverse = $owner->currentPlayerUniverse;
        $item->sourceType = 'event';$item->sourceKey = bin2hex(random_bytes(16));
        $item->status = $status;$item->earnedAt = new \DateTimeImmutable('2026-11-15T12:00:00Z');
        $item->kind = $payload === null ? 'boost' : 'resource_box';$item->payload = $payload;
        $this->em->persist($item);return $item;
    }

    public function testDirectPagesHaveTheirOwnContentAndKeepSharedCapacityAndActiveNavigation(): void
    {
        foreach (['/boosts'=>'Boosts','/inventaire/boites'=>'Boîtes de ressources','/inventaire/recompenses'=>'Récompenses en attente'] as $url=>$title) {
            $this->client->request('GET',$url);self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1',$title);self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
            self::assertSelectorExists('nav[aria-label="Navigation de l’inventaire"] a[href="'.$url.'"][aria-current="page"]');
            self::assertSelectorExists('.sidebar a[href="/boosts"].active');
            self::assertSelectorExists('progress[value="5"][max="100"]');
            self::assertSelectorTextContains('nav[aria-label="Navigation de l’inventaire"] a[href="/boosts"]','2');
            self::assertSelectorTextContains('nav[aria-label="Navigation de l’inventaire"] a[href="/inventaire/boites"]','3');
            self::assertSelectorTextContains('nav[aria-label="Navigation de l’inventaire"] a[href="/inventaire/recompenses"]','28');
            self::assertSelectorTextNotContains('#main','Autre commandant');self::assertSelectorTextNotContains('#main','Autre univers');
            self::assertSelectorTextNotContains('#main','Historique utilisé');
            if ($url==='/boosts') {
                self::assertSelectorExists('#inventory-boosts');self::assertSelectorNotExists('#inventory-boxes, #inventory-pending');
            } elseif ($url==='/inventaire/boites') {
                self::assertSelectorCount(1,'.resource-box-card');self::assertSelectorTextContains('.resource-box-card','Quantité : 3');
                self::assertSelectorNotExists('#inventory-boosts, #inventory-pending');
            } else {
                self::assertSelectorCount(25,'[data-pending-item]');self::assertSelectorNotExists('#inventory-boosts, #inventory-boxes');
                self::assertSelectorExists('[data-pending-item="'.$this->pending[0]->id.'"]');
            }
        }
    }

    public function testPendingPageIsBoundedAndFollowsOldestFirstWithoutChangingStatuses(): void
    {
        $this->client->request('GET','/inventaire/recompenses?page=2');self::assertResponseIsSuccessful();
        self::assertSelectorCount(3,'[data-pending-item]');
        self::assertSelectorExists('[data-pending-item="'.$this->pending[25]->id.'"]');
        self::assertSelectorNotExists('[data-pending-item="'.$this->pending[0]->id.'"]');
        self::assertSelectorTextContains('nav[aria-label="Pages des récompenses en attente"]','Page 2 / 2');
        $this->client->request('GET','/inventaire/recompenses?page=999');self::assertResponseIsSuccessful();self::assertSelectorCount(3,'[data-pending-item]');
        self::assertSame(28,$this->em->getRepository(PlayerBoost::class)->count(['playerUniverse'=>$this->owner->currentPlayerUniverse,'status'=>'pending']));
    }

    public function testPagePreparationDoesNotHydrateOtherInventorySectionsOrUsedHistory(): void
    {
        $id = $this->owner->id;
        foreach (['boosts'=>0,'boxes'=>3,'pending'=>25] as $page=>$loaded) {
            $this->em->clear();$owner=$this->em->find(Player::class,$id);
            $data=static::getContainer()->get(InventoryPageView::class)->prepare($owner->currentPlayerUniverse,$page);
            self::assertSame(5,$data['inventory_counts']['available']);
            $items=$this->em->getUnitOfWork()->getIdentityMap()[PlayerBoost::class]??[];
            self::assertCount($loaded,$items,$page);
            foreach ($items as $item) self::assertNotSame('used',$item->status);
        }
    }

    public function testOpeningLegacyPostReturnsToBoxesAndKeepsCsrfAndOwnershipChecks(): void
    {
        $crawler=$this->client->request('GET','/inventaire/boites');
        $form=$crawler->filter('.resource-box-form')->form();$values=$form->getPhpValues();$values['planet_id']=$this->home->id;
        $action=$form->getUri();
        $invalid=$values;$invalid['_token']='invalid';$this->client->request('POST',$action,$invalid);self::assertResponseStatusCodeSame(403);
        $otherPlanet=$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$this->other->currentPlayerUniverse]);
        $denied=$values;$denied['planet_id']=$otherPlanet->id;$this->client->request('POST',$action,$denied);
        self::assertResponseRedirects('/inventaire/boites');self::assertSame('available',$this->em->find(PlayerBoost::class,$this->boxes[0]->id)->status);
        $this->client->followRedirect();self::assertSelectorExists('.flash.error');
        $this->client->request('POST',$action,$values);self::assertResponseRedirects('/inventaire/boites');
        $crawler=$this->client->followRedirect();self::assertSame([], $crawler->filter('.flash.error')->each(static fn($node)=>$node->text()));
        self::assertSame('used',$this->em->find(PlayerBoost::class,$this->boxes[0]->id)->status);
        $this->client->request('POST',$action,$values);self::assertResponseRedirects('/inventaire/boites');
        $this->client->followRedirect();self::assertSelectorTextContains('.flash.success','déjà été traitée');
    }

    public function testLegacyFleetBoostFormIsRefusedWithoutChangingInventoryOrFleet(): void
    {
        $fleet = new Fleet();
        $fleet->playerUniverse = $this->owner->currentPlayerUniverse;
        $fleet->homePlanet = $this->home;
        $fleet->destinationSystem = $this->home->solarSystem;
        $fleet->status = 'traveling';
        $fleet->mission = 'explore';
        $fleet->departureAt = new \DateTimeImmutable('2026-11-16T12:00:00Z');
        $fleet->arrivalAt = $fleet->departureAt->modify('+1 hour');
        $fleet->travelSeconds = 3600;
        $fleet->dispatchToken = bin2hex(random_bytes(16));
        $historical = $this->item($this->owner, 'used');
        $this->em->persist($fleet);
        $this->em->flush();
        $historical->operationType = 'fleet';
        $historical->operationId = $fleet->id;
        $historical->usedAt = $fleet->departureAt;
        $this->em->flush();

        $connection = $this->em->getConnection();
        $fleetRow = fn () => $connection->fetchAssociative('SELECT * FROM fleet WHERE id = ?', [$fleet->id]);
        $inventoryRows = fn () => $connection->fetchAllAssociative('SELECT * FROM player_boost WHERE player_universe_id = ? ORDER BY id', [$fleet->playerUniverse->id]);
        $beforeFleet = $fleetRow();
        $beforeInventory = $inventoryRows();
        $csrf = bin2hex(random_bytes(32));
        $requestKey = bin2hex(random_bytes(16));

        // Reconstituer un jeton encore valide d’un formulaire chargé avant la mise à jour.
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->setId($this->client->getCookieJar()->get($session->getName())->getValue());
        $session->set('_csrf/boost-fleet-'.$fleet->id, $csrf);
        $session->save();
        $url = '/boosts/utiliser/fleet/'.$fleet->id;
        $this->client->request('POST', $url, ['_token' => 'invalid', 'request_key' => $requestKey]);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $url, ['_token' => $csrf, 'request_key' => $requestKey]);
        self::assertResponseRedirects('/boosts');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'Les boosts de l’inventaire ne s’appliquent plus aux missions de flotte.');
        self::assertSelectorNotExists('form[action^="/boosts/utiliser/fleet/"]');
        self::assertSame($beforeFleet, $fleetRow());
        self::assertSame($beforeInventory, $inventoryRows());
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM action_receipt WHERE request_key = ?', [$requestKey]));
    }

    public function testAnonymousAccessToEveryDestinationRequiresLogin(): void
    {
        $this->client->getCookieJar()->clear();static::getContainer()->get('security.token_storage')->setToken(null);
        foreach (['/boosts','/inventaire/boites','/inventaire/recompenses'] as $url) {
            $this->client->request('GET',$url);self::assertResponseRedirects('/connexion');
        }
    }
}
