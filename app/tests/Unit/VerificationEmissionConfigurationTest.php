<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class VerificationEmissionConfigurationTest extends TestCase
{
    public function testProductionContainerConstructsOnlyAnExplicitValidTransport(): void
    {
        $syntheticKey = base64_encode(random_bytes(32));
        $valid = [
            'EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED' => '1',
            'EMAIL_VERIFICATION_MIXED_READER_ENABLED' => '1',
            'EMAIL_VERIFICATION_ACTIVE_KEY_ID' => 'fixture-current',
            'EMAIL_VERIFICATION_KEYS_JSON' => json_encode(['fixture-current' => $syntheticKey], JSON_THROW_ON_ERROR),
            // construction seulement ; le précontrôle ne tente jamais de connecter un hôte.
            'EMAIL_VERIFICATION_SMTP_DSN' => 'smtps://smtp.example.test:465',
        ];

        [$code, $message] = $this->check(array_fill_keys(array_keys($valid), '') + $valid, writerEnabled: '0');
        self::assertSame(Command::SUCCESS, $code);
        self::assertStringContainsString('Émission classique', $message);

        foreach ([
            ['EMAIL_VERIFICATION_MIXED_READER_ENABLED', '0'],
            ['EMAIL_VERIFICATION_ACTIVE_KEY_ID', ''],
            ['EMAIL_VERIFICATION_KEYS_JSON', '{}'],
            ['EMAIL_VERIFICATION_KEYS_JSON', 'non-json-de-recette'],
            ['EMAIL_VERIFICATION_SMTP_DSN', ''],
            ['EMAIL_VERIFICATION_SMTP_DSN', 'null://null'],
            ['EMAIL_VERIFICATION_SMTP_DSN', 'smtp://smtp.example.test:587'],
            ['EMAIL_VERIFICATION_SMTP_DSN', 'smtp://smtp.example.test:587?require_tls=1&auto_tls=0'],
            ['EMAIL_VERIFICATION_SMTP_DSN', 'smtps://smtp.example.test:465?verify_peer=0'],
            ['EMAIL_VERIFICATION_SMTP_DSN', 'failover(smtps://smtp.example.test:465 smtps://autre.example.test:465)'],
        ] as [$field, $invalid]) {
            [$code, $message] = $this->check(array_replace($valid, [$field => $invalid]));
            self::assertSame(Command::FAILURE, $code);
            self::assertStringContainsString($field, $message);
            self::assertStringNotContainsString('smtp.example.test', $message);
            self::assertStringNotContainsString('non-json-de-recette', $message);
        }

        foreach ([$valid['EMAIL_VERIFICATION_SMTP_DSN'], 'smtp://smtp.example.test:587?require_tls=true'] as $direct) {
            [$code, $message] = $this->check(array_replace($valid, ['EMAIL_VERIFICATION_SMTP_DSN' => $direct]));
            self::assertSame(Command::SUCCESS, $code);
            self::assertStringContainsString('Aucun contact SMTP et aucune remise vérifiés.', $message);
        }
    }

    /** @return array{int, string} */
    private function check(#[\SensitiveParameter] array $environment, ?string $writerEnabled = null): array
    {
        if ($writerEnabled !== null) {
            $environment['EMAIL_VERIFICATION_VERSIONED_WRITER_ENABLED'] = $writerEnabled;
            $environment['EMAIL_VERIFICATION_MIXED_READER_ENABLED'] = '0';
        }
        $previous = [];
        foreach ($environment as $name => $value) {
            $previous[$name] = [getenv($name), $_ENV[$name] ?? null, $_SERVER[$name] ?? null];
            putenv($name.'='.$value);
            $_ENV[$name] = $_SERVER[$name] = $value;
        }
        $kernel = new Kernel('prod', false);
        try {
            $app = new Application($kernel);
            $command = new CommandTester($app->find('app:email-verification:check-config'));

            return [$command->execute([]), $command->getDisplay()];
        } finally {
            $kernel->shutdown();
            foreach ($previous as $name => [$process, $env, $server]) {
                putenv($process === false ? $name : $name.'='.$process);
                if ($env === null) {
                    unset($_ENV[$name]);
                } else {
                    $_ENV[$name] = $env;
                }
                if ($server === null) {
                    unset($_SERVER[$name]);
                } else {
                    $_SERVER[$name] = $server;
                }
            }
        }
    }
}
