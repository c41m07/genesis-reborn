<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\{Player, PlayerBoost, PlayerUniverse, Universe};
use App\Service\Boost\TimedBoostEffects;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class InventoryGiftNoticesTest extends WebTestCase
{
    private KernelBrowser $browser;
    private EntityManagerInterface $entities;
    private \DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->browser = self::createClient([], ['HTTP_HOST' => 'genesis-reborn.localhost']);
        $this->browser->disableReboot();
        $this->entities = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->entities->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée.');
        }
        $schema = new SchemaTool($this->entities);
        $metadata = $this->entities->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->now = self::getContainer()->get(ClockInterface::class)->now()->setTimezone(new \DateTimeZone('UTC'));
    }

    public function testNewGiftIsCountedUntilAcknowledgedWithoutOpeningIt(): void
    {
        $owner = $this->player('GiftOwner');
        $stranger = $this->player('GiftStranger');
        $visible = $this->seedBoost($owner, 'first-gift');
        $this->seedBoost($owner, 'expired-gift', earnedAt: $this->now->modify('-604800 seconds'));
        $this->seedBoost($owner, 'used-gift', status: 'used');
        $this->seedBoost($owner, 'ordinary-boost', kind: 'boost', unread: false);
        $this->seedBoost($stranger, 'unrelated-gift');
        $otherUniverse = $this->entities->getRepository(Universe::class)->findOneBy(['slug' => 'turbo-1']);
        $service = self::getContainer()->get(UniverseService::class);
        $service->select($owner, $otherUniverse->id);
        $this->seedBoost($owner, 'other-universe');
        $originalUniverse = $this->entities->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        $service->select($owner, $originalUniverse->id);

        $this->browser->loginUser($owner);
        $crawler = $this->browser->request('GET', '/boosts');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.inventory-gift-count:not([hidden])', '1');
        self::assertSelectorTextContains('#inventory-gift-description', '1 nouveau cadeau');
        self::assertSelectorExists('.inventory-gift-mobile-indicator:not([hidden])');
        self::assertSame(1, $this->giftCount());
        $form = $crawler->selectButton('Marquer les cadeaux comme vus')->form();

        $new = $this->seedBoost($owner, 'later-gift', status: 'pending');
        // Une ancienne vue ne doit pas acquitter le cadeau attribué après ce rendu.
        $this->browser->submit($form);
        self::assertResponseRedirects('/boosts', 303);
        $this->browser->followRedirect();
        self::assertSame(1, $this->giftCount());
        $connection = $this->entities->getConnection();
        self::assertSame('available', $connection->fetchOne('SELECT status FROM player_boost WHERE id = ?', [$visible]));
        self::assertEquals(0, $connection->fetchOne('SELECT gift_unread FROM player_boost WHERE id = ?', [$visible]));
        self::assertEquals(1, $connection->fetchOne('SELECT gift_unread FROM player_boost WHERE id = ?', [$new]));

        $this->browser->request('POST', '/inventaire/cadeaux/consultation', ['page' => 'boosts', 'through' => '{"daily":999999}', '_token' => 'invalide']);
        self::assertResponseStatusCodeSame(403);
        self::assertSame(1, $this->giftCount());
        $this->browser->request('GET', '/inventaire/recompenses');
        $this->browser->submitForm('Marquer les cadeaux comme vus');
        self::assertResponseRedirects('/inventaire/recompenses', 303);
        $this->browser->followRedirect();
        self::assertSelectorExists('.inventory-gift-count[hidden]');
        self::assertSelectorNotExists('.inventory-gift-notice');
        self::assertSame(0, $this->giftCount());

        $owner = $this->entities->find(Player::class, $owner->id);
        self::getContainer()->get(UniverseService::class)->select($owner, $otherUniverse->id);
        $this->browser->loginUser($owner);
        self::assertSame(1, $this->giftCount());
        $this->browser->loginUser($stranger);
        self::assertSame(1, $this->giftCount());
    }

    private function giftCount(): int
    {
        $this->browser->request('GET', '/messages/non-lus');
        self::assertResponseIsSuccessful();
        $headers = $this->browser->getResponse()->headers;
        self::assertTrue($headers->hasCacheControlDirective('private'));
        self::assertTrue($headers->hasCacheControlDirective('no-store'));
        $data = json_decode($this->browser->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        return $data['inventoryGiftCount'];
    }

    private function seedBoost(
        Player $owner,
        string $key,
        string $status = 'available',
        string $kind = TimedBoostEffects::KIND,
        bool $unread = true,
        ?\DateTimeImmutable $earnedAt = null,
    ): int {
        $item = new PlayerBoost();
        $item->playerUniverse = $this->entities->getReference(PlayerUniverse::class, $owner->currentPlayerUniverse->id);
        $item->sourceType = $kind === TimedBoostEffects::KIND ? 'daily_reward' : 'event';
        $item->sourceKey = $key;
        $item->status = $status;
        $item->kind = $kind;
        // Un cadeau quotidien possède le même effet enregistré que lors d’une attribution réelle.
        if ($kind === TimedBoostEffects::KIND) {
            $item->payload = ['effect' => TimedBoostEffects::CIVIL_FUEL];
        }
        $item->giftUnread = $unread;
        $item->earnedAt = $earnedAt ?? $this->now;
        $this->entities->persist($item);
        $this->entities->flush();

        return $item->id;
    }

    private function player(string $name): Player
    {
        $data = new RegistrationData();
        $data->username = $name;
        $data->email = strtolower($name).'@example.test';
        $data->plainPassword = 'Browser-test-2026!';
        $player = self::getContainer()->get(RegistrationService::class)->register($data);
        $universe = $this->entities->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);

        return $player;
    }
}
