<?php

namespace App\Tests\Controller;

use App\Entity\{GameEvent, GuildProjectDefinition, Player, Universe};
use App\Service\Universe\UniverseImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminDedicatedPagesTest extends WebTestCase
{
    public function testDirectPagesLegacyLinksAndAccessGuards(): void
    {
        $client = self::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $universe = $em->getRepository(Universe::class)->findOneBy(['slug'=>'omega-1']);
        $other = $em->getRepository(Universe::class)->findOneBy(['slug'=>'turbo-1']);
        $admin = new Player();
        $admin->username = 'AdminPages';
        $admin->email = 'admin-pages@example.test';
        $admin->password = bin2hex(random_bytes(32));
        $admin->roles = ['ROLE_ADMIN'];
        $admin->emailVerifiedAt = new \DateTimeImmutable();
        $em->persist($admin);
        $definition = new GuildProjectDefinition();
        $definition->universe = $universe;
        $definition->name = 'Modèle de navigation';
        $definition->stages = [['name'=>'Palier', 'costs'=>['metal'=>10]]];
        $definition->createdAt = $definition->updatedAt = new \DateTimeImmutable();
        $em->persist($definition);
        $event = new GameEvent();
        $event->name = 'Événement de navigation';
        $event->type = 'announcement';
        $event->startsAt = new \DateTimeImmutable('+1 day');
        $event->endsAt = new \DateTimeImmutable('+2 days');
        $event->universe = $universe;
        $em->persist($event);
        $em->flush();
        $base = '/admin/universes/'.$universe->id;
        $routes = ['/admin/univers', '/admin/universes/nouveau', $base.'/gerer', $base.'/catalogue',
            $base.'/catalogue/technologies', $base.'/catalogue/ships/fighter', '/admin/events', '/admin/events/nouveau',
            '/admin/events/'.$event->id.'/edit', '/admin/projets-guilde', '/admin/projets-guilde/nouveau',
            '/admin/projets-guilde/'.$definition->id.'/modifier', '/admin/rotations?universe='.$universe->id,
            '/admin/rotations/nouveau?universe='.$universe->id, '/admin/rotations/'.$universe->id.'/exploration/modifier'];
        $client->request('GET', $routes[2]);
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($admin);
        foreach ($routes as $route) {
            $crawler = $client->request('GET', $route);
            self::assertResponseIsSuccessful($route);
            self::assertCount(1, $crawler->filter('h1'), $route);
            self::assertSelectorExists('.admin-primary-link[aria-current="location"]');
            self::assertSelectorExists('main[data-page-sections="disabled"]');
        }
        foreach (['/admin/univers', '/admin/events', '/admin/projets-guilde', '/admin/rotations?universe='.$universe->id] as $route) {
            $crawler = $client->request('GET', $route);
            self::assertCount(0, $crawler->filter('main input[name="name"]'), 'La liste ne doit pas préparer les formulaires de création.');
        }
        $client->request('GET', '/admin/projets-guilde?edit='.$definition->id);
        self::assertResponseRedirects('/admin/projets-guilde/'.$definition->id.'/modifier');
        // Les boutons de paliers ne valident ni n’enregistrent un brouillon encore vide.
        $crawler = $client->request('GET', '/admin/projets-guilde/nouveau');
        $client->submit($crawler->selectButton('Ajouter un palier')->form());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('#stage-1-name[autofocus]');
        self::assertSelectorExists('#project-universe option[value=""]');
        self::assertSame(1, $em->getRepository(GuildProjectDefinition::class)->count([]));
        $crawler = $client->getCrawler();
        $client->submit($crawler->selectButton('Retirer le dernier palier')->form());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#stage-1-name');
        self::assertSelectorExists('#stage-0-name[autofocus]');
        self::assertSame(1, $em->getRepository(GuildProjectDefinition::class)->count([]));
        $client->request('GET', $base.'/catalogue?q=fighter');
        self::assertSelectorExists('a[href="'.$base.'/catalogue/ships/fighter"]');
        $client->request('GET', $base.'/catalogue/ships/inconnu');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/admin/universes/2147483647/gerer');
        self::assertResponseStatusCodeSame(404);
        $client->request('POST', $base.'/settings', ['_token'=>'invalid']);
        self::assertResponseRedirects('/acces-refuse');
        $client->request('POST', '/admin/rotations/'.$universe->id.'/exploration/modifier', ['_token'=>'invalid']);
        self::assertResponseRedirects('/acces-refuse');
        $client->request('GET', '/admin/univers?q=omega-1');
        self::assertSelectorTextContains('.admin-universe-card', $universe->name);
        self::assertSelectorNotExists('#admin-universe-'.$other->id);
        // Dépasser une page vérifie que LIMIT s’applique avant le rendu et que la recherche reste globale.
        $universe = $em->find(Universe::class, $universe->id);
        for ($index = 0; $index < 55; ++$index) {
            $extra = new Universe();
            $extra->slug = 'admin-pagination-'.$index;
            $extra->name = 'Univers de pagination '.$index;
            $extra->status = 'closed';
            $em->persist($extra);
            $model = new GuildProjectDefinition();
            $model->universe = $universe;
            $model->name = 'Modèle de pagination '.$index;
            $model->stages = $definition->stages;
            $model->createdAt = $model->updatedAt = new \DateTimeImmutable();
            $em->persist($model);
        }
        $em->flush();
        $reads = self::getContainer()->get(\App\Repository\AdminReadRepository::class);
        self::assertCount(50, $reads->universes(1)['entries']);
        self::assertSame(55, $reads->universes(1, '', 'closed')['total']);
        self::assertSame(1, $reads->universes(100, 'admin-pagination-54')['total']);
        self::assertSame(1, $reads->universes(100, 'admin-pagination-54')['page']);
        self::assertCount(50, $reads->guildProjectDefinitions(1)['entries']);
        self::assertCount(6, $reads->guildProjectDefinitions(2)['entries']);
        self::assertSame(2, $reads->guildProjectDefinitions(PHP_INT_MAX)['page']);
        $crawler = $client->request('GET', '/admin/univers?page=2&status=closed');
        parse_str(parse_url($crawler->filter('.admin-mission-pages a')->attr('href'), PHP_URL_QUERY), $paginationQuery);
        self::assertSame('1', $paginationQuery['page']);
        self::assertSame('closed', $paginationQuery['status']);
        $player = new Player();
        $player->username = 'OrdinaryPages';
        $player->email = 'ordinary-pages@example.test';
        $player->password = bin2hex(random_bytes(32));
        $player->emailVerifiedAt = new \DateTimeImmutable();
        $em->persist($player);
        $em->flush();
        $client->loginUser($player);
        foreach ($routes as $route) {
            $client->request('GET', $route);
            self::assertResponseRedirects('/acces-refuse', 302, $route);
        }
    }
}
