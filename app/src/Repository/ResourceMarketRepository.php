<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\{MarketMerchantDelivery, MarketTrade, ResourceMarket, Universe};
use Doctrine\ORM\EntityManagerInterface;

final readonly class ResourceMarketRepository
{
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
        return $this->em->getRepository(MarketTrade::class)->findBy(
            ['universe' => $universe, 'membershipId' => $membershipId], ['id' => 'DESC'], 30
        );
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
