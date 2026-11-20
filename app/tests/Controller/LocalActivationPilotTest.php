<?php

namespace App\Tests\Controller;

use App\Entity\Player;
use App\Manager\GameManager;
use App\Repository\VerificationDeliveryRequestRepository;
use App\Service\Admin\AdminActivationAction;
use App\Service\Player\{EmailVerificationEmissionSelector, EmailVerificationService, SymfonyVerificationEmissionTransport, VerificationEmailMessageFactory, VerificationEmissionProvider, VerificationGenerationStore, VerificationLinkKeyResolver, VerificationRequestLifecycle, VerificationRequestReservation, VersionedVerificationLink};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mailer\{Envelope, MailerInterface, SentMessage};
use Symfony\Component\Mailer\Transport\Smtp\{EsmtpTransport, SmtpTransport};
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Recette opt-in ; SMTP uniquement sur le réseau Docker interne documenté. */
final class LocalActivationPilotTest extends WebTestCase
{
    private const RECIPIENT = 'activation-pilot@example.test';
    private const BASE = 'https://genesis-pilot.example.test';
    private bool $pilotEnabled = false;

    protected function tearDown(): void
    {
        parent::tearDown();
        if (!$this->pilotEnabled) {
            return;
        }
        foreach (['EMAIL_VERIFICATION_MIXED_READER_ENABLED', 'EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED',
            'ADMIN_ACTIVATION_ENABLED', 'ADMIN_ACTIVATION_RECOVERY_ENABLED'] as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    public function testOneLocalRegistrationCapturesTwoMessagesAndConfirmsOnlyOnce(): void
    {
        if (getenv('GENESIS_LOCAL_ACTIVATION_PILOT') !== '1') {
            self::markTestSkipped('Recette locale isolée à lancer explicitement.');
        }
        $this->pilotEnabled = true;
        foreach (['EMAIL_VERIFICATION_MIXED_READER_ENABLED' => '1', 'EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED' => '1',
            'ADMIN_ACTIVATION_ENABLED' => '0', 'ADMIN_ACTIVATION_RECOVERY_ENABLED' => '0'] as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
        }

        $client = self::createClient([], ['HTTP_HOST' => 'genesis-pilot.example.test', 'HTTPS' => 'on']);
        $client->disableReboot();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $params = $db->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de recette non isolée.');
        }
        if ($db->createSchemaManager()->listTableNames() !== []) {
            throw new \RuntimeException('La base doit être dédiée, vierge et jetable.');
        }
        // Aucune suppression de schéma : cette recette crée uniquement son schéma vierge.
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());

        $legacyMailer = $this->createMock(MailerInterface::class);
        $legacyMailer->expects(self::never())->method('send');
        $container->set(MailerInterface::class, $legacyMailer);
        self::assertFalse($container->get(AdminActivationAction::class)->isEnabled());

        $captured = [];
        $smtpCapture = getenv('GENESIS_LOCAL_ACTIVATION_CAPTURE') === 'smtp-private';
        if ($smtpCapture) {
            // Adresse fixe dans le réseau interne du runbook ; aucun DSN du propriétaire.
            self::assertSame([], $this->smtpMessages());
            $direct = new EsmtpTransport('pilot-capture', 1025, false);
            $direct->getStream()->setTimeout(3);
        } else {
            $direct = $this->createMock(SmtpTransport::class);
            $direct->expects(self::exactly(2))->method('send')->willReturnCallback(
                static function (#[\SensitiveParameter] Email $message) use (&$captured): SentMessage {
                    $captured[] = [
                        'subject' => $message->getSubject(),
                        'addresses' => array_map(static fn ($address): string => $address->getAddress(), $message->getTo()),
                        'body' => $message->getTextBody().$message->getHtmlBody(),
                    ];
                    return new SentMessage($message, Envelope::create($message));
                },
            );
        }
        $transport = new SymfonyVerificationEmissionTransport($direct, new VerificationEmailMessageFactory(
            $container->get(UrlGeneratorInterface::class), $container->get(Environment::class),
            self::BASE, 'no-reply@genesis-pilot.example.test',
        ));
        $keys = new class(random_bytes(32)) implements VerificationLinkKeyResolver {
            public function __construct(#[\SensitiveParameter] private readonly string $ephemeralKey)
            {
            }

            public function resolve(string $keyId): ?string
            {
                return $keyId === 'pilot-fixture' ? $this->ephemeralKey : null;
            }
        };
        $clock = $container->get(ClockInterface::class);
        $manager = new GameManager($em);
        $codec = new VersionedVerificationLink($clock);
        $container->set(EmailVerificationEmissionSelector::class, new EmailVerificationEmissionSelector(
            $container->get(EmailVerificationService::class),
            new VerificationGenerationStore($db, $clock, $codec),
            new VerificationRequestReservation($em, $manager, new VerificationDeliveryRequestRepository($em), $clock),
            new VerificationRequestLifecycle($em, $manager, $clock),
            $clock, true, true,
            new VerificationEmissionProvider('pilot-fixture', $keys, $transport),
        ));

        $page = $client->request('GET', '/inscription');
        $client->submit($page->selectButton('Créer mon compte')->form(), [
            'registration[email]' => self::RECIPIENT,
            'registration[username]' => 'ActivationPilotFixture',
            'registration[plainPassword][first]' => 'Local-Fixture-Only-2026!',
            'registration[plainPassword][second]' => 'Local-Fixture-Only-2026!',
        ]);
        self::assertResponseRedirects('/connexion');
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM player'));
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM player'));
        self::assertSame('transport_accepted', $db->fetchOne('SELECT state FROM verification_delivery_request'));
        self::assertSame(0, (int) $db->fetchOne('SELECT COUNT(*) FROM player_email_verification_token'));

        $messages = $smtpCapture ? $this->smtpMessages() : $captured;
        self::assertCount(2, $messages);
        $messages = array_column($messages, null, 'subject');
        self::assertArrayHasKey('Confirmez votre adresse email', $messages);
        self::assertArrayHasKey('Bienvenue dans Genesis Reborn', $messages);
        foreach ($messages as $message) {
            self::assertSame([self::RECIPIENT], $message['addresses']);
        }
        self::assertFalse(str_contains($messages['Bienvenue dans Genesis Reborn']['body'], '/inscription/confirmer/'));
        $confirmation = $messages['Confirmez votre adresse email']['body'];
        $pattern = '#'.preg_quote(self::BASE, '#').'/inscription/confirmer/([A-Za-z0-9_-]{43})(?![A-Za-z0-9_-])#';
        self::assertSame(1, preg_match($pattern, $confirmation, $matches));
        $link = $matches[1];
        unset($messages, $confirmation, $matches);

        $route = '/inscription/confirmer/'.$link;
        $page = $client->request('GET', $route);
        self::assertResponseIsSuccessful();
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM player'));
        self::assertFalse(str_contains($client->getResponse()->getContent(), $link));
        $client->submit($page->selectButton('Confirmer mon adresse')->form());
        self::assertResponseRedirects('/connexion');
        self::assertSame('consumed', $db->fetchOne('SELECT state FROM player_verification_generation'));
        self::assertNotNull($db->fetchOne('SELECT email_verified_at FROM player'));
        $page = $client->request('GET', $route);
        $client->submit($page->selectButton('Confirmer mon adresse')->form());
        self::assertResponseStatusCodeSame(400);
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        if ($smtpCapture) {
            self::assertCount(2, $this->smtpMessages());
        }
    }

    private function smtpMessages(): array
    {
        $context = stream_context_create(['http' => ['timeout' => 3, 'follow_location' => 0, 'max_redirects' => 0]]);
        $raw = file_get_contents('http://pilot-capture:8025/api/v2/messages', context: $context);
        if ($raw === false) {
            throw new \RuntimeException('Capture SMTP privée inaccessible.');
        }
        $capture = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
        return array_map(static fn (array $item): array => [
            'subject' => $item['Content']['Headers']['Subject'][0],
            'addresses' => array_map(static fn (array $to): string => $to['Mailbox'].'@'.$to['Domain'], $item['To']),
            'body' => quoted_printable_decode($item['Content']['Body']),
        ], $capture['items']);
    }
}
