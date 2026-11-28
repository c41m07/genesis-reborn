<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Fleet, FleetShip, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use Doctrine\ORM\EntityManagerInterface;

/** Présente uniquement les coques personnelles à quai sur la colonie sélectionnée. */
final readonly class GuildDockView
{
    public function __construct(
        private GameRepository $repository,
        private EntityManagerInterface $em,
        private GameCatalog $catalog,
    ) {
    }

    public function describe(PlayerUniverse $actor, int $originId): array
    {
        $colonies = $this->repository->planets($actor->universe, $actor);
        $origin = $colonies[0] ?? null;
        foreach ($colonies as $colony) {
            if ($colony->id === $originId) {
                $origin = $colony;
                break;
            }
        }
        $definitions = $this->catalog->forUniverse($actor->universe)['catalog']['ships'];
        $fleet = $origin === null ? null : $this->repository->one(Fleet::class, [
            'playerUniverse' => $actor, 'homePlanet' => $origin, 'status' => 'idle',
        ]);
        $choices = [];
        if ($fleet instanceof Fleet) {
            $stocks = $this->em->getRepository(FleetShip::class)->findBy(['fleet' => $fleet], ['slug' => 'ASC', 'tier' => 'ASC']);
            foreach ($stocks as $stock) {
                $definition = $definitions[$stock->slug] ?? null;
                if ($stock->quantity < 1 || !in_array($stock->tier, ['basic', 'intermediate'], true)
                    || $definition === null || !($definition['mobile'] ?? true) || isset($definition['economic_unit'])) {
                    continue;
                }
                $choices[] = ['slug' => $stock->slug, 'name' => $definition['name'] ?? $stock->slug, 'tier' => $stock->tier, 'quantity' => $stock->quantity];
            }
        }

        return ['colonies' => $colonies, 'origin' => $origin, 'choices' => $choices];
    }
}
