<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\{EditorialRevision, Universe};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class EditorialRepository
{
    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock) {}

    /** Une seule version publiée par code, y compris après restauration ou publication différée. */
    public function published(string $kind, ?Universe $universe, ?string $code = null): array
    {
        if ($universe !== null && $universe->id === null) return [];
        $db = $this->em->getConnection();
        $args = [$kind, $universe?->id, $this->clock->now()->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s')];
        $filter = '';
        if ($code !== null) {
            $filter = ' AND code = ?';
            $args[] = $code;
        }
        $rows = $db->fetchAllAssociative('SELECT code, content FROM (
            SELECT code, content, ROW_NUMBER() OVER (PARTITION BY code ORDER BY publish_at DESC, id DESC) AS position_in_history
            FROM editorial_revision WHERE kind = ? AND universe_id <=> ? AND publish_at <= ?'.$filter.'
        ) ranked WHERE position_in_history = 1 ORDER BY code', $args);
        $result = [];
        foreach ($rows as $row) $result[$row['code']] = json_decode($row['content'], true, flags: JSON_THROW_ON_ERROR);
        return $result;
    }

    public function history(int $page): array
    {
        return $this->em->createQueryBuilder()->select('revision','author','world')
            ->from(EditorialRevision::class,'revision')
            ->leftJoin('revision.author','author')->leftJoin('revision.universe','world')
            ->orderBy('revision.id','DESC')->setFirstResult(($page-1)*25)->setMaxResults(26)
            ->getQuery()->getResult();
    }
}
