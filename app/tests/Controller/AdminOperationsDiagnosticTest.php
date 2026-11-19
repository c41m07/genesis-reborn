<?php
namespace App\Tests\Controller;

use App\Entity\{OutboxMessage, Player};
use App\Service\Universe\UniverseImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AdminOperationsDiagnosticTest extends WebTestCase
{
    public function testDiagnosticIsProtectedAndLeavesMessagesUntouched(): void
    {
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $admin = new Player();
        $admin->username = 'OpsDiagnostic';
        $admin->email = 'ops-diagnostic@example.test';
        $admin->password = 'test';
        $admin->roles = ['ROLE_ADMIN'];
        $em->persist($admin);
        foreach (['occupation', 'reconstruction', 'unknown_diagnostic', 'fleet'] as $kind) {
            $message = new OutboxMessage();
            $message->kind = $kind;
            $message->targetId = 987654;
            $message->token = 'secret-'.$kind;
            $message->dueAt = new \DateTimeImmutable('-10 minutes', new \DateTimeZone('UTC'));
            $em->persist($message);
        }
        $em->flush();
        $before = $em->getConnection()->fetchAllAssociative('SELECT * FROM outbox_message ORDER BY id');
        $client->request('GET', '/admin/exploitation');
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($admin);
        $client->request('GET', '/admin/exploitation');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.admin-operations', 'Occupations : 1');
        self::assertSelectorTextContains('.admin-operations', 'Reconstructions : 1');
        self::assertSelectorTextContains('.admin-operations', 'Type non reconnu par les relais');
        self::assertSelectorTextContains('.admin-operations', 'Non collectés sur cette page');
        self::assertStringNotContainsString('secret-', $client->getResponse()->getContent());
        self::assertSelectorNotExists('.admin-operations form[method=post]');
        self::assertSelectorTextContains('.admin-operations', 'Examen humain requis');
        self::assertSelectorTextContains('.admin-operations', 'Publication à vérifier');
        self::assertSelectorTextContains('.admin-operations', 'Exécution métier : non déterminable');
        self::assertSelectorExists('.admin-operations a[href="/admin/flottes/987654"]');
        // Recharger le diagnostic ne doit pas retirer ni republier un message.
        $client->request('GET', '/admin/exploitation');
        self::assertResponseIsSuccessful();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        self::assertSame($before, $em->getConnection()->fetchAllAssociative('SELECT * FROM outbox_message ORDER BY id'));
    }
}
