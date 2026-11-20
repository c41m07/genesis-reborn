<?php

namespace App\Tests\Controller;

use App\Entity\{AdminAuditLog, Player, VerificationDeliveryRequest};
use App\Service\Admin\AdminActivationTransport;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\MailerInterface;

final class AdminActivationTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('ADMIN_ACTIVATION_ENABLED=0');
        $_ENV['ADMIN_ACTIVATION_ENABLED'] = $_SERVER['ADMIN_ACTIVATION_ENABLED'] = '0';
    }

    private function fixture(bool $enabled = true): array
    {
        putenv('ADMIN_ACTIVATION_ENABLED='.($enabled ? '1' : '0'));
        $_ENV['ADMIN_ACTIVATION_ENABLED'] = $_SERVER['ADMIN_ACTIVATION_ENABLED'] = $enabled ? '1' : '0';
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
            $player->password = 'synthetic';
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
}
