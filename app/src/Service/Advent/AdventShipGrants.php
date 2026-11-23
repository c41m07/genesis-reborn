<?php

declare(strict_types=1);

namespace App\Service\Advent;

use App\Entity\{Planet, PlanetBuilding, PlayerTechnology, PlayerUniverse};
use App\Manager\GameManager;
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Combat\LocalGarrison;
use App\Service\PirateCampaign\PirateRecoveryHangar;
use App\Service\Requirement\CatalogAccess;

final readonly class AdventShipGrants
{
    public function __construct(
        private GameRepository $repo,
        private GameCatalog $catalog,
        private CatalogAccess $access,
        private PirateRecoveryHangar $hangar,
        private LocalGarrison $garrison,
        private GameManager $manager,
    ) {}

    /** @param list<array{slug:string,tier:string,quantity:int}> $units */
    public function deliverLocked(PlayerUniverse $membership, Planet $planet, array $units, \DateTimeImmutable $at): bool
    {
        $buildingLevels = $this->repo->levelsByTier(PlanetBuilding::class, ['planet' => $planet]);
        $technologies = $this->repo->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
        $composition = [];
        foreach ($units as ['slug' => $slug, 'tier' => $tier, 'quantity' => $quantity]) {
            $definition = $this->catalog->getTierForUniverse($membership->universe, 'ships', $slug, $tier);
            // La livraison n’est pas une construction et ne débloque rien.
            // Aucune unité dépendante d’une place de production ne fait partie des cadeaux.
            if (isset($definition['economic_unit']) || $quantity < 1
                || $this->access->failures($membership, $planet, 'ships', $definition, $tier, $buildingLevels, $technologies) !== []) {
                return false;
            }
            $composition[$slug][$tier] = ($composition[$slug][$tier] ?? 0) + $quantity;
        }
        // Le crédit natif en hangar préserve le fret et le carburant existants.
        // Les coques offertes n’ajoutent ni file de construction ni jalon.
        if (!$this->hangar->install($membership, $planet, $composition, 0.0)) {
            return false;
        }
        $this->manager->flush();
        $this->garrison->enlist($planet, $at);

        return true;
    }
}
