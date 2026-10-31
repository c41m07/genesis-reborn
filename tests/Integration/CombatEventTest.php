<?php
namespace App\Tests\Integration;

use App\Entity\CombatEvent;
use App\Entity\CombatLog;
use App\Entity\CombatParticipant;
use App\Entity\Fleet;
use App\Entity\FleetShip;
use App\Entity\Planet;
use App\Entity\PlanetDefense;
use App\Entity\Player;
use App\Entity\Universe;
use App\Service\Combat\CombatEventService;
use App\Service\Universe\UniverseImporter;
use App\Service\Universe\UniverseService;
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\MockClock;

final class CombatEventTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $params = $this->em->getConnection()->getParams();
        if (($params['host'] ?? '') !== 'database-test' || ($params['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base de test non isolée : test refusé.');
        }
        $tool = new SchemaTool($this->em);
        $meta = $this->em->getMetadataFactory()->getAllMetadata();
        $tool->dropSchema($meta);
        $tool->createSchema($meta);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    private function clock(): MockClock
    {
        $clock = new MockClock('2026-10-21T12:00:00Z');
        self::getContainer()->set('clock', $clock);

        return $clock;
    }

    private function player(string $name): Player
    {
        $data = new RegistrationData();
        $data->email = strtolower($name) . '@example.test';
        $data->username = $name;
        $data->plainPassword = 'A-test-password-2026';

        return self::getContainer()->get(\App\Service\Player\RegistrationService::class)->register($data);
    }

    private function join(Player $player): Planet
    {
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => 'omega-1']);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);

        return $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);
    }

    private function attackFleet(Player $player, Planet $origin, Planet $target, string $slug): Fleet
    {
        $fleet = new Fleet();
        $fleet->playerUniverse = $player->currentPlayerUniverse;
        $fleet->homePlanet = $origin;
        $fleet->destination = $target;
        $fleet->destinationSystem = $target->solarSystem;
        $fleet->status = 'traveling';
        $fleet->mission = 'attack';
        $fleet->travelSeconds = 60;
        $fleet->reservedFuel = 20;
        $fleet->cargo = ['hydrogen' => 20];
        $fleet->departureAt = new \DateTimeImmutable('2026-10-21T11:59:00Z');
        $fleet->arrivalAt = new \DateTimeImmutable('2026-10-21T12:00:00Z');
        $fleet->dispatchToken = 'test-' . $slug;
        $this->em->persist($fleet);

        $ship = new FleetShip();
        $ship->fleet = $fleet;
        $ship->slug = 'fighter';
        $ship->quantity = 1;
        $this->em->persist($ship);
        $this->em->flush();

        return $fleet;
    }

    public function testAttackArrivalsJoinOneWindowAndResolutionPersistsOnce(): void
    {
        $clock = $this->clock();
        $defender = $this->player('Defender');
        $target = $this->join($defender);
        $attackerA = $this->player('AttackerA');
        $originA = $this->join($attackerA);
        $attackerB = $this->player('AttackerB');
        $originB = $this->join($attackerB);

        $turret = new PlanetDefense();
        $turret->planet = $target;
        $turret->slug = 'ground_turret';
        $turret->quantity = 1;
        $this->em->persist($turret);
        $this->em->flush();

        $garrison = new Fleet();
        $garrison->playerUniverse = $defender->currentPlayerUniverse;
        $garrison->homePlanet = $target;
        $garrison->status = 'idle';
        $garrison->mission = 'idle';
        $this->em->persist($garrison);
        $garrisonShip = new FleetShip();
        $garrisonShip->fleet = $garrison;
        $garrisonShip->slug = 'battleship';
        $garrisonShip->quantity = 1;
        $this->em->persist($garrisonShip);
        $this->em->flush();

        $fleetA = $this->attackFleet($attackerA, $originA, $target, 'a');
        $fleetB = $this->attackFleet($attackerB, $originB, $target, 'b');
        $service = self::getContainer()->get(CombatEventService::class);
        $manager = self::getContainer()->get(\App\Manager\GameManager::class);

        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($fleetA)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($event);
        self::assertSame('defending', $garrison->status);
        self::assertEquals(600, $event->resolveAt->getTimestamp() - $event->startedAt->getTimestamp());

        $clock->sleep(300);
        $fleetB->arrivalAt = $clock->now();
        $this->em->flush();
        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($fleetB)));
        self::assertSame(3, $this->em->getRepository(CombatParticipant::class)->count(['combatEvent' => $event]));
        $participantB = $this->em->getRepository(CombatParticipant::class)->findOneBy(['combatEvent' => $event, 'fleet' => $fleetB]);
        self::assertSame(6, $participantB->arrivalRound);

        $clock->sleep(300);
        $service->resolve($event->id, $event->dispatchToken);
        $service->resolve($event->id, $event->dispatchToken);

        self::assertSame('resolved', $event->status);
        self::assertSame(1, $this->em->getRepository(CombatLog::class)->count(['combatEvent' => $event]));
        self::assertContains($fleetA->status, ['returning', 'completed']);
        self::assertContains($fleetB->status, ['returning', 'completed']);
        self::assertSame('idle', $garrison->status);
    }
}
