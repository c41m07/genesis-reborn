<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{GuildHall, GuildHallConvoy, PlayerUniverse};
use Doctrine\ORM\EntityManagerInterface;

final readonly class GuildHallConvoyRepository
{
    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    /** @return list<GuildHallConvoy> Les dix derniers dons reçus, visibles uniquement par leur donateur. */
    public function receivedHallShipDonations(PlayerUniverse $member, GuildHall $hall): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('receipt', 'fleet')
            ->from(GuildHallConvoy::class, 'receipt')
            ->join('receipt.fleet', 'fleet')
            ->where('receipt.hallId = :hall AND receipt.guildId = :guild')
            ->andWhere('fleet.playerUniverse = :member')
            ->andWhere('receipt.shipDonation = true AND receipt.deliveredShips IS NOT NULL AND receipt.settledAt IS NOT NULL')
            ->setParameter('hall', $hall->id)
            ->setParameter('guild', $hall->guild->id)
            ->setParameter('member', $member)
            ->orderBy('receipt.settledAt', 'DESC')
            ->addOrderBy('fleet.id', 'DESC')
            ->setMaxResults(10)
            ->getQuery()->getResult();
    }
}
