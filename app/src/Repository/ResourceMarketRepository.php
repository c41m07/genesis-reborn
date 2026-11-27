<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{Galaxy, Planet, PlayerUniverse, MarketMerchantDelivery, MarketTrade, ResourceMarket, Universe};
use Doctrine\ORM\EntityManagerInterface;

final readonly class ResourceMarketRepository
{
    public const ACTIVE_STATUSES = ['outbound', 'returning', 'awaiting_colony'];

    public function __construct(private EntityManagerInterface $em)
    {
    }

    public function find(Universe $universe): ?ResourceMarket
    {
        return $this->em->find(ResourceMarket::class, $universe->id);
    }

    /** @return list<MarketTrade> */
    public function trades(Universe $universe, int $membershipId): array
    {
        $recent = $this->em->getRepository(MarketTrade::class)->findBy(
            ['universe' => $universe, 'membershipId' => $membershipId], ['id' => 'DESC'], 30
        );
        if (count($recent) < 30) {
            return $recent;
        }
        // Les liens de suivi et les commandes de rappel ou de redirection doivent rester accessibles.
        $olderActive = $this->em->createQueryBuilder()->select('t')->from(MarketTrade::class, 't')
            ->where('t.universe = :universe AND t.membershipId = :member AND t.id < :oldest')
            ->andWhere('t.status IN (:active)')->orderBy('t.id', 'DESC')
            ->setParameter('universe', $universe)->setParameter('member', $membershipId)
            ->setParameter('oldest', end($recent)->id)->setParameter('active', self::ACTIVE_STATUSES)
            ->getQuery()->getResult();
        return array_merge($recent, $olderActive);
    }

    /** Convois actifs du seul empire consulté, avec leur colonie de départ ou de retour en une lecture. */
    public function activeTraffic(PlayerUniverse $member, ?Galaxy $colonyGalaxy): array
    {
        $query = $this->em->createQueryBuilder()
            ->select('t, p.name AS colonyName, IDENTITY(p.owner) AS planetOwner, s.id AS systemId, g.id AS galaxyId')
            ->from(MarketTrade::class, 't')
            ->leftJoin(Planet::class, 'p', 'WITH', "p.id = CASE WHEN t.status = 'outbound' THEN t.originId ELSE t.returnPlanetId END")
            ->leftJoin('p.solarSystem', 's')
            ->leftJoin('s.galaxy', 'g')
            ->where('t.membershipId = :member AND t.universe = :universe')
            ->andWhere('g.universe = :universe OR p.id IS NULL')
            ->andWhere('t.status IN (:active)')
            ->setParameter('member', $member->id)
            ->setParameter('universe', $member->universe)
            ->setParameter('active', self::ACTIVE_STATUSES)
            ->orderBy('t.id', 'DESC');
        if ($colonyGalaxy) {
            $query->andWhere('g = :galaxy')->setParameter('galaxy', $colonyGalaxy);
        }
        return $query->getQuery()->getResult();
    }

    /** @return list<MarketMerchantDelivery> */
    public function merchantLog(Universe $universe): array
    {
        return $this->em->getRepository(MarketMerchantDelivery::class)->findBy(
            ['universe' => $universe], ['occurrence' => 'DESC'], 8
        );
    }

    /** @return list<int> */
    public function dueUniverses(string $occurrence): array
    {
        return array_map('intval', $this->em->getConnection()->fetchFirstColumn(
            'SELECT universe_id FROM resource_market WHERE enabled = 1 AND last_occurrence < ? ORDER BY universe_id',
            [$occurrence]
        ));
    }

    /** @return array{incoming: array<string, int>, outgoing: array<string, int>} */
    public function reserved(Universe $universe): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT pay_resource, receive_resource, SUM(pay_quantity) AS paid, SUM(receive_quantity) AS received
             FROM market_trade WHERE universe_id = ? AND status = ? GROUP BY pay_resource, receive_resource',
            [$universe->id, 'outbound']
        );
        $incoming = $outgoing = [];
        foreach ($rows as $row) {
            $incoming[$row['pay_resource']] = ($incoming[$row['pay_resource']] ?? 0) + (int) $row['paid'];
            $outgoing[$row['receive_resource']] = ($outgoing[$row['receive_resource']] ?? 0) + (int) $row['received'];
        }

        return ['incoming' => $incoming, 'outgoing' => $outgoing];
    }

    /** @return array<string, int> Demande nette exécutée depuis l’instant fourni. */
    public function pressureSince(Universe $universe, \DateTimeImmutable $since): array
    {
        $rows = $this->em->getConnection()->fetchAllAssociative(
            'SELECT pay_resource, receive_resource, SUM(pay_quantity) AS paid, SUM(receive_quantity) AS received
             FROM market_trade WHERE universe_id = ? AND exchanged_at >= ? GROUP BY pay_resource, receive_resource',
            [$universe->id, $since->format('Y-m-d H:i:s')]
        );
        $pressure = [];
        foreach ($rows as $row) {
            $pressure[$row['pay_resource']] = ($pressure[$row['pay_resource']] ?? 0) - (int) $row['paid'];
            $pressure[$row['receive_resource']] = ($pressure[$row['receive_resource']] ?? 0) + (int) $row['received'];
        }
        return $pressure;
    }

    /** @return array<string, int> */
    public function soldBetween(Universe $universe, \DateTimeImmutable $from, \DateTimeImmutable $until): array
    {
        $rows = $this->em->getConnection()->fetchAllKeyValue(
            'SELECT receive_resource, SUM(receive_quantity) FROM market_trade
             WHERE universe_id = ? AND exchanged_at >= ? AND exchanged_at < ? GROUP BY receive_resource',
            [$universe->id, $from->format('Y-m-d H:i:s'), $until->format('Y-m-d H:i:s')]
        );

        return array_map('intval', $rows);
    }
}
