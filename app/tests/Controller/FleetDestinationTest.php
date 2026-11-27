<?php
namespace App\Tests\Controller;

use App\Entity\{AllianceContract,Fleet,FleetShip,Planet,Player,PlayerDiscovery,PlayerUniverse,Universe};
use App\Repository\GameRepository;
use App\Service\Fleet\DestinationCatalogue;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class FleetDestinationTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Player $player;
    private Planet $home;
    private Planet $own;
    private Planet $ally;
    private Planet $enemy;
    private Planet $neutral;
    private Planet $hidden;
    private Planet $foreign;
    private Planet $npc;
    private Fleet $idle;

    protected function setUp(): void
    {
        $this->client=static::createClient([],['HTTP_HOST'=>'genesis-reborn.localhost']);$this->client->disableReboot();
        $this->em=static::getContainer()->get(EntityManagerInterface::class);$params=$this->em->getConnection()->getParams();
        if(($params['host']??'')!=='database-test' || ($params['dbname']??'')!=='genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema=new SchemaTool($this->em);$metadata=$this->em->getMetadataFactory()->getAllMetadata();$schema->dropSchema($metadata);$schema->createSchema($metadata);
        static::getContainer()->set('clock',new \Symfony\Component\Clock\MockClock());
        static::getContainer()->get(UniverseImporter::class)->import();
        [$this->player,$this->home]=$this->player('DestOwner');
        [$ally,$this->ally]=$this->player('DestAlly');[$enemy,$this->enemy]=$this->player('DestEnemy');
        [, $this->neutral]=$this->player('DestNeutral');
        $this->neutral->owner->player->preferences['transportReception']='all';
        // Une ancienne planète PNJ peut conserver son drapeau après capture par un joueur neutre.
        $this->neutral->npcPlanet=true;[$hidden,$oldHidden]=$this->player('SecretUnseen');
        [, $this->foreign]=$this->player('ForeignGalaxy','turbo-1');
        foreach([[$ally,'accepted'],[$enemy,'enemies']] as [$other,$status]) {
            $contract=new AllianceContract();$contract->playerA=$this->player->currentPlayerUniverse;$contract->playerB=$other->currentPlayerUniverse;
            $contract->proposer=$this->player->currentPlayerUniverse;$contract->status=$status;$this->em->persist($contract);
        }
        $repo=static::getContainer()->get(GameRepository::class);$member=$this->player->currentPlayerUniverse;
        foreach($repo->planets($member->universe,null,true) as $planet) if(!$planet->npcPlanet) {
            $this->own=$planet;$planet->name='Colonie connue';$planet->acquireBy($member,new \DateTimeImmutable());break;
        }
        foreach($repo->planets($member->universe,null,true) as $planet) if($planet->npcPlanet) {$this->npc=$planet;break;}
        foreach([$this->own,$this->ally,$this->enemy,$this->neutral,$this->npc] as $planet) $this->discover($planet);
        $known=array_column($repo->all(PlayerDiscovery::class,['playerUniverse'=>$member]),'solarSystem');
        $known=array_map(static fn($system)=>$system->id,$known);
        foreach($repo->planets($member->universe,null,true) as $planet) if(!$planet->npcPlanet && !in_array($planet->solarSystem->id,$known,true)) {
            $oldHidden->owner=null;$oldHidden->acquiredAt=null;$planet->acquireBy($hidden->currentPlayerUniverse,new \DateTimeImmutable());$this->hidden=$planet;break;
        }
        $this->idle=$this->em->getRepository(Fleet::class)->findOneBy(['playerUniverse'=>$member,'homePlanet'=>$this->home,'status'=>'idle'])??new Fleet();
        $this->idle->playerUniverse=$member;$this->idle->homePlanet=$this->home;$this->em->persist($this->idle);
        foreach(['probe','fighter','transport'] as $slug) {
            $ship=$this->em->getRepository(FleetShip::class)->findOneBy(['fleet'=>$this->idle,'slug'=>$slug,'tier'=>'basic'])??new FleetShip();
            $ship->fleet=$this->idle;$ship->slug=$slug;$ship->quantity=10;$this->em->persist($ship);
        }
        $this->em->flush();$this->client->loginUser($this->player);
    }

    private function player(string $name,string $slug='omega-1'): array
    {
        $data=new RegistrationData();$data->email=strtolower($name).'@example.test';$data->username=$name;$data->plainPassword=bin2hex(random_bytes(16)).'aA1!';
        $player=static::getContainer()->get(RegistrationService::class)->register($data);
        $universe=$this->em->getRepository(Universe::class)->findOneBy(['slug'=>$slug]);
        static::getContainer()->get(UniverseService::class)->select($player,$universe->id);
        return [$player,$this->em->getRepository(Planet::class)->findOneBy(['owner'=>$player->currentPlayerUniverse])];
    }

    private function discover(Planet $planet): void
    {
        $criteria=['playerUniverse'=>$this->player->currentPlayerUniverse,'solarSystem'=>$planet->solarSystem];
        if($this->em->getRepository(PlayerDiscovery::class)->findOneBy($criteria)) return;
        $discovery=new PlayerDiscovery();$discovery->playerUniverse=$criteria['playerUniverse'];$discovery->solarSystem=$planet->solarSystem;$this->em->persist($discovery);$this->em->flush();
    }

    public function testQuickExplorationUsesTheOnlyAvailableIntermediateProbeFromEitherMap(): void
    {
        $probe = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $this->idle, 'slug' => 'probe']);
        $probe->tier = 'intermediate';
        $probe->quantity = 2;
        $this->em->flush();
        $this->client->request('GET', '/planetes/' . $this->home->id);
        $target = $this->hidden->solarSystem;
        $label = static::getContainer()->get(\App\Service\Catalog\GameCatalog::class)
            ->getTierForUniverse($this->player->currentPlayerUniverse->universe, 'ships', 'probe', 'intermediate')['label'];

        foreach (['/carte-v2', '/carte'] as $url) {
            $map = $this->client->request('GET', $url, ['galaxy' => $target->galaxy->id, 'system' => $target->id]);
            self::assertResponseIsSuccessful();
            $forms = $this->quickMissionForms($map, 'explore', $target->id);
            self::assertCount(1, $forms);
            self::assertSame('intermediate', $forms->filter('input[name="ship_tier"]')->attr('value'));
            self::assertStringContainsString($label . ' · 2 disponibles', $forms->text());
        }
        $this->client->submit($forms->form());
        self::assertResponseRedirects('/carte?galaxy=' . $target->galaxy->id . '&system=' . $target->id);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', $label . ' envoyée en exploration.');
        $sent = $this->em->getRepository(Fleet::class)->findOneBy(['mission' => 'explore', 'status' => 'traveling']);
        self::assertNotNull($sent);
        $ship = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $sent, 'slug' => 'probe']);
        self::assertSame('intermediate', $ship->tier);
        self::assertSame(1, $ship->quantity);
        self::assertSame(1, $this->quickMissionInventory($probe->id));
    }

    public function testQuickEspionageKeepsIndependentRanksAndRejectsAStaleQuantityWithoutDebit(): void
    {
        $basic = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $this->idle, 'slug' => 'probe']);
        $superior = new FleetShip();
        $superior->fleet = $this->idle;
        $superior->slug = 'probe';
        $superior->tier = 'superior';
        $superior->quantity = 3;
        $this->em->persist($superior);
        $this->em->flush();
        $this->client->request('GET', '/planetes/' . $this->home->id);
        $target = $this->enemy;
        $url = '/carte?galaxy=' . $target->solarSystem->galaxy->id . '&system=' . $target->solarSystem->id;
        $forms = $this->quickMissionForms($this->client->request('GET', $url), 'spy', $target->id);
        self::assertResponseIsSuccessful();
        self::assertCount(2, $forms);
        self::assertSame(['basic', 'superior'], $forms->filter('input[name="ship_tier"]')->extract(['value']));
        self::assertSame(['10', '3'], $forms->filter('input[name="probe_quantity"]')->extract(['max']));
        $oldForm = $forms->last()->form(['probe_quantity' => 3]);

        // Une autre action peut consommer une sonde entre l’affichage du formulaire et le départ.
        // Doctrine détache les entités entre deux requêtes KernelBrowser : utiliser l'identifiant persistant.
        $this->em->getConnection()->update('fleet_ship', ['quantity' => 2], ['id' => $superior->id]);
        $before = $this->quickMissionFuel();
        $this->client->submit($oldForm);
        self::assertResponseRedirects($url);
        $updatedMap = $this->client->followRedirect();
        self::assertSelectorTextContains('body', 'La composition n’est plus disponible exactement.');
        self::assertSame(0, $this->em->getRepository(Fleet::class)->count(['mission' => 'spy', 'status' => 'traveling']));
        self::assertSame($before, $this->quickMissionFuel());
        self::assertSame(2, $this->quickMissionInventory($superior->id));
        $newForm = $this->quickMissionForms($updatedMap, 'spy', $target->id)->last();
        self::assertSame('2', $newForm->filter('input[name="probe_quantity"]')->attr('max'));
        $this->client->submit($newForm->form(['probe_quantity' => 1]));
        self::assertResponseRedirects($url);
        $sent = $this->em->getRepository(Fleet::class)->findOneBy(['mission' => 'spy', 'status' => 'traveling']);
        self::assertNotNull($sent);
        $ship = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $sent, 'slug' => 'probe']);
        self::assertSame('superior', $ship->tier);
        self::assertSame(1, $ship->quantity);
        self::assertSame(1, $this->quickMissionInventory($superior->id));
        self::assertSame(10, $this->quickMissionInventory($basic->id));
    }

    public function testExplorationChecksNewDiscoveryBeforeDispatchInBothFleetModesAndTheMap(): void
    {
        $target = $this->hidden->solarSystem;
        $this->client->request('GET', '/planetes/' . $this->home->id);
        $url = '/carte?galaxy=' . $target->galaxy->id . '&system=' . $target->id;
        $map = $this->client->request('GET', $url);
        $quickForm = $this->quickMissionForms($map, 'explore', $target->id)->form();
        $prepare = $this->client->request('GET', '/flottes/preparer', ['mission' => 'explore', 'origin' => $this->home->id]);
        $form = $prepare->filter('form[action="/flottes/envoyer"]');
        self::assertCount(1, $form);
        $params = [
            '_token' => $form->filter('input[name="_token"]')->attr('value'),
            'origin' => $this->home->id,
            'mission' => 'explore',
            'ratio' => '0.5',
            'ships' => ['probe' => ['basic' => 1]],
            'coordinates' => $target->galaxy->coordinateCode() . ':' . $target->number(),
            'unexplored_destination' => $target->id,
        ];
        foreach (['custom', 'unexplored'] as $mode) {
            $this->client->request('POST', '/flottes/estimer', $params + ['destination_mode' => $mode]);
            self::assertResponseIsSuccessful();
        }

        $this->em->getConnection()->insert('player_discovery', [
            'player_universe_id' => $this->player->currentPlayerUniverse->id,
            'solar_system_id' => $target->id,
        ]);
        $before = $this->quickMissionFuel();
        foreach (['custom', 'unexplored'] as $mode) {
            $this->client->request('POST', '/flottes/estimer', $params + ['destination_mode' => $mode]);
            self::assertResponseStatusCodeSame(422);
            self::assertSame(DestinationCatalogue::UNEXPLORED_SYSTEM_UNAVAILABLE,
                json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['error']);
            $this->client->request('POST', '/flottes/envoyer', $params + [
                'destination_mode' => $mode, 'request_key' => bin2hex(random_bytes(16)),
            ]);
            self::assertResponseRedirects('/flottes/preparer?mission=explore', 302);
            $this->client->followRedirect();
            self::assertSelectorTextContains('body', DestinationCatalogue::UNEXPLORED_SYSTEM_UNAVAILABLE);
        }
        $this->client->submit($quickForm);
        self::assertResponseRedirects($url);
        $this->client->followRedirect();
        self::assertSelectorTextContains('body', DestinationCatalogue::UNEXPLORED_SYSTEM_UNAVAILABLE);
        self::assertSame(0, $this->em->getRepository(Fleet::class)->count(['mission' => 'explore', 'status' => 'traveling']));
        self::assertSame($before, $this->quickMissionFuel());
        self::assertSame(10, $this->em->getRepository(FleetShip::class)->findOneBy([
            'fleet' => $this->idle, 'slug' => 'probe', 'tier' => 'basic',
        ])->quantity);
    }

    private function quickMissionForms(\Symfony\Component\DomCrawler\Crawler $map, string $mission, int $target): \Symfony\Component\DomCrawler\Crawler
    {
        return $map->filter('.map-quick-mission')->reduce(static function ($form) use ($mission, $target): bool {
            return $form->filter('input[name="mission"]')->attr('value') === $mission
                && $form->filter('input[name="target"]')->attr('value') === (string) $target;
        });
    }

    private function quickMissionFuel(): float
    {
        return (float) $this->em->getConnection()->fetchOne(
            'SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = ?',
            [$this->home->id, 'hydrogen'],
        );
    }

    private function quickMissionInventory(int $shipId): int
    {
        return (int) $this->em->getConnection()->fetchOne('SELECT quantity FROM fleet_ship WHERE id = ?', [$shipId]);
    }

    public function testDedicatedPagesExposeOnlyTheirOwnRegionsAndActiveNavigation(): void
    {
        foreach ([
            '/flottes'=>['Missions actives','active-flights'],
            '/flottes/preparer'=>['Préparer une mission','available-fleets'],
            '/flottes/orbites'=>['Demandes d’orbite','orbit-requests'],
            '/flottes/quai'=>['À quai et stationnements','fleet-holds'],
        ] as $url=>[$title,$region]) {
            $this->client->request('GET',$url);self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1',$title);
            self::assertSelectorExists('nav[aria-label="Navigation des flottes"] a[href="'.$url.'"][aria-current="page"]');
            self::assertSelectorExists('.sidebar a[href="/flottes"].active');
            self::assertSelectorCount(1,'[data-live-region="'.$region.'"]');
            foreach (['active-flights','available-fleets','orbit-requests','fleet-holds'] as $other) {
                if ($other!==$region) self::assertSelectorNotExists('[data-live-region="'.$other.'"]');
            }
            $this->client->request('GET',$url,[],[],['HTTP_X_GENESIS_LIVE'=>'1','HTTP_ACCEPT'=>'application/json']);
            self::assertResponseIsSuccessful();
            $payload=json_decode($this->client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);
            $expected=['colony-selector','resources','fleet-counts',$region];
            if ($url==='/flottes') $expected[]='mission-pagination';
            if ($url==='/flottes/preparer') $expected[]='mission-summary';
            self::assertSame($expected,array_keys($payload['regions']));
        }
    }

    public function testColonySelectorKeepsTheCurrentFleetPageIncludingAfterLiveRefresh(): void
    {
        $homeId = $this->home->id;
        $otherId = $this->own->id;
        $live = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_GENESIS_LIVE' => '1'];
        foreach ([
            '/flottes' => 'Missions actives',
            '/flottes/preparer' => 'Préparer une mission',
            '/flottes/orbites' => 'Demandes d’orbite',
            '/flottes/quai' => 'À quai et stationnements',
        ] as $url => $title) {
            $this->client->request('GET', '/planetes/'.$homeId);
            $crawler = $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            // Soumettre le formulaire réellement rendu dans le menu, y compris sans JavaScript.
            $selectedForm = $crawler->filter('[data-live-region="colony-selector"] form')->form();
            self::assertSame($url, parse_url($selectedForm->getUri(), PHP_URL_PATH));
            $this->client->submit($selectedForm, ['origin' => (string) $otherId]);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', $title);
            self::assertSelectorTextContains('.fleet-colony-scope', $this->own->name);
            self::assertSelectorExists('.fleet-page-layout[data-fleet-colony="'.$otherId.'"]');
            self::assertSelectorExists('#sidebar-colony option[value="'.$otherId.'"][selected]');
            self::assertSelectorExists('[data-live-region="resources"][data-resource-strip-url-value="/planetes/'.$otherId.'/ressources"]');

            $this->client->request('GET', $url, ['origin' => $otherId], server: $live);
            self::assertResponseIsSuccessful();
            $regions = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['regions'];
            $fragment = new \Symfony\Component\DomCrawler\Crawler($regions['colony-selector']);
            self::assertCount(1, $fragment->filter('form[action="'.$url.'"] #sidebar-colony[name="origin"] option[value="'.$otherId.'"][selected]'));

            $this->client->request('GET', $url, ['origin' => $this->foreign->id]);
            self::assertResponseStatusCodeSame(404);
            $this->client->request('GET', '/');
            self::assertResponseRedirects('/planetes/'.$otherId);
        }
    }

    public function testLegacyUrlsPreservePreparationAndRejectForeignColonies(): void
    {
        $this->client->request('GET','/planetes/'.$this->home->id);
        $this->client->request('GET','/flottes',['origin'=>$this->own->id]);self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.fleet-colony-scope',$this->own->name);
        $this->client->request('GET','/planetes/'.$this->home->id);
        $query=['mission'=>'transport','planet'=>$this->ally->id];
        $this->client->request('GET','/flottes',$query);
        self::assertResponseRedirects('/flottes/preparer?'.http_build_query($query));
        $this->client->followRedirect();self::assertResponseIsSuccessful();
        self::assertSelectorExists('select[name="mission"] option[value="transport"][selected]');
        self::assertSelectorExists('input[name="coordinates"][value="'.$this->ally->coordinates().'"]');
        $this->client->request('GET','/flottes',['colony'=>$this->own->id]);
        self::assertResponseRedirects('/flottes/orbites?colony='.$this->own->id);
        $this->client->followRedirect();self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.fleet-colony-scope',$this->own->name);
        foreach (['/flottes/preparer','/flottes/orbites','/flottes/quai'] as $url) {
            foreach ([$this->enemy,$this->foreign] as $planet) {
                $this->client->request('GET',$url,['colony'=>$planet->id]);self::assertResponseStatusCodeSame(404);
            }
        }
        $this->client->request('GET','/flottes/compositions',['edit'=>999999]);
        self::assertResponseRedirects('/flottes/compositions/999999/modifier');
        $this->client->followRedirect();self::assertResponseStatusCodeSame(404);
    }

    public function testMissionsAreBoundedAndDirectLinksLocateTheCorrectPage(): void
    {
        $member = $this->player->currentPlayerUniverse;
        $universes = static::getContainer()->get(UniverseService::class);
        $universes->select($this->player, $this->foreign->owner->universe->id);
        $otherMembership = $this->player->currentPlayerUniverse;
        $otherHome = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $otherMembership]);
        $universes->select($this->player, $member->universe->id);

        $now = new \DateTimeImmutable();
        $arrives = $now->modify('+1 day');
        $create = function (PlayerUniverse $owner, Planet $origin, Planet $destination, string $status = 'traveling') use ($now, $arrives): Fleet {
            $flight = new Fleet();
            $flight->playerUniverse = $owner;
            $flight->homePlanet = $origin;
            $flight->destination = $destination;
            $flight->mission = 'transport';
            $flight->status = $status;
            $flight->departureAt = $now;
            $flight->arrivalAt = $arrives;
            $this->em->persist($flight);
            return $flight;
        };
        $flights = [];
        for ($i = 0; $i < 32; ++$i) {
            $flights[] = $create($member, $i % 2 === 0 ? $this->home : $this->own, $this->enemy);
        }
        // Le retour appartient toujours à cet empire, mais l’origine n’est plus à lui.
        $lostOrigin = $create($member, $this->neutral, $this->enemy, 'returning');
        $lostOrigin->returnPlanet = $this->home;
        $foreignPlayer = $create($this->ally->owner, $this->ally, $this->enemy);
        $foreignUniverse = $create($otherMembership, $otherHome, $this->foreign);
        $this->em->flush();

        $activeIds = static fn (\Symfony\Component\DomCrawler\Crawler $page): array => $page
            ->filter('[data-active-fleet]')
            ->each(static fn ($row): int => (int) $row->attr('data-active-fleet'));
        $ids = static fn (array $rows): array => array_map(static fn (Fleet $item): int => $item->id, $rows);
        $pageOne = $ids(array_slice($flights, 0, 30));
        $pageTwo = [$flights[30]->id, $flights[31]->id, $lostOrigin->id];

        foreach ([$this->home, $this->own] as $colony) {
            $this->client->request('GET', '/planetes/'.$colony->id);
            $full = $this->client->request('GET', '/flottes');
            self::assertResponseIsSuccessful();
            self::assertSame($pageOne, $activeIds($full));
            self::assertSelectorTextSame('[data-live-region="active-flights"] h2 .badge', '33');
            self::assertSelectorTextContains('[data-live-region="active-flights"]', 'Toutes vos colonies · univers actif');
            self::assertSelectorTextContains('[data-live-region="mission-pagination"]', 'Page 1 / 2');

            $query = ['fleet' => $flights[31]->id];
            $focused = $this->client->request('GET', '/flottes', $query);
            self::assertResponseIsSuccessful();
            self::assertSame($pageTwo, $activeIds($focused));
            self::assertSelectorTextContains('[data-live-region="mission-pagination"]', 'Page 2 / 2');

            $this->client->request('GET', '/flottes', $query, [], ['HTTP_X_GENESIS_LIVE' => '1', 'HTTP_ACCEPT' => 'application/json']);
            self::assertResponseIsSuccessful();
            $regions = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['regions'];
            $fragment = new \Symfony\Component\DomCrawler\Crawler($regions['active-flights']);
            self::assertSame($pageTwo, $activeIds($fragment));
            self::assertStringContainsString('Toutes vos colonies · univers actif', $regions['active-flights']);
            self::assertStringContainsString('Page 2 / 2', $regions['mission-pagination']);

            $this->client->request('GET', '/flottes/preparer');
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(3, '[data-live-region="mission-summary"] li');
            self::assertSelectorTextSame('[data-live-region="mission-summary"] .badge', '16');
        }
        foreach ([$foreignPlayer, $foreignUniverse] as $inaccessible) {
            $result = $this->client->request('GET', '/flottes', ['fleet' => $inaccessible->id]);
            self::assertResponseIsSuccessful();
            self::assertSame($pageOne, $activeIds($result));
        }
    }

    public function testFleetRefreshReusesTheFullPageRegionsWithoutChangingTheResourceStrip(): void
    {
        $normalize = static function(string $html): string {
            $crawler = new \Symfony\Component\DomCrawler\Crawler($html);
            $crawler->filter('input[name="request_key"], input[name="_token"]')->each(static function($input): void {
                self::assertNotEmpty($input->attr('value'));
                $input->getNode(0)->removeAttribute('value');
            });
            return trim($crawler->filter('[data-live-region]')->html());
        };
        foreach (['explore', 'spy'] as $mission) {
            $full = $this->client->request('GET', '/flottes/preparer', ['mission'=>$mission]);
            self::assertResponseIsSuccessful();
            $regions = [];
            foreach (['colony-selector', 'resources', 'fleet-counts', 'available-fleets', 'mission-summary'] as $name) {
                $node = $full->filter('[data-live-region="'.$name.'"]');
                self::assertCount(1, $node, $name);
                $regions[$name] = $node->outerHtml();
            }
            $this->client->request('GET', '/flottes/preparer', ['mission'=>$mission], [], ['HTTP_X_GENESIS_LIVE'=>'1', 'HTTP_ACCEPT'=>'application/json']);
            self::assertResponseIsSuccessful();
            self::assertResponseHeaderSame('Content-Type', 'application/json');
            $payload = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
            self::assertSame(array_keys($regions), array_keys($payload['regions']));
            foreach ($regions as $name=>$html) {
                self::assertSame($normalize($html), $normalize($payload['regions'][$name]), $name.' / '.$mission);
            }
            self::assertStringNotContainsString('<!DOCTYPE', implode('', $payload['regions']));
            self::assertStringNotContainsString($this->hidden->name, $payload['regions']['available-fleets']);
        }
    }

    public function testLiveFragmentsKeepTheirColonyWithoutReplacingTheSessionSelection(): void
    {
        $oldColony = $this->home;
        $selectedColony = $this->own;
        // La préparation n’affiche un formulaire de départ que s’il existe un vaisseau disponible.
        $stationaryFleet = new Fleet();
        $stationaryFleet->playerUniverse = $this->player->currentPlayerUniverse;
        $stationaryFleet->homePlanet = $selectedColony;
        $probe = new FleetShip();
        $probe->fleet = $stationaryFleet;
        $probe->slug = 'probe';
        $probe->quantity = 1;
        $this->em->persist($stationaryFleet);
        $this->em->persist($probe);
        $this->em->flush();

        $this->client->request('GET', '/planetes/'.$oldColony->id);
        self::assertResponseIsSuccessful();

        // Le sélecteur natif choisit B, même sans JavaScript.
        $this->client->request('GET', '/?colony='.$selectedColony->id);
        self::assertResponseRedirects('/planetes/'.$selectedColony->id);
        $this->client->followRedirect();
        self::assertSelectorExists('#sidebar-colony option[value="'.$selectedColony->id.'"][selected]');

        $live = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_GENESIS_LIVE' => '1'];
        foreach (['/planetes/'.$oldColony->id, '/flottes/preparer?origin='.$oldColony->id] as $oldUrl) {
            $this->client->request('GET', $oldUrl, server: $live);
            self::assertResponseIsSuccessful();
            $regions = json_decode($this->client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR)['regions'];
            $selector = new \Symfony\Component\DomCrawler\Crawler($regions['colony-selector']);
            self::assertCount(1, $selector->filter('#sidebar-colony option[value="'.$oldColony->id.'"][selected]'));
            self::assertStringContainsString('/planetes/'.$oldColony->id.'/ressources', $regions['resources']);

            $this->client->request('GET', '/');
            self::assertResponseRedirects('/planetes/'.$selectedColony->id);
            $this->client->request('GET', '/technologies');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('#sidebar-colony option[value="'.$selectedColony->id.'"][selected]');
            $this->client->request('GET', '/flottes/preparer');
            self::assertResponseIsSuccessful();
            self::assertSelectorExists('input[name="origin"][value="'.$selectedColony->id.'"]');
        }

        $this->client->request('GET', '/planetes/'.$this->ally->id, server: $live);
        self::assertResponseStatusCodeSame(404);
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/planetes/'.$selectedColony->id);

        // À l’inverse, une navigation HTML explicite vers A garde la mémorisation normale.
        $this->client->request('GET', '/flottes/preparer?origin='.$oldColony->id);
        self::assertResponseIsSuccessful();
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/planetes/'.$oldColony->id);
    }

    public function testFleetPageShowsOnlyTheActiveColonyAndScopedOrbitRequests(): void
    {
        $member=$this->player->currentPlayerUniverse;
        $otherIdle=new Fleet();$otherIdle->playerUniverse=$member;$otherIdle->homePlanet=$this->own;$this->em->persist($otherIdle);
        $ship=new FleetShip();$ship->fleet=$otherIdle;$ship->slug='probe';$ship->quantity=5;$this->em->persist($ship);
        $create=function(Planet $origin,Planet $destination,string $mission,string $status) {
            $fleet=new Fleet();$fleet->playerUniverse=$origin->owner;$fleet->homePlanet=$origin;$fleet->destination=$destination;
            $fleet->mission=$mission;$fleet->status=$status;$fleet->departureAt=new \DateTimeImmutable();$fleet->arrivalAt=new \DateTimeImmutable('+1 day');
            $this->em->persist($fleet);return $fleet;
        };
        $homeFlight=$create($this->home,$this->enemy,'attack','traveling');
        $otherFlight=$create($this->own,$this->enemy,'attack','returning');
        $homeOrbit=$create($this->home,$this->ally,'orbit','orbit_pending');
        $otherOrbit=$create($this->own,$this->ally,'orbit','orbit_pending');
        $homeIncoming=$create($this->ally,$this->home,'orbit','orbit_pending');
        $otherIncoming=$create($this->ally,$this->own,'orbit','orbit_pending');
        $foreignFlight=$create($this->foreign,$this->foreign,'explore','traveling');$this->em->flush();
        foreach([[$this->home,$homeFlight,$homeOrbit,$homeIncoming,$otherFlight,$otherOrbit,$otherIncoming],[$this->own,$otherFlight,$otherOrbit,$otherIncoming,$homeFlight,$homeOrbit,$homeIncoming]] as [$planet,$flight,$orbit,$incoming,$hiddenFlight,$hiddenOrbit,$hiddenIncoming]) {
            $this->client->request('GET','/planetes/'.$planet->id);self::assertResponseIsSuccessful();
            $this->client->request('GET','/flottes/preparer');self::assertResponseIsSuccessful();
            self::assertSelectorCount(1,'form[action="/flottes/envoyer"]');
            self::assertSelectorExists('form[action="/flottes/envoyer"] input[name="origin"][value="'.$planet->id.'"]');
            self::assertSelectorTextContains('.fleet-colony-scope',$planet->name);
            self::assertSelectorTextContains('[data-live-region="mission-summary"] .badge','2');
            self::assertSelectorNotExists('[data-orbit-request]');
            $this->client->request('GET','/flottes');self::assertResponseIsSuccessful();
            foreach([$flight,$orbit,$hiddenFlight,$hiddenOrbit] as $visible) self::assertSelectorExists('[data-active-fleet="'.$visible->id.'"]');
            self::assertSelectorNotExists('[data-active-fleet="'.$foreignFlight->id.'"]');
            $this->client->request('GET','/flottes/orbites');self::assertResponseIsSuccessful();
            foreach([$orbit,$incoming] as $visible) self::assertSelectorExists('[data-orbit-request="'.$visible->id.'"]');
            foreach([$hiddenOrbit,$hiddenIncoming] as $hidden) self::assertSelectorNotExists('[data-orbit-request="'.$hidden->id.'"]');
            self::assertSelectorNotExists('main a[href="'.static::getContainer()->get('router')->generate('app_combat_simulation').'"]');
            self::assertSelectorExists('nav[aria-label="Navigation des flottes"] a[href="/flottes/compositions"]');
            $this->client->request('GET','/flottes/surveillance',['orbits'=>1,'colony'=>$planet->id]);self::assertResponseIsSuccessful();
            $payload=json_decode($this->client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);
            self::assertStringContainsString('data-orbit-request="'.$incoming->id.'"',$payload['orbitHtml']);
            self::assertStringNotContainsString('data-orbit-request="'.$hiddenIncoming->id.'"',$payload['orbitHtml']);
        }
        $view=static::getContainer()->get(\App\Service\Fleet\OrbitRequestView::class);
        self::assertSame([],$view->forMember($member,$this->foreign));
        self::assertSame([],$view->forMember($member,$this->ally));
        foreach([$this->ally,$this->foreign] as $forbidden) {
            $this->client->request('GET','/flottes/surveillance',['orbits'=>1,'colony'=>$forbidden->id]);self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET','/planetes/'.$this->home->id);
        $member=$this->em->find(PlayerUniverse::class,$member->id);
        $this->own=$this->em->find(Planet::class,$this->own->id);
        $service=static::getContainer()->get(\App\Service\Fleet\FleetCompositionService::class);
        $saved=$service->save($member,0,'Sondes',['probe'=>['basic'=>1]],0,str_repeat('a',32));$plan=$service->plan($member,$saved,$this->own);
        $this->client->request('GET','/flottes/preparer',['composition'=>$saved->id,'origin'=>$this->own->id,'confirmation'=>$plan['fingerprint']]);
        self::assertResponseIsSuccessful();self::assertSelectorExists('input[name="origin"][value="'.$this->own->id.'"]');
        self::assertSelectorTextContains('.fleet-colony-scope',$this->own->name);
        self::assertSame('idle',$otherIdle->status);
        $session=$this->client->getRequest()->getSession();$session->set('selected_planet_'.$member->id,$this->foreign->id);$session->save();
        $fallback=static::getContainer()->get(GameRepository::class)->planets($member->universe,$member)[0];
        $this->client->request('GET','/flottes/preparer');self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="origin"][value="'.$fallback->id.'"]');
    }

    public function testGroupsAndSuggestionsExposeOnlyKnownPlanetsOfActiveUniverse(): void
    {
        $member=$this->player->currentPlayerUniverse;$repo=static::getContainer()->get(GameRepository::class);
        foreach($repo->planets($member->universe,null,true) as $planet) if(!$planet->npcPlanet
            && $this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse'=>$member,'solarSystem'=>$planet->solarSystem])) {
            // L’anomalie est limitée à ce test de l’API, qui doit la filtrer avant toute donnée.
            $planet->owner=$this->foreign->owner;$planet->name='Relation étrangère';$this->em->flush();break;
        }
        $this->client->request('GET','/flottes/destinations?mission=spy');
        self::assertResponseIsSuccessful();$data=json_decode($this->client->getResponse()->getContent(),true,512,JSON_THROW_ON_ERROR);
        $npcs=array_column($data['groups']['npc'],'id');$enemies=array_column($data['groups']['enemies'],'id');
        self::assertContains($this->enemy->id,$enemies);self::assertContains($this->npc->id,$npcs);
        self::assertNotContains($this->npc->id,$enemies);self::assertNotContains($this->enemy->id,$npcs);
        self::assertNotContains($this->neutral->id,[...$npcs,...$enemies]);self::assertNotContains($this->hidden->id,[...$npcs,...$enemies]);
        self::assertStringContainsString('no-store',$this->client->getResponse()->headers->get('Cache-Control'));
        foreach(['SecretUnseen','ForeignGalaxy','%%'] as $query) {
            $this->client->request('GET','/flottes/destinations',['mission'=>'explore','q'=>$query,'universe'=>$this->foreign->solarSystem->galaxy->universe->id]);
            self::assertResponseIsSuccessful();$rows=json_decode($this->client->getResponse()->getContent(),true)['suggestions'];self::assertSame([],$rows);
        }
        $this->client->request('GET','/flottes/destinations',['mission'=>'transport','q'=>'destneutral']);
        $rows=json_decode($this->client->getResponse()->getContent(),true)['suggestions'];
        self::assertSame([$this->neutral->id],array_column($rows,'id'));
        $this->client->request('GET','/flottes/destinations',['mission'=>'explore','q'=>'D']);self::assertSame([],json_decode($this->client->getResponse()->getContent(),true)['suggestions']);
    }

    public function testModesAreRevalidatedServerSideAndCustomExplorationRemainsPossible(): void
    {
        $member=$this->player->currentPlayerUniverse;$catalogue=static::getContainer()->get(DestinationCatalogue::class);
        self::assertSame($this->own->solarSystem->id,$catalogue->resolve($member,'transport','owned','',$this->own->id)->solarSystem->id);
        self::assertSame($this->npc->id,$catalogue->resolve($member,'attack','npc','',$this->npc->id)->id);
        self::assertSame($this->hidden->solarSystem->id,$catalogue->resolve($member,'explore','custom',$this->hidden->solarSystem->galaxy->coordinateCode().':'.$this->hidden->solarSystem->number())->id);
        $this->client->request('GET','/planetes/'.$this->home->id);
        $this->client->request('GET','/flottes/preparer');
        $token=$this->client->getCrawler()->filter('form[action="/flottes/envoyer"] input[name="_token"]')->first()->attr('value');
        $base=['_token'=>$token,'origin'=>$this->home->id,'mission'=>'spy','ships'=>['probe'=>['basic'=>1]],'ratio'=>1];
        $url=static::getContainer()->get('router')->generate('app_fleet_preview');
        foreach([['npc',$this->ally],['enemies',$this->npc],['enemies',$this->neutral],['enemies',$this->hidden],['enemies',$this->foreign]] as [$mode,$planet]) {
            $this->client->request('POST',$url,$base+['destination_mode'=>$mode,$mode.'_destination'=>$planet->id]);
            self::assertResponseStatusCodeSame(422);self::assertStringNotContainsString('SecretUnseen',$this->client->getResponse()->getContent());
        }
        $this->client->request('POST',$url,$base+['destination_mode'=>'invalid']);self::assertResponseStatusCodeSame(422);
        $this->client->request('POST',$url,array_replace($base,['mission'=>'attack','coordinates'=>$this->hidden->coordinates()]));
        self::assertResponseStatusCodeSame(422);self::assertStringContainsString('Explorez',$this->client->getResponse()->getContent());
        self::assertSame(0,$this->em->getRepository(Fleet::class)->count(['status'=>'traveling']));
    }

    public function testDefaultModePrefillAndPnjAttackChoicesKeepMissionRules(): void
    {
        $this->client->request('GET','/planetes/'.$this->home->id);
        $this->client->request('GET','/flottes/preparer');self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="destination_mode"][value="unexplored"][checked]');
        $this->client->request('GET','/flottes/preparer',['mission'=>'attack','planet'=>$this->npc->id]);self::assertResponseIsSuccessful();
        self::assertSelectorExists('input[name="destination_mode"][value="npc"][checked]');
        self::assertSelectorExists('select[name="npc_destination"] option[value="'.$this->npc->id.'"][selected]');
        $this->client->request('GET','/flottes/preparer',['mission'=>'attack','planet'=>$this->hidden->id]);self::assertResponseStatusCodeSame(404);
        $this->client->request('GET','/flottes/destinations',['mission'=>'invalid']);self::assertResponseStatusCodeSame(422);
        $this->client->getCookieJar()->clear();static::getContainer()->get('security.token_storage')->setToken(null);
        $this->client->request('GET','/flottes/destinations');self::assertResponseRedirects('/connexion');
    }
    public function testExplorationOnlyRevealsUnknownSystemCoordinates(): void
    {
        $this->client->request('GET','/flottes/destinations?mission=explore');
        self::assertResponseIsSuccessful();$data=json_decode($this->client->getResponse()->getContent(),true);
        self::assertSame(['unexplored'],$data['modes']);
        self::assertContains($this->hidden->solarSystem->id,array_column($data['groups']['unexplored'],'id'));
        self::assertNotContains($this->home->solarSystem->id,array_column($data['groups']['unexplored'],'id'));
        foreach($data['groups']['unexplored'] as $row) {
            self::assertSame(['id','type','coordinates','systemCoordinates','label'],array_keys($row));
            self::assertSame($row['coordinates'],$row['label']);self::assertSame('system',$row['type']);
        }
        foreach($data['groups'] as $group=>$rows) if($group!=='unexplored') self::assertSame([],$rows);
        self::assertStringNotContainsString('SecretUnseen',$this->client->getResponse()->getContent());
    }

    public function testMissionMatrixAndCustomPermissionsStayDistinct(): void
    {
        $expected=['explore'=>['unexplored'],'colonize'=>['neutral'],'transport'=>['owned','allied'],
            'attack'=>['npc','enemies'],'spy'=>['npc','enemies'],'conquer'=>['npc'],
            'defend'=>['owned','allied'],'orbit'=>['owned','allied'],'station'=>['owned']];
        $catalogue=static::getContainer()->get(DestinationCatalogue::class);$member=$this->player->currentPlayerUniverse;
        foreach($expected as $mission=>$modes) self::assertSame($modes,$catalogue->choices($member,$mission)['modes']);
        $transport=$catalogue->choices($member,'transport')['groups'];
        self::assertContains($this->own->id,array_column($transport['owned'],'id'));
        self::assertContains($this->ally->id,array_column($transport['allied'],'id'));
        self::assertNotContains($this->neutral->id,array_column($transport['allied'],'id'));
        self::assertSame($this->neutral->id,$catalogue->resolve($member,'transport','custom',$this->neutral->coordinates())->id);
        self::assertSame($this->own->id,$catalogue->resolve($member,'orbit','owned','',$this->own->id)->id);
        self::assertTrue(static::getContainer()->get(\App\Service\Fleet\OrbitPolicy::class)->automatic($member,$this->own));
        self::assertSame($this->own->id,$catalogue->resolve($member,'station','owned','',$this->own->id)->id);
    }

}
