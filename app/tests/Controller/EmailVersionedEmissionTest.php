<?php

namespace App\Tests\Controller;

use App\Entity\{EmailVerificationToken, Player};
use App\Manager\GameManager;
use App\Repository\VerificationDeliveryRequestRepository;
use App\Service\Player\{EmailVerificationEmissionSelector, EmailVerificationService, VerificationEmissionProvider, VerificationEmissionTransport, VerificationGenerationStore, VerificationLinkKeyResolver, VerificationRecoveryOutcome, VerificationRequestLifecycle, VerificationRequestReservation, VersionedVerificationLink};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;

final class EmailVersionedEmissionTest extends WebTestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        foreach (['EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED', 'EMAIL_VERIFICATION_MIXED_READER_ENABLED'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    private function fixture(bool $reader, ?VerificationEmissionTransport $transport = null): array
    {
        foreach (['EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED' => '1', 'EMAIL_VERIFICATION_MIXED_READER_ENABLED' => $reader ? '1' : '0'] as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $_SERVER[$key] = $value;
        }
        static $fixtureIp = 43;
        $address = '192.0.2.'.++$fixtureIp;
        $client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost', 'REMOTE_ADDR' => $address]);
        $client->disableReboot();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $params = $db->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base non isolée.');
        }
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $legacyMailer = $this->createMock(MailerInterface::class);
        $legacyMailer->expects(self::never())->method('send');
        $container->set(MailerInterface::class, $legacyMailer);
        // Ne pas hériter d'un compteur d'une recette précédente. Le vrai
        // limiteur reste ensuite en place pour toutes les soumissions.
        $container->get('limiter.password_reset_request')->create('verification:'.$address)->reset();
        $clock = new MockClock();
        if ($transport !== null) {
            $keys = new class implements VerificationLinkKeyResolver {
                public function resolve(string $keyId): ?string
                {
                    return $keyId === 'writer-http-fixture' ? str_repeat('w', 32) : null;
                }
            };
            $codec = new VersionedVerificationLink($clock);
            $manager = new GameManager($em);
            $selector = new EmailVerificationEmissionSelector(
                $container->get(EmailVerificationService::class),
                new VerificationGenerationStore($db, $clock, $codec),
                new VerificationRequestReservation($em, $manager, new VerificationDeliveryRequestRepository($em), $clock),
                new VerificationRequestLifecycle($em, $manager, $clock),
                $clock, true, $reader,
                new VerificationEmissionProvider('writer-http-fixture', $keys, $transport),
            );
            $container->set(EmailVerificationEmissionSelector::class, $selector);
        }

        return [$client, $em, $db, $clock];
    }

    private function register(KernelBrowser $client): void
    {
        $password = 'Aa1!'.bin2hex(random_bytes(16));
        $page = $client->request('GET', '/inscription');
        $client->submit($page->selectButton('Créer mon compte')->form(), [
            'registration[email]' => 'writer-portal@example.test',
            'registration[username]' => 'WriterPortal',
            'registration[plainPassword][first]' => $password,
            'registration[plainPassword][second]' => $password,
        ]);
    }

    private function resend(KernelBrowser $client, string $email): string
    {
        $page = $client->request('GET', '/inscription/renvoyer');
        $client->submit($page->selectButton('Envoyer le lien de confirmation')->form(), ['password_reset_request[email]' => $email]);
        self::assertResponseRedirects('/inscription/renvoyer');
        return $client->followRedirect()->filter('.flash.success')->text();
    }

    public function testTwoAllowedResendsKeepTheFirstLinkAndOnlyOneConfirmation(): void
    {
        $links = [];
        $transport = $this->createMock(VerificationEmissionTransport::class);
        $transport->expects(self::exactly(3))->method('confirmation')
            ->willReturnCallback(static function (#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $link) use (&$links): VerificationRecoveryOutcome {
                $links[] = $link;
                return VerificationRecoveryOutcome::TransportAccepted;
            });
        $transport->expects(self::once())->method('welcome');
        [$client, , $db, $clock] = $this->fixture(true, $transport);
        $this->register($client);
        self::assertResponseRedirects('/connexion');
        $initial = $db->fetchAssociative('SELECT version, expires_at FROM player_verification_generation');
        self::assertIsArray($initial);
        $clock->sleep(300);
        $firstText = $this->resend($client, 'writer-portal@example.test');
        self::assertSame($firstText, $this->resend($client, 'writer-portal@example.test')); // Encore sous refroidissement.
        self::assertCount(2, $links);
        $clock->sleep(300);
        self::assertSame($firstText, $this->resend($client, 'writer-portal@example.test'));
        self::assertCount(3, $links);
        // En cas de divergence, ne jamais imprimer les liens dans la sortie du test.
        self::assertTrue(hash_equals($links[0], $links[1]) && hash_equals($links[0], $links[2]));
        self::assertSame($initial, $db->fetchAssociative('SELECT version, expires_at FROM player_verification_generation'));
        self::assertSame(3, (int) $db->fetchOne("SELECT COUNT(*) FROM verification_delivery_request WHERE state = 'transport_accepted'"));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_email_verification_token'));

        $confirmation = $client->request('GET', '/inscription/confirmer/'.$links[1]);
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM player'));
        $client->submit($confirmation->selectButton('Confirmer mon adresse')->form());
        self::assertResponseRedirects('/connexion');
        $verified = $db->fetchOne('SELECT email_verified_at FROM player');
        self::assertNotNull($verified);
        $replay = $client->request('GET', '/inscription/confirmer/'.$links[2]);
        $client->submit($replay->selectButton('Confirmer mon adresse')->form());
        self::assertResponseStatusCodeSame(400);
        self::assertSame($verified, $db->fetchOne('SELECT email_verified_at FROM player'));
        self::assertSame('consumed', $db->fetchOne('SELECT state FROM player_verification_generation'));
        self::assertSame(3, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
    }

    public function testVersionedSignupAndResendUseSameLinkAndAddressChangeInvalidatesIt(): void
    {
        $links = [];
        $transport = $this->createMock(VerificationEmissionTransport::class);
        $transport->expects(self::exactly(2))->method('confirmation')->with('writer-portal@example.test', self::matchesRegularExpression('/^[A-Za-z0-9_-]{43}$/D'), 'WriterPortal')
            ->willReturnCallback(static function (#[\SensitiveParameter] string $address, #[\SensitiveParameter] string $link) use (&$links): VerificationRecoveryOutcome {
                $links[] = $link;
                return VerificationRecoveryOutcome::TransportAccepted;
            });
        $transport->expects(self::once())->method('welcome')->with('writer-portal@example.test', 'WriterPortal');
        [$client, , $db, $clock] = $this->fixture(true, $transport);
        $this->register($client);
        self::assertResponseRedirects('/connexion');
        $initial = $db->fetchAssociative('SELECT * FROM player_verification_generation');
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertCount(1, $links);
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_email_verification_token'));
        $firstText = $this->resend($client, 'writer-portal@example.test'); // Dupliqué pendant cooldown.
        self::assertCount(1, $links);
        $clock->sleep(300);
        self::assertSame($firstText, $this->resend($client, 'writer-portal@example.test'));
        self::assertSame($firstText, $this->resend($client, 'no-such-fixture@example.test'));
        self::assertCount(2, $links);
        self::assertSame($links[0], $links[1]);
        self::assertSame($initial, $db->fetchAssociative('SELECT * FROM player_verification_generation'));

        $db->update('player', ['email' => 'changed-writer@example.test'], ['id' => $initial['player_id']]);
        $clock->sleep(300);
        self::assertSame($firstText, $this->resend($client, 'changed-writer@example.test'));
        $confirmation = $client->request('GET', '/inscription/confirmer/'.$links[0]);
        $client->submit($confirmation->selectButton('Confirmer mon adresse')->form());
        self::assertResponseStatusCodeSame(400);
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$initial['player_id']]));
        self::assertSame($initial, $db->fetchAssociative('SELECT * FROM player_verification_generation'));
    }

    #[DataProvider('incompleteConfigs')]
    public function testIncompleteVersionedSelectionNeverFallsBackToOldWriter(bool $reader, bool $providerPresent): void
    {
        $transport = $providerPresent ? $this->createMock(VerificationEmissionTransport::class) : null;
        if ($transport !== null) {
            $transport->expects(self::never())->method('confirmation');
            $transport->expects(self::never())->method('welcome');
        }
        [$client, , $db] = $this->fixture($reader, $transport);
        $this->register($client);
        self::assertResponseRedirects('/inscription/renvoyer');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash.warning', 'Votre compte est créé');
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM player'));
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM player'));
        $this->resend($client, 'writer-portal@example.test');
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_verification_generation'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_email_verification_token'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
    }

    public static function incompleteConfigs(): iterable
    {
        yield 'lecteur manquant' => [false, true];
        yield 'couple absent' => [true, false];
    }

    #[DataProvider('missingPairParts')]
    public function testIncompletePairCannotBeConstructed(bool $missingKeys): void
    {
        $keys = $missingKeys ? null : $this->createStub(VerificationLinkKeyResolver::class);
        $transport = $missingKeys ? $this->createStub(VerificationEmissionTransport::class) : null;
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore argument.type, argument.type (fixture volontairement invalide) */
        new VerificationEmissionProvider('fixture', $keys, $transport);
    }

    public static function missingPairParts(): iterable
    {
        yield 'sans clé' => [true];
        yield 'sans transport' => [false];
    }

    public function testActiveLegacyLinkNeverConvertsInVersionedMode(): void
    {
        $transport = $this->createMock(VerificationEmissionTransport::class);
        $transport->expects(self::never())->method('confirmation');
        $transport->expects(self::never())->method('welcome');
        [$client, $em, $db, $clock] = $this->fixture(true, $transport);
        $player = new Player();
        $player->email = 'legacy-writer@example.test';
        $player->username = 'LegacyWriter';
        $player->password = bin2hex(random_bytes(16));
        $em->persist($player);
        $token = new EmailVerificationToken();
        $token->player = $player;
        $token->tokenHash = hash('sha256', str_repeat('l', 43));
        $token->expiresAt = $clock->now()->modify('+1 hour');
        $em->persist($token);
        $em->flush();
        $before = $db->fetchAssociative('SELECT * FROM player_email_verification_token');
        $this->resend($client, $player->email);
        self::assertSame($before, $db->fetchAssociative('SELECT * FROM player_email_verification_token'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_verification_generation'));
    }

    public function testAmbiguousConfirmationNeverRetriesAndDoesNotSuppressWelcome(): void
    {
        $transport = $this->createMock(VerificationEmissionTransport::class);
        $transport->expects(self::once())->method('confirmation')->willThrowException(new \RuntimeException('synthetic ambiguous return'));
        $transport->expects(self::once())->method('welcome')->with('writer-portal@example.test', 'WriterPortal');
        [$client, , $db, $clock] = $this->fixture(true, $transport);
        $this->register($client);
        self::assertResponseRedirects('/inscription/renvoyer');
        $client->followRedirect();
        self::assertSelectorTextContains('.flash.warning', 'Votre compte est créé');
        self::assertSame('unknown', $db->fetchOne('SELECT state FROM verification_delivery_request'));
        $clock->sleep(300);
        $this->resend($client, 'writer-portal@example.test');
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_email_verification_token'));
    }
}
