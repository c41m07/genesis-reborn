<?php

namespace App\Tests\Unit;

use App\Service\Player\{SymfonyVerificationEmissionTransport, VerificationEmailMessageFactory, VerificationRecoveryOutcome};
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Mailer\{Envelope, SentMessage};
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Transport\{Dsn, FailoverTransport, NullTransport, RoundRobinTransport};
use Symfony\Component\Mailer\Transport\Smtp\SmtpTransport;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\{RequestContext, Route, RouteCollection};
use Symfony\Component\Routing\Generator\UrlGenerator;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class SymfonyVerificationEmissionTransportTest extends TestCase
{
    private function messages(): VerificationEmailMessageFactory
    {
        $routes = new RouteCollection();
        $routes->add('app_email_verify', new Route('/inscription/confirmer/{token}'));
        return new VerificationEmailMessageFactory(
            new UrlGenerator($routes, new RequestContext(host: 'untrusted-fixture.example.test')),
            new Environment(new FilesystemLoader(dirname(__DIR__, 2).'/templates'), ['strict_variables' => true]),
            'https://genesis-fixture.example.test', 'no-reply@genesis-fixture.example.test',
        );
    }

    public function testDirectAcceptancePreservesConfirmationSubjectAndTemplate(): void
    {
        $link = str_repeat('a', 43);
        $direct = $this->createMock(SmtpTransport::class);
        $direct->expects(self::once())->method('send')->willReturnCallback(
            static function (#[\SensitiveParameter] Email $message) use ($link): SentMessage {
                self::assertSame('Confirmez votre adresse email', $message->getSubject());
                self::assertSame('recipient@example.test', $message->getTo()[0]->getAddress());
                self::assertCount(1, $message->getTo());
                self::assertSame('no-reply@genesis-fixture.example.test', $message->getFrom()[0]->getAddress());
                self::assertStringContainsString('Bonjour FixturePlayer', $message->getHtmlBody());
                self::assertStringContainsString('Ce lien est valable pendant 24 heures.', $message->getHtmlBody());
                $url = 'https://genesis-fixture.example.test/inscription/confirmer/'.$link;
                self::assertStringContainsString($url, $message->getTextBody());
                self::assertStringContainsString($url, $message->getHtmlBody());
                self::assertStringNotContainsString('untrusted-fixture.example.test', $message->getHtmlBody());

                return new SentMessage($message, Envelope::create($message));
            },
        );
        $adapter = new SymfonyVerificationEmissionTransport($direct, $this->messages());
        self::assertSame(VerificationRecoveryOutcome::TransportAccepted, $adapter->confirmation('recipient@example.test', $link, 'FixturePlayer'));
    }

    #[DataProvider('ambiguousResults')]
    public function testNoSecondAttemptAndNoFalseAcceptanceForUnknown(string $case): void
    {
        $direct = $this->createMock(SmtpTransport::class);
        $once = $direct->expects(self::once())->method('send');
        if ($case === 'exception') {
            $once->willThrowException(new TransportException('synthetic private transport context'));
        } else {
            $once->willReturn(null);
        }
        self::assertSame(VerificationRecoveryOutcome::Unknown, (new SymfonyVerificationEmissionTransport($direct, $this->messages()))
            ->confirmation('recipient@example.test', str_repeat('a', 43), 'FixturePlayer'));
    }

    public static function ambiguousResults(): iterable
    {
        yield 'null' => ['null'];
        yield 'exception' => ['exception'];
    }

    public function testNothingIsSentForInvalidPreparation(): void
    {
        $direct = $this->createMock(SmtpTransport::class);
        $direct->expects(self::never())->method('send');
        $adapter = new SymfonyVerificationEmissionTransport($direct, $this->messages());
        self::assertSame(VerificationRecoveryOutcome::NotDispatched, $adapter->confirmation('recipient@example.test', 'invalid-link', 'FixturePlayer'));
        self::assertSame(VerificationRecoveryOutcome::NotDispatched, $adapter->confirmation('malformed-address', str_repeat('a', 43), 'FixturePlayer'));
    }

    public function testNullAndCompositeTransportsAreNotInvoked(): void
    {
        $dispatch = new EventDispatcher();
        $dispatch->addListener(MessageEvent::class, static fn () => self::fail('NullTransport ne doit pas simuler une remise.'));
        $first = $this->createMock(SmtpTransport::class);
        $second = $this->createMock(SmtpTransport::class);
        $first->expects(self::never())->method('send');
        $second->expects(self::never())->method('send');
        foreach ([new NullTransport($dispatch), new FailoverTransport([$first, $second]), new RoundRobinTransport([$first, $second])] as $blocked) {
            $adapter = new SymfonyVerificationEmissionTransport($blocked, $this->messages());
            self::assertSame(VerificationRecoveryOutcome::NotDispatched, $adapter->confirmation('recipient@example.test', str_repeat('a', 43), 'FixturePlayer'));
            $adapter->welcome('recipient@example.test', 'FixturePlayer');
        }
    }

    public function testWelcomeIsSeparateAndContainsNoActivationLink(): void
    {
        $direct = $this->createMock(SmtpTransport::class);
        $direct->expects(self::once())->method('send')->willReturnCallback(
            static function (#[\SensitiveParameter] Email $message): ?SentMessage {
                self::assertSame('Bienvenue dans Genesis Reborn', $message->getSubject());
                self::assertStringContainsString('Les étoiles vous attendent.', $message->getHtmlBody());
                self::assertStringNotContainsString('/inscription/confirmer/', $message->getHtmlBody());
                self::assertStringNotContainsString('/inscription/confirmer/', $message->getTextBody());
                return null;
            },
        );
        (new SymfonyVerificationEmissionTransport($direct, $this->messages()))->welcome('recipient@example.test', 'FixturePlayer');
    }

    #[DataProvider('disallowedDsn')]
    public function testDirectFactoryRejectsNonLocalPolicyBeforeConstruction(string $input): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Transport direct TLS non disponible.');
        SymfonyVerificationEmissionTransport::forSingleSmtp(Dsn::fromString($input), $this->messages());
    }

    public static function disallowedDsn(): iterable
    {
        yield 'null' => ['null://null'];
        yield 'smtp sans tls requis' => ['smtp://fixture.example.test'];
        yield 'vérification de certificat désactivée' => ['smtps://fixture.example.test?verify_peer=0'];
    }
}
