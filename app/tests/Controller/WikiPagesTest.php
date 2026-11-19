<?php

namespace App\Tests\Controller;

use App\Entity\Player;
use App\Service\Wiki\WikiManual;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class WikiPagesTest extends WebTestCase
{
    private $client;
    private Player $player;

    protected function setUp(): void
    {
        $this->client = self::createClient([], ['HTTP_HOST'=>'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $schema = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        $this->player = new Player();
        $this->player->username = 'WikiPages';
        $this->player->email = 'wiki-pages@example.test';
        $this->player->password = bin2hex(random_bytes(32));
        $this->player->emailVerifiedAt = new \DateTimeImmutable();
        $em->persist($this->player);
        $em->flush();
    }

    public function testPagesRequireLoginButNoUniverseMembership(): void
    {
        foreach (['/wiki','/wiki/chapitres/missions','/wiki/recherche?q=carburant','/wiki/references/regles'] as $route) {
            $this->client->request('GET', $route);
            self::assertResponseRedirects('http://genesis-reborn.localhost/connexion');
        }
        $this->client->loginUser($this->player);
        $crawler = $this->client->request('GET', '/wiki');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('h1', 'Wiki du jeu');
        self::assertSelectorCount(0, '.wiki-chapter');
        self::assertSelectorCount(0, '.wiki-formula');
        self::assertSelectorExists('#wiki-missions.wiki-chapter-card a[href="/wiki/chapitres/missions"]');
        self::assertSelectorExists('#wiki-title-missions');
        $manual = self::getContainer()->get(WikiManual::class);
        self::assertSelectorCount(count($manual->chapters()), '.wiki-chapter-card');
        foreach ($manual->chapters() as $chapter) {
            $crawler = $this->client->request('GET', '/wiki/chapitres/'.$chapter['id']);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', $chapter['title']);
            self::assertSelectorCount(1, '.wiki-chapter');
            self::assertSelectorExists('.wiki-summary a[aria-current="page"][href="/wiki/chapitres/'.$chapter['id'].'"]');
            self::assertSelectorExists('meta[name="robots"][content="noindex"]');
            self::assertSelectorTextContains('title', $chapter['title']);
            self::assertSame($chapter['id'], $crawler->filter('#wiki-chapter-choice option[selected]')->attr('value'));
            self::assertSelectorExists('a[href="/wiki"][aria-current="location"]');
        }
        self::assertNull($this->player->currentPlayerUniverse);
    }

    public function testHomePreviewsAreBoundedAndOnlyPreparedWhenRequested(): void
    {
        $this->client->loginUser($this->player);
        $crawler = $this->client->request('GET', '/wiki');
        $manual = self::getContainer()->get(WikiManual::class);
        foreach ($manual->chapters() as $chapter) {
            self::assertArrayNotHasKey('preview', $chapter);
            self::assertArrayNotHasKey('paragraphs', $chapter);
            self::assertArrayNotHasKey('calculations', $chapter);
        }
        foreach ($manual->chapters(true) as $chapter) {
            $paragraph = $manual->chapter($chapter['id'])['paragraphs'][0];
            self::assertLessThanOrEqual(161, mb_strlen($chapter['preview']));
            self::assertStringStartsWith(mb_substr($paragraph, 0, 160), $chapter['preview']);
            self::assertSame($chapter['preview'], $crawler->filter('#wiki-'.$chapter['id'].' .wiki-chapter-preview')->text());
            self::assertSame('Lire le chapitre '.$chapter['title'], $crawler->filter('#wiki-'.$chapter['id'].' > a')->attr('aria-label'));
        }
        self::assertSelectorCount(0, '.wiki-calculation');
        self::assertNull($this->player->currentPlayerUniverse);
    }

    public function testLegacyQueryParametersRedirectAndSearchLinksToDedicatedChapters(): void
    {
        $this->client->loginUser($this->player);
        $this->client->request('GET', '/wiki?chapter=missions');
        self::assertResponseRedirects('/wiki/chapitres/missions');
        $this->client->request('GET', '/wiki?q=colonisation');
        self::assertResponseRedirects('/wiki/recherche?q=colonisation');
        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.wiki-search-result a[href="/wiki/chapitres/colonisation"]');
        self::assertSelectorCount(0, '.wiki-chapter');
        self::assertSelectorCount(0, '.wiki-formula');
        $this->client->request('GET', '/wiki/recherche?q=aucunchapitrexyz');
        self::assertSelectorTextContains('[role="status"]', '0 chapitre');
        self::assertSelectorTextContains('.wiki-chapters', 'Aucun chapitre ne correspond.');
        $this->client->request('GET', '/wiki/recherche?q='.urlencode('<script>alert(1)</script>'));
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('#wiki-top script');
        self::assertSelectorExists('input[name="q"][value="<script>alert(1)</script>"]');
        $crawler = $this->client->request('GET', '/wiki/recherche?q='.str_repeat('x', 200));
        self::assertSame(150, mb_strlen($crawler->filter('input[name=q]')->attr('value')));
        $this->client->request('GET', '/wiki/recherche');
        self::assertSelectorCount(0, '.wiki-search-result');
        self::assertSelectorTextContains('h1', 'Recherche');
    }

    public function testUnknownChaptersAndReferencePathsAreRejected(): void
    {
        $this->client->loginUser($this->player);
        foreach (['/wiki/chapitres/inconnu','/wiki?chapter=inconnu','/wiki/references/inconnu','/wiki/references/%2E%2E%2F.env'] as $route) {
            $this->client->request('GET', $route);
            self::assertResponseStatusCodeSame(404);
        }
        foreach (['regles'=>'Règles de référence','decisions'=>'Décisions du projet'] as $source=>$title) {
            $this->client->request('GET', '/wiki/references/'.$source);
            self::assertResponseIsSuccessful();
            self::assertSelectorTextSame('h1', $title);
            self::assertSelectorExists('.wiki-space-navigation a[aria-current="page"][href="/wiki/references/'.$source.'"]');
        }
    }
}
