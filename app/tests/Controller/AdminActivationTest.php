<?php

namespace App\Tests\Controller;

use App\Entity\{AdminAuditLog, Player, VerificationDeliveryRequest};
use App\Manager\GameManager;
use App\Repository\VerificationDeliveryRequestRepository;
use App\Service\Admin\{AdminActivationAction, AdminActivationFormContext, AdminActivationRecoveryAction, AdminActivationRecoveryProvider, AdminActivationService, AdminActivationTransport};
use App\Service\Player\{VerificationGenerationStore, VerificationLinkKeyResolver, VerificationRecoveryDeliveryAdapter, VerificationRecoveryOrchestrator, VerificationRecoveryOutcome, VerificationRecoveryTransport, VerificationRequestLifecycle, VerificationRequestReservation, VersionedVerificationLink};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;

final class AdminActivationTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ADMIN_ACTIVATION_ENABLED=0');
        putenv('ADMIN_ACTIVATION_RECOVERY_ENABLED=0');
        $_ENV['ADMIN_ACTIVATION_RECOVERY_ENABLED'] = $_SERVER['ADMIN_ACTIVATION_RECOVERY_ENABLED'] = '0';
        $_ENV['ADMIN_ACTIVATION_ENABLED'] = $_SERVER['ADMIN_ACTIVATION_ENABLED'] = '0';
    }

    private function fixture(bool $enabled = true, bool $recoveryEnabled = false): array
    {
        putenv('ADMIN_ACTIVATION_ENABLED='.($enabled ? '1' : '0'));
        $_ENV['ADMIN_ACTIVATION_ENABLED'] = $_SERVER['ADMIN_ACTIVATION_ENABLED'] = $enabled ? '1' : '0';
        putenv('ADMIN_ACTIVATION_RECOVERY_ENABLED='.($recoveryEnabled ? '1' : '0'));
        $_ENV['ADMIN_ACTIVATION_RECOVERY_ENABLED'] = $_SERVER['ADMIN_ACTIVATION_RECOVERY_ENABLED'] = $recoveryEnabled ? '1' : '0';
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $players = [];
        foreach (['admin', 'target', 'ordinary'] as $name) {
            $player = new Player();
            $player->username = 'Activation'.$name;
            $player->email = $name.'@example.test';
            $player->password = bin2hex(random_bytes(16));
            $player->roles = $name === 'admin' ? ['ROLE_ADMIN'] : [];
            $player->emailVerifiedAt = $name === 'target' ? null : new \DateTimeImmutable();
            $em->persist($player);
            $players[] = $player;
        }
        $em->flush();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        self::getContainer()->set(MailerInterface::class, $mailer);
        return [$client, $em, ...$players];
    }

    public function testDisabledGuardNeverCreatesRequest(): void
    {
        [$client, $em, $admin, $target] = $this->fixture(false);
        $client->loginUser($admin);
        $client->request('GET', '/admin/joueurs/'.$target->id);
        self::assertSelectorNotExists('form[action$="/activation"]');
        $client->request('POST', '/admin/joueurs/'.$target->id.'/activation');
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
    }

    public function testConfirmedCsrfProtectedRequestIsAuditedAndNotRepeated(): void
    {
        [$client, $em, $admin, $target, $ordinary] = $this->fixture();
        $transport = $this->createMock(AdminActivationTransport::class);
        $transport->expects(self::once())->method('deliver')->with($target->id)->willReturn('transport_accepted');
        self::getContainer()->set(AdminActivationTransport::class, $transport);
        $url = '/admin/joueurs/'.$target->id.'/activation';
        $client->request('POST', $url);
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($ordinary);
        $client->request('POST', $url);
        self::assertResponseRedirects('/acces-refuse');
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/joueurs/'.$target->id);
        $form = $crawler->filter('form[action$="/activation"]');
        $data = ['_token' => $form->filter('[name="_token"]')->attr('value'), 'request_key' => $form->filter('[name="request_key"]')->attr('value')];
        $client->request('POST', $url, $data);
        self::assertSame(0, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
        $client->request('POST', $url, array_replace($data, ['confirmed' => '1', '_token' => 'invalid']));
        self::assertResponseRedirects('/acces-refuse');
        $data['confirmed'] = '1';
        $client->request('POST', $url, $data);
        self::assertResponseRedirects('/admin/joueurs/'.$target->id.'#account-diagnostic');
        $client->request('POST', $url, $data);
        $client->request('POST', $url, array_replace($data, ['request_key' => str_repeat('b', 64)]));
        self::assertSame(1, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
        self::assertSame(1, $em->getRepository(AdminAuditLog::class)->count(['action' => 'admin_activation_requested']));
        self::assertSame(1, $em->getRepository(AdminAuditLog::class)->count(['action' => 'admin_activation_result']));
        self::assertSame('transport_accepted', $em->getRepository(VerificationDeliveryRequest::class)->findOneBy([])->state);
    }

    public function testAmbiguousTransportDoesNotRetryOrLogException(): void
    {
        [$client, $em, $admin, $target] = $this->fixture();
        $transport = $this->createMock(AdminActivationTransport::class);
        $transport->expects(self::once())->method('deliver')->willThrowException(new \RuntimeException('secret-example-not-for-logs'));
        self::getContainer()->set(AdminActivationTransport::class, $transport);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/joueurs/'.$target->id);
        $form = $crawler->filter('form[action$="/activation"]');
        $data = ['_token' => $form->filter('[name="_token"]')->attr('value'), 'request_key' => $form->filter('[name="request_key"]')->attr('value'), 'confirmed' => '1'];
        $client->request('POST', '/admin/joueurs/'.$target->id.'/activation', $data);
        $client->request('POST', '/admin/joueurs/'.$target->id.'/activation', array_replace($data, ['request_key' => str_repeat('c', 64)]));
        self::assertSame('unknown', $em->getRepository(VerificationDeliveryRequest::class)->findOneBy([])->state);
        $log = $em->getRepository(AdminAuditLog::class)->findOneBy(['action' => 'admin_activation_result']);
        self::assertSame('unknown', $log->details['result']);
        self::assertStringNotContainsString('secret-example', json_encode($log->details));
        self::assertStringNotContainsString($target->email, json_encode($log->details));
    }

    public function testRevokedActorIsRecheckedInDatabase(): void
    {
        [$client, $em, $admin, $target] = $this->fixture();
        $transport = $this->createMock(AdminActivationTransport::class);
        $transport->expects(self::never())->method('deliver');
        self::getContainer()->set(AdminActivationTransport::class, $transport);
        // L'objet chargé garde ROLE_ADMIN, seule la base perd le rôle.
        $em->getConnection()->executeStatement('UPDATE player SET roles = ? WHERE id = ?', ['[]', $admin->id]);
        $service = self::getContainer()->get(\App\Service\Admin\AdminActivationService::class);
        $this->expectException(\DomainException::class);
        try {
            $service->request($admin, $target->id, str_repeat('e', 64));
        } finally {
            self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        }
    }

    public function testAlreadyVerifiedTargetIsNotDispatched(): void
    {
        [$client, $em, $admin, $target, $ordinary] = $this->fixture();
        $transport = $this->createMock(AdminActivationTransport::class);
        $transport->expects(self::never())->method('deliver');
        self::getContainer()->set(AdminActivationTransport::class, $transport);
        $service = self::getContainer()->get(\App\Service\Admin\AdminActivationService::class);
        self::assertSame('ineligible', $service->request($admin, $ordinary->id, str_repeat('f', 64)));
        self::assertSame(0, $em->getRepository(VerificationDeliveryRequest::class)->count([]));
    }

    public function testDefaultTransportProvesNoDispatch(): void
    {
        [$client, $em, $admin, $target] = $this->fixture();
        $service = self::getContainer()->get(\App\Service\Admin\AdminActivationService::class);
        self::assertSame('not_dispatched', $service->request($admin, $target->id, str_repeat('d', 64)));
        self::assertSame('cancelled', $em->getRepository(VerificationDeliveryRequest::class)->findOneBy([])->state);
    }

    public function testAdminFormExercisesRecoveryWithOnlyProviderDoubles(): void
    {
        [$client, $em, $admin, $target, $ordinary] = $this->fixture(true, true);
        $db = $em->getConnection();
        $clock = new MockClock('2030-01-01T00:00:00Z');
        $manager = new GameManager($em);
        $codec = new VersionedVerificationLink($clock);
        $reservations = new VerificationRequestReservation($em, $manager, new VerificationDeliveryRequestRepository($em), $clock);
        $lifecycle = new VerificationRequestLifecycle($em, $manager, $clock);
        $recovery = new VerificationRecoveryOrchestrator($db, $clock, $codec);
        $adapter = new VerificationRecoveryDeliveryAdapter();
        $keys = new class implements VerificationLinkKeyResolver {
            public function resolve(string $keyId): ?string
            {
                return $keyId === 'http-fixture' ? str_repeat('r', 32) : null;
            }
        };
        $generation = (new VerificationGenerationStore($db, $clock, $codec))->prepare($target->id, 'http-fixture', $clock->now()->modify('+1 hour'), $keys);
        $expected = $codec->reproduce($generation, $keys);
        $initial = $reservations->reserve($target->id, str_repeat('a', 64), 300, generationVersion: $generation->version);
        $oldId = $initial['request_id'];
        $oldClaim = $lifecycle->claim($oldId, 120);
        self::assertNotNull($lifecycle->prepareVersionedEmission($oldId, $oldClaim['claim_token'], $keys));
        self::assertSame('completed', $lifecycle->complete($oldId, $oldClaim['claim_token'], 'unknown')['result']);
        $snapshot = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$oldId]);
        $clock->sleep(300);

        $transport = $this->createMock(VerificationRecoveryTransport::class);
        $transport->expects(self::once())->method('handoff')->with($target->email, $expected, $target->username)->willReturn(VerificationRecoveryOutcome::TransportAccepted);
        // Composition réelle ; seuls le transport, le trousseau et le
        // temps sont contrôlés par la fixture. Aucun contrôleur substitué.
        $action = new AdminActivationRecoveryAction(
            self::getContainer()->get(AdminActivationService::class),
            new AdminActivationFormContext($clock),
            self::getContainer()->get(RequestStack::class),
            $recovery, $lifecycle, $adapter, true,
            new AdminActivationRecoveryProvider($keys, $transport),
        );
        self::getContainer()->set(AdminActivationAction::class, $action);

        $page = '/admin/joueurs/'.$target->id;
        $post = $page.'/activation';
        $client->request('POST', $post);
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($ordinary);
        $client->request('POST', $post);
        self::assertResponseRedirects('/acces-refuse');

        $client->loginUser($admin);
        // Les archives sans version sont toujours consultables, mais ne proposent pas de récupération.
        $db->update('verification_delivery_request', ['versioned_generation_version' => null], ['id' => $oldId]);
        $client->request('GET', $page);
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('form[action$="/activation"]');
        $db->update('verification_delivery_request', ['versioned_generation_version' => $generation->version], ['id' => $oldId]);
        $crawler = $client->request('GET', $page);
        $form = $crawler->filter('form[action$="/activation"]');
        self::assertCount(1, $form);
        self::assertStringNotContainsString($expected, $client->getResponse()->getContent());
        $data = ['_token' => $form->filter('[name="_token"]')->attr('value'), 'request_key' => $form->filter('[name="request_key"]')->attr('value')];
        $client->request('POST', $post, $data);
        $client->request('POST', $post, array_replace($data, ['_token' => 'invalid', 'confirmed' => '1']));
        self::assertResponseRedirects('/acces-refuse');
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));

        self::assertCount(0, $form->filter('input[name*="receipt"], input[name*="original"]'));
        $client->request('POST', $post, array_replace($data, ['request_key' => str_repeat('d', 64), 'confirmed' => '1']));
        $client->followRedirect();
        self::assertStringContainsString('Formulaire périmé.', $client->getResponse()->getContent());

        // Même administrateur, autre cookie de session et son propre CSRF
        // valide : la clé empruntée au premier formulaire est refusée.
        $otherBrowser = new KernelBrowser($client->getKernel(), ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $otherBrowser->disableReboot();
        $otherBrowser->loginUser($admin);
        $otherForm = $otherBrowser->request('GET', $page)->filter('form[action$="/activation"]');
        $otherCsrf = $otherForm->filter('[name="_token"]')->attr('value');
        $otherBrowser->request('POST', $post, ['_token' => $otherCsrf, 'request_key' => $data['request_key'], 'confirmed' => '1']);
        $otherBrowser->followRedirect();
        self::assertStringContainsString('Formulaire périmé.', $otherBrowser->getResponse()->getContent());

        $clock->sleep(120);
        $client->request('POST', $post, $data + ['confirmed' => '1']);
        $fresh = $client->followRedirect()->filter('form[action$="/activation"]');
        self::assertStringContainsString('Formulaire périmé.', $client->getResponse()->getContent());
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        $data = ['_token' => $fresh->filter('[name="_token"]')->attr('value'), 'request_key' => $fresh->filter('[name="request_key"]')->attr('value')];

        $data['confirmed'] = '1';
        $client->request('POST', $post, $data);
        self::assertResponseRedirects($page.'#account-diagnostic');
        self::assertStringNotContainsString($expected, $client->getResponse()->headers->get('Location'));
        $client->followRedirect();
        self::assertStringContainsString('Sa réception n’est pas confirmée.', $client->getResponse()->getContent());
        self::assertStringNotContainsString($expected, $client->getResponse()->getContent());
        $client->request('POST', $post, $data);
        $client->followRedirect();
        self::assertStringContainsString('Aucun nouvel envoi.', $client->getResponse()->getContent());
        self::assertStringNotContainsString($expected, $client->getResponse()->getContent());
        $client->request('POST', $post, array_replace($data, ['request_key' => str_repeat('d', 64)]));

        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        $event = $db->fetchAssociative('SELECT * FROM verification_recovery_event');
        self::assertSame('transport_accepted', $event['delivery_outcome']);
        self::assertSame(1, (int) $db->fetchOne("SELECT COUNT(*) FROM admin_audit_log WHERE action = 'admin_activation_recovery_requested' AND actor_id = ?", [$admin->id]));
        self::assertStringNotContainsString($expected, json_encode($event, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($data['request_key'], json_encode($event, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($expected, json_encode($db->fetchAllAssociative('SELECT details FROM admin_audit_log'), JSON_THROW_ON_ERROR));
        self::assertSame($snapshot, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$oldId]));
    }

    public function testRecoveryOptInWithoutProviderNeverFallsBackToOrdinaryAction(): void
    {
        [$client, $em, $admin, $target] = $this->fixture(true, true);
        $normal = $this->createMock(AdminActivationTransport::class);
        $normal->expects(self::never())->method('deliver');
        self::getContainer()->set(AdminActivationTransport::class, $normal);
        $client->loginUser($admin);
        $client->request('GET', '/admin/joueurs/'.$target->id);
        self::assertSelectorNotExists('form[action$="/activation"]');
        $client->request('POST', '/admin/joueurs/'.$target->id.'/activation', ['confirmed' => '1', 'request_key' => str_repeat('a', 64)]);
        self::assertResponseStatusCodeSame(404);
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
    }

    public function testProviderCannotBeBuiltWithOnlyOneHalf(): void
    {
        $transport = $this->createStub(VerificationRecoveryTransport::class);
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore argument.type (défaut volontaire du couple) */
        new AdminActivationRecoveryProvider(null, $transport);
    }

    public function testTransportConfigurationAloneCannotEnableRecovery(): void
    {
        [$client, $em, $admin, $target] = $this->fixture(false, true);
        $keys = $this->createStub(VerificationLinkKeyResolver::class);
        $transport = $this->createMock(VerificationRecoveryTransport::class);
        $transport->expects(self::never())->method('handoff');
        $container = self::getContainer();
        $action = new AdminActivationRecoveryAction(
            $container->get(AdminActivationService::class),
            $container->get(AdminActivationFormContext::class),
            $container->get(RequestStack::class),
            $container->get(VerificationRecoveryOrchestrator::class),
            $container->get(VerificationRequestLifecycle::class),
            $container->get(VerificationRecoveryDeliveryAdapter::class),
            true,
            new AdminActivationRecoveryProvider($keys, $transport),
        );
        self::assertFalse($action->isEnabled());
        self::assertSame('disabled', $action->request($admin, $target->id, str_repeat('a', 64)));
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
    }

    public function testProviderDoesNotSelectRecoveryWithoutExplicitCapability(): void
    {
        [$client, $em, $admin, $target] = $this->fixture(true, false);
        $keys = $this->createMock(VerificationLinkKeyResolver::class);
        $keys->expects(self::never())->method('resolve');
        $transport = $this->createMock(VerificationRecoveryTransport::class);
        $transport->expects(self::never())->method('handoff');
        $container = self::getContainer();
        $action = new AdminActivationRecoveryAction(
            $container->get(AdminActivationService::class),
            $container->get(AdminActivationFormContext::class),
            $container->get(RequestStack::class),
            $container->get(VerificationRecoveryOrchestrator::class),
            $container->get(VerificationRequestLifecycle::class),
            $container->get(VerificationRecoveryDeliveryAdapter::class),
            false,
            new AdminActivationRecoveryProvider($keys, $transport),
        );
        self::assertTrue($action->isEnabled());
        self::assertSame('not_dispatched', $action->request($admin, $target->id, str_repeat('d', 64)));
        self::assertSame(0, (int) $em->getConnection()->fetchOne('SELECT COUNT(*) FROM verification_recovery_event'));
        self::assertSame('cancelled', $em->getConnection()->fetchOne('SELECT state FROM verification_delivery_request'));
    }
}
