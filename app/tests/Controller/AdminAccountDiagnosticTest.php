<?php

namespace App\Tests\Controller;

use App\Entity\{AdminAuditLog, EmailVerificationToken, PasswordResetToken, Player};
use App\Service\Admin\AdminAccountDiagnosticService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;

final class AdminAccountDiagnosticTest extends WebTestCase
{
    private function resetSchema(): EntityManagerInterface
    {
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        return $em;
    }

    private function player(EntityManagerInterface $em, string $name, bool $verified = false, bool $admin = false): Player
    {
        $player = new Player();
        $player->username = $name;
        $player->email = $name.'@example.test';
        $player->password = 'secret-password-not-for-support';
        $player->roles = $admin ? ['ROLE_ADMIN'] : [];
        $player->emailVerifiedAt = $verified ? new \DateTimeImmutable('2026-11-19T10:00:00Z') : null;
        $em->persist($player);
        $em->flush();
        return $player;
    }

    public function testDiagnosticSeparatesMissingExpiredActiveAndConfirmedWithoutChangingTokens(): void
    {
        self::bootKernel();
        $em = $this->resetSchema();
        $now = new \DateTimeImmutable('2026-11-19T12:00:00Z');
        self::getContainer()->set(ClockInterface::class, new MockClock($now));
        $player = $this->player($em, 'Pending');
        $other = $this->player($em, 'Other');
        $service = self::getContainer()->get(AdminAccountDiagnosticService::class);
        $snapshot = $service->snapshot($player);
        self::assertSame('unavailable', $snapshot['activation_action']);
        self::assertSame('absent', $snapshot['verification']['state']);
        self::assertSame('absent', $snapshot['password_reset']['state']);
        $verification = new EmailVerificationToken();
        $verification->player = $other;
        $verification->tokenHash = str_repeat('a', 64);
        $verification->expiresAt = $now->modify('+24 hours');
        $em->persist($verification);
        $em->flush();
        self::assertSame('absent', $service->snapshot($player)['verification']['state']);
        $verification->player = $player;
        $em->flush();
        self::assertSame('active', $service->snapshot($player)['verification']['state']);
        $verification->expiresAt = $now;
        $reset = new PasswordResetToken();
        $reset->player = $player;
        $reset->tokenHash = str_repeat('b', 64);
        $reset->createdAt = $now;
        $reset->expiresAt = $now->modify('+1 hour');
        $em->persist($reset);
        $em->flush();
        $snapshot = $service->snapshot($player);
        self::assertSame('expired', $snapshot['verification']['state']);
        self::assertSame('active', $snapshot['password_reset']['state']);
        $reset->expiresAt = $now;
        $player->emailVerifiedAt = $now;
        $player->lockedAt = $now;
        $em->flush();
        $snapshot = $service->snapshot($player);
        self::assertSame('not_required', $snapshot['activation_action']);
        self::assertSame('not_required', $snapshot['verification']['state']);
        self::assertNull($snapshot['verification']['expires_at']);
        self::assertSame('expired', $snapshot['password_reset']['state']);
        self::assertEquals($now, $snapshot['locked_at']);
        self::assertEquals($now, $snapshot['verified_at']);
        self::assertSame(1, $em->getRepository(EmailVerificationToken::class)->count([]));
        self::assertSame(1, $em->getRepository(PasswordResetToken::class)->count([]));
        self::assertSame(0, $em->getRepository(AdminAuditLog::class)->count([]));
        self::assertStringNotContainsString($verification->tokenHash, json_encode($snapshot));
        self::assertStringNotContainsString($reset->tokenHash, json_encode($snapshot));
    }

