<?php

namespace App\Repository;

use App\Entity\{Planet, PlayerUniverse};
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\ORM\EntityManagerInterface;

/** Résumés d’affichage ; les rapports détaillés restent des entités complètes. */
final class ReportSummaryRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    public function combats(PlayerUniverse $viewer, array $ids): array
    {
        if ($ids === []) return [];
        $rows = $this->em->getConnection()->fetchAllAssociative(<<<'SQL'
SELECT r.id, r.outcome, r.resolved_at, e.planet_id,
       COALESCE(JSON_LENGTH(r.snapshot, '$.participants'), 0) AS participant_count
FROM combat_log r
JOIN combat_event e ON e.id = r.combat_event_id
JOIN planet p ON p.id = e.planet_id
JOIN solar_system s ON s.id = p.solar_system_id
JOIN galaxy g ON g.id = s.galaxy_id
WHERE r.id IN (:ids) AND g.universe_id = :universe
  AND (p.owner_id = :viewer OR EXISTS (
      SELECT 1 FROM combat_participant cp WHERE cp.combat_event_id = e.id AND cp.player_universe_id = :viewer
  ))
SQL, ['ids'=>$ids, 'universe'=>$viewer->universe->id, 'viewer'=>$viewer->id], ['ids'=>ArrayParameterType::INTEGER]);
        if ($rows === []) return [];
        $planets = $this->em->createQueryBuilder()->select('planet', 'system', 'galaxy')
            ->from(Planet::class, 'planet')->join('planet.solarSystem', 'system')->join('system.galaxy', 'galaxy')
            ->where('planet.id IN (:ids)')->andWhere('galaxy.universe = :universe')
            ->setParameter('ids', array_unique(array_column($rows, 'planet_id')))
            ->setParameter('universe', $viewer->universe)->getQuery()->getResult();
        $byId = [];
        foreach ($planets as $planet) $byId[$planet->id] = $planet;
        $summaries = [];
        foreach ($rows as $row) {
            $summaries[(int) $row['id']] = ['id'=>(int) $row['id'], 'outcome'=>$row['outcome'],
                'resolvedAt'=>new \DateTimeImmutable($row['resolved_at'], new \DateTimeZone('UTC')),
                'planet'=>$byId[(int) $row['planet_id']], 'participantCount'=>(int) $row['participant_count']];
        }
        return $summaries;
    }
}
