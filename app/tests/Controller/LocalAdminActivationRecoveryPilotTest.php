<?php

namespace App\Tests\Controller;

use App\Entity\Player;
use App\Manager\GameManager;
use App\Repository\VerificationDeliveryRequestRepository;
use App\Service\Admin\{AdminActivationAction, AdminActivationFormContext, AdminActivationRecoveryAction, AdminActivationRecoveryProvider, AdminActivationService};
use App\Service\Player\{SymfonyVerificationEmissionTransport, VerificationEmailMessageFactory, VerificationGenerationStore, VerificationLinkKeyResolver, VerificationRecoveryDeliveryAdapter, VerificationRecoveryOrchestrator, VerificationRequestLifecycle, VerificationRequestReservation, VersionedVerificationLink};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransport;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/** Recette distincte du pilote inscription ; exige la capture SMTP interne. */
final class LocalAdminActivationRecoveryPilotTest extends WebTestCase
{
    private const RECIPIENT = 'admin-recovery-pilot@example.test';
    private const BASE = 'https://genesis-pilot.example.test';
    private bool $pilotEnabled = false;
    private const FLAGS = [
        'ADMIN_ACTIVATION_ENABLED' => '1',
        'ADMIN_ACTIVATION_RECOVERY_ENABLED' => '1',
        'EMAIL_VERIFICATION_MIXED_READER_ENABLED' => '1',
        'EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED' => '0',
    ];

    protected function tearDown(): void
    {
        parent::tearDown();
        if ($this->pilotEnabled) {
            foreach (array_keys(self::FLAGS) as $name) {
                putenv($name);
                unset($_ENV[$name], $_SERVER[$name]);
            }
        }
    }

    public function testAdminRecoveryUsesPrivateSmtpOnceWithBoundRecipientAndConfirmsOnce(): void
    {
        if (getenv('GENESIS_LOCAL_ADMIN_ACTIVATION_PILOT') !== '1') {
            self::markTestSkipped('Recette admin locale isolée à lancer explicitement.');
        }
        if (getenv('GENESIS_LOCAL_ACTIVATION_CAPTURE') !== 'smtp-private') {
            throw new \RuntimeException('Le réseau interne et sa capture SMTP privée sont requis.');
        }
        $this->pilotEnabled = true;
        foreach (self::FLAGS as $name => $value) {
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        $browser = ['HTTP_HOST' => 'genesis-pilot.example.test', 'HTTPS' => 'on'];
        $client = self::createClient([], $browser);
        $client->disableReboot();
        $container = self::getContainer();
        $em = $container->get(EntityManagerInterface::class);
        $db = $em->getConnection();
        $params = $db->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test'
            || $db->createSchemaManager()->listTableNames() !== []) {
            throw new \RuntimeException('Base jetable vierge requise.');
        }
        (new SchemaTool($em))->createSchema($em->getMetadataFactory()->getAllMetadata());
        self::assertSame([], $this->capture());

        $legacyMailer = $this->createMock(MailerInterface::class);
        $legacyMailer->expects(self::never())->method('send');
        $container->set(MailerInterface::class, $legacyMailer);
        $clock = new MockClock();
        $admin = $this->player($em, 'PilotAdmin', 'pilot-admin@example.test', $clock->now(), ['ROLE_ADMIN']);
        $ordinary = $this->player($em, 'PilotOrdinary', 'pilot-ordinary@example.test', $clock->now());
        $target = $this->player($em, 'PilotRecovery', self::RECIPIENT);
        $em->flush();
        $targetId = $target->id;

        $keys = new class(random_bytes(32)) implements VerificationLinkKeyResolver {
            public function __construct(#[\SensitiveParameter] private readonly string $ephemeralKey)
            {
            }

            public function resolve(string $keyId): ?string
            {
                return $keyId === 'pilot-recovery-fixture' ? $this->ephemeralKey : null;
            }
        };
        $codec = new VersionedVerificationLink($clock);
        $generation = (new VerificationGenerationStore($db, $clock, $codec))->prepare(
            $targetId, 'pilot-recovery-fixture', $clock->now()->modify('+1 hour'), $keys,
        );
        $manager = new GameManager($em);
        $reservations = new VerificationRequestReservation($em, $manager, new VerificationDeliveryRequestRepository($em), $clock);
        $lifecycle = new VerificationRequestLifecycle($em, $manager, $clock);
        $initial = $reservations->reserve($targetId, str_repeat('a', 64), 300, generationVersion: $generation->version)['request_id'];
        $initialClaim = $lifecycle->claim($initial, 120)['claim_token'];
        // Instance initiale indéterminée, sans appeler aucun transport.
        self::assertNotNull($lifecycle->prepareVersionedEmission($initial, $initialClaim, $keys));
        self::assertSame('completed', $lifecycle->complete($initial, $initialClaim, 'unknown')['result']);
        $before = $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$initial]);
        $clock->sleep(300);

