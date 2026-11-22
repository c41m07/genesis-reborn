<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\{Guild, GuildHall, Planet, PlayerDiscovery, PlayerUniverse, SolarSystem, Universe};
use App\Repository\GuildHallRepository;
use App\Service\Guild\GuildHallService;
use App\Service\Player\{DiplomacyService, RegistrationService, RelationResolver};
use App\Service\Universe\{UniverseImporter, UniverseService};
use App\Validator\DTO\RegistrationData;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class GuildHallTest extends KernelTestCase
{
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $parameters = $this->em->getConnection()->getParams();
        if (($parameters['host'] ?? '') !== 'database-test' || ($parameters['dbname'] ?? '') !== 'genesis_v2_test') {
            throw new \RuntimeException('Base isolée attendue.');
        }
        $schema = new SchemaTool($this->em);
        $metadata = $this->em->getMetadataFactory()->getAllMetadata();
        $schema->dropSchema($metadata);
        $schema->createSchema($metadata);
        self::getContainer()->get(UniverseImporter::class)->import();
    }

    public function testGuildPoolsContributionsAndExposesOnlyDiscoveredCoordinate(): void
    {
        [$leader, $origin, $guild] = $this->guild('HallLeader', 'HLA');
        [$member, $colony] = $this->player('HallMember');
        [$neighbor] = $this->player('HallNeighbor');
        [$otherUniverse] = $this->player('HallVisitor', 'turbo-1');
        self::getContainer()->get(DiplomacyService::class)->join($member, $guild->id);
        $service = self::getContainer()->get(GuildHallService::class);
        $planetCount = $this->em->getRepository(Planet::class)->count();
        $halls = self::getContainer()->get(GuildHallRepository::class);
        $system = $this->unknownSystem($neighbor);
        $this->discover($leader, $system);
        $beforeHydro = $this->stock($origin, 'hydrogen');
        $beforeMetal = $this->stock($colony, 'metal');

        // Chaque groupe de ressources est un équivalent de 8 hydrogènes.
        $hall = $service->contribute($leader, $guild->id, $origin->id, 0, ['hydrogen' => '8']);
        self::assertSame(8, $hall->strength);
        self::assertSame(1, $hall->revision);
        $hall = $service->contribute($member, $guild->id, $colony->id, 1, ['metal' => 4, 'nano' => 2, 'antimatter' => 1]);
        self::assertSame(32, $hall->strength);
        self::assertSame(32, $hall->requiredStrength);
        self::assertEqualsWithDelta($beforeHydro - 8, $this->stock($origin, 'hydrogen'), 0.1);
        self::assertEqualsWithDelta($beforeMetal - 4, $this->stock($colony, 'metal'), 0.1);
        self::assertNull($hall->solarSystem);
        self::assertSame([], $halls->claimsForKnownSystems($leader, [$system->id]));
        $placed = $service->place($leader, $guild->id, $system->id, 2);
        self::assertSame($hall->id, $placed->id);
        self::assertSame(3, $placed->revision);
        self::assertNotNull($placed->placedAt);
        self::assertSame($planetCount, $this->em->getRepository(Planet::class)->count());
        self::assertSame([], $halls->claimsForKnownSystems($neighbor, [$system->id]));
        $this->discover($neighbor, $system);
        self::assertSame([$system->id => ['tag' => 'HLA', 'position' => 0]], $halls->claimsForKnownSystems($neighbor, [$system->id]));
        self::assertSame([], $halls->claimsForKnownSystems($otherUniverse, [$system->id]));
    }

    #[DataProvider('refusals')]
    public function testRefusedCommandsDoNotDebitOrWrite(string $scenario, string $reason): void
    {
        [$leader, $origin, $guild] = $this->guild('HallLead', 'HLD');
        [$member, $memberPlanet] = $this->player('HallAlly');
        [$stranger, $strangerPlanet, $otherGuild] = $this->guild('HallStranger', 'HST');
        [, $otherWorldPlanet] = $this->player('HallOtherWorld', 'turbo-1');
        $diplomacy = self::getContainer()->get(DiplomacyService::class);
        $diplomacy->join($member, $guild->id);
        $service = self::getContainer()->get(GuildHallService::class);
        $hall = $service->contribute($leader, $guild->id, $origin->id, 0, ['metal' => $scenario === 'early' ? 1 : 16]);
        if ($scenario === 'taken') {
            $service->place($leader, $guild->id, $origin->solarSystem->id, 1);
            $service->contribute($stranger, $otherGuild->id, $strangerPlanet->id, 0, ['metal' => 16]);
            $this->discover($stranger, $origin->solarSystem);
        }
        $unknown = $this->unknownSystem($leader);
        $expected = $hall->revision;
        $db = $this->em->getConnection();
        if ($scenario === 'stock') {
            $db->executeStatement("UPDATE planet_resource SET current_stock = 0, last_updated_at = UTC_TIMESTAMP() WHERE planet_id = ? AND resource_type = 'metal'", [$strangerPlanet->id]);
        }
        $beforeHall = $db->fetchAllAssociative('SELECT id, strength, solar_system_id, revision FROM guild_hall ORDER BY id');
        $beforeStocks = $db->fetchAllAssociative('SELECT id, current_stock, last_updated_at FROM planet_resource ORDER BY id');
        try {
            match ($scenario) {
                'other-guild' => $service->contribute($stranger, $guild->id, $strangerPlanet->id, $expected, ['metal' => 1]),
                'not-leader' => $service->place($member, $guild->id, $memberPlanet->solarSystem->id, $expected),
                'early' => $service->place($leader, $guild->id, $origin->solarSystem->id, $expected),
                'other-world' => $service->place($leader, $guild->id, $otherWorldPlanet->solarSystem->id, $expected),
                'unknown' => $service->place($leader, $guild->id, $unknown->id, $expected),
                'taken' => $service->place($stranger, $otherGuild->id, $origin->solarSystem->id, 1),
                'foreign-planet' => $service->contribute($stranger, $otherGuild->id, $origin->id, 0, ['metal' => 1]),
                'stale' => $service->contribute($leader, $guild->id, $origin->id, 0, ['metal' => 1]),
                'overfund' => $service->contribute($stranger, $otherGuild->id, $strangerPlanet->id, 0, ['metal' => 17]),
                'stock' => $service->contribute($stranger, $otherGuild->id, $strangerPlanet->id, 0, ['metal' => 16]),
            };
            self::fail('La commande aurait dû être refusée.');
        } catch (\DomainException $exception) {
            self::assertStringContainsString($reason, $exception->getMessage());
        }
        self::assertSame($beforeHall, $db->fetchAllAssociative('SELECT id, strength, solar_system_id, revision FROM guild_hall ORDER BY id'));
        self::assertSame($beforeStocks, $db->fetchAllAssociative('SELECT id, current_stock, last_updated_at FROM planet_resource ORDER BY id'));
    }

    public static function refusals(): iterable
    {
        yield 'droits guilde' => ['other-guild', 'Guilde inaccessible'];
        yield 'membre simple' => ['not-leader', 'commandant actuel'];
        yield 'financement incomplet' => ['early', 'entièrement financé'];
        yield 'système autre univers' => ['other-world', 'Système inaccessible'];
        yield 'jamais découvert' => ['unknown', 'Système inaccessible'];
        yield 'revendication concurrente déjà validée' => ['taken', 'déjà occupée'];
        yield 'stock tierce colonie' => ['foreign-planet', 'ne vous appartient pas'];
        yield 'ancienne révision' => ['stale', 'Le Hall a changé'];
        yield 'don supérieur au reste' => ['overfund', 'dépasse la force'];
        yield 'stock insuffisant' => ['stock', 'Ressources insuffisantes'];
    }

    /** @return array{PlayerUniverse, Planet, Guild} */
    private function guild(string $name, string $tag): array
    {
        [$participant, $planet] = $this->player($name);
        self::getContainer()->get(DiplomacyService::class)->create($participant, $name, $tag);

        return [$participant, $planet, self::getContainer()->get(RelationResolver::class)->guild($participant)];
    }

    /** @return array{PlayerUniverse, Planet} */
    private function player(string $name, string $universeSlug = 'omega-1'): array
    {
        $credentials = new RegistrationData();
        $credentials->email = strtolower($name).'@example.test';
        $credentials->username = $name;
        $credentials->plainPassword = bin2hex(random_bytes(16)).'Aa!';
        $player = self::getContainer()->get(RegistrationService::class)->register($credentials);
        $player->emailVerifiedAt = new \DateTimeImmutable('-1 minute');
        $this->em->flush();
        $universe = $this->em->getRepository(Universe::class)->findOneBy(['slug' => $universeSlug]);
        self::getContainer()->get(UniverseService::class)->select($player, $universe->id);
        $membership = $player->currentPlayerUniverse;
        $planet = $this->em->getRepository(Planet::class)->findOneBy(['owner' => $membership]);

        return [$membership, $planet];
    }

    private function unknownSystem(PlayerUniverse $viewer): SolarSystem
    {
        $candidate = $this->em->getConnection()->fetchOne(
            'SELECT s.id FROM solar_system s JOIN galaxy a ON a.id = s.galaxy_id
            WHERE a.universe_id = ? AND NOT EXISTS (SELECT 1 FROM player_discovery d WHERE d.player_universe_id = ? AND d.solar_system_id = s.id)
            ORDER BY s.id LIMIT 1',
            [$viewer->universe->id, $viewer->id],
        );

        return $this->em->find(SolarSystem::class, (int) $candidate);
    }

    private function discover(PlayerUniverse $viewer, SolarSystem $system): void
    {
        if ($this->em->getRepository(PlayerDiscovery::class)->findOneBy(['playerUniverse' => $viewer, 'solarSystem' => $system])) {
            return;
        }
        $discovery = new PlayerDiscovery();
        $discovery->playerUniverse = $viewer;
        $discovery->solarSystem = $system;
        $this->em->persist($discovery);
        $this->em->flush();
    }

    private function stock(Planet $planet, string $resource): float
    {
        return (float) $this->em->getConnection()->fetchOne('SELECT current_stock FROM planet_resource WHERE planet_id = ? AND resource_type = ?', [$planet->id, $resource]);
    }
}
