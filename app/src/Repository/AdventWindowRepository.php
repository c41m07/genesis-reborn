<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{AdventWindow, Universe};
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class AdventWindowRepository
{
    public function __construct(private EntityManagerInterface $em, private ClockInterface $clock) {}

    public function current(Universe $universe, ?int $editionId = null): ?AdventWindow
    {
        $query = $this->em->createQueryBuilder()
            ->select('w', 'e')->from(AdventWindow::class, 'w')->join('w.edition', 'e')
            ->where('w.universe = :universe AND w.visibleFrom <= :now AND w.visibleUntil > :now')
            ->setParameter('universe', $universe)->setParameter('now', $this->utcNow());
        if ($editionId !== null) {
            $query->andWhere('e.id = :edition')->setParameter('edition', $editionId);
        }

        return $query->getQuery()->getOneOrNullResult();
    }

    /** Lecture légère du bandeau et contrôle répété après les verrous des actions de jeu. */
    public function isVisible(Universe $universe, ?int $editionId = null): bool
    {
        return $this->visibleUntil($universe, $editionId) !== null;
    }

    public function visibleUntil(Universe $universe, ?int $editionId = null): ?\DateTimeImmutable
    {
        $now = $this->utcNow()->format('Y-m-d H:i:s');
        $sql = 'SELECT visible_until FROM advent_window WHERE universe_id = ? AND visible_from <= ? AND visible_until > ?';
        $params = [$universe->id, $now, $now];
        if ($editionId !== null) {
            $sql .= ' AND edition_id = ?';
            $params[] = $editionId;
        }

        $until = $this->em->getConnection()->fetchOne($sql.' LIMIT 1', $params);

        return $until === false ? null : new \DateTimeImmutable($until, new \DateTimeZone('UTC'));
    }

    /** @return list<AdventWindow> */
    public function scheduled(): array
    {
        return $this->em->createQuery('SELECT w, u FROM '.AdventWindow::class.' w JOIN w.universe u
            WHERE w.visibleUntil > :now ORDER BY w.visibleFrom, u.name')
            ->setParameter('now', $this->utcNow())->getResult();
    }

    private function utcNow(): \DateTimeImmutable
    {
        return $this->clock->now()->setTimezone(new \DateTimeZone('UTC'));
    }
}