        $smtp = new EsmtpTransport('pilot-capture', 1025, false);
        $smtp->getStream()->setTimeout(3);
        $captureTransport = new SymfonyVerificationEmissionTransport($smtp, new VerificationEmailMessageFactory(
            $container->get(UrlGeneratorInterface::class), $container->get(Environment::class),
            self::BASE, 'no-reply@genesis-pilot.example.test',
        ));
        $container->set(AdminActivationAction::class, new AdminActivationRecoveryAction(
            $container->get(AdminActivationService::class), new AdminActivationFormContext($clock), $container->get(RequestStack::class),
            new VerificationRecoveryOrchestrator($db, $clock, $codec), $lifecycle,
            new VerificationRecoveryDeliveryAdapter(), true, new AdminActivationRecoveryProvider($keys, $captureTransport),
        ));

        $page = '/admin/joueurs/'.$targetId;
        $post = $page.'/activation';
        $client->request('POST', $post);
        self::assertResponseRedirects('https://genesis-pilot.example.test/connexion');
        $client->loginUser($ordinary);
        $client->request('POST', $post);
        self::assertResponseRedirects('/acces-refuse');
        $client->loginUser($admin);
        $form = $client->request('GET', $page)->filter('form[action$="/activation"]');
        self::assertCount(1, $form);
        $data = ['_token' => $form->filter('[name="_token"]')->attr('value'), 'request_key' => $form->filter('[name="request_key"]')->attr('value')];
        $client->request('POST', $post, $data); // Confirmation explicite manquante.
        $client->request('POST', $post, array_replace($data, ['_token' => 'invalid', 'confirmed' => '1']));
        self::assertResponseRedirects('/acces-refuse');
        self::assertSame([], $this->capture());
        self::assertSame(1, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));

        // Ce champ supplémentaire ne fait pas partie du contrat et ne doit pas être pris comme destinataire.
        $data += ['confirmed' => '1', 'email' => 'ignored-pilot@example.test'];
        $client->request('POST', $post, $data);
        self::assertResponseRedirects($page.'#account-diagnostic');
        $client->followRedirect();
        self::assertStringContainsString('Sa réception n’est pas confirmée.', $client->getResponse()->getContent());
        $client->request('POST', $post, $data);
        $client->followRedirect();
        self::assertStringContainsString('Aucun nouvel envoi.', $client->getResponse()->getContent());

        $captured = $this->capture();
        self::assertCount(1, $captured);
        $message = $captured[0];
        self::assertSame('Confirmez votre adresse email', $message['Content']['Headers']['Subject'][0]);
        self::assertCount(1, $message['To']);
        self::assertSame(self::RECIPIENT, $message['To'][0]['Mailbox'].'@'.$message['To'][0]['Domain']);
        $pattern = '#'.preg_quote(self::BASE, '#').'/inscription/confirmer/([A-Za-z0-9_-]{43})(?![A-Za-z0-9_-])#';
        self::assertSame(1, preg_match($pattern, quoted_printable_decode($message['Content']['Body']), $matches));
        $link = $matches[1];
        unset($captured, $message, $matches);
        self::assertFalse(str_contains($client->getResponse()->getContent(), $link));
        $event = $db->fetchAssociative('SELECT * FROM verification_recovery_event');
        self::assertSame('transport_accepted', $event['delivery_outcome']);
        self::assertFalse(str_contains(json_encode($event, JSON_THROW_ON_ERROR), $link));
        self::assertSame($before, $db->fetchAssociative('SELECT * FROM verification_delivery_request WHERE id = ?', [$initial]));

        $guest = new KernelBrowser($client->getKernel(), $browser);
        $guest->disableReboot();
        $route = '/inscription/confirmer/'.$link;
        $confirmation = $guest->request('GET', $route);
        self::assertSame(200, $guest->getResponse()->getStatusCode());
        self::assertNull($db->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$targetId]));
        $guest->submit($confirmation->selectButton('Confirmer mon adresse')->form());
        self::assertSame('/connexion', $guest->getResponse()->headers->get('Location'));
        self::assertNotNull($db->fetchOne('SELECT email_verified_at FROM player WHERE id = ?', [$targetId]));
        $confirmation = $guest->request('GET', $route);
        $guest->submit($confirmation->selectButton('Confirmer mon adresse')->form());
        self::assertSame(400, $guest->getResponse()->getStatusCode());
        self::assertSame('consumed', $db->fetchOne('SELECT state FROM player_verification_generation'));
        self::assertSame(2, (int) $db->fetchOne('SELECT COUNT(*) FROM verification_delivery_request'));
        self::assertCount(1, $this->capture());
    }

    private function player(EntityManagerInterface $em, string $name, string $email, ?\DateTimeImmutable $verified = null, array $roles = []): Player
    {
        $player = new Player();
        $player->username = $name;
        $player->email = $email;
        $player->password = bin2hex(random_bytes(16));
        $player->emailVerifiedAt = $verified;
        $player->roles = $roles;
        $em->persist($player);

        return $player;
    }

    private function capture(): array
    {
        $context = stream_context_create(['http' => ['timeout' => 3, 'follow_location' => 0, 'max_redirects' => 0]]);
        $raw = file_get_contents('http://pilot-capture:8025/api/v2/messages', context: $context);
        if ($raw === false) {
            throw new \RuntimeException('Capture SMTP privée inaccessible.');
        }

        return json_decode($raw, true, flags: JSON_THROW_ON_ERROR)['items'];
    }
}
