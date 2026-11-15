<?php
declare(strict_types=1);

namespace App\Repository;

use App\Entity\PatchNote;
use Doctrine\ORM\EntityManagerInterface;

final class PatchNoteRepository
{
    public function __construct(private EntityManagerInterface $em) {}

    /** @return PatchNote[] */
    public function published(): array
    {
        return $this->em->createQueryBuilder()->select('p')->from(PatchNote::class, 'p')
            ->where('p.status = :status')->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->orderBy('p.publishedAt', 'DESC')->addOrderBy('p.id', 'DESC')->getQuery()->getResult();
    }

    public function latestPublishedId(): int
    {
        return (int) ($this->em->createQueryBuilder()->select('MAX(p.id)')->from(PatchNote::class, 'p')
            ->where('p.status = :status')->andWhere('p.publishedAt <= :now')
            ->setParameter('status', 'published')->setParameter('now', new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->getQuery()->getSingleScalarResult() ?? 0);
    }

    public function page(string $status, string $search, int $page): array
    {
        $query = $this->em->createQueryBuilder()->select('p')->from(PatchNote::class, 'p');
        if (in_array($status, ['draft', 'published'], true)) $query->andWhere('p.status = :status')->setParameter('status', $status);
        if ($search !== '') $query->andWhere('LOWER(p.title) LIKE :search OR LOWER(p.version) LIKE :search')->setParameter('search', '%'.mb_strtolower($search).'%');
        $total = (int) (clone $query)->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        $pages = max(1, (int) ceil($total / 50));
        $page = max(1, min($page, $pages));
        return ['patchNotes'=>$query->orderBy('p.updatedAt', 'DESC')->addOrderBy('p.id', 'DESC')
            ->setFirstResult(($page - 1) * 50)->setMaxResults(50)->getQuery()->getResult(),
            'total'=>$total, 'page'=>$page, 'pages'=>$pages];
    }
}
