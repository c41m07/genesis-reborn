<?php

namespace App\Repository;

use App\Entity\AdminAuditLog;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/** Lectures de la console ; les contrôleurs conservent les permissions et les écritures. */
final class AdminReadRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    /** @return array<string,int> */
    public function counters(): array
    {
        return array_map('intval', $this->em->getConnection()->fetchAssociative(<<<'SQL'
SELECT
    (SELECT COUNT(*) FROM player) AS playerCount,
    (SELECT COUNT(*) FROM player WHERE locked_at IS NOT NULL) AS suspendedPlayerCount,
    ((SELECT COUNT(*) FROM message_report WHERE status = 'open') +
     (SELECT COUNT(*) FROM player_report WHERE status = 'open')) AS openReportCount
SQL));
    }

    /** @return array<int,int> */
    public function universePlayerCounts(): array
    {
        $counts = [];
        foreach ($this->em->getConnection()->fetchAllAssociative('SELECT universe_id, COUNT(*) AS total FROM player_universe GROUP BY universe_id') as $row) {
            $counts[(int) $row['universe_id']] = (int) $row['total'];
        }
        return $counts;
    }

    public function securityIncidentCount(): int
    {
        return (int) $this->unreviewedIncidents()->select('COUNT(e.id)')->getQuery()->getSingleScalarResult();
    }

    public function latestSecurityIncidentId(): int
    {
        return (int) $this->unreviewedIncidents()->select('COALESCE(MAX(e.id), 0)')->getQuery()->getSingleScalarResult();
    }

    /** @return list<AdminAuditLog> */
    public function securityIncidents(): array
    {
        return $this->unreviewedIncidents()->addSelect('actor')->leftJoin('e.actor', 'actor')
            ->orderBy('e.createdAt', 'DESC')->addOrderBy('e.id', 'DESC')->setMaxResults(50)->getQuery()->getResult();
    }

    /** @return list<AdminAuditLog> */
    public function recentActions(): array
    {
        return $this->em->createQueryBuilder()->select('e', 'actor')->from(AdminAuditLog::class, 'e')
            ->leftJoin('e.actor', 'actor')->orderBy('e.createdAt', 'DESC')->addOrderBy('e.id', 'DESC')
            ->setMaxResults(5)->getQuery()->getResult();
    }

    private function unreviewedIncidents(): QueryBuilder
    {
        // L’historique d’examen reste la preuve ; aucun incident n’est effacé.
        return $this->em->createQueryBuilder()->select('e')->from(AdminAuditLog::class, 'e')
            ->where('e.action = :denied')
            ->andWhere('NOT EXISTS (SELECT review.id FROM App\Entity\AdminAuditLog review WHERE review.action = :reviewed AND review.targetType = :targetType AND review.targetId = e.id)')
            ->setParameter('denied', 'admin_access_denied')->setParameter('reviewed', 'admin_access_reviewed')
            ->setParameter('targetType', 'admin_access_incident');
    }
}
