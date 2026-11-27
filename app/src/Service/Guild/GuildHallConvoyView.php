<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, FleetShip, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;

final readonly class GuildHallConvoyView
{
    public function __construct(private GameRepository $repository, private GameCatalog $catalog)
    {
    }

    public function describe(PlayerUniverse $member, int $requestedOrigin): array
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
                if (($ship['mobile'] ?? true) && (!isset($ship['allowed_missions']) || in_array('transport', $ship['allowed_missions'], true))) {
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
}
