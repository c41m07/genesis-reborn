<?php
namespace App\Tests\Controller;

use App\Entity\Player;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RememberMeTest extends WebTestCase
{
    public function testRememberMeIsOptionalRestoresLoginAndIsClearedOnLogout(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
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
        $player->email = 'remember@example.test';
        $player->username = 'Remember';
        $player->emailVerifiedAt = new \DateTimeImmutable();
        $player->password = static::getContainer()->get('security.user_password_hasher')->hashPassword($player, 'Password-Test-2026');
        $em->persist($player);
        $em->flush();
        $crawler = $client->request('GET', '/connexion');
        self::assertSelectorExists('input[name="_remember_me"]:not([checked])');
        $client->submit($crawler->selectButton('Connexion')->form([
            '_username' => $player->email, '_password' => 'Password-Test-2026',
        ]));
        self::assertResponseRedirects('/univers');
        self::assertNull($client->getCookieJar()->get('REMEMBERME'));
        $session = static::getContainer()->get('session.factory')->createSession();
        $session->setId($client->getCookieJar()->get($session->getName())->getValue());
        $session->set('_last_activity', time() - 86400);
        $session->save();
        $client->request('GET', '/univers');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->restart();
        $crawler = $client->request('GET', '/connexion');
        $client->submit($crawler->selectButton('Connexion')->form([
            '_username' => $player->email, '_password' => 'Password-Test-2026', '_remember_me' => '1',
        ]));
        self::assertResponseRedirects('/univers');
        $rememberCookie = $client->getCookieJar()->get('REMEMBERME');
        self::assertNotNull($rememberCookie);
        self::assertEqualsWithDelta(time() + 2592000, $rememberCookie->getExpiresTime(), 10);
        self::assertTrue($rememberCookie->isHttpOnly());
        $client->restart();
        $client->getCookieJar()->set($rememberCookie);
        $client->request('GET', '/univers');
        self::assertResponseIsSuccessful();
        self::assertSame($player->email, static::getContainer()->get('security.token_storage')->getToken()?->getUser()->getUserIdentifier());
        $token = static::getContainer()->get('security.csrf.token_manager')->getToken('logout')->getValue();
        $client->request('POST', '/deconnexion', ['_csrf_token' => $token]);
        self::assertResponseRedirects('/connexion');
        self::assertNull($client->getCookieJar()->get('REMEMBERME'));
        $client->request('GET', '/univers');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
    }
}
