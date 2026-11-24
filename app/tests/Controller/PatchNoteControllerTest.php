<?php
declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{PatchNote, Player};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PatchNoteControllerTest extends WebTestCase
{
    private EntityManagerInterface $em;
    private $client;

    protected function setUp(): void
    {
        $this->client = static::createClient(['environment' => 'test'], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata); $schema->createSchema($metadata);
    }

    public function testPlayersOnlySeePublishedPatchnotes(): void
    {
        $player = $this->player(); $this->client->loginUser($player);
        $draft = new PatchNote(); $draft->title='Brouillon'; $draft->version='v0'; $draft->summary='Résumé'; $draft->content='Secret';
        $published = new PatchNote(); $published->title='Publié'; $published->version='v1'; $published->summary='Résumé public'; $published->content='Détail public'; $published->status='published'; $published->publishedAt=new \DateTimeImmutable('-1 hour');
        $this->em->persist($draft); $this->em->persist($published); $this->em->flush();
        $this->client->request('GET', '/patchnotes');
        self::assertResponseIsSuccessful(); self::assertSelectorTextContains('body', 'Publié'); self::assertSelectorTextNotContains('body', 'Brouillon');
    }

    public function testOnlyAdminsCanManagePatchnotes(): void
    {
        $this->client->loginUser($this->player());
        $this->client->request('GET', '/admin/patchnotes');
        self::assertResponseRedirects('/acces-refuse');
    }

    public function testDedicatedPagesAndLegacyPostsKeepValidationAuditAndPublication(): void
    {
        $this->client->loginUser($this->player(['ROLE_ADMIN']));
        $crawler = $this->client->request('GET', '/admin/patchnotes/nouvelle');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, '.admin-patchnote-form');
        $token = $crawler->filter('input[name="_token"]')->attr('value');
        $values = ['_token' => $token, 'title' => 'Note de recette', 'version' => 'Alpha-test', 'summary' => 'Résumé', 'content' => '<script>texte</script>', 'status' => 'published', 'publishedAt' => '2030-02-28T12:15'];
        $this->client->request('POST', '/admin/patchnotes', array_replace($values, ['title' => '']));
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('version', 'Alpha-test');
        self::assertInputValueSame('publishedAt', '2030-02-28T12:15');
        self::assertSelectorTextContains('[role="alert"]', 'Tous les champs');
        self::assertSame(0, self::getContainer()->get(EntityManagerInterface::class)->getRepository(PatchNote::class)->count([]));
        $this->client->request('POST', '/admin/patchnotes', $values);
        self::assertResponseRedirects('/admin/patchnotes');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $note = $em->getRepository(PatchNote::class)->findOneBy(['version' => 'Alpha-test']);
        $id = $note->id;
        self::assertSame('2030-02-28T12:15:00+00:00', $note->publishedAt->format('c'));
        $crawler = $this->client->request('GET', '/admin/patchnotes/'.$id);
        self::assertResponseIsSuccessful();
        self::assertInputValueSame('publishedAt', '2030-02-28T12:15');
        self::assertSelectorTextContains('[aria-label="Fil d’Ariane"]', 'Modifier Note de recette');
        $values['_token'] = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/patchnotes/'.$id, array_replace($values, ['publishedAt' => '2026-02-30T12:00', 'title' => 'Modification refusée']));
        self::assertResponseStatusCodeSame(422);
        self::assertInputValueSame('title', 'Modification refusée');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame('Note de recette', $em->find(PatchNote::class, $id)->title);
        self::assertSame(1, $em->getRepository(\App\Entity\AdminAuditLog::class)->count(['targetType' => 'patch_note']));
        $this->client->request('POST', '/admin/patchnotes/'.$id, array_replace($values, ['status' => 'draft']));
        self::assertResponseRedirects('/admin/patchnotes');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->find(PatchNote::class, $id)->publishedAt);
        self::assertSame(1, $em->getRepository(\App\Entity\AdminAuditLog::class)->count(['action' => 'patchnote_unpublished']));
        $this->client->request('POST', '/admin/patchnotes/'.$id, array_replace($values, ['publishedAt' => '']));
        self::assertResponseRedirects('/admin/patchnotes');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->find(PatchNote::class, $id)->publishedAt);
        self::assertSame(1, $em->getRepository(\App\Entity\AdminAuditLog::class)->count(['action' => 'patchnote_published']));
        $this->client->request('GET', '/admin/patchnotes');
        self::assertSelectorCount(0, '.admin-patchnote-form');
        self::assertSelectorCount(1, '.admin-patchnote-list article');
        $crawler = $this->client->request('GET', '/admin/patchnotes/'.$id.'/supprimer');
        self::assertResponseIsSuccessful();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNotNull($em->find(PatchNote::class, $id));
        $deleteToken = $crawler->filter('input[name="_token"]')->attr('value');
        $this->client->request('POST', '/admin/patchnotes/'.$id.'/delete', ['_token' => 'invalid']);
        self::assertResponseRedirects('/acces-refuse');
        $this->client->followRedirect();
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/patchnotes/'.$id.'/delete', ['_token' => $deleteToken]);
        self::assertResponseRedirects('/admin/patchnotes');
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertNull($em->find(PatchNote::class, $id));
        self::assertSame(1, $em->getRepository(\App\Entity\AdminAuditLog::class)->count(['action' => 'patchnote_deleted']));
    }

    public function testAllNewDestinationsAndWritesRemainAdminOnly(): void
    {
        $note = new PatchNote();
        $this->em->persist($note);
        $this->em->flush();
        $id = $note->id;
        $this->client->loginUser($this->player());
        foreach ([['GET', '/admin/patchnotes/nouvelle'], ['GET', '/admin/patchnotes/'.$id], ['GET', '/admin/patchnotes/'.$id.'/supprimer'], ['POST', '/admin/patchnotes'], ['POST', '/admin/patchnotes/nouvelle'], ['POST', '/admin/patchnotes/'.$id], ['POST', '/admin/patchnotes/'.$id.'/delete']] as [$method, $url]) {
            $this->client->request($method, $url);
            self::assertResponseRedirects('/acces-refuse');
        }
    }

    public function testCreationAndEditingRejectInvalidTokens(): void
    {
        $note = new PatchNote();
        $this->em->persist($note);
        $this->em->flush();
        $id = $note->id;
        $this->client->loginUser($this->player(['ROLE_ADMIN']));
        foreach (['/admin/patchnotes', '/admin/patchnotes/nouvelle', '/admin/patchnotes/'.$id] as $url) {
            $this->client->request('POST', $url, ['_token' => 'invalid']);
            self::assertResponseRedirects('/acces-refuse');
            $this->client->followRedirect();
            self::assertResponseStatusCodeSame(403);
        }
        $this->client->request('GET', '/admin/patchnotes/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminBadgeMatchesUtcPublicationWithoutChangingStoredStatusOrFilters(): void
    {
        $admin = $this->player(['ROLE_ADMIN']);
        $player = $this->player();
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $notes = [
            ['Titre test en préparation', 'Alpha-draft', 'draft', null, 'Brouillon'],
            ['Titre test à paraître', 'Alpha-future', 'published', $now->modify('+1 hour'), 'Planifié'],
            ['Titre test visible', 'Alpha-visible', 'published', $now->modify('-1 hour'), 'Publié'],
        ];
        foreach ($notes as [$title, $version, $storedStatus, $publication]) {
            $note = new PatchNote();
            $note->title = $title;
            $note->version = $version;
            $note->summary = 'Résumé de la vérification.';
            $note->content = 'Contenu de la vérification.';
            $note->status = $storedStatus;
            $note->publishedAt = $publication;
            $this->em->persist($note);
        }
        $this->em->flush();
        $connection = $this->em->getConnection();

        $adminBadges = static fn ($list): array => $list->filter('.admin-patchnote-list article .badge')
            ->each(static fn ($badge): string => $badge->text());
        $expected = array_map(static fn (array $note): string => $note[1] . ' · ' . $note[4], $notes);
        $this->client->loginUser($admin);
        $list = $this->client->request('GET', '/admin/patchnotes');
        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing($expected, $adminBadges($list));
        $filtered = $this->client->request('GET', '/admin/patchnotes', ['status' => 'published']);
        self::assertResponseIsSuccessful();
        self::assertEqualsCanonicalizing(array_slice($expected, 1), $adminBadges($filtered));
        self::assertSame('published', $connection->fetchOne('SELECT status FROM patch_note WHERE version = ?', ['Alpha-future']));

        $this->client->loginUser($player);
        $this->client->request('GET', '/patchnotes');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('body', 'Titre test visible');
        self::assertSelectorTextNotContains('body', 'Titre test à paraître');
        self::assertSelectorTextNotContains('body', 'Titre test en préparation');
    }

    private function player(array $roles = []): Player
    {
        $player = new Player(); $player->email=uniqid('patch-', true).'@example.test'; $player->username=uniqid('Patch'); $player->password='test'; $player->roles=$roles;
        $this->em->persist($player); $this->em->flush();
        return $player;
    }
}
