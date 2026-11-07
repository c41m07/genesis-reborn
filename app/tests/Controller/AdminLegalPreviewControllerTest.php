<?php
namespace App\Tests\Controller;

use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminLegalPreviewControllerTest extends WebTestCase
{
    public function testGuestsCannotReadDrafts(): void
    {
        $client = static::createClient();
        $client->request('GET', '/admin/juridique');
        self::assertResponseRedirects('http://localhost/connexion');
    }

    public function testPlayersCannotReadDrafts(): void
    {
        $client = $this->authenticatedClient([]);
        $client->request('GET', '/admin/juridique');
        self::assertResponseRedirects('/acces-refuse');
        self::assertStringNotContainsString('MENTION_EDITEUR', $client->getResponse()->getContent());
    }

    public function testAdminsCanReviewAllFourDraftsWithoutPublishingThem(): void
    {
        $client = $this->authenticatedClient(['ROLE_ADMIN']);
        foreach (['cgu', 'mentions-legales', 'confidentialite', 'cookies'] as $page) {
            $client->request('GET', '/admin/juridique/'.$page);
            self::assertResponseIsSuccessful();
            self::assertSelectorCount(1, 'h1');
            self::assertSelectorExists('article h2');
            self::assertSelectorCount(4, 'nav[aria-label="Brouillons juridiques"] a');
            self::assertSelectorCount(1, 'nav[aria-label="Brouillons juridiques"] a[aria-current="page"]');
            self::assertSelectorTextContains('main', 'texte non publié');
            self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
            self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('private'));
        }
        $client->request('GET', '/admin/juridique/mentions-legales');
        self::assertSelectorTextContains('article', '[MENTION_EDITEUR_ET_DIRECTION_PUBLICATION_A_VALIDER]');
        $client->request('GET', '/admin/juridique/confidentialite');
        self::assertSelectorExists('table th[scope="col"]');
        $client->request('GET', '/admin/juridique');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Conditions générales');
        $client->request('GET', '/admin/juridique/inconnue');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', '/sitemap.xml');
        self::assertStringNotContainsString('juridique', $client->getResponse()->getContent());
    }

    private function authenticatedClient(array $roles): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $player = new Player();
        $player->email = 'legal-preview@example.test';
        $player->username = 'LegalPreview';
        $player->password = 'test';
        $player->roles = $roles;
        $em->persist($player);
        $em->flush();
        $client->loginUser($player);
        return $client;
    }
}
