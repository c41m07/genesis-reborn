<?php

namespace App\Tests\Controller;

use App\Entity\{BoostEvent, GameEvent, Player, PlayerBoost, Universe};
use App\Repository\GameRepository;
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
        $data->plainPassword = 'Events-ui-tests-2026!';
        $this->player = static::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($this->player, $universe->id);
        $this->client->loginUser($this->player);
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
        self::assertSelectorTextContains('main > .events-space > p .badge', '54');
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
