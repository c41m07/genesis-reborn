<?php

namespace App\Tests\Controller;

use App\Entity\{GuildProject, GuildProjectContribution, GuildProjectDefinition, Planet, PlayerBoost, PlayerUniverse, Universe, VacationPeriod};
use App\Service\Boost\BoostGrantService;
use App\Service\Player\{DiplomacyService, GuildProjectService, RegistrationService, RelationResolver};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GuildProjectPagesTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $em;
    private PlayerUniverse $actor;
    private int $guildId;

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
        static::getContainer()->get(UniverseImporter::class)->import();
        $this->actor = $this->player('ProjectPages');
        static::getContainer()->get(DiplomacyService::class)->create($this->actor, 'Bâtisseurs', 'BAT');
        $this->guildId = static::getContainer()->get(RelationResolver::class)->guild($this->actor)->id;
        $this->client->loginUser($this->actor->player);
    }

    private function player(string $name): PlayerUniverse
    {
        $data = new RegistrationData();
        $data->email = strtolower($name).'@example.test';
        $data->username = $name;
        $data->plainPassword = 'Project-pages-tests-2026!';
        $player = static::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        static::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        return $player->currentPlayerUniverse;
    }

    private function definition(string $name, ?Universe $universe = null, bool $enabled = true): GuildProjectDefinition
    {
        $definition = new GuildProjectDefinition();
        $definition->universe = $universe ?? $this->actor->universe;
        $definition->name = $name;
        $definition->description = 'Projet de navigation.';
        $definition->stages = [['name' => 'Structure', 'costs' => ['metal' => 10]]];
        $definition->minimums = ['metal' => 2];
        $definition->enabled = $enabled;
        $definition->createdAt = $definition->updatedAt = new \DateTimeImmutable();
        $this->em->persist($definition);
        $this->em->flush();
        return $definition;
    }

    private function project(string $name, string $status = 'active'): GuildProject
    {
        $definition = $this->definition($name);
        $project = new GuildProject();
        $project->universe = $this->actor->universe;
        $project->guildId = $this->guildId;
        $project->guildName = 'Bâtisseurs';
        $project->definition = $definition;
        $project->snapshot = ['name' => $name, 'description' => $definition->description, 'stages' => $definition->stages, 'minimums' => $definition->minimums];
        $project->status = $status;
        $project->startedAt = new \DateTimeImmutable();
        $this->em->persist($project);
        $this->em->flush();
        return $project;
    }

    public function testPagesSelectTheirOwnDataAndKeepBothNavigationsActive(): void
    {
        $active = $this->project('Projet actif');
        $completed = $this->project('Projet terminé', 'completed');
        $this->project('Projet abandonné', 'abandoned');
        $this->definition('Modèle disponible');
        $this->definition('Modèle désactivé', enabled: false);
        $other = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        $this->definition('Modèle autre univers', $other);
        foreach ([
            '/guildes/projets' => ['Projets en cours', 'Projet actif', 'Modèle disponible'],
            '/guildes/projets/disponibles' => ['Projets disponibles', 'Modèle disponible', 'Projet actif'],
            '/guildes/projets/historique' => ['Historique des projets', 'Projet terminé', 'Projet actif'],
        ] as $url => [$title, $present, $absent]) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', $title);
            self::assertSelectorTextContains('.guild-project-grid', $present);
            self::assertSelectorTextNotContains('.guild-project-grid', $absent);
            self::assertSelectorTextNotContains('.guild-project-grid', 'Modèle désactivé');
            self::assertSelectorTextNotContains('.guild-project-grid', 'Modèle autre univers');
            self::assertSelectorExists('.sidebar a[href="/guildes"].active');
            self::assertSelectorExists('nav[aria-label="Navigation de guilde"] a[href="/guildes/projets"].active');
            self::assertSelectorExists('nav[aria-label="Navigation des projets collectifs"] a[href="'.$url.'"][aria-current="page"]');
            self::assertSelectorNotExists('input[name="planet_id"]');
        }
        foreach ([$active, $completed] as $project) {
            $this->client->request('GET', '/guildes/projets/'.$project->id);
            self::assertResponseIsSuccessful();
            $parent = $project === $active ? '/guildes/projets' : '/guildes/projets/historique';
            self::assertSelectorExists('nav[aria-label="Navigation des projets collectifs"] a[href="'.$parent.'"][aria-current="location"]');
        }
        self::assertSelectorNotExists('form[action$="/contribuer"]');
    }

    public function testListPaginationBoundsHydrationAndDoesNotLoadHistory(): void
    {
        for ($i = 0; $i < 30; ++$i) $this->project('Actif '.$i);
        $this->project('Historique séparé', 'completed');
        $this->em->clear();
        $crawler = $this->client->request('GET', '/guildes/projets');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, '.guild-project-grid article');
        $loaded = $this->em->getUnitOfWork()->getIdentityMap()[GuildProject::class] ?? [];
        self::assertCount(26, $loaded);
        foreach ($loaded as $project) self::assertSame('active', $project->status);
        $this->client->click($crawler->selectLink('Voir la suite')->link());
        self::assertSelectorCount(5, '.guild-project-grid article');
        self::assertSelectorNotExists('nav[aria-label="Pagination des projets"] a[rel="next"]');
        self::assertSelectorTextNotContains('.guild-project-grid', 'Historique séparé');
    }

    public function testAvailableAndHistoricalPagesArePaginatedAndExcludeEveryLaunchedModel(): void
    {
        for ($i = 0; $i < 27; ++$i) {
            $this->definition('Disponible '.$i);
            $this->project('Archivé '.$i, $i % 2 ? 'completed' : 'abandoned');
        }
        foreach (['disponibles', 'historique'] as $page) {
            $crawler = $this->client->request('GET', '/guildes/projets/'.$page);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(25, '.guild-project-grid article');
            $this->client->click($crawler->selectLink('Voir la suite')->link());
            self::assertSelectorCount(2, '.guild-project-grid article');
            self::assertSelectorTextNotContains('.guild-project-grid', $page === 'disponibles' ? 'Archivé' : 'Disponible');
        }
    }

    public function testContributorsArePagedWithoutChangingOwnTotalsOrRewardHistory(): void
    {
        $project = $this->project('Contributions paginées');
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $this->actor]);
        for ($i = 0; $i < 30; ++$i) {
            $contribution = new GuildProjectContribution();
            $contribution->project = $project;
            $contribution->playerUniverseId = $i === 0 ? $this->actor->id : 100000 + $i;
            $contribution->planetId = $planet->id;
            $contribution->resources = ['metal' => $i === 0 ? 7 : 1];
            $contribution->updatedAt = new \DateTimeImmutable();
            $this->em->persist($contribution);
        }
        $this->em->flush();
        $boosts = static::getContainer()->get(BoostGrantService::class);
        $boosts->awardGuildProject($this->actor, $project->definition->id);
        $boost = $this->em->getRepository(PlayerBoost::class)->findOneBy(['sourceType' => 'guild_project']);
        $boost->status = 'used';
        $this->em->flush();
        $crawler = $this->client->request('GET', '/guildes/projets/'.$project->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(25, '#project-contributions > ul > li');
        self::assertSelectorTextContains('.project-overview > .project-personal-recap > ul > li', '7 / 2');
        self::assertSelectorTextContains('.project-overview > .project-personal-recap', 'déjà gagné le boost');
        $this->client->click($crawler->selectLink('Voir les contributions précédentes')->link());
        self::assertSelectorCount(5, '#project-contributions > ul > li');
        self::assertSelectorExists('#project-contributions[open]');
        self::assertSelectorTextContains('.project-overview > .project-personal-recap > ul > li', '7 / 2');
        self::assertSelectorTextContains('#project-contributions', 'ProjectPages');
        $this->client->request('GET', '/guildes/projets');
        self::assertSelectorTextContains('.guild-project-grid', 'déjà gagné le boost');
        self::assertSame(1, $this->em->getRepository(PlayerBoost::class)->count(['sourceType' => 'guild_project']));
    }

    public function testAccessIsIsolatedAndGuestsCanOnlyFindGuilds(): void
    {
        $project = $this->project('Projet privé');
        $other = $this->player('OtherGuild');
        $this->client->loginUser($other->player);
        foreach (['', '/disponibles', '/historique'] as $suffix) {
            $this->client->request('GET', '/guildes/projets'.$suffix);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Rejoignez une guilde');
            self::assertSelectorNotExists('.guild-project-grid');
        }
        $other = $this->em->find(PlayerUniverse::class, $other->id);
        static::getContainer()->get(DiplomacyService::class)->create($other, 'Autre guilde', 'AUTRE');
        $this->client->request('GET', '/guildes/projets/'.$project->id);
        self::assertResponseStatusCodeSame(404);
        $this->client->loginUser($this->actor->player);
        $project = $this->em->find(GuildProject::class, $project->id);
        $project->universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        $this->em->flush();
        $this->client->request('GET', '/guildes/projets/'.$project->id);
        self::assertResponseStatusCodeSame(404);
        $this->client->getCookieJar()->clear();
        static::getContainer()->get('security.token_storage')->setToken(null);
        foreach (['', '/disponibles', '/historique', '/'.$project->id] as $suffix) {
            $this->client->request('GET', '/guildes/projets'.$suffix);
            self::assertResponseRedirects('/connexion');
        }
    }

    public function testLaunchUsesExistingPostAndReturnsErrorsToAvailableProjects(): void
    {
        $definition = $this->definition('Lancement protégé');
        $crawler = $this->client->request('GET', '/guildes/projets/disponibles');
        $form = $crawler->selectButton('Lancer Lancement protégé')->form();
        $definition = $this->em->find(GuildProjectDefinition::class, $definition->id);
        $definition->description = 'Version changée';
        $this->em->flush();
        $this->client->submit($form);
        self::assertResponseRedirects('/guildes/projets/disponibles');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'Le modèle a changé');
        self::assertSame(0, $this->em->getRepository(GuildProject::class)->count([]));
        $this->client->request('POST', '/guildes/projets/modeles/'.$definition->id.'/lancer', ['_token' => 'invalid', 'version' => $definition->version]);
        self::assertResponseStatusCodeSame(403);
    }

    public function testDisabledGuildsExposeNeitherModelsNorProjectDetails(): void
    {
        $project = $this->project('Projet masqué');
        $this->definition('Modèle masqué');
        $this->actor->universe->gameSettings['features']['guilds_enabled'] = false;
        $this->em->flush();
        foreach (['', '/disponibles', '/historique'] as $suffix) {
            $this->client->request('GET', '/guildes/projets'.$suffix);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextContains('main', 'Les guildes sont désactivées');
            self::assertSelectorNotExists('.guild-project-grid');
        }
        $this->client->request('GET', '/guildes/projets/'.$project->id);
        self::assertResponseStatusCodeSame(404);
    }

    public function testVacationRemainsReadableWithoutLaunchOrDonationForms(): void
    {
        $project = $this->project('Lecture en vacances');
        $this->definition('Disponible en vacances');
        $period = new VacationPeriod();
        $period->membership = $this->actor;
        $period->startedAt = new \DateTimeImmutable('-3 days');
        $period->scheduledEndAt = new \DateTimeImmutable('+10 days');
        $this->em->persist($period);
        $this->em->flush();
        $this->client->request('GET', '/guildes/projets/disponibles');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/lancer"]');
        $this->client->request('GET', '/guildes/projets/'.$project->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('main', 'Pendant les vacances');
        self::assertSelectorNotExists('form[action$="/contribuer"]');
    }
}
