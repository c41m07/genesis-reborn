<?php
namespace App\Tests\Controller;

use App\Entity\{EmailVerificationToken, Player};
use App\Service\Player\{EmailVerificationService, VerificationGenerationStore, VerificationLinkKeyResolver, VersionedVerificationLink};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Psr\Clock\ClockInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Security\PlayerUserChecker;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

final class EmailVerificationTest extends WebTestCase
{
    private function pendingAccount(): array
    {
        self::bootKernel();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base non isolée.');
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $player = new Player();
        $player->email = 'verification@example.test';
        $player->username = 'Verification';
        $player->password = 'test';
        $em->persist($player);
        $em->flush();
        return [$em, $player];
    }

    public function testConfirmationPersistsAndCanOnlyBeUsedOnce(): void
    {
        [$em, $player] = $this->pendingAccount();
        try {
            (new PlayerUserChecker())->checkPreAuth($player);
            self::fail('Le compte non confirmé doit être refusé.');
        } catch (CustomUserMessageAccountStatusException) {}
        $messages = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(3))->method('send')->willReturnCallback(function (Email $email) use (&$messages): void { $messages[] = $email; });
        self::getContainer()->set(MailerInterface::class, $mailer);
        $service = self::getContainer()->get(EmailVerificationService::class);
        $service->sendWelcomeAndVerification($player);
        preg_match('#/inscription/confirmer/([A-Za-z0-9_-]+)#', $messages[0]->getTextBody(), $matches);
        $plainToken = $matches[1];
        self::assertStringContainsString('/inscription/confirmer/' . $plainToken . '"', $messages[0]->getHtmlBody());
        self::assertStringContainsString('Ce lien est valable pendant 24 heures.', $messages[0]->getHtmlBody());
        self::assertStringContainsString('Les étoiles vous attendent.', $messages[1]->getHtmlBody());
        self::assertStringNotContainsString('/inscription/confirmer/', $messages[1]->getHtmlBody());
        $stored = $em->getRepository(EmailVerificationToken::class)->findOneBy(['player' => $player]);
        self::assertSame(hash('sha256', $plainToken), $stored->tokenHash);
        self::assertNotSame($plainToken, $stored->tokenHash);
        $stored->expiresAt = new \DateTimeImmutable('-1 second');
        $em->flush();
        self::assertFalse($service->verify($plainToken));
        $service->sendWelcomeAndVerification($player, false);
        self::assertFalse($service->verify($plainToken));
        preg_match('#/inscription/confirmer/([A-Za-z0-9_-]+)#', $messages[2]->getTextBody(), $matches);
        $plainToken = $matches[1];
        self::assertFalse($service->verify('invalid'));
        self::assertTrue($service->verify($plainToken));
        self::assertFalse($service->verify($plainToken));
        $id = $player->id;
        $service->sendWelcomeAndVerification($player, false);
        self::assertSame(0, $em->getRepository(EmailVerificationToken::class)->count(['player' => $player]));
        $em->clear();
        $saved = $em->find(Player::class, $id);
        self::assertNotNull($saved->emailVerifiedAt);
        (new PlayerUserChecker())->checkPreAuth($saved);
    }

    public function testConfirmationDiscoveredUnderLockDoesNotSendAnUnpersistedLink(): void
    {
        [$em, $player] = $this->pendingAccount();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        self::getContainer()->set(MailerInterface::class, $mailer);

        // État Doctrine volontairement périmé : simule une confirmation
        // acquise après lecture du compte, avant le refresh sous verrou.
        // Ce test déterministe n'est pas une preuve de concurrence simultanée.
        $metadata = $em->getClassMetadata(Player::class);
        $em->getConnection()->update(
            $metadata->getTableName(),
            [$metadata->getColumnName('emailVerifiedAt') => '2026-11-19 12:00:00'],
            ['id' => $player->id],
        );
        self::assertNull($player->emailVerifiedAt);

        $service = self::getContainer()->get(EmailVerificationService::class);
        self::assertTrue($service->sendWelcomeAndVerification($player));
        self::assertNotNull($player->emailVerifiedAt);
        self::assertSame(0, $em->getRepository(EmailVerificationToken::class)->count(['player' => $player]));
    }

    public function testConfirmationRequiresExplicitPostAndCsrfToken(): void
    {
        $client = self::createClient();
        $crawler = $client->request('GET', '/inscription/confirmer/invalid');
        self::assertResponseIsSuccessful();
        self::assertSame('no-referrer', $client->getResponse()->headers->get('Referrer-Policy'));
        $form = $crawler->selectButton('Confirmer mon adresse')->form();
        $client->request('POST', '/inscription/confirmer/invalid', ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        $client->submit($form);
        self::assertResponseStatusCodeSame(400);
        self::assertSelectorExists('a[href="/inscription/renvoyer"]');
    }

    public function testTransportFailureLeavesAccountPendingAndResendPossible(): void
    {
        [$em, $player] = $this->pendingAccount();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(9))->method('send')->willThrowException(new \Symfony\Component\Mailer\Exception\TransportException('Test transport indisponible'));
        self::getContainer()->set(MailerInterface::class, $mailer);
        $service = self::getContainer()->get(EmailVerificationService::class);
        self::assertFalse($service->sendWelcomeAndVerification($player));
        self::assertNull($player->emailVerifiedAt);
        self::assertSame(1, $em->getRepository(EmailVerificationToken::class)->count(['player' => $player]));
        $service->sendWelcomeAndVerification($player, false);
        self::assertSame(1, $em->getRepository(EmailVerificationToken::class)->count(['player' => $player]));
    }

    public function testRegistrationFailureOffersResendAndPreservesPendingAccount(): void
    {
        $this->pendingAccount();
        self::ensureKernelShutdown();
        $client = self::createClient();
        $client->disableReboot();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::exactly(6))->method('send')->willThrowException(new \Symfony\Component\Mailer\Exception\TransportException('Transport indisponible'));
        self::getContainer()->set(MailerInterface::class, $mailer);
        $crawler = $client->request('GET', '/inscription');
        $client->submit($crawler->selectButton('Créer mon compte')->form(), [
            'registration[email]' => 'new@example.test',
            'registration[username]' => 'NewCommander',
            'registration[plainPassword][first]' => 'New-Password-2026',
            'registration[plainPassword][second]' => 'New-Password-2026',
        ]);
        self::assertResponseRedirects('/inscription/renvoyer');
        $player = self::getContainer()->get(EntityManagerInterface::class)->getRepository(Player::class)->findOneBy(['email' => 'new@example.test']);
        self::assertNotNull($player);
        self::assertNull($player->emailVerifiedAt);
        $client->followRedirect();
        self::assertSelectorTextContains('.entry-access-panel .flash.warning[role="alert"]', 'Votre compte est créé');
        self::assertSelectorExists('button');
    }

    public function testResendDoesNotDiscloseWhetherAccountExists(): void
    {
        $this->pendingAccount();
        self::ensureKernelShutdown();
        $client = self::createClient();
        $messages = [];
        foreach (['missing@example.test', 'verification@example.test'] as $email) {
            $crawler = $client->request('GET', '/inscription/renvoyer');
            $client->submit($crawler->selectButton('Envoyer le lien de confirmation')->form(), ['password_reset_request[email]' => $email]);
            self::assertResponseRedirects('/inscription/renvoyer');
            $crawler = $client->followRedirect();
            $messages[] = $crawler->filter('.flash.success')->text();
        }
        self::assertSame($messages[0], $messages[1]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        putenv('EMAIL_VERIFICATION_MIXED_READER_ENABLED');
        unset($_ENV['EMAIL_VERIFICATION_MIXED_READER_ENABLED'], $_SERVER['EMAIL_VERIFICATION_MIXED_READER_ENABLED']);
    }

    private function readerFixture(bool $mixed): array
    {
        $value = $mixed ? '1' : '0';
        if ($mixed) {
            putenv('EMAIL_VERIFICATION_MIXED_READER_ENABLED='.$value);
            $_ENV['EMAIL_VERIFICATION_MIXED_READER_ENABLED'] = $_SERVER['EMAIL_VERIFICATION_MIXED_READER_ENABLED'] = $value;
        }
        [, $pending] = $this->pendingAccount();
        $id = $pending->id;
        self::ensureKernelShutdown();
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        self::getContainer()->set(MailerInterface::class, $mailer);

        return [$client, $em, $em->find(Player::class, $id)];
    }

    private function syntheticVersionedLink(Player $player): string
    {
        $container = self::getContainer();
        $keys = new class implements VerificationLinkKeyResolver {
            public function resolve(string $keyId): ?string
            {
                return $keyId === 'route-fixture' ? str_repeat('r', 32) : null;
            }
        };
        $clock = $container->get(ClockInterface::class);
        $codec = new VersionedVerificationLink($clock);
        $store = new VerificationGenerationStore($container->get(EntityManagerInterface::class)->getConnection(), $clock, $codec);
        $generation = $store->prepare($player->id, 'route-fixture', $clock->now()->modify('+1 hour'), $keys);
        return $codec->reproduce($generation, $keys);
    }

    private function legacyLink(EntityManagerInterface $em, Player $player, string $link): void
    {
        $legacy = new EmailVerificationToken();
        $legacy->player = $em->find(Player::class, $player->id);
        $legacy->tokenHash = hash('sha256', $link);
        $legacy->expiresAt = new \DateTimeImmutable('+1 hour');
        $em->persist($legacy);
        $em->flush();
    }

    private function postConfirmation(KernelBrowser $client, #[\SensitiveParameter] string $link): void
    {
        $page = $client->request('GET', '/inscription/confirmer/'.$link);
        self::assertResponseIsSuccessful();
        self::assertStringNotContainsString($link, $client->getResponse()->getContent());
        $client->submit($page->selectButton('Confirmer mon adresse')->form());
        self::assertSame('no-referrer', $client->getResponse()->headers->get('Referrer-Policy'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
    }

    public function testVersionedReaderIsOffByDefaultAndLegacySuspensionBehaviorIsPreserved(): void
    {
        [$client, $em, $player] = $this->readerFixture(false);
        $link = $this->syntheticVersionedLink($player);
        $this->postConfirmation($client, $link);
        self::assertResponseStatusCodeSame(400);
        self::assertNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
        self::assertSame('active', $em->getConnection()->fetchOne('SELECT state FROM player_verification_generation'));

        $em->getConnection()->update('player', ['locked_at' => '2030-01-01 00:00:00'], ['id' => $player->id]);
        $legacy = str_repeat('l', 43);
        $this->legacyLink($em, $player, $legacy);
        $this->postConfirmation($client, $legacy);
        self::assertResponseRedirects('/connexion');
        self::assertNotNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
    }

    public function testExplicitReaderConsumesVersionedOnlyAfterValidPostAndReplaysFail(): void
    {
        [$client, $em, $player] = $this->readerFixture(true);
        $link = $this->syntheticVersionedLink($player);
        $url = '/inscription/confirmer/'.$link;
        $page = $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSame('no-referrer', $client->getResponse()->headers->get('Referrer-Policy'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
        self::assertStringNotContainsString($link, $client->getResponse()->getContent());
        $form = $page->selectButton('Confirmer mon adresse')->form();
        $client->request('POST', $url, ['_token' => 'invalid']);
        self::assertResponseStatusCodeSame(403);
        self::assertNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
        $client->submit($form);
        self::assertResponseRedirects('/connexion');
        self::assertSame('no-referrer', $client->getResponse()->headers->get('Referrer-Policy'));
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        self::assertNotNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
        self::assertSame('consumed', $em->getConnection()->fetchOne('SELECT state FROM player_verification_generation'));
        $client->followRedirect();
        self::assertStringContainsString('Adresse email confirmée.', $client->getResponse()->getContent());
        $this->postConfirmation($client, $link);
        self::assertResponseStatusCodeSame(400);
    }

    public function testExplicitReaderRejectsAmbiguousRevokedExpiredAndSuspendedVersionedLinks(): void
    {
        [$client, $em, $player] = $this->readerFixture(true);
        $db = $em->getConnection();
        $link = $this->syntheticVersionedLink($player);
        $this->legacyLink($em, $player, $link);
        $this->postConfirmation($client, $link);
        self::assertResponseStatusCodeSame(400);
        $em->remove($em->getRepository(EmailVerificationToken::class)->findOneBy(['player' => $player]));
        $em->flush();
        $db->update('player_verification_generation', ['state' => 'revoked'], ['player_id' => $player->id]);
        $this->postConfirmation($client, $link);
        self::assertResponseStatusCodeSame(400);
        $next = $this->syntheticVersionedLink($player);
        $db->update('player', ['locked_at' => '2030-01-01 00:00:00'], ['id' => $player->id]);
        $this->postConfirmation($client, $next);
        self::assertResponseStatusCodeSame(400);
        self::assertNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
        $db->update('player', ['locked_at' => null], ['id' => $player->id]);
        $db->update('player_verification_generation', ['expires_at' => 1], ['player_id' => $player->id]);
        $this->postConfirmation($client, $next);
        self::assertResponseStatusCodeSame(400);
        self::assertNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
    }

    public function testExplicitReaderStillAcceptsLegacyLinkForSuspendedAccount(): void
    {
        [$client, $em, $player] = $this->readerFixture(true);
        $em->getConnection()->update('player', ['locked_at' => '2030-01-01 00:00:00'], ['id' => $player->id]);
        $legacy = str_repeat('l', 43);
        $this->legacyLink($em, $player, $legacy);
        $this->postConfirmation($client, $legacy);
        self::assertResponseRedirects('/connexion');
        self::assertNotNull($em->getConnection()->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$player->id]));
        self::assertNotNull($em->getConnection()->fetchOne('SELECT locked_at FROM player WHERE id = ?', [$player->id]));
    }
}
