<?php

declare(strict_types=1);

namespace App\Service\Guild;

use App\Entity\{Planet, PlanetBuilding, PlayerTechnology, PlayerUniverse};
use App\Repository\GameRepository;
use App\Service\Catalog\GameCatalog;
use App\Service\Queue\OperationCalculator;
use App\Service\Requirement\CatalogAccess;

/** Le devis sépare le débit commun du débit électrique de la colonie volontaire. */
final readonly class GuildAtelierManufacturingQuote
{
    public function __construct(
        private GameCatalog $catalog,
        private CatalogAccess $access,
        private GameRepository $repository,
        private OperationCalculator $calculator,
    ) {
    }

    /** @return array{duration:int,materials:array<string,int>,electricity:int,confirmation:string} */
    public function forColony(
        PlayerUniverse $sponsor,
        Planet $colony,
        int $hallId,
        string $slug,
        string $tier,
        int $quantity,
    ): array {
        if ($sponsor->id === null || $colony->id === null || $colony->owner?->id !== $sponsor->id) {
            throw new \DomainException('Choisissez une colonie qui vous appartient.');
        }
        if ($hallId <= 0 || !in_array($tier, GameCatalog::TIERS, true)) {
            throw new \DomainException('Hall ou rang invalide.');
        }
        $config = $this->catalog->forUniverse($sponsor->universe);
        $definition = $this->catalog->getTierForUniverse($sponsor->universe, 'ships', $slug, $tier);
        if (($definition['mobile'] ?? true) === false || isset($definition['economic_unit'])) {
            throw new \DomainException('L’Atelier ne fabrique que des vaisseaux mobiles non plafonnés.');
        }
        if ($quantity < 1 || $quantity > min(2147483647, (int) $config['limits']['max_fleet_size'])) {
            throw new \DomainException('Quantité invalide pour ce lot de vaisseaux.');
        }
        $buildings = $this->repository->levelsByTier(PlanetBuilding::class, ['planet' => $colony]);
        $technologies = $this->repository->levelsByTier(PlayerTechnology::class, ['playerUniverse' => $sponsor]);
        $failures = $this->access->failures($sponsor, $colony, 'ships', $definition, $tier, $buildings, $technologies);
        if ($failures !== []) {
            throw new \DomainException('Prérequis de la colonie volontaire : ' . implode(', ', array_unique($failures)) . '.');
        }

        // Les niveaux personnels servent aux prérequis, pas au prix ou à la cadence de l’Atelier.
        $plan = $this->calculator->calculateQueuePlan($definition, 'ships', 1, $quantity, [], $config, []);
        $materials = $plan['costs'];
        $electricity = $materials['electricity'] ?? 0;
        unset($materials['electricity']);
        foreach ($plan['costs'] as $amount) {
            if (!is_int($amount) || $amount < 0) {
                throw new \DomainException('Le coût du lot dépasse la capacité du devis.');
            }
        }
        $materials = array_filter($materials, static fn (int $amount): bool => $amount > 0);
        ksort($materials);
        $duration = $plan['duration'];

        // Recalculer sous verrou lors de la confirmation, sans accepter de coût fourni par le formulaire.
        $confirmation = hash('sha256', json_encode(
            [$sponsor->id, $colony->id, $hallId, $slug, $tier, $quantity, $duration, $materials, $electricity],
            JSON_THROW_ON_ERROR,
        ));

        return compact('duration', 'materials', 'electricity', 'confirmation');
    }
}
