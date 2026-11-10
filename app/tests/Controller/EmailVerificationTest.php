<?php
namespace App\Tests\Controller;

use App\Entity\{EmailVerificationToken, Player};
use App\Service\Player\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
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
        self::assertSelectorTextContains('.flash.warning', 'Votre compte est créé');
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
}
