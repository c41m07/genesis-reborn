<?php

namespace App\Tests\Integration;

use App\Entity\{Player, Universe};
use App\Service\Event\GameEventService;
use App\Service\Colony\ResourceService;
use App\Service\Player\RegistrationService;
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class GameEventTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private MockClock $clock;
    private Player $admin;
    private Universe $universe;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
        $this->clock = new MockClock('2026-11-05T12:00:00Z');
        self::getContainer()->set('clock', $this->clock);
        $data = new RegistrationData();
        $data->email = 'game-event@example.test';
        $data->username = 'GameEventAdmin';
        $data->plainPassword = 'A-test-password-2026';
        $this->admin = self::getContainer()->get(RegistrationService::class)->register($data);
        $this->universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($this->admin, $this->universe->id);
    }

    public function testCreatesValidEventAndRejectsInvalidDates(): void
    {
        $event = self::getContainer()->get(GameEventService::class)->save($this->admin, null, $this->data());
        self::assertSame('Production doublée', $event->name);
        self::assertSame(100.0, (float) $event->parameters['percentage']);

        $invalid = $this->data();
        $invalid['ends_at'] = '2026-11-05T11:00';
        $this->expectException(\DomainException::class);
        self::getContainer()->get(GameEventService::class)->save($this->admin, null, $invalid);
    }

    public function testDraftIsHiddenAndActiveEventIsVisibleToPlayer(): void
    {
        $events = self::getContainer()->get(GameEventService::class);
        $events->save($this->admin, null, $this->data(['status' => 'draft']));
        self::assertCount(0, $events->visibleFor($this->admin->currentPlayerUniverse));
        $events->save($this->admin, null, $this->data(['status' => 'active']));
        self::assertCount(1, $events->visibleFor($this->admin->currentPlayerUniverse));
        $hidden = $this->data(['name' => 'Annonce cachée', 'type' => 'announcement', 'status' => 'active', 'visible' => false, 'parameters' => []]);
        $events->save($this->admin, null, $hidden);
        self::assertCount(1, $events->visibleFor($this->admin->currentPlayerUniverse));
    }

    public function testActiveProductionBonusAppliesAndExpiredEventDoesNot(): void
    {
        $service = self::getContainer()->get(GameEventService::class);
        $service->save($this->admin, null, $this->data(['status' => 'active']));
        self::assertEqualsWithDelta(2.0, $service->productionMultiplier($this->universe), 0.000001);

        $this->clock->sleep(7200);
        self::assertEqualsWithDelta(1.0, $service->productionMultiplier($this->universe), 0.000001);
    }

    public function testProductionBonusChangesTheServerCalculatedRate(): void
    {
        $planet = $this->em->getRepository(\App\Entity\Planet::class)->findOneBy(['owner' => $this->admin->currentPlayerUniverse]);
        $resources = self::getContainer()->get(ResourceService::class);
        $baseRate = $resources->rate($planet, 'metal', 0, []);
        self::getContainer()->get(GameEventService::class)->save($this->admin, null, $this->data());
        $boostedRate = $resources->rate($planet, 'metal', 0, []);
        self::assertGreaterThan(0, $baseRate);
        self::assertEqualsWithDelta($baseRate * 2, $boostedRate, 0.000001);
    }

    private function data(array $override = []): array
    {
        return array_replace([
            'name' => 'Production doublée', 'description' => 'Événement de test', 'type' => 'production_bonus', 'status' => 'active',
            'starts_at' => '2026-11-05T11:00', 'ends_at' => '2026-11-05T13:00', 'universe_id' => $this->universe->id,
            'parameters' => ['percentage' => 100], 'visible' => true,
        ], $override);
    }
}
