<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Planet, PlanetBuilding, PlayerTechnology, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use App\Service\Requirement\CatalogAccess;

/** Devis de lecture ; le départ devra le recalculer sous verrou avant tout débit. */
final readonly class GuildDockQuote
{
    public function __construct(
        private GameCatalog $catalog,
        private GameRepository $repository,
        private OperationCalculator $operations,
        private CatalogAccess $access,
    ) {
    }

    /** @return array{ship:string,source_tier:string,target_tier:string,quantity:int,costs:array<string,int>,duration:int,source_duration:int,target_duration:int,source_costs:array<string,int>,target_costs:array<string,int>} */
    public function forColony(PlayerUniverse $membership, Planet $colony, string $ship, string $sourceTier, int $quantity): array
    {
        if ($membership->id === null || $colony->id === null || $colony->owner?->id !== $membership->id) {
            throw new \DomainException('Sélectionnez une colonie de votre empire.');
        }
        $sourceIndex = array_search($sourceTier, GameCatalog::TIERS, true);
        $targetTier = $sourceIndex === false ? null : (GameCatalog::TIERS[$sourceIndex + 1] ?? null);
        if ($targetTier === null || $quantity < 1) {
            throw new \DomainException('Ce rang ou cette quantité ne permet pas une modernisation.');
        }

        $config = $this->catalog->forUniverse($membership->universe);
        $shipDefinition = $config['catalog']['ships'][$ship] ?? null;
        if ($shipDefinition === null || !($shipDefinition['mobile'] ?? true) || isset($shipDefinition['economic_unit'])) {
            // Le Dock traite les coques mobiles ordinaires, sans réallouer une capacité spéciale.
            throw new \DomainException('Ce vaisseau ne peut pas être modernisé au Dock.');
        }

        $buildings = $this->repository->levelsByTier(PlanetBuilding::class, ['planet' => $colony]);
        $technology = $this->repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $membership]);
        $target = $this->catalog->withTier($shipDefinition, $targetTier);
        if ($this->access->failures($membership, $colony, 'ships', $target, $targetTier, $buildings, $technology) !== []) {
            throw new \DomainException('Débloquez le rang cible sur cette colonie avant la modernisation.');
        }

        // Les deux références utilisent le chantier et les remises de la même colonie,
        // y compris les règles propres à l’univers. L’attente en file est hors devis.
        $source = $this->catalog->withTier($shipDefinition, $sourceTier);
        $sourceQuote = $this->operations->calculateQueuePlan($source, 'ships', 1, $quantity, $buildings, $config, $technology);
        $targetQuote = $this->operations->calculateQueuePlan($target, 'ships', 1, $quantity, $buildings, $config, $technology);

        return ['ship' => $ship, 'source_tier' => $sourceTier, 'target_tier' => $targetTier, 'quantity' => $quantity]
            + GuildDockPricing::fromConstructionQuotes($sourceQuote, $targetQuote)
            + ['source_costs' => $sourceQuote['costs'], 'target_costs' => $targetQuote['costs']];
    }
}
