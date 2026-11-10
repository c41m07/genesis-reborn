<?php
namespace App\Tests\Controller;

use App\Entity\{Notification,OutboxMessage,Player,SocialMessage,Universe};
use App\Repository\GameRepository;
use App\Service\Player\{DiplomacyService,RegistrationService,RelationResolver,SocialMessageService};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class GlobalMessageTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;
    private Player $sender;
    private Player $reader;

    protected function setUp(): void
    {
        $this->client = static::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->client->disableReboot();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        static::getContainer()->get(UniverseImporter::class)->import();
        $this->sender = $this->player('GlobalSender');
        $this->reader = $this->player('GlobalReader');
    }

    private function player(string $name, string $slug = 'omega-1'): Player
    {
        $data = new RegistrationData();
        $data->email = strtolower($name) . '@example.test';
        $data->username = $name;
        $data->plainPassword = 'A-test-password-2026';
        $player = static::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $slug]);
        static::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        static::getContainer()->get('limiter.social_message')->create('player-' . $player->id)->reset();
        return $player;
    }

    public function testAnonymousCannotReadOrSend(): void
    {
        $this->client->request('GET', '/messages?channel=global');
        self::assertResponseRedirects('/connexion');
        $this->client->request('POST', '/messages?channel=global', ['action' => 'global', 'content' => 'Interdit']);
        self::assertResponseRedirects('/connexion');
        self::assertSame(0, $this->em->getRepository(SocialMessage::class)->count());
    }

    public function testGlobalSendAndReadStaySilentAndInsideUniverse(): void
    {
        $outsider = $this->player('GlobalOther', 'turbo-1');
        $service = static::getContainer()->get(SocialMessageService::class);
        $service->sendGlobal($outsider->currentPlayerUniverse, 'Autre univers');
        $notifications = $this->em->getRepository(Notification::class)->count();
        $outbox = $this->em->getRepository(OutboxMessage::class)->count();
        $this->client->loginUser($this->sender);
        $crawler = $this->client->request('GET', '/messages?channel=global&with=' . $this->reader->currentPlayerUniverse->id);
        self::assertResponseIsSuccessful();
        self::assertSelectorTextSame('.message-pane h2', 'Global');
        $this->client->submit($crawler->selectButton('Envoyer')->form(['content' => '  <script>global</script>  ']));
        self::assertResponseRedirects('/messages?channel=global');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.message-thread', '<script>global</script>');
        self::assertSelectorNotExists('.message-thread script');

        $this->client->loginUser($this->reader);
        $this->client->request('GET', '/messages?channel=global');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.message-thread', '<script>global</script>');
        self::assertSelectorTextNotContains('.message-thread', 'Autre univers');
        self::assertSelectorExists('a[href="/messages?channel=global"][aria-current="page"]');
        self::assertSelectorNotExists('a[href="/messages?channel=global"] .message-unread-dot');
        self::assertSelectorNotExists('.inbox-heading .badge');
        self::assertSelectorExists('body[data-menu-notifications-messages-value="0"]');
        self::assertSelectorNotExists('[data-message-notifications-target="badge"]:not([hidden])');

        $message = $this->em->getRepository(SocialMessage::class)->findOneBy(['sender' => $this->sender->currentPlayerUniverse]);
        self::assertNull($message->readAt);
        self::assertNull($message->recipient);
        self::assertNull($message->guild);
        self::assertSame($notifications, $this->em->getRepository(Notification::class)->count());
        self::assertSame($outbox, $this->em->getRepository(OutboxMessage::class)->count());
        self::assertSame([], static::getContainer()->get(GameRepository::class)->messageContacts($this->sender->currentPlayerUniverse));
        self::assertSame([], static::getContainer()->get(GameRepository::class)->messageContacts($this->reader->currentPlayerUniverse));
        $this->client->request('GET', '/messages/non-lus?channel=global&with=' . $this->sender->currentPlayerUniverse->id);
        $payload = json_decode($this->client->getResponse()->getContent(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $payload['unread']);
        self::assertStringNotContainsString('message-unread-dot', $payload['conversations']);
        self::assertStringContainsString('aria-current="page"', $payload['conversations']);
    }

    public function testGlobalPrecedesGuildAndPreservesOtherUnreadCounts(): void
    {
        $diplomacy = static::getContainer()->get(DiplomacyService::class);
        $diplomacy->create($this->sender->currentPlayerUniverse, 'Global test');
        $guild = static::getContainer()->get(RelationResolver::class)->guild($this->sender->currentPlayerUniverse);
        $diplomacy->join($this->reader->currentPlayerUniverse, $guild->id);
        $service = static::getContainer()->get(SocialMessageService::class);
        $service->sendPrivate($this->sender->currentPlayerUniverse, $this->reader->currentPlayerUniverse->id, 'Privé');
        $service->sendGuild($this->sender->currentPlayerUniverse, 'Guilde');
        $service->sendGlobal($this->sender->currentPlayerUniverse, 'Global');
        $this->client->loginUser($this->reader);
        $crawler = $this->client->request('GET', '/messages?channel=global');
        self::assertSame('/messages?channel=global', $crawler->filter('.inbox-list > a')->eq(0)->attr('href'));
        self::assertSame('/messages?channel=guild', $crawler->filter('.inbox-list > a')->eq(1)->attr('href'));
        self::assertSelectorExists('body[data-menu-notifications-messages-value="2"]');
        self::assertSame(1, $service->unreadPrivateCount($this->reader->currentPlayerUniverse));
        self::assertSame(1, $service->unreadGuildCount($this->reader->currentPlayerUniverse));
        $this->client->request('GET', '/messages/non-lus?channel=global');
        self::assertSame(2, json_decode($this->client->getResponse()->getContent(), true)['unread']);
        $reader = $this->em->find(Player::class, $this->reader->id)->currentPlayerUniverse;
        $guild = $this->em->find(\App\Entity\Guild::class, $guild->id);
        $service = static::getContainer()->get(SocialMessageService::class);
        self::assertCount(1, $service->privateConversation($reader, $this->sender->currentPlayerUniverse->id));
        self::assertCount(1, $service->guildConversation($reader, $guild));
        self::assertSame(0, $service->unreadPrivateCount($reader));
        self::assertSame(0, $service->unreadGuildCount($reader));
    }

    public function testCsrfAndContentValidation(): void
    {
        $this->client->loginUser($this->sender);
        $this->client->request('POST', '/messages?channel=global', ['action' => 'global', '_token' => 'invalid', 'content' => 'Interdit']);
        self::assertResponseStatusCodeSame(403);
        foreach (['   ', str_repeat('é', 2001)] as $content) {
            $crawler = $this->client->request('GET', '/messages?channel=global');
            $form = $crawler->selectButton('Envoyer')->form();
            $this->client->request('POST', '/messages?channel=global', ['action' => 'global', '_token' => $form['_token']->getValue(), 'content' => $content]);
            self::assertResponseRedirects('/messages?channel=global');
            $this->client->followRedirect();
            self::assertSelectorTextContains('.flash.error', 'entre 1 et 2 000 caractères');
        }
        self::assertSame(0, $this->em->getRepository(SocialMessage::class)->count());
    }

    public function testGlobalSharesPrivateMessageRateLimit(): void
    {
        $service = static::getContainer()->get(SocialMessageService::class);
        $sender = $this->sender->currentPlayerUniverse;
        for ($i = 0; $i < 9; ++$i) {
            $service->sendPrivate($sender, $this->reader->currentPlayerUniverse->id, 'Privé ' . $i);
        }
        $service->sendGlobal($sender, str_repeat('é', 2000));
        self::assertCount(1, $service->globalConversation($sender));
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Limite atteinte');
        $service->sendGlobal($sender, 'Envoi en trop');
    }

    public function testGlobalHistoryIsBoundedAndChronological(): void
    {
        for ($i = 0; $i < 105; ++$i) {
            $message = new SocialMessage();
            $message->sender = $this->sender->currentPlayerUniverse;
            $message->content = 'Message ' . $i;
            $message->createdAt = new \DateTimeImmutable('2026-11-09T12:00:00Z');
            $this->em->persist($message);
        }
        $this->em->flush();
        $thread = static::getContainer()->get(SocialMessageService::class)->globalConversation($this->reader->currentPlayerUniverse);
        self::assertCount(100, $thread);
        self::assertSame('Message 5', $thread[0]->content);
        self::assertSame('Message 104', $thread[99]->content);
    }

    public function testClosedUniverseRejectsGlobalAccess(): void
    {
        $membership = $this->sender->currentPlayerUniverse;
        $membership->universe->status = 'closed';
        $this->em->flush();
        $this->expectException(\DomainException::class);
        static::getContainer()->get(SocialMessageService::class)->globalConversation($membership);
    }
}
