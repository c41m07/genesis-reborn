<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Guild, GuildHall, Planet, PlayerDiscovery, PlayerUniverse, SolarSystem};
use Doctrine\DBAL\{ArrayParameterType, LockMode};
use Doctrine\ORM\EntityManagerInterface;

final readonly class GuildHallRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public const SITE_PAGE_SIZE = 12;

    public function forGuild(Guild $guild): ?GuildHall
    {
        return $this->em->getRepository(GuildHall::class)->findOneBy(['guild' => $guild]);
    }

    /** @return list<SolarSystem> */
    public function availableSites(PlayerUniverse $actor, string $query, int $after): array
    {
        $builder = $this->em->createQueryBuilder()
            ->select('system', 'galaxy')->from(SolarSystem::class, 'system')
            ->join('system.galaxy', 'galaxy')->join(PlayerDiscovery::class, 'discovery', 'WITH', 'discovery.solarSystem = system')
            ->leftJoin(GuildHall::class, 'hall', 'WITH', 'hall.solarSystem = system')
            ->leftJoin(Planet::class, 'planet', 'WITH', 'planet.solarSystem = system AND planet.position = 0')
            ->where('discovery.playerUniverse = :actor AND galaxy.universe = :universe AND hall.id IS NULL AND planet.id IS NULL')
            ->andWhere('system.id > :after')->orderBy('system.id', 'ASC')->setMaxResults(self::SITE_PAGE_SIZE + 1)
            ->setParameter('actor', $actor)->setParameter('universe', $actor->universe)->setParameter('after', $after);
        if ($query !== '') {
            // LOCATE est une recherche littérale : % et _ restent de simples caractères.
            $builder->andWhere('LOCATE(:search, system.name) > 0')->setParameter('search', $query);
        }

        return $builder->getQuery()->getResult();
    }

    public function lockForGuild(Guild $guild): ?GuildHall
    {
        $id = $this->em->getConnection()->fetchOne('SELECT id FROM guild_hall WHERE guild_id = ? FOR UPDATE', [$guild->id]);
        if ($id === false) {
            return null;
        }

        $hall = $this->em->find(GuildHall::class, (int) $id, LockMode::PESSIMISTIC_WRITE);
        if (!$hall instanceof GuildHall) {
            throw new \LogicException('Construction de guilde introuvable après verrouillage.');
        }
        $this->em->refresh($hall, LockMode::PESSIMISTIC_WRITE);

        return $hall;
    }

    public function releaseClaimForEndedGuild(Guild $guild): ?int
    {
        if ($guild->archivedAt === null) {
            throw new \LogicException('Une guilde encore active conserve sa revendication.');
        }
        // L'appelant tient déjà la guilde et son univers dans la transaction de
        // conversion. Libérer uniquement la place, jamais les messages ni le stock.
        $hall = $this->lockForGuild($guild);
        if ($hall?->solarSystem === null) {
            return null;
        }
        $system = $hall->solarSystem->id;
        $hall->solarSystem = null;
        $hall->placedAt = null;
        ++$hall->revision;

        return $system;
    }

    public function isClaimed(int $systemId): bool
    {
        return $this->em->getConnection()->fetchOne(
            'SELECT id FROM guild_hall WHERE solar_system_id = ? FOR UPDATE',
            [$systemId],
        ) !== false;
    }

    /** @param list<int> $systemIds @return array<int, array{tag: ?string, position: int}> */
    public function claimsForKnownSystems(PlayerUniverse $viewer, array $systemIds): array
    {
        if (!$viewer->isPlayerControlled() || $systemIds === []) {
            return [];
        }

        // Un Hall n'ajoute pas de découverte. Filtrer aussi côté SQL pour que les
        // identifiants reçus par erreur d'un autre univers ne dévoilent aucun tag.
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT s.id, g.tag FROM guild_hall h
                JOIN guild g ON g.id = h.guild_id AND g.archived_at IS NULL
                JOIN solar_system s ON s.id = h.solar_system_id
                JOIN galaxy a ON a.id = s.galaxy_id AND a.universe_id = g.universe_id
                JOIN player_discovery d ON d.solar_system_id = s.id
                WHERE g.universe_id = ? AND d.player_universe_id = ? AND s.id IN (?)',
            [$viewer->universe->id, $viewer->id, $systemIds],
            [\Doctrine\DBAL\ParameterType::INTEGER, \Doctrine\DBAL\ParameterType::INTEGER, ArrayParameterType::INTEGER],
        );

        $claims = [];
        foreach ($rows as $row) {
            $claims[(int) $row['id']] = ['tag' => $row['tag'], 'position' => 0];
        }

        return $claims;
    }
}
