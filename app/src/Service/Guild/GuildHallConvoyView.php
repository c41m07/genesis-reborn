<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, FleetShip, GuildHall, PlayerUniverse};
use App\Repository\{GameRepository, GuildHallConvoyRepository};
use App\Service\Catalog\GameCatalog;

final readonly class GuildHallConvoyView
{
    public function __construct(
        private GameRepository $repository,
        private GameCatalog $catalog,
        private GuildHallConvoyRepository $convoys,
    ) {
    }

    public function describe(PlayerUniverse $member, int $requestedOrigin, bool $withoutFreight = false): array
    {
        $colonies = $this->repository->planets($member->universe, $member);
        $origin = $colonies[0] ?? null;
        foreach ($colonies as $colony) {
            if ($colony->id === $requestedOrigin) {
                $origin = $colony;
                break;
            }
        }
        $catalogue = $this->catalog->forUniverse($member->universe)['catalog']['ships'];
        $available = [];
        if ($origin !== null) {
            $idle = $this->repository->one(Fleet::class, ['homePlanet' => $origin, 'playerUniverse' => $member, 'status' => 'idle']);
            foreach ($idle ? $this->repository->all(FleetShip::class, ['fleet' => $idle]) : [] as $stock) {
                if ($stock->quantity <= 0 || !isset($catalogue[$stock->slug]) || !in_array($stock->tier, GameCatalog::TIERS, true)) {
                    continue;
                }
                $ship = $this->catalog->getTierForUniverse($member->universe, 'ships', $stock->slug, $stock->tier);
                if (($ship['mobile'] ?? true) && ($withoutFreight ? !isset($ship['economic_unit'])
                    : (!isset($ship['allowed_missions']) || in_array('transport', $ship['allowed_missions'], true)))) {
                    $available[$stock->slug][$stock->tier] = $stock->quantity;
                }
            }
        }

        $rows = [];
        foreach ($catalogue as $slug => $ship) {
            if (isset($available[$slug])) {
                $rows[] = ['slug' => $slug, 'name' => $ship['label'] ?? $slug, 'ranks' => $available[$slug]];
            }
        }

        return [
            'colonies' => $colonies,
            'origin' => $origin,
            'ships' => $rows,
            'materials' => ['hydrogen' => 'Hydrogène', 'metal' => 'Métal', 'nano' => 'Nano', 'antimatter' => 'Antimatière'],
        ];
    }

    /** @return list<array{ships: list<array{name: string, rank: string, quantity: int}>}> */
    public function receivedDonations(PlayerUniverse $member, GuildHall $hall): array
    {
        $receipts = $this->convoys->receivedHallShipDonations($member, $hall);
        if ($receipts === []) {
            return [];
        }
        $catalogue = $this->catalog->forUniverse($member->universe)['catalog']['ships'];
        $ranks = ['basic' => 'Basique', 'intermediate' => 'Intermédiaire', 'superior' => 'Supérieur'];
        $rows = [];
        foreach ($receipts as $receipt) {
            $ships = [];
            foreach ($receipt->deliveredShips ?? [] as $slug => $quantities) {
                foreach ($quantities as $rank => $quantity) {
                    $ships[] = ['name' => $catalogue[$slug]['label'] ?? $slug, 'rank' => $ranks[$rank] ?? $rank, 'quantity' => $quantity];
                }
            }
            $rows[] = ['ships' => $ships];
        }

        return $rows;
    }
}
