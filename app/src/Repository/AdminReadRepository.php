<?php

namespace App\Repository;

use App\Entity\AdminAuditLog;
use App\Entity\Player;
use App\Service\Search\SearchCriteria;
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

    public function players(array $filters, int $page): array
    {
        $query = $this->em->createQueryBuilder()->select('p')->from(Player::class, 'p');
        if ($filters['q'] !== '') {
            $query->andWhere("LOWER(CONCAT(p.username, ' ', p.email)) LIKE :query ESCAPE '!'")
                ->setParameter('query', SearchCriteria::pattern($filters['q']));
        }
        if ($filters['status'] !== '') $query->andWhere($filters['status'] === 'suspended' ? 'p.lockedAt IS NOT NULL' : 'p.lockedAt IS NULL');
        if ($filters['role'] !== '') {
            $query->andWhere('p.roles '.($filters['role'] === 'admin' ? 'LIKE' : 'NOT LIKE').' :role')
                ->setParameter('role', '%"ROLE_ADMIN"%');
        }
        return $this->page($query, 'p', $page, ['p.id'=>'DESC']);
    }

    public function audit(array $filters, int $page): array
    {
        return $this->page($this->auditQuery($filters), 'e', $page, ['e.createdAt'=>'DESC', 'e.id'=>'DESC']);
    }

    public function auditActions(): array
    {
        return array_column($this->em->createQueryBuilder()->select('DISTINCT e.action AS action')
            ->from(AdminAuditLog::class, 'e')->orderBy('e.action', 'ASC')->getQuery()->getArrayResult(), 'action');
    }

    public function feedbacks(array $filters, int $page): array
    {
        $query = $this->em->createQueryBuilder()->select('f', 'p', 'u')->from(\App\Entity\UserFeedback::class, 'f')
            ->join('f.player', 'p')->leftJoin('f.universe', 'u');
        foreach (['type', 'priority'] as $field) {
            if ($filters[$field] !== '') $query->andWhere('f.'.$field.' = :'.$field)->setParameter($field, $filters[$field]);
        }
        if ($filters['status'] === '') $query->andWhere('f.status <> :archived')->setParameter('archived', 'archived');
        elseif ($filters['status'] !== 'all') $query->andWhere('f.status = :status')->setParameter('status', $filters['status']);
        if ($filters['universe'] !== '') $query->andWhere('u.id = :universe')->setParameter('universe', (int) $filters['universe']);
        if ($filters['q'] !== '') $query->andWhere('f.title LIKE :query OR p.username LIKE :query')->setParameter('query', '%'.$filters['q'].'%');
        $stats = (clone $query)->select("COUNT(f.id) AS total, COALESCE(MAX(f.id), 0) AS latest, SUM(CASE WHEN f.status = 'open' THEN 1 ELSE 0 END) AS openCount")
            ->getQuery()->getSingleResult();
        return $this->page($query, 'f', $page, ['f.createdAt'=>'DESC', 'f.id'=>'DESC'], (int) $stats['total'])
            + ['latest'=>(int) $stats['latest'], 'openCount'=>(int) $stats['openCount']];
    }

    public function gameEvents(bool $archived, int $page): array
    {
        // Reproduire !empty(parameters['archived_at']) avant LIMIT, y compris les anciens JSON.
        $archiveState = <<<'SQL'
CASE JSON_TYPE(JSON_EXTRACT(e.parameters, '$.archived_at'))
    WHEN 'STRING' THEN JSON_UNQUOTE(JSON_EXTRACT(e.parameters, '$.archived_at')) NOT IN ('', '0')
    WHEN 'NULL' THEN 0
    WHEN 'BOOLEAN' THEN JSON_UNQUOTE(JSON_EXTRACT(e.parameters, '$.archived_at')) = 'true'
    WHEN 'ARRAY' THEN JSON_LENGTH(JSON_EXTRACT(e.parameters, '$.archived_at')) > 0
    WHEN 'OBJECT' THEN JSON_LENGTH(JSON_EXTRACT(e.parameters, '$.archived_at')) > 0
    ELSE COALESCE(JSON_UNQUOTE(JSON_EXTRACT(e.parameters, '$.archived_at')) + 0, 0) <> 0
END
SQL;
        $connection = $this->em->getConnection();
        $where = ' FROM game_event e WHERE ('.$archiveState.') = :archived';
        $params = ['archived'=>$archived ? 1 : 0];
        $total = (int) $connection->fetchOne('SELECT COUNT(*)'.$where, $params);
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));
        $ids = $connection->fetchFirstColumn('SELECT e.id'.$where.' ORDER BY e.starts_at DESC, e.id DESC LIMIT 50 OFFSET '.(($page - 1) * 50), $params);
        $events = $ids === [] ? [] : $this->em->createQueryBuilder()->select('e', 'u')->from(\App\Entity\GameEvent::class, 'e')
            ->leftJoin('e.universe', 'u')->where('e.id IN (:ids)')->setParameter('ids', $ids)
            ->orderBy('e.startsAt', 'DESC')->addOrderBy('e.id', 'DESC')->getQuery()->getResult();
        return ['events'=>$events, 'total'=>$total, 'page'=>$page, 'pages'=>$pages];
    }

    /** Export borné en mémoire ; le plafond exclut les actions ajoutées pendant la lecture. */
    public function auditExport(array $filters): \Generator
    {
        $ceiling = (int) $this->em->createQueryBuilder()->select('COALESCE(MAX(e.id), 0)')
            ->from(AdminAuditLog::class, 'e')->getQuery()->getSingleScalarResult();
        $query = $this->auditQuery($filters)->select('e.id', 'e.createdAt', 'e.action', 'e.targetType', 'e.targetId', 'e.details', 'actor.username AS actorName')
            ->andWhere('e.id <= :ceiling')->setParameter('ceiling', $ceiling)
            ->orderBy('e.createdAt', 'DESC')->addOrderBy('e.id', 'DESC')->setMaxResults(100);
        for ($offset = 0; ; $offset += 100) {
            $rows = $query->setFirstResult($offset)->getQuery()->getArrayResult();
            foreach ($rows as $row) yield $row;
            if (count($rows) < 100) break;
        }
    }

    private function auditQuery(array $filters): QueryBuilder
    {
        $query = $this->em->createQueryBuilder()->select('e', 'actor')->from(AdminAuditLog::class, 'e')->leftJoin('e.actor', 'actor');
        if ($filters['q'] !== '') {
            $query->andWhere("LOWER(CONCAT(COALESCE(actor.username, 'Compte supprimé'), ' ', e.action, ' ', e.targetType, ' #', e.targetId, ' ', e.details)) LIKE :query ESCAPE '!'")
                ->setParameter('query', SearchCriteria::pattern($filters['q']));
        }
        if ($filters['action'] !== '') $query->andWhere('e.action = :action')->setParameter('action', $filters['action']);
        return $query;
    }

    private function page(QueryBuilder $query, string $alias, int $page, array $order, ?int $total = null): array
    {
        $total ??= (int) (clone $query)->select('COUNT('.$alias.'.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));
        foreach ($order as $field=>$direction) $query->addOrderBy($field, $direction);
        return ['entries'=>$query->setFirstResult(($page - 1) * 50)->setMaxResults(50)->getQuery()->getResult(),
            'total'=>$total, 'page'=>$page, 'pages'=>$pages];
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
