<?php

namespace App\Repository;

use App\Entity\{Player, VerificationDeliveryRequest};
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\DBAL\LockMode;

final class VerificationDeliveryRequestRepository
{
    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function byKey(Player $player, string $hash): ?VerificationDeliveryRequest
    {
        return $this->em->createQueryBuilder()->select('request')
            ->from(VerificationDeliveryRequest::class, 'request')
            ->where('request.player = :player AND request.requestKeyHash = :hash')
            ->setParameter('player', $player)->setParameter('hash', $hash)
            ->setMaxResults(1)->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)
            ->getOneOrNullResult();
    }

    public function blocking(Player $player, \DateTimeImmutable $now): ?VerificationDeliveryRequest
    {
        // Lectures courantes verrouillées, même en isolation REPEATABLE READ.
        // Un résultat indéterminé ne devient jamais réessayable par le seul
        // passage du temps. Les états inconnus sont aussi bloqués par défaut.
        return $this->em->createQueryBuilder()->select('request')
            ->from(VerificationDeliveryRequest::class, 'request')
            ->where('request.player = :player')
            ->andWhere('request.state NOT IN (:terminal) OR request.cooldownUntil > :now')
            ->setParameter('player', $player)
            ->setParameter('terminal', ['transport_accepted', 'cancelled'])
            ->setParameter('now', $now)
            ->orderBy('request.id', 'DESC')->setMaxResults(1)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(\Doctrine\ORM\Query::HINT_REFRESH, true)->getOneOrNullResult();
    }
}