    public function testPrivatePageExplainsLimitsWithoutSecretsOrMailAndRetainsAccessGuards(): void
    {
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $client->disableReboot();
        $em = $this->resetSchema();
        $admin = $this->player($em, 'SupportAdmin', true, true);
        $player = $this->player($em, 'SupportTarget');
        $ordinary = $this->player($em, 'OrdinarySupport', true);
        $token = new EmailVerificationToken();
        $token->player = $player;
        $token->tokenHash = str_repeat('c', 64);
        $token->expiresAt = new \DateTimeImmutable('+1 day');
        $em->persist($token);
        $em->flush();
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects(self::never())->method('send');
        self::getContainer()->set(MailerInterface::class, $mailer);
        $url = '/admin/joueurs/'.$player->id;
        $client->request('GET', $url);
        self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        $client->loginUser($ordinary);
        $client->request('GET', $url);
        self::assertResponseRedirects('/acces-refuse');
        $client->loginUser($admin);
        $client->request('GET', '/admin/joueurs/2147483647');
        self::assertResponseStatusCodeSame(404);
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-account-state="verification"]', 'Non confirmée');
        self::assertSelectorTextContains('[data-account-link="verification"]', 'Non expiré');
        self::assertSelectorTextContains('#account-diagnostic', 'ne prouve ni l’envoi ni la réception');
        self::assertSelectorExists('#account-diagnostic a[href="/inscription/renvoyer"]');
        self::assertSelectorTextContains('[data-account-action="activation"]', 'Renvoi depuis l’administration indisponible');
        self::assertStringNotContainsString($token->tokenHash, $client->getResponse()->getContent());
        self::assertStringNotContainsString($player->password, $client->getResponse()->getContent());
        self::assertStringNotContainsString($player->email, $client->getResponse()->getContent());
        self::assertNull($player->emailVerifiedAt);
        self::assertSame(1, $em->getRepository(EmailVerificationToken::class)->count(['player' => $player]));
        self::assertSame(1, $em->getRepository(AdminAuditLog::class)->count(['action' => 'admin_player_view', 'targetId' => $player->id]));
        // Une suspension ne se résout pas en sollicitant un nouveau lien.
        $player = $em->find(Player::class, $player->id);
        $player->lockedAt = new \DateTimeImmutable();
        $em->flush();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-account-action="activation"]', 'ne lève pas la suspension');
        self::assertSelectorNotExists('#account-diagnostic a[href="/inscription/renvoyer"]');
        self::assertSame(1, $em->getRepository(EmailVerificationToken::class)->count(['player' => $player]));
        // L'affichage ne doit pas proposer de renvoi pour une adresse déjà confirmée.
        $player = $em->find(Player::class, $player->id);
        $player->emailVerifiedAt = new \DateTimeImmutable();
        $player->lockedAt = new \DateTimeImmutable();
        $em->flush();
        $client->request('GET', $url);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-account-state="suspension"]', 'Compte suspendu');
        self::assertSelectorTextContains('[data-account-link="verification"]', 'Non nécessaire');
        self::assertSelectorNotExists('#account-diagnostic a[href="/inscription/renvoyer"]');
    }
    public function testPendingFilterIsAppliedBeforePaginationAndCombinedWithStatus(): void
    {
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $em = $this->resetSchema();
        $admin = $this->player($em, 'FilterAdmin', true, true);
        for ($index = 0; $index < 52; ++$index) {
            $pending = $this->player($em, 'PendingFilter'.$index);
            if ($index === 0) {
                $pending->lockedAt = new \DateTimeImmutable();
            }
        }
        $em->flush();
        $reads = self::getContainer()->get(\App\Repository\AdminReadRepository::class);
        $filters = ['q' => '', 'status' => '', 'role' => '', 'verification' => 'pending'];
        self::assertSame(52, $reads->players($filters, 1)['total']);
        self::assertCount(50, $reads->players($filters, 1)['entries']);
        self::assertCount(2, $reads->players($filters, 2)['entries']);
        self::assertSame(1, $reads->players(array_replace($filters, ['status' => 'suspended']), 1)['total']);
        self::assertSame(51, $reads->players(array_replace($filters, ['status' => 'active']), 1)['total']);
        self::assertSame(0, $reads->players(array_replace($filters, ['role' => 'admin']), 1)['total']);
        self::assertSame(1, $reads->players(array_replace($filters, ['verification' => 'confirmed']), 1)['total']);
        $client->loginUser($admin);
        $crawler = $client->request('GET', '/admin/joueurs?verification=pending&page=2');
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('tr[data-admin-filter-target="row"]'));
        self::assertSelectorExists('#player-verification-filter option[value="pending"][selected]');
        self::assertSelectorTextContains('.admin-player-status', 'Adresse à confirmer');
        self::assertSelectorExists('a[href*="verification=pending"]');
        $crawler = $client->request('GET', '/admin/joueurs?verification=pending&status=suspended', [], [], ['HTTP_X_GENESIS_ADMIN_LIST' => '1']);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $crawler->filter('tr[data-admin-filter-target="row"]'));
        self::assertSelectorTextContains('.admin-player-status', 'Suspendu');
        self::assertTrue($client->getResponse()->headers->hasCacheControlDirective('no-store'));
        $crawler = $client->request('GET', '/admin/joueurs?verification=invalid');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[role="status"]', '53 résultats');
    }

}
