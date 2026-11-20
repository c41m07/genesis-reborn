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
        $admin->password = bin2hex(random_bytes(16));
        $admin->roles = ['ROLE_ADMIN'];
        $em->persist($admin);
        foreach (['occupation', 'reconstruction', 'unknown_diagnostic', 'fleet', 'queue'] as $kind) {
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
        $queueMessage = $em->getRepository(OutboxMessage::class)->findOneBy(['kind' => 'queue']);
        $inspectionUrl = '/admin/exploitation/relais/'.$queueMessage->id;
        $client->request('GET', $inspectionUrl);
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $player = new Player();
        $player->username = 'OrdinaryOpsReader';
        $player->email = 'ordinary-ops@example.test';
        $player->password = bin2hex(random_bytes(16));
        $em->persist($player);
        $em->flush();
        $client->loginUser($player);
        $client->request('GET', $inspectionUrl);
        self::assertResponseRedirects('/acces-refuse');
        $client->loginUser($admin);
        $auditBefore = (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM admin_audit_log');
        $client->request('GET', $inspectionUrl);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Diagnostic du relais');
        self::assertSelectorTextContains('.panel', 'Commande absente');
        self::assertSelectorTextContains('.panel', 'Aucune relance autorisée');
        self::assertStringNotContainsString('secret-', $client->getResponse()->getContent());
        self::assertSelectorNotExists('#admin-main .panel form');
        self::assertSame($auditBefore, (int) self::getContainer()->get(EntityManagerInterface::class)->getConnection()->fetchOne('SELECT COUNT(*) FROM admin_audit_log'));
        $client->request('POST', $inspectionUrl);
        self::assertResponseStatusCodeSame(405);
        $client->request('GET', '/admin/exploitation/relais/999999999');
        self::assertResponseStatusCodeSame(404);
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
