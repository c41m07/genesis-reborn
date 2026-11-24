<?php

namespace App\Tests\Controller;

use App\Entity\{BoostEvent, BoostEventContribution, GameEvent, Player, PlayerBoost, PlayerUniverse, Universe};
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
        $data->plainPassword = bin2hex(random_bytes(16)).'aA1!';
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

    public function testAdvancedDraftHasOneAuditedRemovalAndTheLegacyPostNeverDeletes(): void
    {
        $this->player->roles = ['ROLE_ADMIN'];
        $draft = $this->event('Brouillon avancé', true);
        $draft->status = 'draft';
        $draft->visibleToPlayers = false;
        $active = $this->event('Annonce en cours');
        $this->em->flush();
        $this->client->loginUser($this->player);
        $base = '/admin/events/' . $draft->id;
        $canonical = $base . '/retirer';
        $expectedStatus = fn (): mixed => $this->em->getConnection()->fetchOne('SELECT status FROM game_event WHERE id = ?', [$draft->id]);
        $auditRows = fn (): array => $this->em->getConnection()->fetchAllAssociative(
            "SELECT actor_id, action, details FROM admin_audit_log WHERE target_type = 'game_event' AND target_id = ?",
            [$draft->id],
        );
        $list = $this->client->request('GET', '/admin/events');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action="' . $base . '/delete"]');
        self::assertSelectorNotExists('form[action="' . $base . '/cancel"]');
        self::assertSelectorExists('form[action="/admin/events/' . $active->id . '/cancel"]');
        self::assertSelectorCount(1, 'form[action="' . $canonical . '"]');
        self::assertSelectorTextSame('#event-removal-' . $draft->id . ' summary', 'Retirer le brouillon');
        $token = $list->filter('form[action="' . $canonical . '"] input[name="_token"]')->attr('value');
        self::assertNotEmpty($token);

        foreach ([
            ['_token' => 'invalid', 'reason' => 'Contexte normal', 'confirmed' => '1', 'status' => 403],
            ['_token' => $token, 'reason' => 'Sans confirmation', 'status' => 302],
            ['_token' => $token, 'reason' => '  ', 'confirmed' => '1', 'status' => 302],
        ] as $payload) {
            $status = $payload['status'];
            unset($payload['status']);
            $this->client->request('POST', $canonical, $payload);
            self::assertResponseStatusCodeSame($status);
            self::assertSame('draft', $expectedStatus());
            self::assertSame([], $auditRows());
        }

        // Simuler un onglet ouvert avant le retrait du bouton, avec un jeton encore valide.
        $legacyCsrf = bin2hex(random_bytes(32));
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->setId($this->client->getCookieJar()->get($session->getName())->getValue());
        $session->set('_csrf/game_event_delete_' . $draft->id, $legacyCsrf);
        $session->save();
        $this->client->request('POST', $base . '/delete', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', $base . '/delete', ['_token' => $legacyCsrf]);
        self::assertResponseRedirects('/admin/events#event-removal-' . $draft->id, 303);
        self::assertSame('draft', $expectedStatus());
        self::assertSame([], $auditRows());
        $current = $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'Aucune suppression effectuée.');
        $form = $current->filter('form[action="' . $canonical . '"]')->form([
            'reason' => '  Doublon confirmé  ',
            'confirmed' => '1',
        ]);
        $this->client->submit($form);
        self::assertResponseRedirects('/admin/events');
        self::assertFalse($expectedStatus());
        $rows = $auditRows();
        self::assertCount(1, $rows);
        self::assertSame('admin_event_deleted', $rows[0]['action']);
        self::assertSame($this->player->id, (int) $rows[0]['actor_id']);
        self::assertSame(['reason' => 'Doublon confirmé', 'name' => 'Brouillon avancé'],
            json_decode($rows[0]['details'], true, 512, JSON_THROW_ON_ERROR));
        self::assertSame('active', $this->em->getConnection()->fetchOne('SELECT status FROM game_event WHERE id = ?', [$active->id]));
    }
}
