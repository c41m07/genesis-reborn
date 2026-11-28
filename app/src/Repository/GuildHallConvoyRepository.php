<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Guild, GuildHall, GuildHallConvoy, PlayerUniverse};
use App\Service\Guild\GuildCommandBerth;
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

    /** @return list<GuildHallConvoy> Stationnements privés, pas les dons ni les vols en approche. */
    public function commandBerths(Guild $guild, ?PlayerUniverse $owner): array
    {
        $query = $this->entityManager->createQueryBuilder()
            ->select('receipt', 'fleet')
            ->from(GuildHallConvoy::class, 'receipt')
            ->join('receipt.fleet', 'fleet')
            ->join('fleet.playerUniverse', 'participant')
            ->where('receipt.guildId = :guild AND participant.universe = :universe')
            ->andWhere('receipt.commandStation = true AND receipt.shipDonation = false AND receipt.settledAt IS NOT NULL')
            ->andWhere('fleet.mission = :mission AND fleet.status = :docked')
            ->setParameter('guild', $guild->id)
            ->setParameter('universe', $guild->universe)
            ->setParameter('mission', GuildHallConvoy::MISSION)
            ->setParameter('docked', GuildCommandBerth::DOCKED)
            ->orderBy('fleet.id', 'ASC');
        if ($owner !== null) {
            $query->andWhere('fleet.playerUniverse = :owner')->setParameter('owner', $owner);
        }

        return $query->getQuery()->getResult();
    }
}
