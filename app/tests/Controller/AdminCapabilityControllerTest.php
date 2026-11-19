<?php

namespace App\Tests\Controller;

use App\Entity\{AdminAuditLog, Player};
use App\Security\AdminCapability;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

final class AdminCapabilityControllerTest extends WebTestCase
{
    public function testCapabilityVoterIsRegisteredAndLegacyRouteGuardRemainsEffective(): void
    {
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $admin = new Player();
        $admin->username = 'CapabilityAdmin';
        $admin->email = 'capability-admin@example.test';
        $admin->password = 'unused';
        $admin->emailVerifiedAt = new \DateTimeImmutable();
        $admin->roles = ['ROLE_ADMIN'];
        $ordinary = new Player();
        $ordinary->username = 'CapabilityPlayer';
        $ordinary->email = 'capability-player@example.test';
        $ordinary->password = 'unused';
        $ordinary->emailVerifiedAt = new \DateTimeImmutable();
        $em->persist($admin);
        $em->persist($ordinary);
        $em->flush();
        $storage = self::getContainer()->get(TokenStorageInterface::class);
        $checker = self::getContainer()->get(AuthorizationCheckerInterface::class);
        $storage->setToken(new UsernamePasswordToken($admin, 'main', $admin->getRoles()));
        foreach (AdminCapability::all() as $capability) {
            self::assertTrue($checker->isGranted($capability));
        }
        self::assertFalse($checker->isGranted('ADMIN_CAPABILITY_UNKNOWN'));
        $storage->setToken(new UsernamePasswordToken($ordinary, 'main', $ordinary->getRoles()));
        foreach (AdminCapability::all() as $capability) {
            self::assertFalse($checker->isGranted($capability));
        }
        $storage->setToken(null);
        $url = '/admin/joueurs/'.$ordinary->id;
        $client->request('GET', $url);
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->request('GET', '/admin/permissions');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($ordinary);
        $client->request('GET', '/admin/permissions');
        self::assertResponseRedirects('/acces-refuse');
        $client->request('GET', $url);
        self::assertResponseRedirects('/acces-refuse');
        $client->request('POST', $url.'/colonies/1/ressources');
        self::assertResponseRedirects('/acces-refuse');
        $client->request('POST', $url.'/colonies/1/vaisseaux');
        self::assertResponseRedirects('/acces-refuse');
        $client->loginUser($admin);
        $client->request('GET', '/admin/permissions');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Permissions administratives');
        self::assertSelectorCount(4, 'tr[data-capability]');
        self::assertSelectorTextContains('main', 'ne sont pas délégués');
        self::assertSelectorCount(0, 'main form');
        self::assertSame(0, $em->getRepository(AdminAuditLog::class)->count(['action' => 'admin_resources_adjust']));
        // Une lecture répétée n'attribue aucun droit et ne crée aucune intervention.
        $client->request('GET', '/admin/permissions');
        self::assertResponseIsSuccessful();
        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $admin->getRoles());
        $client->request('POST', '/admin/permissions');
        self::assertResponseStatusCodeSame(405);
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $em->getRepository(AdminAuditLog::class)->count(['action' => 'admin_player_view']));
        $client->request('POST', $url.'/colonies/1/ressources', ['_token' => 'invalid']);
        self::assertResponseRedirects('/acces-refuse');
        self::assertSame(0, $em->getRepository(AdminAuditLog::class)->count(['action' => 'admin_resources_adjust']));
    }
}
