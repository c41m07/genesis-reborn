<?php
namespace App\Tests\Controller;

use App\Entity\{Notification,Player,SocialMessage,Universe};
use App\Service\Player\{DiplomacyService,RegistrationService,RelationResolver,SocialMessageService};
use App\Service\Universe\{UniverseImporter,UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

final class SocialMessageSubmissionTest extends WebTestCase
{
    private KernelBrowser $client;
    private Player $sender;
    private int $peerId;

    private function em(): EntityManagerInterface { return self::getContainer()->get(EntityManagerInterface::class); }

    protected function setUp(): void
    {
        $this->client = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $em = $this->em();$params = $em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') throw new \RuntimeException('Base de test non isolée.');
        $tool = new SchemaTool($em);$metadata = $em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($metadata);$tool->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->sender = $this->player('SubmissionSender');
        $this->peerId = $this->player('SubmissionPeer')->currentPlayerUniverse->id;
        self::getContainer()->get('limiter.social_message')->create('player-'.$this->sender->id)->reset();
    }

    private function player(string $name, string $universe = 'omega-1'): Player
    {
        $data = new RegistrationData();$data->username = $name;$data->email = strtolower($name).'@example.test';
        $data->plainPassword = bin2hex(random_bytes(16)).'aA1!';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->em()->getRepository(Universe::class)->findOneBy(['slug' => $universe]);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        return $player;
    }

    private function form(string $channel): array
    {
        if ($channel === 'guild') {
            $diplomacy = self::getContainer()->get(DiplomacyService::class);
            $diplomacy->create($this->sender->currentPlayerUniverse, 'Submission Guild', 'SUB');
            $guild = self::getContainer()->get(RelationResolver::class)->guild($this->sender->currentPlayerUniverse);
            $diplomacy->join($this->em()->find(\App\Entity\PlayerUniverse::class, $this->peerId), $guild->id);
        }
        $this->client->loginUser($this->sender);
        $url = $channel === 'private' ? '/messages?with='.$this->peerId : '/messages?channel='.$channel;
        $crawler = $this->client->request('GET', $url);
        self::assertResponseIsSuccessful();
        return [$url, $crawler->selectButton('Envoyer')->form(['content' => '**Message**'])->getPhpValues()];
    }

    public static function channels(): iterable { foreach (['private', 'guild', 'global'] as $channel) yield $channel => [$channel]; }

    #[DataProvider('channels')]
    public function testReplayedSubmissionCreatesOneMessageAndOneNotificationBatch(string $channel): void
    {
        [$url, $data] = $this->form($channel);
        $data['content_format'] = 'plain';
        $this->client->request('POST', $url, $data);self::assertResponseRedirects();
        $this->client->followRedirect();self::assertSelectorExists('.message-content strong');
        $notifications = $this->em()->getRepository(Notification::class)->count();
        $this->client->request('POST', $url, $data);self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSame(1, $this->em()->getRepository(SocialMessage::class)->count());
        self::assertSame($notifications, $this->em()->getRepository(Notification::class)->count());
        $message = $this->em()->getRepository(SocialMessage::class)->findOneBy(['submissionId' => $data['submission_id']]);
        self::assertSame('markdown', $message->contentFormat);
    }

    public function testSameTextInTwoNewFormsIsStillTwoIntentionalMessages(): void
    {
        [$url, $data] = $this->form('global');
        $this->client->request('POST', $url, $data);
        $crawler = $this->client->followRedirect();
        $this->client->submit($crawler->selectButton('Envoyer')->form(['content' => $data['content']]));
        $this->client->followRedirect();
        self::assertSame(2, $this->em()->getRepository(SocialMessage::class)->count());
    }

    public static function changedSubmissions(): iterable
    {
        foreach (['content', 'recipient', 'channel'] as $change) yield $change => [$change];
    }

    #[DataProvider('changedSubmissions')]
    public function testReusedIdCannotSendDifferentContentOrChangeChannel(string $change): void
    {
        $otherId = $change === 'recipient' ? $this->player('SubmissionOther')->currentPlayerUniverse->id : null;
        [$url, $data] = $this->form('private');
        $this->client->request('POST', $url, $data);$this->client->followRedirect();
        if ($change === 'channel') {
            $url = '/messages?channel=global';$crawler = $this->client->request('GET', $url);
            $data = $crawler->selectButton('Envoyer')->form(['content' => $data['content'], 'submission_id' => $data['submission_id']])->getPhpValues();
        } elseif ($change === 'content') $data['content'] = 'Autre message';
        else $data['recipient'] = $otherId;
        $this->client->request('POST', $url, $data);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'déjà servi');
        self::assertSame(1, $this->em()->getRepository(SocialMessage::class)->count());
    }

    public function testSubmissionIdIsScopedToItsSender(): void
    {
        [$url, $data] = $this->form('global');
        $this->client->request('POST', $url, $data);$this->client->followRedirect();
        $peer = $this->em()->find(\App\Entity\PlayerUniverse::class, $this->peerId)->player;
        $this->client->loginUser($peer);$crawler = $this->client->request('GET', $url);
        $this->client->submit($crawler->selectButton('Envoyer')->form(['content' => $data['content'], 'submission_id' => $data['submission_id']]));
        $this->client->followRedirect();
        self::assertSame(2, $this->em()->getRepository(SocialMessage::class)->count());
    }

    public function testMissingSubmissionIdDoesNotBypassProtection(): void
    {
        [$url, $data] = $this->form('global');unset($data['submission_id']);
        $this->client->request('POST', $url, $data);$this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'Formulaire expiré');
        self::assertSame(0, $this->em()->getRepository(SocialMessage::class)->count());
    }

    public function testNewFieldsCannotBypassCsrfOrUniverseIsolation(): void
    {
        $foreignId = $this->player('SubmissionForeign', 'turbo-1')->currentPlayerUniverse->id;
        [$url, $data] = $this->form('private');
        $invalid = $data;$invalid['_token'] = 'invalid';
        $this->client->request('POST', $url, $invalid);self::assertResponseStatusCodeSame(403);
        $data['recipient'] = $foreignId;
        $this->client->request('POST', $url, $data);$this->client->followRedirect();
        self::assertSelectorTextContains('.flash.error', 'Destinataire inaccessible');
        self::assertSame(0, $this->em()->getRepository(SocialMessage::class)->count());
    }

    public function testLegacyMessagesKeepMarkupAndSpacingLiteral(): void
    {
        $message = new SocialMessage();$message->sender = $this->sender->currentPlayerUniverse;
        $message->content = "**Ancien**  texte\n<script>ancien</script>";$message->createdAt = new \DateTimeImmutable();
        $this->em()->persist($message);$this->em()->flush();
        $this->client->loginUser($this->sender);$this->client->request('GET', '/messages?channel=global');
        self::assertStringContainsString("**Ancien**  texte\n", $this->client->getCrawler()->filter('.message-plain')->getNode(0)->textContent);
        self::assertSelectorTextContains('.message-plain', '<script>ancien</script>');
        self::assertSelectorNotExists('.message-content script');self::assertSelectorNotExists('.message-content strong');
    }

    public function testDuplicateIsNotCountedAgainstRateLimit(): void
    {
        $service = self::getContainer()->get(SocialMessageService::class);$sender = $this->sender->currentPlayerUniverse;
        $id = Uuid::v4()->toRfc4122();$service->sendGlobal($sender, 'Message', $id);
        for ($i = 0; $i < 20; ++$i) $service->sendGlobal($sender, 'Message', $id);
        for ($i = 0; $i < 9; ++$i) $service->sendGlobal($sender, 'Suivant '.$i, Uuid::v4()->toRfc4122());
        self::assertSame(10, $this->em()->getRepository(SocialMessage::class)->count());
    }

    public function testInvalidUtf8IsRejected(): void
    {
        $this->expectException(\DomainException::class);$this->expectExceptionMessage('caractères invalides');
        self::getContainer()->get(SocialMessageService::class)->sendGlobal($this->sender->currentPlayerUniverse, "\xff", Uuid::v4()->toRfc4122());
    }

    public function testMigrationPreservesExistingContentAndAssignsPlainFormat(): void
    {
        $db = $this->em()->getConnection();
        $db->executeStatement('DROP INDEX social_submission_unique ON social_message');
        $db->executeStatement('ALTER TABLE social_message DROP content_format, DROP submission_id');
        $content = "**Ancien**\n<script>texte</script>";
        $db->insert('social_message', ['sender_id' => $this->sender->currentPlayerUniverse->id, 'content' => $content, 'created_at' => '2026-11-14 12:00:00']);
        require_once dirname(__DIR__, 2).'/migrations/Version20261114151245.php';
        $migration = new \DoctrineMigrations\Version20261114151245($db, new \Psr\Log\NullLogger());
        $migration->up(new \Doctrine\DBAL\Schema\Schema());
        foreach ($migration->getSql() as $query) $db->executeStatement($query->getStatement());
        $row = $db->fetchAssociative('SELECT content, content_format, submission_id FROM social_message');
        self::assertSame($content, $row['content']);self::assertSame('plain', $row['content_format']);self::assertNull($row['submission_id']);
        self::assertTrue((new \Doctrine\ORM\Tools\SchemaValidator($this->em()))->schemaInSyncWithMetadata());
    }
}
