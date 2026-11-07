<?php
namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class PublicSeoTest extends WebTestCase
{
    public function testPublicMetadataUsesConfiguredOriginAndNeverTheRequestHost(): void
    {
        $client = static::createClient([], ['HTTP_HOST' => 'untrusted.example.test']);
        $origin = rtrim($_ENV['DEFAULT_URI'], '/');
        foreach (['/' => 'Genesis Reborn · Jeu de stratégie spatiale', '/inscription' => 'Inscription · Genesis Reborn'] as $path => $title) {
            $crawler = $client->request('GET', $path);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('title', $title);
            self::assertSelectorCount(1, 'meta[name="description"]');
            self::assertSelectorNotExists('meta[name="robots"][content*="noindex"]');
            self::assertSelectorCount(1, 'link[rel="canonical"]');
            self::assertSame($origin.$path, $crawler->filter('link[rel="canonical"]')->attr('href'));
            self::assertSelectorCount(1, 'meta[property="og:title"]');
            self::assertSelectorCount(1, 'meta[property="og:description"]');
            self::assertSame($origin.'/art/genesis-reborn-emblem.webp', $crawler->filter('meta[property="og:image"]')->attr('content'));
            self::assertFalse($client->getResponse()->headers->has('X-Robots-Tag'));
        }
        $client->request('GET', '/');
        self::assertSelectorExists('img[alt="Genesis Reborn"]');
        self::assertSelectorExists('meta[name="description"][content="Genesis Reborn, jeu de stratégie spatiale par navigateur : bâtis ta colonie, explore des systèmes inconnus et forge ton empire."]');
    }

    public function testLoginIsNoindexAndPrivatePagesStillRequireAuthentication(): void
    {
        $client = static::createClient();
        $client->request('GET', '/connexion');
        self::assertResponseIsSuccessful();
        self::assertSelectorCount(1, 'meta[name="robots"][content="noindex"]');
        foreach (['/planetes', '/wiki', '/admin'] as $path) {
            $client->request('GET', $path);
            self::assertResponseRedirects('http://localhost/connexion');
        }
    }

    public function testCrawlersOnlyListCanonicalPublicPages(): void
    {
        $client = static::createClient();
        $origin = rtrim($_ENV['DEFAULT_URI'], '/');
        $client->request('GET', '/robots.txt');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'text/plain; charset=UTF-8');
        self::assertSame("User-agent: *\nAllow: /\nSitemap: ".$origin."/sitemap.xml\n", $client->getResponse()->getContent());
        $client->request('GET', '/sitemap.xml');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'application/xml; charset=UTF-8');
        $xml = new \DOMDocument();
        self::assertTrue($xml->loadXML($client->getResponse()->getContent()));
        self::assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $xml->documentElement->namespaceURI);
        $urls = array_map(static fn ($node) => $node->textContent, iterator_to_array($xml->getElementsByTagName('loc')));
        self::assertSame([$origin.'/', $origin.'/inscription'], $urls);
    }
}
