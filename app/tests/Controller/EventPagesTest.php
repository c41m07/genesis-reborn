<?php

namespace App\Tests\Controller;

use App\Entity\{BoostEvent, BoostEventContribution, GameEvent, Planet, PlanetResource, Player, PlayerBoost, PlayerUniverse, Universe};
use App\Repository\GameRepository;
use App\Service\Colony\ResourceService;
use App\Service\Event\GameEventService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;

final class EventPagesTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private Player $player;
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
        $this->clock = new MockClock('2026-11-17T12:00:00Z');
        static::getContainer()->set('clock', $this->clock);
        static::getContainer()->get(UniverseImporter::class)->import();
        $data = new RegistrationData();
        $data->email = 'events-ui@example.test';
        $data->username = 'EventsUI';
        $data->plainPassword = bin2hex(random_bytes(16)).'aA1!';
        $this->player = static::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($this->player, $universe->id);
        $this->client->loginUser($this->player);
    }

    public function testAdvancedProductionCancellationCreditsOnlyTheEarnedWindow(): void
    {
        $this->assertAdvancedProductionStop('cancel', 'planned');
    }

    public function testAdvancedProductionRemovalCreditsOnlyTheEarnedWindow(): void
    {
        $this->assertAdvancedProductionStop('retirer', 'active');
    }

    private function assertAdvancedProductionStop(string $action, string $status): void
    {
        // Aucun stock réel n’est utilisé. On laisse une marge sous le plafond pour observer le gain.
        $this->player->roles = ['ROLE_ADMIN'];
        $this->player->emailVerifiedAt = $this->clock->now();
        $membership = $this->player->currentPlayerUniverse;
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $membership]);
        self::assertInstanceOf(Planet::class, $planet);
        $stock = $this->em->getRepository(PlanetResource::class)->findOneBy(['planet' => $planet, 'resourceType' => 'metal']);
        self::assertInstanceOf(PlanetResource::class, $stock);
        $planetId = $planet->id;
        $stockId = $stock->id;
        $universeId = $membership->universe->id;
        $stock->lastUpdatedAt = $this->clock->now();
        $stock->currentStock = 100.0;
        $this->em->flush();
        $this->client->loginUser($this->player);

        $resources = self::getContainer()->get(ResourceService::class);
        $ordinaryRate = $resources->snapshot($planet)['metal']['rate'];
        self::assertGreaterThan(0, $ordinaryRate);
        $form = $this->client->request('GET', '/admin/events/nouveau')->selectButton('Créer')->form([
            'name' => 'Bonus avancé de recette',
            'description' => 'Fenêtre de production fixe.',
            'type' => 'production_bonus',
            'status' => $status,
            'starts_at' => '2026-11-17T12:05',
            'ends_at' => '2026-11-17T13:00',
            'universe_id' => (string) $membership->universe->id,
            'percentage' => '100',
            'visible' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/events');
        $event = $this->em->getRepository(GameEvent::class)->findOneBy(['name' => 'Bonus avancé de recette']);
        self::assertInstanceOf(GameEvent::class, $event);
        self::assertSame($status, $event->status);
        self::assertArrayNotHasKey('guided_key', $event->parameters);

        $this->clock->sleep(900);
        $eventId = $event->id;
        $path = '/admin/events/'.$eventId.'/'.$action;
        $form = $this->client->request('GET', '/admin/events')->filter('form[action="'.$path.'"]')->form();
        if ($action === 'retirer') {
            $form['reason'] = 'Arrêt de recette après dix minutes de bonus.';
            $form['confirmed']->tick();
        }
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/events');
        // Le kernel peut vider le gestionnaire entre deux requêtes du client.
        $event = $this->em->find(GameEvent::class, $eventId);
        self::assertInstanceOf(GameEvent::class, $event);
        self::assertSame('cancelled', $event->status);
        self::assertSame($action === 'retirer', isset($event->parameters['archived_at']));

        // La colonie n’a pas été synchronisée pendant l’événement. Après rejeu du même formulaire
        // cinq minutes plus tard, elle reçoit 30 min normales + exactement 10 min de bonus.
        $this->clock->sleep(300);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/events');
        $this->clock->sleep(600);
        $universe = $this->em->find(Universe::class, $universeId);
        self::assertInstanceOf(Universe::class, $universe);
        $events = self::getContainer()->get(GameEventService::class);
        self::assertEqualsWithDelta(2.0, $events->productionMultiplier($universe, new \DateTimeImmutable('2026-11-17T12:10:00Z')), 0.000001);
        self::assertEqualsWithDelta(1.0, $events->productionMultiplier($universe, new \DateTimeImmutable('2026-11-17T12:15:00Z')), 0.000001);
        $stock = $this->em->find(PlanetResource::class, $stockId);
        self::assertInstanceOf(PlanetResource::class, $stock);
        $planet = $this->em->find(Planet::class, $planetId);
        self::assertInstanceOf(Planet::class, $planet);
        self::assertSame('2026-11-17T12:00:00+00:00', $stock->lastUpdatedAt->format(DATE_ATOM));
        $expected = 100 + 2400 * $ordinaryRate;
        $resources = self::getContainer()->get(ResourceService::class);
        self::assertEqualsWithDelta($expected, $resources->materialize($planet)['metal']->currentStock, 0.001);
        $this->em->flush();
        $this->em->clear();
        $planet = $this->em->find(Planet::class, $planetId);
        self::assertInstanceOf(Planet::class, $planet);
        self::assertEqualsWithDelta($expected, $resources->materialize($planet)['metal']->currentStock, 0.001);
        $event = $this->em->find(GameEvent::class, $eventId);
        self::assertInstanceOf(GameEvent::class, $event);
        self::assertCount(1, $event->parameters['production_history']);
    }

    private function event(string $name, bool $upcoming = false): GameEvent
    {
        $event = new GameEvent();
        $event->name = $name;
        $event->description = 'Annonce de navigation.';
        $event->type = 'announcement';
        $event->status = 'active';
        $event->universe = $this->player->currentPlayerUniverse->universe;
        $event->startsAt = $this->clock->now()->modify($upcoming ? '+1 day' : '-1 day');
        $event->endsAt = $this->clock->now()->modify('+3 days');
        $event->visibleToPlayers = true;
        $this->em->persist($event);
        return $event;
    }

    private function challenge(string $name, string $status = 'closed'): BoostEvent
    {
        $event = new BoostEvent();
        $event->universe = $this->player->currentPlayerUniverse->universe;
        $event->eventKey = 'test-'.$name;
        $event->templateKey = 'exploration';
        $event->format = 'collective';
        $event->rewardMode = 'participation';
        $event->contributionMetric = 'systems';
        $event->durationSeconds = 3600;
        $event->scheduledAt = $this->clock->now();
        $event->status = $status;
        $event->configuration = ['guided' => true, 'name' => $name, 'reward' => ['kind' => 'boost']];
        $this->em->persist($event);
        return $event;
    }

    public function testPagesSeparateTheirDataAndKeepPrivateMetadataAndActiveNavigation(): void
    {
        $this->event('Annonce visible');
        $challenge = $this->challenge('Défi conservé');
        $announcement = $this->event('Annonce de défi');
        $this->em->flush();
        $announcement->parameters = ['challenge_id' => $challenge->id];
        $this->em->flush();
        foreach ([
            '/evenements' => ['Événements et bonus', 'Annonce visible', 'Défi conservé'],
            '/evenements/defis' => ['Défis et rotations', 'Défi conservé', 'Annonce visible'],
        ] as $url => [$title, $present, $absent]) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', $title);
            self::assertSelectorTextContains('main', $present);
            self::assertSelectorTextNotContains('main', $absent);
            self::assertSelectorTextNotContains('main', 'Annonce de défi');
            self::assertSelectorExists('meta[name="robots"][content="noindex"]');
            self::assertSelectorExists('.sidebar a[href="/evenements"].active');
            self::assertSelectorExists('nav[aria-label="Navigation des événements"] a[href="'.$url.'"][aria-current="page"]');
        }
        $this->client->request('GET', '/evenements/defis/'.$challenge->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Défi conservé');
        self::assertSelectorExists('nav[aria-label="Navigation des événements"] a[href="/evenements/defis"][aria-current="location"]');
        self::assertSelectorTextContains('.collective-challenge', 'Objectif et récompense');
    }

    public function testUpcomingAndCurrentPagesKeepEachOthersQueryContext(): void
    {
        for ($i = 0; $i < 27; ++$i) {
            $this->event('Actuel '.$i);
            $this->event('Futur '.$i, true);
        }
        $this->em->flush();
        $crawler = $this->client->request('GET', '/evenements?current_page=2&upcoming_page=1');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, 'section[aria-labelledby="events-current"] article');
        self::assertSelectorCount(25, 'section[aria-labelledby="events-upcoming"] article');
        $link = $crawler->filter('nav[aria-label="Pagination des événements à venir"] a[rel="next"]')->link();
        self::assertStringContainsString('current_page=2', $link->getUri());
        $this->client->click($link);
        self::assertSelectorCount(2, 'section[aria-labelledby="events-current"] article');
        self::assertSelectorCount(2, 'section[aria-labelledby="events-upcoming"] article');
        self::assertSelectorTextContains('.event-drawer-count', '54');
        self::assertSelectorTextContains('section[aria-labelledby="events-current"] .core-event-group-heading .badge', '27 événements');
        self::assertSelectorTextContains('section[aria-labelledby="events-upcoming"] .core-event-group-heading .badge', '27 événements');
    }

    public function testChallengePaginationFiltersBeforeItsLimitAndRootDoesNotHydrateHistory(): void
    {
        for ($i = 0; $i < 30; ++$i) $this->challenge('Historique '.$i, $i % 2 ? 'closed' : 'cancelled');
        for ($i = 0; $i < 55; ++$i) $this->challenge('Hors parcours '.$i)->configuration = null;
        $this->em->flush();
        $this->em->clear();
        $this->client->request('GET', '/evenements');
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->em->getUnitOfWork()->getIdentityMap()[BoostEvent::class] ?? []);
        $crawler = $this->client->request('GET', '/evenements/defis');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, '.event-challenge-grid article');
        self::assertSelectorTextNotContains('main', 'Hors parcours');
        self::assertCount(25, $this->em->getUnitOfWork()->getIdentityMap()[BoostEvent::class] ?? []);
        $this->client->click($crawler->selectLink('Page suivante')->link());
        self::assertSelectorCount(5, '.event-challenge-grid article');
    }

    public function testJsonSelectionKeepsPhpEmptySemanticsAndWeeklyPrefix(): void
    {
        $values = [null, false, 0, 0.0, '', '0', [], new \stdClass(), true, 1, 0.5, 'false', '00', '0 ', ' ', [0], ['x' => 0]];
        $expectedAnnouncements = $expectedChallenges = [];
        foreach ($values as $i => $value) {
            $this->event('Valeur '.$i)->parameters = ['challenge_id' => $value];
            $this->challenge('Valeur '.$i)->configuration = ['guided' => $value, 'name' => 'Valeur '.$i];
            $decoded = json_decode(json_encode($value), true);
            if (empty($decoded)) $expectedAnnouncements[] = 'Valeur '.$i;
            else $expectedChallenges[] = 'Valeur '.$i;
        }
        $weekly = $this->challenge('Rotation sans guided');
        $weekly->configuration = null;
        $weekly->eventKey = 'weekly-test';
        $uppercase = $this->challenge('Préfixe de casse différente', 'active');
        $uppercase->eventKey = 'Weekly-uppercase';
        $uppercase->configuration = null;
        $this->em->flush();
        $actorId = $this->player->currentPlayerUniverse->id;
        $this->em->clear();
        $actor = $this->em->find(\App\Entity\PlayerUniverse::class, $actorId);
        $repo = static::getContainer()->get(GameRepository::class);
        $announcements = $repo->playerGameEventsPage($actor, $this->clock->now(), false, 1);
        $challenges = $repo->playerChallengesPage($actor, 1);
        self::assertCount(count($expectedAnnouncements), $announcements['rows']);
        self::assertEqualsCanonicalizing($expectedAnnouncements, array_column($announcements['rows'], 'name'));
        self::assertSame(count($expectedChallenges) + 1, $challenges['total']);
        $names = array_map(static fn(BoostEvent $event): string => $event->configuration['name'] ?? 'weekly', $challenges['rows']);
        self::assertEqualsCanonicalizing([...$expectedChallenges, 'weekly'], $names);
        self::assertSame([], $repo->playerChallengeEvents($actor, ['active']));
    }

    public function testVisibilityAndUniverseIsolationKeepArchivedDetailsAccessible(): void
    {
        $foreign = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        $this->event('Global')->universe = null;
        $this->event('Autre univers')->universe = $foreign;
        $this->event('Caché')->visibleToPlayers = false;
        $this->event('Brouillon')->status = 'draft';
        $this->event('Expiré')->endsAt = $this->clock->now();
        $other = $this->challenge('Défi étranger');
        $other->universe = $foreign;
        $ordinary = $this->challenge('Sans parcours');
        $ordinary->configuration = null;
        $archived = $this->challenge('Défi archivé');
        $archived->configuration['archived_at'] = '2026-11-16T12:00:00Z';
        $this->em->flush();
        $this->client->request('GET', '/evenements');
        self::assertSelectorCount(1, '.event-list article');
        self::assertSelectorTextContains('main', 'Global');
        foreach ([$other, $ordinary] as $event) {
            $this->client->request('GET', '/evenements/defis/'.$event->id);
            self::assertResponseStatusCodeSame(404);
        }
        $this->client->request('GET', '/evenements/defis');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.event-challenge-grid article');
        $this->client->request('GET', '/evenements/defis/'.$archived->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Défi archivé');
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count([]));
    }

    public function testContributionRankingIsRestrictedToThisEventsContributorsAndKeepsTiesAcrossPages(): void
    {
        $event = $this->challenge('Défi classé', 'active');
        $event->rewardMode = 'all_contributors';
        $event->endsAt = $this->clock->now()->modify('+1 day');
        $other = $this->challenge('Autre défi');
        $viewer = $this->player->currentPlayerUniverse;

        // Deux scores égaux en tête, puis autant de scores égaux de rang 3 que nécessaire
        // pour que le même rang soit affiché de part et d'autre de la pagination.
        $this->addContribution($event, $viewer, 'viewer-a', 482);
        $this->addContribution($event, $viewer, 'viewer-b', 251);
        $this->addContribution($event, $this->newContributor('PremierExAequo'), 'tie', 733);
        $former = $this->newContributor('AliasHistorique');
        $former->detachPlayer($this->clock->now());
        $this->addContribution($event, $former, 'former', 211);
        for ($i = 0; $i < 24; ++$i) {
            $this->addContribution($event, $this->newContributor('Commandant'.$i), 'rank-'.$i, 211);
        }
        $nonContributor = $this->newContributor('AutreEvenement');
        $this->addContribution($other, $nonContributor, 'other', 917);
        $this->em->flush();
        $path = '/evenements/defis/'.$event->id;

        $first = $this->client->request('GET', $path);
        self::assertResponseIsSuccessful();
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('private'));
        self::assertTrue($this->client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertSelectorTextContains('.event-participation', 'Votre classement : #1');
        self::assertSelectorTextContains('.event-participation', '733');
        self::assertSelectorCount(25, '#event-ranking tbody tr');
        self::assertSame(['1', '1', '3'], $first->filter('#event-ranking tbody tr td:first-child')->slice(0, 3)->each(
            static fn ($cell): string => $cell->text(),
        ));
        self::assertSelectorTextContains('#event-ranking', 'PremierExAequo');
        self::assertSelectorTextContains('#event-ranking', '(vous)');
        self::assertSelectorTextContains('#event-ranking', 'Ancien participant');
        self::assertSelectorTextNotContains('#event-ranking', 'AliasHistorique');
        self::assertSelectorTextNotContains('#event-ranking', '@example.test');
        self::assertSelectorTextNotContains('#event-ranking', 'AutreEvenement');
        self::assertSelectorTextNotContains('#event-ranking', '917');

        $second = $this->client->click($first->filter('#event-ranking nav a[rel="next"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(2, '#event-ranking tbody tr');
        self::assertSame(['3', '3'], $second->filter('#event-ranking tbody tr td:first-child')->each(
            static fn ($cell): string => $cell->text(),
        ));
        self::assertSelectorTextContains('#event-ranking nav', 'Page 2 / 2');
        self::assertSelectorTextContains('.event-participation', 'Votre classement : #1');
        $this->client->request('GET', $path.'?ranking_page=999999');
        self::assertSelectorTextContains('#event-ranking nav', 'Page 2 / 2');

        // Être dans le même univers et avoir joué à un autre événement ne suffit pas.
        $this->client->loginUser($nonContributor->player);
        $this->client->request('GET', $path.'?ranking_page=2');
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.event-participation');
        self::assertSelectorNotExists('#event-ranking');
        self::assertSelectorTextNotContains('main', 'PremierExAequo');
        self::assertSelectorTextNotContains('main', '733');
        self::assertSame(0, $this->em->getRepository(PlayerBoost::class)->count([]));
    }

    private function newContributor(string $name): PlayerUniverse
    {
        $player = new Player();
        $player->username = $name;
        $player->email = strtolower($name).'@example.test';
        $player->password = 'fixture-unused';
        $player->emailVerifiedAt = $this->clock->now();
        $membership = new PlayerUniverse();
        $membership->universe = $this->player->currentPlayerUniverse->universe;
        $membership->joinedAt = $this->clock->now();
        $membership->player = $player;
        $player->currentPlayerUniverse = $membership;
        $this->em->persist($player);
        $this->em->persist($membership);

        return $membership;
    }

    private function addContribution(BoostEvent $event, PlayerUniverse $membership, string $source, float $value): void
    {
        $contribution = new BoostEventContribution();
        $contribution->event = $event;
        $contribution->playerUniverse = $membership;
        $contribution->sourceKey = $source;
        $contribution->value = $value;
        $this->em->persist($contribution);
    }

    public function testAnonymousAndMissingEmpireCannotOpenEventPages(): void
    {
        $player = $this->em->find(Player::class, $this->player->id);
        $player->currentPlayerUniverse = null;
        $this->em->flush();
        foreach (['/evenements', '/evenements/defis'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/univers');
        }
        $this->client->getCookieJar()->clear();
        static::getContainer()->get('security.token_storage')->setToken(null);
        foreach (['/evenements', '/evenements/defis', '/evenements/defis/1'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/connexion');
        }
    }
}
