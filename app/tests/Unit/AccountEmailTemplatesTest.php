<?php
namespace App\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class AccountEmailTemplatesTest extends TestCase
{
    public static function templates(): iterable
    {
        yield 'confirmation' => ['verification', 'Votre aventure commence.', '24 heures', 2];
        yield 'bienvenue' => ['welcome', 'Les étoiles vous attendent.', 'un autre email', 0];
        yield 'mot de passe' => ['password_reset', 'Retrouvez votre accès.', 'une seule fois', 2];
    }

    #[DataProvider('templates')]
    public function testContentIsEscapedAndActionLinksArePreserved(string $template, string $heading, string $notice, int $links): void
    {
        $twig = new Environment(new FilesystemLoader(__DIR__ . '/../../templates'), ['autoescape' => 'html', 'strict_variables' => true]);
        $url = 'https://accounts.example.test/action/token?first=1&second=2';
        $html = $twig->render('emails/' . $template . '.html.twig', [
            'username' => '<img src=x onerror=alert(1)>', 'actionUrl' => $url,
        ]);

        self::assertStringNotContainsString('<img', $html);
        self::assertStringContainsString('&lt;img', $html);
        self::assertStringContainsString('<html lang="fr">', $html);
        self::assertStringContainsString($heading, $html);
        self::assertStringContainsString($notice, $html);
        self::assertSame(1, substr_count($html, '<h1 '));
        self::assertSame($links, substr_count($html, 'href="https://accounts.example.test/action/token?first=1&amp;second=2"'));
        self::assertStringNotContainsString('<script', $html);
    }
}
