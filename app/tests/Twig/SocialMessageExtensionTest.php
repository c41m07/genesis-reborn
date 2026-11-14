<?php
namespace App\Tests\Twig;

use App\Entity\SocialMessage;
use App\Twig\SocialMessageExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class SocialMessageExtensionTest extends TestCase
{
    private function render(string $content, string $format = 'markdown'): string
    {
        $message = new SocialMessage();$message->content = $content;$message->contentFormat = $format;
        return (new SocialMessageExtension())->render($message);
    }

    public function testApprovedFormattingAndLineBreaks(): void
    {
        $html = $this->render("**Gras** et *italique*\nsuite\n\n- un\n- deux\n\n1. premier\n2. second\n\n> citation\n\n`code`\n\n```php\n<script>code</script>\n```");
        foreach (['<strong>Gras</strong>', '<em>italique</em>', "<br>\nsuite", '<ul>', '<ol>', '<blockquote>', '<code>code</code>', '<pre><code', '&lt;script&gt;code&lt;/script&gt;'] as $fragment) self::assertStringContainsString($fragment, $html);
        self::assertStringNotContainsString('<script>', $html);
    }

    public function testOldMessagesAndUnknownFormatsStayPlain(): void
    {
        foreach (['plain', 'unknown'] as $format) {
            $html = $this->render("**Ancien**\n<script>alert(1)</script>", $format);
            self::assertStringContainsString('**Ancien**', $html);
            self::assertStringContainsString('&lt;script&gt;', $html);
            self::assertStringContainsString("\n", $html);
            self::assertStringNotContainsString('<strong>', $html);
        }
    }

    public function testOnlyApprovedMarkupIsRendered(): void
    {
        $html = $this->render("# Titre\n\n---\n\n<table><tr><td onclick=alert(1)>texte</td></tr></table>\n\n![Image](https://example.test/pixel.png)\n\n<svg/onload=alert(1)>");
        foreach (['<h1', '<hr', '<table', '<img', '<svg', '<iframe'] as $tag) self::assertStringNotContainsString($tag, $html);
        self::assertStringContainsString('# Titre', $html);
        self::assertStringContainsString('Image', $html);
        self::assertStringNotContainsString('pixel.png', $html);
    }

    public function testHttpLinksEscapeAttributesAndPreventReferrerLeaks(): void
    {
        foreach (['http://example.test', 'https://example.test/?a=1&b=2', 'HTTPS://example.test/path'] as $url) {
            $html = $this->render('[Lien]('.$url.')');
            self::assertStringContainsString('<a href="', $html);
            self::assertStringContainsString('rel="nofollow ugc noreferrer"', $html);
        }
        $html = $this->render('[Lien](https://example.test "&quot; onmouseover=alert(1)")');
        self::assertStringNotContainsString('onmouseover=', $html);
    }

    #[DataProvider('forbiddenLinks')]
    public function testForbiddenProtocolsAndObfuscationNeverCreateLinks(string $content): void
    {
        $html = $this->render($content);
        self::assertStringNotContainsString('<a ', $html);
        self::assertStringNotContainsString('<img', $html);
        self::assertStringNotContainsString('<script', $html);
    }

    public static function forbiddenLinks(): iterable
    {
        foreach (['javascript:alert%281%29', 'JaVaScRiPt:alert%281%29', 'javascript&#58;alert%281%29', 'java&#x09;script:alert%281%29', 'data:text/html;base64,PHNjcmlwdD4=', 'vbscript:msgbox%281%29', 'file:///etc/passwd', 'mailto:player@example.test', 'ftp://example.test', '//example.test/path', '/messages', '#ancre', 'https:\\example.test'] as $url) yield $url => ['[Lien]('.$url.')'];
        yield 'html hostile' => ['<a href="javascript:alert(1)">clic</a><img src=x onerror=alert(1)>'];
    }

    public function testPathologicalInputStaysBounded(): void
    {
        $html = $this->render(str_repeat('> ', 500).str_repeat('*', 1000));
        self::assertLessThan(20000, strlen($html));
    }
}
