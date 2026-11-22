<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Guild, GuildHall, PlayerUniverse};
use Doctrine\DBAL\{ArrayParameterType, LockMode};
use Doctrine\ORM\EntityManagerInterface;

final readonly class GuildHallRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
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
