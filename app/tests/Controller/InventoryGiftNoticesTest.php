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

    public function testBoxesHaveIndependentReadStateAndFollowTheAvailableSlot(): void
    {
        $owner = $this->player('BoxOwner');
        $member = $owner->currentPlayerUniverse;
        $grants = self::getContainer()->get(\App\Service\Boost\BoostGrantService::class);
        $box = ['format' => 'convoy', 'resources' => ['metal' => 1, 'nano' => 0, 'antimatter' => 0, 'hydrogen' => 0]];
        $grants->awardEvent($member, 'first-box', $box);
        $grants->awardEvent($member, 'second-box', $box);
        // Remplir les autres emplacements afin de passer par la vraie mise en attente.
        for ($slot = 2; $slot < $grants->inventoryCapacity(); ++$slot) {
            $this->seedBoost($owner, 'slot-'.$slot, kind: 'boost', unread: false);
        }
        $grants->awardEvent($member, 'waiting-box', $box);

        $this->browser->loginUser($owner);
        $overview = $this->browser->request('GET', '/boosts');
        self::assertSelectorTextContains('.inventory-gift-count', '3');
        $page = $this->browser->click($overview->filter('nav a[href="/inventaire/boites"]')->link());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.resource-box-card .inventory-new-label', '2 nouveaux');
        self::assertSelectorExists('[data-page="boxes"][hidden]');
        self::assertSelectorTextContains('[data-page="pending"]', '1 nouveau');
        self::assertSelectorTextContains('.inventory-gift-count', '1');

        $form = $page->filter('.resource-box-form')->form();
        $planet = $this->entities->getRepository(\App\Entity\Planet::class)->findOneBy(['owner' => $member]);
        $this->entities->getConnection()->executeStatement(
            "UPDATE planet_resource SET current_stock = 0, last_updated_at = ? WHERE planet_id = ? AND resource_type = 'metal'",
            [$this->now->format('Y-m-d H:i:s'), $planet->id],
        );
        $form['planet_id'] = (string) $planet->id;
        $this->browser->submit($form);
        $afterOpening = $this->browser->followRedirect();
        self::assertSame([], $afterOpening->filter('.flash.error')->each(static fn ($notice) => $notice->text()));
        // Le plus ancien exemplaire, déjà vu, est ouvert. La boîte en attente prend sa place.
        self::assertSelectorTextContains('.resource-box-card .inventory-new-label', 'Nouveau');
        self::assertSelectorExists('.inventory-gift-count[hidden]');
        self::assertSelectorExists('[data-page="pending"][hidden]');
        $form = $afterOpening->filter('.resource-box-form')->form();
        $form['planet_id'] = (string) $planet->id;
        $this->browser->submit($form);
        $this->browser->followRedirect();
        self::assertSame(0, $this->giftCount());
        $this->browser->request('GET', '/inventaire/boites');
        self::assertSelectorExists('.inventory-gift-count[hidden]');
        self::assertSelectorNotExists('.resource-box-card .inventory-new-label');
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
