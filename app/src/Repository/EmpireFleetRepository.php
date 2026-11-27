<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PlayerUniverse;
use Doctrine\DBAL\{ArrayParameterType, Connection};

final readonly class EmpireFleetRepository
{
    public function __construct(private Connection $connection)
    {
    }

    /** @return list<array{slug:string,tier:string,quantity:int}> */
    public function ships(PlayerUniverse $member): array
    {
        // Un même instant de lecture évite de compter un retour à la fois en mission et au hangar.
        $compositions = $this->connection->executeQuery(<<<'SQL'
            SELECT JSON_OBJECT(stock.slug, JSON_OBJECT(stock.tier, stock.quantity))
            FROM (
                SELECT s.slug, s.tier, SUM(s.quantity) AS quantity
                FROM fleet_ship s JOIN fleet f ON f.id = s.fleet_id
                JOIN planet p ON p.id = f.home_planet_id
                JOIN solar_system sys ON sys.id = p.solar_system_id
                JOIN galaxy g ON g.id = sys.galaxy_id
                WHERE f.player_universe_id = :member AND g.universe_id = :universe
                    AND f.status NOT IN ('completed', 'failed') AND s.quantity > 0
                GROUP BY s.slug, s.tier
            ) stock
            UNION ALL
            SELECT JSON_OBJECT('transport', JSON_EXTRACT(t.ships, '$'))
            FROM market_trade t
            WHERE t.membership_id = :member AND t.universe_id = :universe AND t.status IN (:active)
            UNION ALL
            SELECT a.reserved_ships
            FROM pirate_attack_campaign a JOIN pirate_reconnaissance r ON r.id = a.reconnaissance_id
            JOIN solar_system sys ON sys.id = r.system_id JOIN galaxy g ON g.id = sys.galaxy_id
            WHERE r.membership_id = :member AND r.owner_id = :owner AND g.universe_id = :universe
                AND JSON_LENGTH(a.reserved_ships) > 0
            SQL, [
                'member' => $member->id, 'owner' => $member->player->id, 'universe' => $member->universe->id,
                'active' => ResourceMarketRepository::ACTIVE_STATUSES,
            ], ['active' => ArrayParameterType::STRING]);

        $rows = [];
        foreach ($compositions->iterateColumn() as $composition) {
            foreach (json_decode($composition, true, flags: JSON_THROW_ON_ERROR) as $slug => $tiers) {
                foreach ($tiers as $tier => $quantity) {
                    $key = $slug.':'.$tier;
                    $rows[$key] ??= ['slug' => $slug, 'tier' => $tier, 'quantity' => 0];
                    $rows[$key]['quantity'] += (int) $quantity;
                }
            }
        }

        return array_values($rows);
    }
}
