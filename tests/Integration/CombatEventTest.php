<?php
namespace App\Tests\Integration;

use App\Entity\CombatEvent;
use App\Entity\CombatLog;
use App\Entity\CombatParticipant;
use App\Entity\Notification;
use App\Entity\Fleet;
use App\Entity\FleetShip;
use App\Entity\Planet;
use App\Entity\PlanetDefense;
use App\Entity\PlanetBuilding;
use App\Entity\PlanetNpcShip;
use App\Entity\PlanetResource;
use App\Entity\PlayerTechnology;
use App\Manager\GameManager;
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
        // Ces scénarios testent le combat entre joueurs établis, hors protection de début de partie.
        $player->currentPlayerUniverse->joinedAt = new \DateTimeImmutable('2026-10-13T12:00:00Z');
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $player->currentPlayerUniverse]);
        $mine = $this->em->getRepository(PlanetBuilding::class)->findOneBy(['planet' => $planet, 'slug' => 'mine_metal']) ?? new PlanetBuilding();
        $mine->planet = $planet; $mine->slug = 'mine_metal'; $mine->level = max(5, $mine->level); $this->em->persist($mine);
        $this->em->flush();

        return $planet;
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

    private function defenseFleet(Player $player, Planet $origin, Planet $target, string $key, \DateTimeImmutable $arrival): Fleet
    {
        $fleet = new Fleet();
        $fleet->playerUniverse = $player->currentPlayerUniverse;
        $fleet->homePlanet = $origin;
        $fleet->destination = $target;
        $fleet->destinationSystem = $target->solarSystem;
        $fleet->status = 'traveling';
        $fleet->mission = 'defend';
        $fleet->travelSeconds = 60;
        $fleet->reservedFuel = 100;
        $fleet->orbitFuel = 10;
        $fleet->cargo = ['hydrogen' => 100];
        $fleet->departureAt = $arrival->modify('-60 seconds');
        $fleet->arrivalAt = $arrival;
        $fleet->dispatchToken = 'defense-' . $key;
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
        $turret->slug = 'ground_turret_anti_light';
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
        $relief = $this->player('Relief');
        $reliefOrigin = $this->join($relief);
        $externalOrbit = new Fleet();
        $externalOrbit->playerUniverse = $relief->currentPlayerUniverse;
        $externalOrbit->homePlanet = $reliefOrigin;
        $externalOrbit->destination = $target;
        $externalOrbit->destinationSystem = $target->solarSystem;
        $externalOrbit->status = 'orbiting';
        $externalOrbit->mission = 'orbit';
        $externalOrbit->travelSeconds = 60;
        $externalOrbit->reservedFuel = 100;
        $externalOrbit->orbitFuel = 10;
        $externalOrbit->orbitDurationSeconds = 3600;
        $externalOrbit->cargo = ['hydrogen' => 100];
        $externalOrbit->departureAt = $clock->now()->modify('-60 seconds');
        $externalOrbit->arrivalAt = $clock->now()->modify('+3600 seconds');
        $externalOrbit->dispatchToken = 'orbiting-test-fleet';
        $this->em->persist($externalOrbit);
        $orbitShip = new FleetShip();
        $orbitShip->fleet = $externalOrbit;
        $orbitShip->slug = 'fighter';
        $orbitShip->quantity = 1;
        $this->em->persist($orbitShip);
        $this->em->flush();
        $service = self::getContainer()->get(CombatEventService::class);
        $fleetService = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $manager = self::getContainer()->get(\App\Manager\GameManager::class);

        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($fleetA)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($event);
        self::assertSame('defending', $garrison->status);
        self::assertEquals(600, $event->resolveAt->getTimestamp() - $event->startedAt->getTimestamp());

        $clock->sleep(300);
        $externalDefense = $this->defenseFleet($relief, $reliefOrigin, $target, 'join', $clock->now());
        $reliefShip = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $externalDefense, 'slug' => 'fighter']);
        $reliefShip->slug = 'battleship'; $reliefShip->quantity = 50; $this->em->flush();
        $fleetService->arrive($externalDefense->id, $externalDefense->dispatchToken);
        self::assertSame('defending', $externalDefense->status);
        self::assertNull($externalDefense->arrivalAt);
        $fleetB->arrivalAt = $clock->now();
        $this->em->flush();
        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($fleetB)));
        self::assertSame(5, $this->em->getRepository(CombatParticipant::class)->count(['combatEvent' => $event]));
        $event->seed = 123;
        self::assertSame('defending', $externalOrbit->status);
        $participantB = $this->em->getRepository(CombatParticipant::class)->findOneBy(['combatEvent' => $event, 'fleet' => $fleetB]);
        self::assertSame(5, $participantB->arrivalRound);

        $clock->sleep(300);
        $service->resolve($event->id, $event->dispatchToken);
        $service->resolve($event->id, $event->dispatchToken);

        self::assertSame('resolved', $event->status);
        self::assertSame(1, $this->em->getRepository(CombatLog::class)->count(['combatEvent' => $event]));
        $log = $this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent' => $event]);
        $attackerCompletionNotices = array_filter(
            $this->em->getRepository(Notification::class)->findBy(['recipient' => $attackerA->currentPlayerUniverse]),
            static fn (Notification $notice): bool => str_starts_with($notice->message, 'Combat terminé sur '),
        );
        $defenderCompletionNotices = array_filter(
            $this->em->getRepository(Notification::class)->findBy(['recipient' => $defender->currentPlayerUniverse]),
            static fn (Notification $notice): bool => str_starts_with($notice->message, 'Combat terminé sur '),
        );
        self::assertSame('defender_wins', $log->outcome);
        self::assertSame(1, $this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['playerUniverse' => $relief->currentPlayerUniverse, 'sourceType' => 'risk_mission']));
        self::assertCount(1, $attackerCompletionNotices);
        self::assertCount(1, $defenderCompletionNotices);
        $spectator = $this->join($this->player('Spectator'));
        $reports = self::getContainer()->get(\App\Service\Combat\CombatReportReader::class);
        self::assertCount(1, $reports->list($attackerA->currentPlayerUniverse));
        self::assertNotNull($reports->findVisible($defender->currentPlayerUniverse, $log->id));
        self::assertNull($reports->findVisible($spectator->owner, $log->id));
        self::assertContains($fleetA->status, ['returning', 'completed']);
        self::assertContains($fleetB->status, ['returning', 'completed']);
        self::assertContains($externalDefense->status, ['returning', 'completed']);
        self::assertSame(0.0, $externalDefense->orbitFuel);
        self::assertContains($externalOrbit->status, ['returning', 'completed']);
        self::assertSame(0.0, $externalOrbit->orbitFuel);
        self::assertSame('idle', $garrison->status);
    }
    public function testCombatRunsAndPersistsOneDeterministicRoundPerMinute(): void
    {
        $clock = $this->clock();
        $defender = $this->player('RoundDefender');
        $target = $this->join($defender);
        $attacker = $this->player('RoundAttacker');
        $origin = $this->join($attacker);
        $fleet = $this->attackFleet($attacker, $origin, $target, 'round-a');
        $attackingShip = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $fleet]);
        $attackingShip->slug = 'probe';
        $attackingShip->quantity = 2;

        $garrison = new Fleet();
        $garrison->playerUniverse = $defender->currentPlayerUniverse;
        $garrison->homePlanet = $target;
        $garrison->status = 'idle';
        $garrison->mission = 'idle';
        $this->em->persist($garrison);
        $defendingShip = new FleetShip();
        $defendingShip->fleet = $garrison;
        $defendingShip->slug = 'probe';
        $defendingShip->quantity = 2;
        $this->em->persist($defendingShip);
        $this->em->flush();

        $service = self::getContainer()->get(CombatEventService::class);
        $manager = self::getContainer()->get(GameManager::class);
        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($fleet)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($event);
        $firstToken = $event->dispatchToken;

        $clock->sleep(59);
        $service->resolve($event->id, $firstToken);
        self::assertSame(0, $event->currentRound);
        self::assertSame('pending', $event->status);

        $clock->sleep(1);
        $reinforcement = $this->attackFleet($attacker, $origin, $target, 'round-b');
        $reinforcement->arrivalAt = $clock->now();
        $reinforcement->departureAt = $clock->now()->modify('-60 seconds');
        $reinforcementShip = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $reinforcement]);
        $reinforcementShip->slug = 'probe';
        $reinforcementShip->quantity = 1;
        $this->em->flush();
        $service->resolve($event->id, $firstToken);
        self::assertSame(0, $event->currentRound, 'Le tick attend le traitement de l’arrivée exactement à +60 s.');
        self::assertNotSame($firstToken, $event->dispatchToken);
        $waitingToken = $event->dispatchToken;
        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($reinforcement)));
        $reinforcementParticipant = $this->em->getRepository(CombatParticipant::class)->findOneBy(['combatEvent' => $event, 'fleet' => $reinforcement]);
        self::assertSame(1, $reinforcementParticipant->arrivalRound, 'Une arrivée exactement à +60 s rejoint le round 1.');

        $clock->sleep(1);
        $service->resolve($event->id, $waitingToken);
        self::assertSame(1, $event->currentRound);
        self::assertSame('pending', $event->status);
        self::assertNotSame($firstToken, $event->dispatchToken);
        self::assertNotEmpty($event->combatSnapshot);
        self::assertSame([1], array_column($event->combatSnapshot['round_history'], 'number'), 'Le round résolu est persisté dans l’instantané du combat.');
        self::assertSame(0, $this->em->getRepository(CombatLog::class)->count(['combatEvent' => $event]));
        $secondToken = $event->dispatchToken;
        $service->resolve($event->id, $firstToken);
        self::assertSame(1, $event->currentRound, 'Un ancien jeton ne rejoue pas le round déjà persisté.');

        $clock->sleep(60);
        $service->resolve($event->id, $secondToken);
        self::assertSame(2, $event->currentRound);
        self::assertSame('pending', $event->status);
        self::assertSame(0, $this->em->getRepository(CombatLog::class)->count(['combatEvent' => $event]));
    }

    public function testCombatClosesAsSoonAsTheDefenderCampIsEmptyAndRejectsLateAttackers(): void
    {
        $clock = $this->clock();
        $defender = $this->player('EarlyDefender');
        $target = $this->join($defender);
        $attacker = $this->player('EarlyAttacker');
        $origin = $this->join($attacker);
        $fleet = $this->attackFleet($attacker, $origin, $target, 'early-a');
        $service = self::getContainer()->get(CombatEventService::class);
        $manager = self::getContainer()->get(GameManager::class);
        self::assertTrue($manager->transaction(fn (): bool => $service->joinAttack($fleet)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($event);

        $clock->sleep(60);
        $service->resolve($event->id, $event->dispatchToken);
        self::assertSame('resolved', $event->status);
        self::assertSame(1, $event->currentRound);
        $log = $this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent' => $event]);
        self::assertNotNull($log);
        self::assertSame('attacker_wins', $log->outcome);

        $clock->sleep(60);
        $lateFleet = $this->attackFleet($attacker, $origin, $target, 'early-late');
        $lateFleet->arrivalAt = $clock->now();
        $this->em->flush();
        self::assertFalse($manager->transaction(fn (): bool => $service->joinAttack($lateFleet)));
        $clock->sleep(600);
        $veryLateFleet = $this->attackFleet($attacker, $origin, $target, 'early-very-late');
        $veryLateFleet->arrivalAt = $clock->now();
        $this->em->flush();
        self::assertFalse($manager->transaction(fn (): bool => $service->joinAttack($veryLateFleet)), 'Une flotte partie avant la clôture reste exclue même après la fenêtre initiale.');
        self::assertSame(1, $this->em->getRepository(CombatEvent::class)->count(['planet' => $target]));
    }

    public function testCombatSnapshotsApplyEachMembershipTechnologyWithoutChangingPlanetDefenses(): void
    {
        $clock = $this->clock();
        $defender = $this->player('TechDefender');
        $target = $this->join($defender);
        $attacker = $this->player('TechAttacker');
        $origin = $this->join($attacker);

        foreach ([[$defender->currentPlayerUniverse, 1], [$attacker->currentPlayerUniverse, 2]] as [$membership, $level]) {
            $technology = new PlayerTechnology();
            $technology->playerUniverse = $membership;
            $technology->slug = 'weapon_calibration';
            $technology->level = $level;
            $this->em->persist($technology);
        }

        $garrison = new Fleet();
        $garrison->playerUniverse = $defender->currentPlayerUniverse;
        $garrison->homePlanet = $target;
        $garrison->status = 'idle';
        $garrison->mission = 'idle';
        $this->em->persist($garrison);
        $garrisonShip = new FleetShip();
        $garrisonShip->fleet = $garrison;
        $garrisonShip->slug = 'fighter';
        $garrisonShip->quantity = 1;
        $this->em->persist($garrisonShip);

        $turret = new PlanetDefense();
        $turret->planet = $target;
        $turret->slug = 'ground_turret_anti_light';
        $turret->quantity = 1;
        $this->em->persist($turret);
        $this->em->flush();

        $fleet = $this->attackFleet($attacker, $origin, $target, 'tech-combat');
        $combat = self::getContainer()->get(CombatEventService::class);
        $manager = self::getContainer()->get(GameManager::class);
        self::assertTrue($manager->transaction(fn (): bool => $combat->joinAttack($fleet)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($event);
        $event->seed = 77;
        $clock->sleep(600);
        $combat->resolve($event->id, $event->dispatchToken);

        $log = $this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent' => $event]);
        self::assertNotNull($log);
        $snapshots = [];
        foreach ($log->snapshot['participants'] as $participant) {
            $snapshots[$participant['fleet_id']] = $participant;
        }
        self::assertEqualsWithDelta(55.308718046242326, $snapshots[$fleet->id]['units'][0]['precision'], 0.000001);
        self::assertSame(['weapon_calibration' => ['basic' => 2]], $snapshots[$fleet->id]['technology_levels']);
        $defenderSnapshot = array_values(array_filter($snapshots, static fn (array $participant): bool => $participant['player_universe_id'] === $defender->currentPlayerUniverse->id))[0];
        self::assertEqualsWithDelta(55.15594535470353, $defenderSnapshot['units'][0]['precision'], 0.000001);
        self::assertSame(['weapon_calibration' => ['basic' => 1]], $defenderSnapshot['technology_levels']);
        self::assertSame(78, $log->snapshot['defenses'][0]['precision']);
    }

    public function testBeginnerProtectionExpiresAndBlocksFiveToOneScoreAdvantage(): void
    {
        $clock = $this->clock();
        $defender = $this->player('ProtectedDefender');
        $target = $this->join($defender);
        $attacker = $this->player('ProtectedAttacker');
        $origin = $this->join($attacker);
        $defender->currentPlayerUniverse->joinedAt = $clock->now()->modify('-1 day');
        $this->em->flush();
        $combat = self::getContainer()->get(CombatEventService::class);

        self::assertFalse($combat->canAttack($attacker->currentPlayerUniverse, $target));
        $defender->currentPlayerUniverse->joinedAt = $clock->now()->modify('-8 days');
        self::assertTrue($combat->canAttack($attacker->currentPlayerUniverse, $target));

        $fleet = new Fleet();
        $fleet->playerUniverse = $attacker->currentPlayerUniverse;
        $fleet->homePlanet = $origin;
        $this->em->persist($fleet);
        $ship = new FleetShip();
        $ship->fleet = $fleet;
        $ship->slug = 'capital';
        $ship->quantity = 1;
        $this->em->persist($ship);
        $this->em->flush();

        self::assertFalse($combat->canAttack($attacker->currentPlayerUniverse, $target));
    }

    public function testDefenseWithoutCombatReturnsAfterItsStationingWindow(): void
    {
        $clock = $this->clock();
        $owner = $this->player('Owner');
        $target = $this->join($owner);
        $visitor = $this->player('Visitor');
        $origin = $this->join($visitor);
        if (!$this->em->getRepository(\App\Entity\PlayerDiscovery::class)->findOneBy(['playerUniverse' => $visitor->currentPlayerUniverse, 'solarSystem' => $target->solarSystem])) {
            $discovery = new \App\Entity\PlayerDiscovery();
            $discovery->playerUniverse = $visitor->currentPlayerUniverse;
            $discovery->solarSystem = $target->solarSystem;
            $this->em->persist($discovery);
        }
        $idle = new Fleet();
        $idle->playerUniverse = $visitor->currentPlayerUniverse;
        $idle->homePlanet = $origin;
        $this->em->persist($idle);
        $stock = new FleetShip();
        $stock->fleet = $idle;
        $stock->slug = 'fighter';
        $stock->quantity = 1;
        $this->em->persist($stock);
        $fuelShip = new FleetShip();
        $fuelShip->fleet = $idle;
        $fuelShip->slug = 'transport';
        $fuelShip->quantity = 1;
        $this->em->persist($fuelShip);
        $this->em->flush();
        $service = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $quote = $service->quote($visitor->currentPlayerUniverse, $origin, 'defend', $target->id, ['fighter' => 1, 'transport' => 1], 0.5);
        self::assertEqualsWithDelta(0.10, $quote['orbit_fuel'], 0.000001);
        $fleet = $this->defenseFleet($visitor, $origin, $target, 'idle', $clock->now());
        $service->arrive($fleet->id, $fleet->dispatchToken);
        self::assertSame('defending', $fleet->status);
        self::assertSame(600, $fleet->arrivalAt->getTimestamp() - $clock->now()->getTimestamp());
        self::assertEqualsWithDelta(55.0, $fleet->cargo['hydrogen'], 0.001);
        $clock->sleep(600);
        $service->arrive($fleet->id, $fleet->dispatchToken);
        self::assertSame('returning', $fleet->status);
        self::assertSame(0.0, $fleet->orbitFuel);
        self::assertEqualsWithDelta(45.0, $fleet->reservedFuel, 0.001);
    }

    public function testOrbitNeedsGuildHostApprovalThenConsumesStationingFuel(): void
    {
        $clock = $this->clock();
        $host = $this->player('OrbitHost');
        $this->join($host);
        $sender = $this->player('OrbitSender');
        $origin = $this->join($sender);
        $target = $this->em->getRepository(Planet::class)->findOneBy(['solarSystem' => $origin->solarSystem, 'owner' => null]);
        self::assertNotNull($target);
        $target->owner = $host->currentPlayerUniverse;

        $guild = new \App\Entity\Guild();
        $guild->universe = $host->currentPlayerUniverse->universe;
        $guild->leader = $host->currentPlayerUniverse;
        $guild->name = 'Orbit guild';
        $this->em->persist($guild);
        foreach ([$host->currentPlayerUniverse, $sender->currentPlayerUniverse] as $membership) {
            $member = new \App\Entity\GuildMember();
            $member->guild = $guild;
            $member->playerUniverse = $membership;
            $this->em->persist($member);
        }

        $idle = new Fleet();
        $idle->playerUniverse = $sender->currentPlayerUniverse;
        $idle->homePlanet = $origin;
        $this->em->persist($idle);
        $stock = new FleetShip();
        $stock->fleet = $idle;
        $stock->slug = 'fighter';
        $stock->quantity = 1;
        $this->em->persist($stock);
        $this->em->flush();

        $fleetService = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $quote = $fleetService->quote($sender->currentPlayerUniverse, $origin, 'orbit', $target->id, ['fighter' => 1], 0.5, [], 24);
        self::assertEqualsWithDelta(7.2, $quote['orbit_fuel'], 0.000001);
        $combat = self::getContainer()->get(CombatEventService::class);
        self::assertFalse($combat->orbitAutoAccepted($sender->currentPlayerUniverse, $target));

        $fleet = new Fleet();
        $fleet->playerUniverse = $sender->currentPlayerUniverse;
        $fleet->homePlanet = $origin;
        $fleet->destination = $target;
        $fleet->destinationSystem = $target->solarSystem;
        $fleet->status = 'traveling';
        $fleet->mission = 'orbit';
        $fleet->travelSeconds = 60;
        $fleet->reservedFuel = 100;
        $fleet->orbitFuel = 10;
        $fleet->orbitDurationSeconds = 86400;
        $fleet->cargo = ['hydrogen' => 100];
        $fleet->arrivalAt = $clock->now();
        $fleet->dispatchToken = 'orbit-arrival';
        $this->em->persist($fleet);
        $outbound = new FleetShip();
        $outbound->fleet = $fleet;
        $outbound->slug = 'fighter';
        $outbound->quantity = 1;
        $this->em->persist($outbound);
        $this->em->flush();

        $fleetService->arrive($fleet->id, $fleet->dispatchToken);
        self::assertSame('orbit_pending', $fleet->status);
        self::assertNull($fleet->arrivalAt);
        $fleetService->decideOrbit($host->currentPlayerUniverse, $fleet->id, true);
        self::assertSame('orbiting', $fleet->status);
        self::assertSame(86400, $fleet->arrivalAt->getTimestamp() - $clock->now()->getTimestamp());
        $orbitToken = $fleet->dispatchToken;
        $clock->sleep(86400);
        $fleetService->arrive($fleet->id, $orbitToken);
        self::assertSame('returning', $fleet->status);
        self::assertSame(0.0, $fleet->orbitFuel);
        self::assertEqualsWithDelta(45.0, $fleet->reservedFuel, 0.001);
        self::assertEqualsWithDelta(45.0, $fleet->cargo['hydrogen'], 0.001);

        $contract = new \App\Entity\AllianceContract();
        $contract->playerA = min($host->currentPlayerUniverse->id, $sender->currentPlayerUniverse->id) === $host->currentPlayerUniverse->id ? $host->currentPlayerUniverse : $sender->currentPlayerUniverse;
        $contract->playerB = $contract->playerA->id === $host->currentPlayerUniverse->id ? $sender->currentPlayerUniverse : $host->currentPlayerUniverse;
        $contract->proposer = $contract->playerA;
        $contract->status = 'accepted';
        $this->em->persist($contract);
        $this->em->flush();
        self::assertTrue($combat->orbitAutoAccepted($sender->currentPlayerUniverse, $target));
    }

    public function testWinningAttackLootProtectsStorageFloorAndSplitsBySurvivingCargoSpace(): void
    {
        $clock = $this->clock();
        $clock->sleep(19 * 86400);
        self::getContainer()->get(\App\Service\Boost\BoostEventService::class)->tick();
        $defender = $this->player('LootDefender');
        $target = $this->join($defender);
        $development = new PlanetBuilding();
        $development->planet = $target;
        $development->slug = 'lab';
        $development->level = 10;
        $this->em->persist($development);
        $attacker = $this->player('LootAttacker');
        $origin = $this->join($attacker);

        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $target]) as $resource) {
            $resource->currentStock = 25000;
            $resource->lastUpdatedAt = $clock->now();
        }
        $this->em->flush();

        $first = $this->attackFleet($attacker, $origin, $target, 'loot-a');
        $first->reservedFuel = 10;
        $first->cargo = ['hydrogen' => 10];
        $firstCargo = new FleetShip();
        $firstCargo->fleet = $first;
        $firstCargo->slug = 'transport';
        $firstCargo->quantity = 1;
        $this->em->persist($firstCargo);

        $second = $this->attackFleet($attacker, $origin, $target, 'loot-b');
        foreach ([$first, $second] as $fleet) { $fleet->departureAt = $clock->now()->modify('-60 seconds'); $fleet->arrivalAt = $clock->now(); }
        $second->reservedFuel = 10;
        $second->cargo = ['hydrogen' => 10];
        $secondCargo = new FleetShip();
        $secondCargo->fleet = $second;
        $secondCargo->slug = 'transport';
        $secondCargo->quantity = 2;
        $this->em->persist($secondCargo);
        $this->em->flush();

        $combat = self::getContainer()->get(CombatEventService::class);
        $manager = self::getContainer()->get(\App\Manager\GameManager::class);
        self::assertTrue($manager->transaction(fn (): bool => $combat->joinAttack($first)));
        self::assertTrue($manager->transaction(fn (): bool => $combat->joinAttack($second)));
        $defendingFleet = $this->defenseFleet($defender, $target, $target, 'damage-event', $clock->now());
        $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $defendingFleet, 'slug' => 'fighter'])->slug = 'probe';
        self::assertTrue($manager->transaction(fn (): bool => $combat->arriveDefender($defendingFleet)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target]);
        self::assertNotNull($event);
        $clock->sleep(600);
        $projected = self::getContainer()->get(\App\Service\Colony\ResourceService::class)->snapshot($target);
        $expectedLoot = 0;
        foreach ($projected as $resource) {
            $expectedLoot += max(0, (int) floor($resource['stock'] - $resource['capacity'] * 0.1));
        }
        $combat->resolve($event->id, $event->dispatchToken);

        $log = $this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent' => $event]);
        self::assertNotNull($log);
        self::assertSame('attacker_wins', $log->outcome);
        self::assertSame(1, $this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['playerUniverse' => $attacker->currentPlayerUniverse, 'sourceType' => 'risk_mission']));
        $combatEvent = $this->em->getRepository(\App\Entity\BoostEvent::class)->findOneBy(['universe' => $attacker->currentPlayerUniverse->universe, 'templateKey' => 'combat', 'status' => 'active']);
        self::assertGreaterThan(0, $this->em->getRepository(\App\Entity\BoostEventContribution::class)->findOneBy(['event' => $combatEvent, 'playerUniverse' => $attacker->currentPlayerUniverse])->value);
        self::assertSame(0.1, $log->snapshot['loot']['protected_storage_ratio']);
        self::assertSame(1.0, $log->snapshot['loot']['ratio']);
        $firstLoot = $log->snapshot['loot']['fleets'][$first->id] ?? [];
        $secondLoot = $log->snapshot['loot']['fleets'][$second->id] ?? [];
        self::assertNotEmpty($firstLoot);
        self::assertNotEmpty($secondLoot);
        self::assertGreaterThan(array_sum($firstLoot), array_sum($secondLoot));
        self::assertSame($expectedLoot, array_sum($log->snapshot['loot']['total']));
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $target]) as $resource) {
            self::assertGreaterThanOrEqual(20000, $resource->currentStock);
        }

        $resourceService = self::getContainer()->get(\App\Service\Colony\ResourceService::class);
        $homeStorage = $resourceService->snapshot($origin);
        foreach ($this->em->getRepository(PlanetResource::class)->findBy(['planet' => $origin]) as $resource) {
            $resource->currentStock = $homeStorage[$resource->resourceType]['capacity'];
            $resource->lastUpdatedAt = $clock->now();
        }
        $this->em->flush();
        $clock->sleep(60);
        $fleetService = self::getContainer()->get(\App\Service\Fleet\FleetService::class);
        $fleetService->arrive($first->id, $first->dispatchToken);
        $fleetService->arrive($second->id, $second->dispatchToken);
        self::assertSame('completed', $first->status);
        self::assertSame('completed', $second->status);
        foreach ($resourceService->snapshot($origin) as $resource) {
            self::assertLessThanOrEqual($resource['capacity'], $resource['stock']);
        }
    }

    public function testWinningPveConquestKeepsProgressAndConsumesColonyShip(): void
    {
        $clock = $this->clock();
        $attacker = $this->player('ConquestAttacker');
        $origin = $this->join($attacker);
        $colonial_expansion = new PlayerTechnology();
        $colonial_expansion->playerUniverse = $attacker->currentPlayerUniverse;
        $colonial_expansion->slug = 'colonial_expansion';
        $colonial_expansion->level = 1;
        $this->em->persist($colonial_expansion);
        $this->em->flush();
        $universe = $attacker->currentPlayerUniverse->universe;
        $target = null;
        foreach ($this->em->getRepository(Planet::class)->findBy(['owner' => null]) as $candidate) {
            if ($candidate->solarSystem->galaxy->universe->id === $universe->id && $candidate->npcPlanet) { $target = $candidate; break; }
        }
        self::assertNotNull($target);
        $target->conquestLevel = 1;
        self::getContainer()->get(\App\Service\Colony\ResourceService::class)->initialize($target);
        $building = new PlanetBuilding();
        $building->planet = $target; $building->slug = 'shipyard'; $building->level = 2;
        $this->em->persist($building);
        $this->em->flush();
        $resourceRows = $this->em->getRepository(PlanetResource::class)->findBy(['planet' => $target]);
        $resourceIds = array_map(static fn (PlanetResource $resource): int => $resource->id, $resourceRows);
        $resourceStocks = array_map(static fn (PlanetResource $resource): float => $resource->currentStock, $resourceRows);

        $combat = self::getContainer()->get(CombatEventService::class);
        self::assertTrue($combat->canConquer($attacker->currentPlayerUniverse, $target));
        $fleet = $this->attackFleet($attacker, $origin, $target, 'pve-conquest');
        $fleet->mission = 'conquer';
        $fighter = $this->em->getRepository(FleetShip::class)->findOneBy(['fleet' => $fleet, 'slug' => 'fighter']);
        $fighter->quantity = 12;
        $capital = new FleetShip(); $capital->fleet = $fleet; $capital->slug = 'battleship'; $capital->quantity = 1; $this->em->persist($capital);
        $colonyShip = new FleetShip(); $colonyShip->fleet = $fleet; $colonyShip->slug = 'colony_ship'; $colonyShip->quantity = 1; $this->em->persist($colonyShip);
        $this->em->flush();

        $manager = self::getContainer()->get(\App\Manager\GameManager::class);
        self::assertTrue($manager->transaction(fn (): bool => $combat->joinAttack($fleet)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target]);
        self::assertNotNull($event);
        $event->seed = 123;
        $clock->sleep(600);
        $combat->resolve($event->id, $event->dispatchToken);

        $log = $this->em->getRepository(CombatLog::class)->findOneBy(['combatEvent' => $event]);
        self::assertNotNull($log);
        self::assertSame('attacker_wins', $log->outcome);
        self::assertSame('captured', $log->snapshot['capture']['status']);
        self::assertSame(1, $this->em->getRepository(\App\Entity\PlayerBoost::class)->count(['playerUniverse' => $attacker->currentPlayerUniverse, 'sourceType' => 'risk_mission']));
        self::assertSame($attacker->currentPlayerUniverse->id, $target->owner->id);
        self::assertSame(1, $log->snapshot['losses'][$fleet->id . ':colony_ship']);
        self::assertSame(0, $colonyShip->quantity);
        self::assertSame(2, $building->level);
        $afterResources = $this->em->getRepository(PlanetResource::class)->findBy(['planet' => $target]);
        self::assertSame($resourceIds, array_map(static fn (PlanetResource $resource): int => $resource->id, $afterResources));
        self::assertSame($resourceStocks, array_map(static fn (PlanetResource $resource): float => $resource->currentStock, $afterResources));
        self::assertSame([], $this->em->getRepository(PlanetNpcShip::class)->findBy(['planet' => $target]));
        self::assertSame([], $this->em->getRepository(PlanetDefense::class)->findBy(['planet' => $target]));
    }

    public function testFailedPveConquestKeepsNpcLossesForTheNextAttack(): void
    {
        $clock = $this->clock();
        $attacker = $this->player('NpcLossAttacker');
        $origin = $this->join($attacker);
        $universe = $attacker->currentPlayerUniverse->universe;
        $target = null;
        foreach ($this->em->getRepository(Planet::class)->findBy(['owner' => null]) as $candidate) {
            if ($candidate->solarSystem->galaxy->universe->id === $universe->id && $candidate->npcPlanet) { $target = $candidate; break; }
        }
        self::assertNotNull($target);
        $target->conquestLevel = 1;
        $combat = self::getContainer()->get(CombatEventService::class);
        $manager = self::getContainer()->get(\App\Manager\GameManager::class);

        $fleet = $this->attackFleet($attacker, $origin, $target, 'npc-loss-a');
        $fleet->mission = 'conquer';
        $battleship = new FleetShip(); $battleship->fleet = $fleet; $battleship->slug = 'battleship'; $battleship->quantity = 8; $this->em->persist($battleship);
        $this->em->flush();
        self::assertTrue($manager->transaction(fn (): bool => $combat->joinAttack($fleet)));
        $event = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($event); $event->seed = 321; $clock->sleep(600);
        $combat->resolve($event->id, $event->dispatchToken);
        $fighterGarrison = $this->em->getRepository(PlanetNpcShip::class)->findOneBy(['planet' => $target, 'slug' => 'fighter']);
        $probeGarrison = $this->em->getRepository(PlanetNpcShip::class)->findOneBy(['planet' => $target, 'slug' => 'probe']);
        self::assertNotNull($fighterGarrison);
        self::assertNotNull($probeGarrison);
        self::assertSame(0, $fighterGarrison->quantity);
        self::assertNull($target->owner);

        $secondFleet = $this->attackFleet($attacker, $origin, $target, 'npc-loss-b');
        $secondFleet->departureAt = $clock->now();
        $secondFleet->mission = 'conquer';
        $this->em->flush();
        self::assertTrue($manager->transaction(fn (): bool => $combat->joinAttack($secondFleet)));
        $nextEvent = $this->em->getRepository(CombatEvent::class)->findOneBy(['planet' => $target, 'status' => 'pending']);
        self::assertNotNull($nextEvent);
        $clock->sleep(600);
        $combat->resolve($nextEvent->id, $nextEvent->dispatchToken);
        self::assertSame(0, $fighterGarrison->quantity);
        self::assertTrue($target->npcGarrisonInitialized);
    }

}
